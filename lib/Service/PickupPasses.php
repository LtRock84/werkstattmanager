<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Service;

use OCA\Werkstattmanager\Db\Store;
use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Domain\Rules;
use OCP\DB\Exception as DBException;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\Security\ICrypto;

/** Order-bound, revocable bearer passes for delegated vehicle pickup. */
final class PickupPasses {
	public function __construct(
		private Store $store,
		private Access $access,
		private Journal $journal,
		private ICrypto $crypto,
		private IURLGenerator $urls,
		private ISession $session,
		private CustomerMailTemplates $templates,
	) {
	}

	public function ensureForAppointment(array $appointment): array {
		$link = $this->store->find('pickup_links', ['appointment_id' => $appointment['id']], 1)[0] ?? null;
		if (!$link && $appointment['order_id']) {
			$link = $this->ensureForOrder($this->store->get('orders', $appointment['order_id']));
			$link = $this->store->update('pickup_links', $link, ['appointment_id' => $appointment['id']]);
		}
		if ($link) {
			return $link;
		}
		$token = bin2hex(random_bytes(32));
		try {
			return $this->store->insert('pickup_links', [
				'appointment_id' => $appointment['id'], 'order_id' => null,
				'vehicle_id' => $appointment['vehicle_id'], 'token_hash' => hash('sha256', $token),
				'token_cipher' => $this->crypto->encrypt($token), 'revoked' => false,
				'ready_at' => null, 'collected_at' => null, 'email_on_ready' => false,
				'recipient' => null, 'initial_sent_at' => null, 'ready_sent_at' => null,
			]);
		} catch (DBException $e) {
			if ($e->getReason() === DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				return $this->store->find('pickup_links', ['appointment_id' => $appointment['id']], 1)[0] ?? throw $e;
			}
			throw $e;
		}
	}

	/** Called inside the appointment transaction while its planning ledger is locked. */
	public function syncAppointment(array $appointment, ?array $old): void {
		$link = $this->store->find('pickup_links', ['appointment_id' => $appointment['id']], 1)[0] ?? null;
		if ($appointment['status'] === 'cancelled') {
			if ($link && !$link['revoked']) {
				$this->store->update('pickup_links', $link, ['revoked' => true, 'email_on_ready' => false]);
				$this->cancelDeliveries($link['id']);
			}
			return;
		}
		if ($appointment['status'] !== 'confirmed') {
			return;
		}
		$link = $link ?? $this->ensureForAppointment($appointment);
		if ($old && ($old['vehicle_id'] !== $appointment['vehicle_id'] || $old['customer_email'] !== $appointment['customer_email'])) {
			$token = bin2hex(random_bytes(32));
			$this->cancelDeliveries($link['id']);
			$link = $this->store->update('pickup_links', $link, [
				'vehicle_id' => $appointment['vehicle_id'], 'token_hash' => hash('sha256', $token),
				'token_cipher' => $this->crypto->encrypt($token), 'recipient' => null,
				'email_on_ready' => false, 'initial_sent_at' => null, 'ready_sent_at' => null,
			]);
		}
		if ($appointment['stage'] === 'ready' && !$link['ready_at'] && !$link['revoked']) {
			$link = $this->store->update('pickup_links', $link, ['ready_at' => time()]);
		} elseif (($old['stage'] ?? null) === 'ready' && !in_array($appointment['stage'], ['ready', 'collected'], true) && $link['ready_at']) {
			$link = $this->store->update('pickup_links', $link, ['ready_at' => null]);
		} elseif ($appointment['stage'] === 'collected' && !$link['collected_at']) {
			$this->store->update('pickup_links', $link, ['collected_at' => time(), 'revoked' => true]);
			$this->cancelDeliveries($link['id']);
		}
	}

	public function bindAppointment(array $appointment, array $order): void {
		$link = $this->ensureForAppointment($appointment);
		if (!$link['order_id']) {
			$this->store->update('pickup_links', $link, ['order_id' => $order['id']]);
		}
	}

	public function appointmentDeliveryData(array $appointment): array {
		$link = $this->ensureForAppointment($appointment);
		$vehicle = $this->store->get('vehicles', $appointment['vehicle_id']);
		if ($appointment['status'] !== 'confirmed' || $link['vehicle_id'] !== $appointment['vehicle_id'] || $link['revoked'] || $link['collected_at'] || $vehicle['archived']) {
			throw new Problem('pickup_link', 'Abholcode ist nicht mehr aktiv.', 404);
		}
		if ($link['order_id']) {
			$this->assertUsable($this->store->get('orders', $link['order_id']), $link);
		}
		return ['pickup_url' => $this->url($link)];
	}

	/** A ready notice queued from the order also covers its linked appointment. */
	public function readyDeliveryExists(array $appointment): bool {
		$link = $this->store->find('pickup_links', ['appointment_id' => $appointment['id']], 1)[0] ?? null;
		if (!$link) {
			return false;
		}
		foreach ($this->store->find('deliveries', ['pickup_link_id' => $link['id'], 'kind' => 'pickup_ready', 'status' => ['pending', 'sending', 'sent', 'submitted', 'uncertain']], 100) as $delivery) {
			if (($delivery['payload']['customer_email'] ?? '') === $appointment['customer_email']) {
				return true;
			}
		}
		return false;
	}

	public function ensureForOrder(array $order): array {
		$link = $this->store->find('pickup_links', ['order_id' => $order['id']], 1)[0] ?? null;
		if ($link) {
			return $link;
		}
		$token = bin2hex(random_bytes(32));
		try {
			return $this->store->insert('pickup_links', [
				'order_id' => $order['id'],
				'vehicle_id' => $order['vehicle_id'],
				'token_hash' => hash('sha256', $token),
				'token_cipher' => $this->crypto->encrypt($token),
				'revoked' => false,
				'ready_at' => null,
				'collected_at' => null,
				'email_on_ready' => false,
				'recipient' => null,
				'initial_sent_at' => null,
				'ready_sent_at' => null,
			]);
		} catch (DBException $e) {
			if ($e->getReason() === DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				return $this->store->find('pickup_links', ['order_id' => $order['id']], 1)[0]
					?? throw $e;
			}
			throw $e;
		}
	}

	public function masterView(string $orderId): array {
		$this->access->master();
		$order = $this->store->get('orders', Rules::text($orderId, 32, true));
		$link = $this->ensureForOrder($order);
		$recipient = $this->recipient($order, false);
		$vehicle = $this->store->get('vehicles', $order['vehicle_id']);
		$appointment = $this->store->find('appointments', ['order_id' => $order['id'], 'status' => 'confirmed'], 1)[0] ?? null;
		$url = $link['revoked'] || $link['collected_at'] ? null : $this->url($link);
		$print = $this->templates->render('pickup_print', [
			'customer_name' => $recipient['name'],
			'vehicle_label' => implode(' · ', array_filter([$vehicle['plate'] ?: $vehicle['number'], $vehicle['make'], $vehicle['model']])),
			'order_number' => 'WM-' . strtoupper(substr($order['id'], 0, 8)),
			'title' => $order['title'],
			'arrival' => $appointment['arrival'] ?? null,
			'pickup' => $appointment['pickup'] ?? null,
			'pickup_url' => $url ?? '',
		]);
		return [
			'id' => $link['id'],
			'version' => $link['version'],
			'url' => $url,
			'print_title' => $print['subject'],
			'print_body' => $print['body'],
			'revoked' => $link['revoked'],
			'ready' => $link['ready_at'] !== null,
			'collected' => $link['collected_at'] !== null,
			'email_on_ready' => $link['email_on_ready'],
			'recipient' => $recipient['email'] ?? '',
			'customer_name' => $recipient['name'] ?? 'Fahrzeugabholung',
			'initial_sent_at' => $link['initial_sent_at'],
			'ready_sent_at' => $link['ready_sent_at'],
		];
	}

	public function action(string $orderId, string $operation, array $input): array {
		$this->access->master();
		if (!in_array($operation, ['send', 'ready', 'collected', 'rotate', 'revoke'], true)) {
			throw new Problem('not_found', 'Unbekannte Abholcode-Aktion.', 404);
		}
		return $this->idempotent($input, $orderId . ':pickup:' . $operation, function () use ($orderId, $operation, $input): array {
			$order = $this->store->get('orders', Rules::text($orderId, 32, true));
			$link = $this->ensureForOrder($order);
			if (($input['version'] ?? null) !== $link['version']) {
				throw new Problem('conflict', 'Abholcode wurde inzwischen geändert. Bitte aktualisieren.', 409);
			}
			if ($operation === 'send') {
				$this->assertUsable($order, $link);
				$recipient = $this->recipient($order, true);
				$link = $this->store->update('pickup_links', $link, [
					'email_on_ready' => true,
					'recipient' => $recipient['email'],
				]);
				$this->queue($link, $order, 'pickup_code', $recipient, Rules::text($input['request_id'], 64, true));
				$this->journal->record($this->access->uid(), 'pickup.sent', $order['vehicle_id'], $order['id']);
			} elseif ($operation === 'ready') {
				$this->markReady($order['id']);
			} elseif ($operation === 'collected') {
				$this->markCollected($order['id']);
			} elseif ($operation === 'revoke') {
				$this->revoke($order, $link, 'pickup.revoked');
			} else {
				if ($link['collected_at'] || $order['status'] === 'cancelled') {
					throw new Problem('state', 'Für einen abgeholten oder stornierten Auftrag kann kein neuer Abholcode erstellt werden.', 409);
				}
				$token = bin2hex(random_bytes(32));
				$this->cancelDeliveries($link['id']);
				$this->store->update('pickup_links', $link, [
					'token_hash' => hash('sha256', $token),
					'token_cipher' => $this->crypto->encrypt($token),
					'revoked' => false,
					'email_on_ready' => false,
					'recipient' => null,
					'initial_sent_at' => null,
					'ready_sent_at' => null,
				]);
				$this->journal->record($this->access->uid(), 'pickup.rotated', $order['vehicle_id'], $order['id']);
			}
			return ['changed' => true];
		});
	}

	public function markReady(string $orderId, bool $sendNotification = true): void {
		$order = $this->store->get('orders', $orderId);
		$link = $this->ensureForOrder($order);
		if ($link['revoked'] || $link['collected_at'] || $order['status'] === 'cancelled') {
			return;
		}
		if ($link['ready_at'] === null) {
			$link = $this->store->update('pickup_links', $link, ['ready_at' => time()]);
			$this->journal->record($this->access->uid(), 'pickup.ready', $order['vehicle_id'], $order['id']);
		}
		$appointment = $link['appointment_id'] ? $this->store->get('appointments', $link['appointment_id']) : null;
		if ($sendNotification || ($appointment && $appointment['notify_ready'] && $appointment['customer_email'])) {
			foreach ($this->store->find('deliveries', ['pickup_link_id' => $link['id'], 'kind' => 'pickup_code', 'status' => ['pending', 'failed']], 100) as $pending) {
				$this->store->update('deliveries', $pending, ['status' => 'cancelled', 'last_error' => 'Durch Abholbereit-Nachricht ersetzt.']);
			}
		}
		if ($sendNotification) {
			$recipient = $this->recipient($order, false);
			if (($recipient['email'] ?? '') !== '') {
				if ($link['appointment_id']) {
					foreach ($this->store->find('deliveries', ['appointment_id' => $link['appointment_id'], 'kind' => 'ready', 'status' => ['pending', 'sending', 'sent', 'submitted', 'uncertain']], 100) as $delivery) {
						if (($delivery['payload']['customer_email'] ?? '') === $recipient['email']) {
							return;
						}
					}
				}
				$link = $this->store->update('pickup_links', $link, ['email_on_ready' => true, 'recipient' => $recipient['email']]);
				$this->queue($link, $order, 'pickup_ready', $recipient, 'ready');
			}
		}
	}

	public function markCollected(string $orderId): void {
		$order = $this->store->get('orders', $orderId);
		$link = $this->store->find('pickup_links', ['order_id' => $orderId], 1)[0] ?? null;
		if (!$link || $link['collected_at']) {
			return;
		}
		$this->store->update('pickup_links', $link, ['collected_at' => time(), 'revoked' => true]);
		$this->cancelDeliveries($link['id']);
		$this->journal->record($this->access->uid(), 'pickup.collected', $order['vehicle_id'], $order['id']);
	}

	public function cancelOrder(string $orderId): void {
		$order = $this->store->get('orders', $orderId);
		$link = $this->store->find('pickup_links', ['order_id' => $orderId], 1)[0] ?? null;
		if ($link && !$link['revoked']) {
			$this->revoke($order, $link, 'pickup.revoked');
		}
	}

	public function exchange(string $token): array {
		if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
			throw new Problem('pickup_link', 'Abholcode ungültig.', 404);
		}
		$link = $this->store->find('pickup_links', ['token_hash' => hash('sha256', $token)], 1)[0] ?? null;
		if (!$link || $link['revoked'] || $link['collected_at']) {
			throw new Problem('pickup_link', 'Abholcode ungültig oder nicht mehr aktiv.', 404);
		}
		$this->publicData($link);
		$this->session->set('wm_pickup', ['link' => $link['id'], 'token_hash' => $link['token_hash'], 'expires' => time() + 1800]);
		return $this->publicData($link);
	}

	public function publicView(): array {
		$session = $this->session->get('wm_pickup');
		if (!is_array($session) || ($session['expires'] ?? 0) < time()) {
			throw new Problem('pickup_link', 'Bitte den gültigen Abhol-QR-Code erneut öffnen.', 401);
		}
		$link = $this->store->get('pickup_links', (string)$session['link']);
		if (!is_string($session['token_hash'] ?? null) || !hash_equals($link['token_hash'], $session['token_hash'])) {
			throw new Problem('pickup_link', 'Abholcode ist nicht mehr aktiv.', 404);
		}
		return $this->publicData($link);
	}

	/** Internal delivery validation; no caller identity is assumed in background jobs. */
	public function deliveryData(string $linkId, string $recipient, bool $ready): array {
		$link = $this->store->get('pickup_links', $linkId);
		$order = $this->store->get('orders', $link['order_id']);
		$this->assertUsable($order, $link);
		$current = $this->recipient($order, true);
		if ($recipient !== $link['recipient'] || $recipient !== $current['email'] || ($ready && $link['ready_at'] === null)) {
			throw new Problem('obsolete', 'Abholcode-Zustellung ist nicht mehr aktuell.', 409);
		}
		$vehicle = $this->store->get('vehicles', $order['vehicle_id']);
		$appointment = $this->store->find('appointments', ['order_id' => $order['id'], 'status' => 'confirmed'], 1)[0] ?? null;
		return $current + [
			'customer_email' => $current['email'],
			'customer_name' => $current['name'],
			'link' => $link,
			'order' => $order,
			'vehicle' => $vehicle,
			'url' => $this->url($link),
			'vehicle_label' => $vehicle['plate'] ?: $vehicle['number'],
			'order_number' => 'WM-' . strtoupper(substr($order['id'], 0, 8)),
			'title' => $order['title'],
			'arrival' => $appointment['arrival'] ?? null,
			'pickup' => $appointment['pickup'] ?? null,
			'pickup_url' => $this->url($link),
		];
	}

	public function sent(string $linkId, bool $ready): void {
		$link = $this->store->get('pickup_links', $linkId);
		$this->store->update('pickup_links', $link, [$ready ? 'ready_sent_at' : 'initial_sent_at' => time()]);
	}

	private function publicData(array $link): array {
		if ($link['order_id']) {
			$order = $this->store->get('orders', $link['order_id']);
			$this->assertUsable($order, $link);
			$recipient = $this->recipient($order, false);
		} else {
			$appointment = $this->store->get('appointments', $link['appointment_id']);
			$this->appointmentDeliveryData($appointment);
			$vehicle = $this->store->get('vehicles', $link['vehicle_id']);
			$customer = $vehicle['customer_id'] ? $this->store->get('customers', $vehicle['customer_id']) : null;
			$recipient = ['name' => $customer ? ($customer['contact'] ?: $customer['name']) : 'Fahrzeugabholung'];
		}
		$vehicle = $this->store->get('vehicles', $link['vehicle_id']);
		return [
			'customer_name' => $recipient['name'] ?? 'Fahrzeugabholung',
			'vehicle' => [
				'plate' => $vehicle['plate'],
				'number' => $vehicle['number'],
				'make' => $vehicle['make'],
				'model' => $vehicle['model'],
			],
			'ready' => $link['ready_at'] !== null,
		];
	}

	private function recipient(array $order, bool $required): array {
		$vehicle = $this->store->get('vehicles', $order['vehicle_id']);
		$customer = $vehicle['customer_id'] ? $this->store->get('customers', $vehicle['customer_id']) : null;
		$email = $customer['email'] ?? '';
		$appointment = $this->store->find('appointments', ['order_id' => $order['id'], 'status' => 'confirmed'], 1)[0] ?? null;
		if ($appointment && $appointment['customer_email']) {
			$email = $appointment['customer_email'];
		}
		if ($required && (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$customer || $vehicle['kind'] !== 'customer')) {
			throw new Problem('pickup_email', 'Für den E-Mail-Versand bitte eine gültige Kundenadresse in der Fahrzeug- oder Terminakte hinterlegen.');
		}
		return [
			'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '',
			'name' => $customer ? ($customer['contact'] ?: $customer['name']) : 'Eigene Flotte',
		];
	}

	private function assertUsable(array $order, array $link): void {
		$vehicle = $this->store->get('vehicles', $order['vehicle_id']);
		if ($link['revoked'] || $link['collected_at'] || $order['status'] === 'cancelled' || $vehicle['archived'] || $link['vehicle_id'] !== $order['vehicle_id']) {
			throw new Problem('pickup_link', 'Abholcode ist nicht mehr aktiv.', 404);
		}
	}

	private function url(array $link): string {
		$token = $this->crypto->decrypt($link['token_cipher']);
		if (!preg_match('/^[a-f0-9]{64}$/D', $token) || !hash_equals($link['token_hash'], hash('sha256', $token))) {
			throw new \RuntimeException('Invalid encrypted pickup token');
		}
		// Background jobs have no request route collection; build from the public webroot.
		$path = rtrim($this->urls->getWebroot(), '/') . '/index.php/apps/werkstattmanager/abholung';
		return $this->urls->getAbsoluteURL($path) . '#' . $token;
	}

	private function queue(array $link, array $order, string $kind, array $recipient, string $nonce): void {
		$key = hash('sha256', 'pickup:' . $link['id'] . ':' . $kind . ':' . $nonce . ':' . $recipient['email']);
		if ($this->store->find('deliveries', ['event_key' => $key], 1)) {
			return;
		}
		$this->store->insert('deliveries', [
			'event_key' => $key,
			'appointment_id' => null,
			'appointment_version' => 0,
			'order_id' => $order['id'],
			'pickup_link_id' => $link['id'],
			'vehicle_id' => $order['vehicle_id'],
			'deadline_id' => null,
			'channel' => 'email',
			'kind' => $kind,
			'status' => 'pending',
			'attempts' => 0,
			'next_try' => time(),
			'last_error' => '',
			'payload' => ['customer_email' => $recipient['email'], 'customer_name' => $recipient['name'], 'title' => $order['title'], 'vehicle_id' => $order['vehicle_id']],
		]);
	}

	private function revoke(array $order, array $link, string $action): void {
		$this->store->update('pickup_links', $link, ['revoked' => true, 'email_on_ready' => false]);
		$this->cancelDeliveries($link['id']);
		$this->journal->record($this->access->uid(), $action, $order['vehicle_id'], $order['id']);
	}

	private function cancelDeliveries(string $linkId): void {
		foreach ($this->store->find('deliveries', ['pickup_link_id' => $linkId, 'status' => ['pending', 'failed', 'uncertain']], 500) as $delivery) {
			$this->store->update('deliveries', $delivery, ['status' => 'cancelled', 'last_error' => '']);
		}
	}

	private function idempotent(array $input, string $operation, callable $work): array {
		$request = Rules::text($input['request_id'] ?? '', 64, true);
		if (!preg_match('/^[a-zA-Z0-9-]{16,64}$/D', $request)) {
			throw new Problem('request_id', 'Ungültige Vorgangskennung.');
		}
		$key = hash('sha256', $this->access->uid() . ':' . $request);
		$fingerprint = hash('sha256', $operation . json_encode($input, JSON_THROW_ON_ERROR));
		$previous = $this->store->find('requests', ['request_key' => $key], 1)[0] ?? null;
		if ($previous) {
			if ($previous['fingerprint'] !== $fingerprint) {
				throw new Problem('request_id', 'Vorgangskennung bereits anders verwendet.', 409);
			}
			return $previous['response'];
		}
		return $this->store->transaction(function () use ($key, $fingerprint, $work): array {
			$row = $this->store->insert('requests', ['request_key' => $key, 'fingerprint' => $fingerprint, 'response' => []]);
			$result = $work();
			$this->store->update('requests', $row, ['response' => $result]);
			return $result;
		});
	}
}
