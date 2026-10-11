<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Service;

use OCA\Werkstattmanager\Db\Store;
use OCA\Werkstattmanager\Domain\Problem;
use OCP\DB\Exception as DBException;
use OCP\IConfig;

/** Bounded discovery; delivery rechecks the current vehicle/customer/permission. */
final class CustomerReminders {
	public function __construct(
		private Store $store,
		private CustomerMailTemplates $templates,
		private IConfig $config,
	) {
	}
	public function eligible(array $deadline, array $vehicle, array $customer, string $today): bool {
		if ($deadline['status'] !== 'open' || $vehicle['archived'] || $vehicle['kind'] !== 'customer' || $customer['archived'] || !($customer['deadline_emails'] ?? false) || !filter_var($customer['email'], FILTER_VALIDATE_EMAIL)) {
			return false;
		}
		$limit = (new \DateTimeImmutable($today))->modify('+' . $this->templates->data()['reminder_days'] . ' days')->format('Y-m-d');
		return ($deadline['due_date'] !== null && $deadline['due_date'] <= $limit) || ($deadline['due_km'] !== null && $vehicle['mileage'] !== null && $vehicle['mileage'] >= $deadline['due_km']);
	}
	public function schedule(): void {
		$cursor = (int)$this->config->getAppValue('werkstattmanager', 'customer_deadline_cursor', '0');
		$rows = $this->store->find('deadlines', ['status' => 'open'], 100, $cursor);
		$today = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')))->format('Y-m-d');
		foreach ($rows as $deadline) {
			try {
				$this->store->transaction(function () use ($deadline, $today) {
					$d = $this->store->get('deadlines', $deadline['id']);
					$v = $this->store->get('vehicles', $d['vehicle_id']);
					if (!$v['customer_id']) {
						return;
					}
					$c = $this->store->get('customers', $v['customer_id']);
					if (!$this->eligible($d, $v, $c, $today)) {
						return;
					}
					$key = hash('sha256', 'customer-deadline:' . $d['id'] . ':' . $d['due_date'] . ':' . $d['due_km'] . ':' . $c['id'] . ':' . $c['email']);
					$previous = $this->store->find('deliveries', ['event_key' => $key], 1)[0] ?? null;
					$payload = ['deadline_id' => $d['id'], 'vehicle_id' => $v['id'], 'customer_id' => $c['id'], 'customer_email' => $c['email'], 'customer_name' => $c['contact'] ?: $c['name'], 'vehicle_label' => $v['plate'] ?: $v['number'], 'title' => $d['title'], 'due_date' => $d['due_date'], 'due_km' => $d['due_km']];
					if (!$previous) {
						$this->store->insert('deliveries', ['event_key' => $key, 'appointment_id' => null, 'appointment_version' => $d['version'], 'vehicle_id' => $v['id'], 'deadline_id' => $d['id'], 'channel' => 'email', 'kind' => 'deadline', 'status' => 'pending', 'attempts' => 0, 'next_try' => time(), 'last_error' => '', 'payload' => $payload]);
					} elseif ($previous['status'] === 'cancelled') {
						$this->store->update('deliveries', $previous, ['status' => 'pending', 'next_try' => time(), 'payload' => $payload]);
					}
				});
			} catch (DBException $e) {
				if ($e->getReason() !== DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
					throw $e;
				}
			} catch (Problem $e) {
				if (!in_array($e->error, ['not_found', 'conflict'], true)) {
					throw $e;
				}
			}
		}
		$this->config->setAppValue('werkstattmanager', 'customer_deadline_cursor', count($rows) === 100 ? (string)($cursor + 100) : '0');
	}
	public function valid(array $payload): bool {
		try {
			$d = $this->store->get('deadlines', $payload['deadline_id']);
			$v = $this->store->get('vehicles', $d['vehicle_id']);
			$c = $this->store->get('customers', $v['customer_id'] ?? '');
			$today = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')))->format('Y-m-d');
			return $v['id'] === $payload['vehicle_id'] && $c['id'] === $payload['customer_id'] && $c['email'] === $payload['customer_email'] && $d['due_date'] === $payload['due_date'] && $d['due_km'] === $payload['due_km'] && $this->eligible($d, $v, $c, $today);
		} catch (Problem $e) {
			if ($e->error !== 'not_found') {
				throw $e;
			}
			return false;
		}
	}
}
