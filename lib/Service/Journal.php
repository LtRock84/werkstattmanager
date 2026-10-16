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
	/** Resolve one page of historical references in batches; never copy employee names into audit storage. */
	public function withContext(array $rows): array {
		$this->access->master();
		$ids = ['orders' => [], 'employees' => [], 'parts' => [], 'defects' => [], 'deadlines' => []];
		foreach ($rows as $row) {
			$ids['orders'][] = $row['order_id'] ?? null;
			$ids['employees'][] = $row['employee_id'] ?? null;
			if ($row['action'] === 'order.plan') {
				$ids['employees'][] = $row['details']['before_employee_id'] ?? null;
			}
			$ids['parts'][] = $row['details']['part_id'] ?? null;
			$ids['defects'][] = $row['details']['defect_id'] ?? null;
			if (str_starts_with($row['action'], 'deadline')) {
				$ids['deadlines'][] = $row['details']['id'] ?? null;
			}
		}
		$fields = [
			'orders' => ['id', 'title'],
			'employees' => ['name'],
			'parts' => ['title', 'quantity', 'part_number', 'priority', 'note'],
			'defects' => ['description', 'category'],
			'deadlines' => ['title'],
		];
		$references = [];
		foreach ($ids as $kind => $values) {
			$values = array_values(array_unique(array_filter($values)));
			$references[$kind] = [];
			if ($values) {
				foreach ($this->store->find($kind, ['id' => $values], 500) as $reference) {
					$references[$kind][$reference['id']] = array_intersect_key($reference, array_flip($fields[$kind]));
				}
			}
		}
		foreach ($rows as &$row) {
			$row['context'] = [
				'order' => $references['orders'][$row['order_id'] ?? ''] ?? null,
				'employee' => $references['employees'][$row['employee_id'] ?? ''] ?? null,
				'previous_employee' => $row['action'] === 'order.plan' ? ($references['employees'][$row['details']['before_employee_id'] ?? ''] ?? null) : null,
				'part' => $references['parts'][$row['details']['part_id'] ?? ''] ?? null,
				'defect' => $references['defects'][$row['details']['defect_id'] ?? ''] ?? null,
				'deadline' => $references['deadlines'][$row['details']['id'] ?? ''] ?? null,
			];
		}
		unset($row);
		return $rows;
	}
}
