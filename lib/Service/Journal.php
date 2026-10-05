<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Service;

use OCA\Werkstattmanager\Db\Store;

final class Journal {
	public function __construct(
		private Store $store,
		private Access $access,
	) {
	}
	public function record(
		string $actor,
		string $action,
		?string $vehicle = null,
		?string $order = null,
		?string $employee = null,
		array $details = [],
	): void {
		$this->store->insert('audit', [
			'actor' => $actor,
			'action' => $action,
			'vehicle_id' => $vehicle,
			'order_id' => $order,
			'employee_id' => $employee,
			'details' => $details,
		]);
	}
	public function notify(string $subject, string $id): void {
		foreach ($this->access->masters() as $uid) {
			$key = hash('sha256', $subject . ':' . $id . ':' . $uid);
			if (!$this->store->find('outbox', ['event_key' => $key], 1)) {
				$this->store->insert('outbox', [
					'event_key' => $key,
					'object_id' => $id,
					'subject' => $subject,
					'recipient' => $uid,
					'status' => 'pending',
					'attempts' => 0,
					'next_try' => time(),
				]);
			}
		}
	}
}
