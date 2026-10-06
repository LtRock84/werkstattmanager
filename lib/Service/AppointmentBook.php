<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Service;

use OCA\Werkstattmanager\Db\Store;
use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Domain\ReceptionChecklist;
use OCA\Werkstattmanager\Domain\Rules;

final class AppointmentBook {
	public function __construct(
		private Store $store,
		private Access $access,
		private Catalog $catalog,
		private Journal $journal,
		private ServiceLibrary $library,
		private PlanningLedger $ledger,
	) {
	}
	public function view(string $from, string $to): array {
		$this->access->appointments();
		$days = $this->ledger->days($from, $to);
		$items = [];
		foreach ($this->ledger->window('appointments', $from, $to) as $a) {
			$items[] = $this->visible($a);
			if (count($items) > 2000) {
				throw new Problem(
					'range',
					'Zu viele Termine. Bitte einen kürzeren Zeitraum wählen.',
				);
			}
		}
		return ['items' => $items, 'days' => $days, 'settings' => $this->ledger->settings()];
	}
	public function save(array $input): array {
		$this->access->appointments();
		return $this->store->transaction(function () use ($input) {
			$this->ledger->lock();
			$request = Rules::text($input['request_id'] ?? '', 64, true);
			if (!preg_match('/^[a-zA-Z0-9-]{16,64}$/D', $request)) {
				throw new Problem('validation', 'Ungültige Vorgangskennung.');
			}
			$key = hash('sha256', $this->access->uid() . ':appointment:' . $request);
			$fingerprint = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
			$previous = $this->store->find('requests', ['request_key' => $key], 1)[0] ?? null;
			if ($previous) {
				if ($previous['fingerprint'] !== $fingerprint) {
					throw new Problem('conflict', 'Vorgangskennung bereits verwendet.', 409);
				}
				return $this->visible($previous['response']);
			}
			$old = empty($input['id'])
				? null
				: $this->store->get('appointments', Rules::text($input['id'], 32, true));
			if ($old && $old['version'] !== ($input['version'] ?? null)) {
				throw new Problem('conflict', 'Termin wurde inzwischen geändert.', 409);
			}
			$status = Rules::choice($input['status'] ?? 'confirmed', [
				'draft',
				'confirmed',
				'cancelled',
			]);
			if ($old && $old['status'] === 'cancelled' && $status !== 'cancelled') {
				throw new Problem('state', 'Einen abgesagten Termin bitte neu anlegen.', 409);
			}
			if ($old && $old['status'] === 'confirmed' && $status === 'draft') {
				throw new Problem(
					'state',
					'Bestätigte Termine können geändert oder abgesagt werden.',
					409,
				);
			}
			if ($this->access->role() === 'reception') {
				if (!empty($input['override_reason']) || (!empty($input['stage']) && $input['stage'] !== ($old['stage'] ?? 'planned'))) {
					throw new Problem('forbidden', 'Werkstattstatus und Überbuchung darf nur der Meister ändern.', 403);
				}
				$input['stage'] = $old['stage'] ?? 'planned';
				$input['checklist'] = $old['checklist'] ?? null;
				$input['note'] = $old['note'] ?? '';
			}
			$vehicle = $this->catalog->reference($input['vehicle_id'] ?? null, 'vehicles', true);
			$vrow = $this->store->get('vehicles', $vehicle);
			$this->store->update('vehicles', $vrow, []);
			$lines = $this->library->lines($input['service_lines'] ?? [], $status === 'confirmed', $old['service_lines'] ?? []);
			$minutes = $lines
				? array_sum(array_map(fn ($l) => ($l['minutes'] ?? 0) * $l['quantity'], $lines))
				: Rules::integer($input['minutes'] ?? null, 1, 100000);
			if ($status === 'confirmed' && !$minutes) {
				throw new Problem('validation', 'Geplante Arbeitsdauer fehlt.');
			}
			$arrival
				= Rules::date($input['arrival'] ?? null, true)
				?? throw new Problem('validation', 'Ankunftszeit fehlt.');
			$start = Rules::date($input['work_start'] ?? null, true);
			$pickup = Rules::date($input['pickup'] ?? null, true);
			Rules::schedule($arrival, $pickup);
			Rules::schedule($start, $pickup);
			Rules::schedule($arrival, $start);
			$email = Rules::text($input['customer_email'] ?? '', 255);
			if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
				throw new Problem('validation', 'Bitte eine gültige E-Mail-Adresse angeben.');
			}
			$reason = Rules::text($input['override_reason'] ?? '', 2000);
			$v = [
				'checklist' => ReceptionChecklist::validate($input['checklist'] ?? $old['checklist'] ?? null),
				'vehicle_id' => $vehicle,
				'title' => Rules::text($input['title'] ?? '', 255, true),
				'customer_email' => $email,
				'arrival' => $arrival,
				'work_start' => $start,
				'pickup' => $pickup,
				'service_lines' => $lines,
				'minutes' => $minutes,
				'allocations' => $this->ledger->allocations(
					$input['allocations'] ?? null,
					(int)($minutes ?? 0),
					$start ?? $arrival,
				),
				'status' => $status,
				'stage' => Rules::choice($input['stage'] ?? 'planned', [
					'planned',
					'arrived',
					'parts_missing',
					'customer_question',
					'ready',
					'collected',
					'no_show',
				]),
				'note' => Rules::text($input['note'] ?? '', 10000),
				'override_reason' => $reason,
				'reminder' => ($input['reminder'] ?? false) === true,
				'order_id' => $old['order_id'] ?? null,
			];
			$zone = new \DateTimeZone('Europe/Berlin');
			$day = static fn (string $date): string => (new \DateTimeImmutable($date))->setTimezone($zone)->format('Y-m-d');
			foreach ($v['allocations'] as $allocation) {
				if ($allocation['date'] < $day($arrival) || ($pickup && $allocation['date'] > $day($pickup))) {
					throw new Problem('validation', 'Arbeitstage müssen zwischen Ankunft und zugesagter Abholung liegen.');
				}
			}
			if ($start && $v['allocations'] && min(array_column($v['allocations'], 'date')) !== $day($start)) {
				throw new Problem('validation', 'Der geplante Arbeitsbeginn muss am ersten Arbeitstag liegen.');
			}
			$v += $this->ledger->bounds($v['allocations'], $arrival);
			if ($v['order_id'] && $vehicle !== $old['vehicle_id']) {
				throw new Problem(
					'validation',
					'Fahrzeug eines verknüpften Auftrags kann nicht gewechselt werden.',
				);
			}
			$checkReason = $reason;
			if ($this->access->role() === 'reception') {
				$v['override_reason'] = $old['override_reason'] ?? '';
				if ($old && $old['status'] === 'confirmed' && $old['allocations'] === $v['allocations'] && $old['vehicle_id'] === $vehicle && $day($old['arrival']) === $day($arrival)) {
					$checkReason = $old['override_reason'];
				}
			}
			if ($status === 'confirmed') {
				$this->ledger->check($v, $checkReason, $old['id'] ?? null);
			}
			$row = $old
				? $this->store->update('appointments', $old, $v)
				: $this->store->insert('appointments', $v);
			$customerFields = ['title', 'arrival', 'pickup', 'customer_email', 'status'];
			$changed
				= !$old
				|| array_intersect_key($old, array_flip($customerFields))
					!== array_intersect_key($row, array_flip($customerFields));
			$calendarChanged
				= $changed
				|| !$old
				|| $old['work_start'] !== $row['work_start']
				|| $old['allocations'] !== $row['allocations'];
			if (
				$calendarChanged
				&& ($status === 'confirmed'
					|| ($status === 'cancelled' && ($old['status'] ?? '') === 'confirmed'))
			) {
				$kind
					= $status === 'cancelled'
						? 'cancel'
						: (($old['status'] ?? '') === 'confirmed'
							? 'update'
							: 'confirm');
				$this->queue($row, 'calendar', $kind);
				if ($changed && $email !== '') {
					$this->queue($row, 'email', $kind);
					if ($kind === 'update' && $old && $old['customer_email'] === $email && $old['pickup'] && !$row['pickup']) {
						$this->queueEvent(array_replace($row, ['pickup' => $old['pickup']]), 'email', 'pickup_cancel', null, 'pickup');
					}
				}
				if ($old && $old['status'] === 'confirmed' && $old['customer_email'] !== '' && $old['customer_email'] !== $email) {
					$this->queue(
						array_replace($row, ['customer_email' => $old['customer_email'], 'arrival' => $old['arrival'], 'pickup' => $old['pickup']]),
						'email',
						'cancel',
					);
				}
			}
			// Reminder validity uses the current schedule, not the last delivery revision.
			if (
				$row['reminder']
				&& $status === 'confirmed'
				&& strtotime($arrival) > time() + 86400
			) {
				$this->queue($row, 'email', 'reminder', strtotime($arrival) - 86400);
			}
			$beforeChecklist = ReceptionChecklist::validate($old['checklist'] ?? null);
			$this->journal->record(
				$this->access->uid(),
				'appointment.' . $status,
				$vehicle,
				details: [
					'title' => $row['title'],
					'arrival' => $arrival,
					'reason' => $reason,
					'order_unchanged' => (bool)$row['order_id'],
					'checklist_changes' => array_values(array_filter(array_map(
						static fn (string $field): ?array => ($beforeChecklist[$field] === $row['checklist'][$field]) ? null : ['field' => $field, 'before' => $beforeChecklist[$field], 'after' => $row['checklist'][$field]],
						array_keys($row['checklist']),
					))),
				],
			);
			$r = $this->store->insert('requests', [
				'request_key' => $key,
				'fingerprint' => $fingerprint,
				'response' => $row,
			]);
			$this->library->usage()->appointment($row);
			return $this->visible($r['response']);
		});
	}
	private function visible(array $row): array {
		if ($this->access->role() === 'reception') {
			$row['note'] = '';
			$row['checklist'] = null;
			$row['override_reason'] = '';
		}
		return $row;
	}
	private function queue(array $row, string $channel, string $kind, ?int $when = null): void {
		if ($channel === 'calendar') {
			$current = ['arrival'];
			if ($row['pickup']) {
				$current[] = 'pickup';
			}
			foreach ($row['allocations'] as $d) {
				$current[] = 'work-' . $d['date'];
			}
			$known = $current;
			for ($offset = 0; ; $offset += 500) {
				$past = $this->store->find(
					'deliveries',
					['appointment_id' => $row['id'], 'channel' => 'calendar'],
					500,
					$offset,
				);
				foreach ($past as $p) {
					if (isset($p['payload']['event_suffix'])) {
						$known[] = $p['payload']['event_suffix'];
					}
				}
				if (count($past) < 500) {
					break;
				}
			}
			foreach (array_unique($known) as $suffix) {
				$this->queueEvent(
					$row,
					$channel,
					$kind === 'cancel' || !in_array($suffix, $current, true) ? 'cancel' : $kind,
					$when,
					$suffix,
				);
			}
			return;
		}
		$this->queueEvent($row, $channel, $kind, $when);
	}
	private function queueEvent(
		array $row,
		string $channel,
		string $kind,
		?int $when = null,
		?string $suffix = null,
	): void {
		if ($channel === 'email' && $row['customer_email'] === '') {
			return;
		}
		$key = hash(
			'sha256',
			$row['id']
				. ':'
				. ($kind === 'reminder' ? $row['arrival'] : $row['version'])
				. ':'
				. $channel
				. ':'
				. $kind
				. ':'
				. $row['customer_email']
				. ':'
				. $suffix,
		);
		$existing = $this->store->find('deliveries', ['event_key' => $key], 1)[0] ?? null;
		if ($existing && $kind === 'reminder' && $existing['status'] === 'cancelled') {
			$this->store->update('deliveries', $existing, [
				'status' => 'pending',
				'next_try' => $when ?? time(),
			]);
		}
		if (!$existing) {
			$vehicle = $this->store->get('vehicles', $row['vehicle_id']);
			$payload = array_intersect_key(
				$row,
				array_flip([
					'id',
					'version',
					'title',
					'arrival',
					'pickup',
					'work_start',
					'allocations',
					'customer_email',
					'status',
					'reminder',
				]),
			);
			$payload['vehicle_label'] = $vehicle['plate'] ?: $vehicle['number'];
			if ($suffix !== null) {
				$payload['event_suffix'] = $suffix;
			}
			$this->store->insert('deliveries', [
				'event_key' => $key,
				'appointment_id' => $row['id'],
				'appointment_version' => $row['version'],
				'channel' => $channel,
				'kind' => $kind,
				'status' => 'pending',
				'attempts' => 0,
				'next_try' => $when ?? time(),
				'last_error' => '',
				'payload' => $payload,
			]);
		}
	}
	public function convert(string $id, int $version): array {
		$this->access->master();
		return $this->store->transaction(function () use ($id, $version) {
			$this->ledger->lock();
			$a = $this->store->get('appointments', $id);
			if ($a['order_id']) {
				return $this->store->get('orders', $a['order_id']);
			}
			if ($a['version'] !== $version) {
				throw new Problem('conflict', 'Termin wurde geändert.', 409);
			}
			if ($a['status'] !== 'confirmed') {
				throw new Problem('state', 'Zuerst den Termin bestätigen.', 409);
			}
			$this->catalog->reference($a['vehicle_id'], 'vehicles', true);
			$steps = [];
			foreach ($a['service_lines'] as $l) {
				$steps[] = $l['quantity'] . ' × ' . $l['title'];
				array_push($steps, ...$l['steps']);
			}
			$o = $this->store->insert('orders', [
				'vehicle_id' => $a['vehicle_id'],
				'title' => $a['title'],
				'description' => $a['note'],
				'steps' => array_values(array_unique($steps)),
				'minutes' => $a['minutes'],
				'planned_start' => $a['work_start'],
				'due_at' => $a['pickup'],
				'priority' => 'normal',
				'status' => 'draft',
				'service_lines' => $a['service_lines'],
				'planning_days' => $a['allocations'],
				'first_day' => $a['first_day'],
				'last_day' => $a['last_day'],
			]);
			$this->store->update('appointments', $a, ['order_id' => $o['id']]);
			$this->journal->record(
				$this->access->uid(),
				'appointment.order_created',
				$a['vehicle_id'],
				$o['id'],
				details: ['title' => $a['title']],
			);
			return $o;
		});
	}
	public function capacity(array $input): array {
		$this->access->master();
		return $this->store->transaction(function () use ($input) {
			$row = $this->ledger->lock();
			$limit = function (array $v): array {
				return [
					'minutes' => Rules::integer($v['minutes'] ?? 0, 0, 100000) ?? 0,
					'max_vehicles' => Rules::integer($v['max_vehicles'] ?? null, 1, 10000),
					'closed' => ($v['closed'] ?? false) === true,
				];
			};
			if (isset($input['weekdays'])) {
				if (($input['version'] ?? null) !== $row['version'] - 1) {
					throw new Problem(
						'conflict',
						'Kapazität wurde geändert. Bitte neu laden.',
						409,
					);
				}
				$week = [];
				for ($i = 1; $i <= 7; $i++) {
					$week[$i] = $limit($input['weekdays'][$i] ?? []);
				}
				return $this->store->update('planning', $row, [
					'settings' => ['weekdays' => $week],
				]);
			}
			$day
				= Rules::date($input['date'] ?? null)
				?? throw new Problem('validation', 'Datum fehlt.');
			$old = $this->store->find('capacity', ['date' => $day], 1)[0] ?? null;
			if ($old && $old['version'] !== ($input['version'] ?? null)) {
				throw new Problem('conflict', 'Tag wurde geändert.', 409);
			}
			$v = $limit($input) + ['date' => $day];
			return $old
				? $this->store->update('capacity', $old, $v)
				: $this->store->insert('capacity', $v);
		});
	}
	public function deliveries(): array {
		$this->access->master();
		return [
			'items' => array_map(static function (array $row): array {
				$row['context'] = array_intersect_key($row['payload'], array_flip(['title', 'vehicle_label', 'arrival']));
				unset($row['payload']);
				return $row;
			}, $this->store->find(
				'deliveries',
				['status' => ['pending', 'failed', 'uncertain']],
				100,
			)),
			'submitted' => array_map(static function (array $row): array {
				$row['context'] = array_intersect_key($row['payload'], array_flip(['title', 'vehicle_label', 'arrival']));
				unset($row['payload']);
				return $row;
			}, $this->store->find('deliveries', ['status' => 'submitted'], 20)),
		];
	}
	public function retry(string $id, int $version): array {
		$this->access->master();
		return $this->store->transaction(function () use ($id, $version) {
			$row = $this->store->get('deliveries', $id);
			if ($row['version'] !== $version) {
				throw new Problem('conflict', 'Zustellung wurde geändert.', 409);
			}
			if (!in_array($row['status'], ['failed', 'uncertain'], true)) {
				throw new Problem('state', 'Zustellung ist bereits vorgemerkt.', 409);
			}
			return $this->store->update('deliveries', $row, [
				'status' => 'pending',
				'attempts' => 0,
				'next_try' => time(),
				'last_error' => '',
			]);
		});
	}
}
