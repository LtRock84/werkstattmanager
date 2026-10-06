<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Service;

use OCA\Werkstattmanager\Db\Store;

/** Derived, anonymous ranking: one selected service per committed work occurrence. */
final class ServiceUsage {
	public function __construct(
		private Store $store,
	) {
	}
	public function appointment(array $a): void {
		$order = $a['order_id'] ? $this->store->get('orders', $a['order_id']) : null;
		$lines = $order && !in_array($order['status'], ['draft', 'cancelled'], true)
			? ($order['service_lines'] ?? [])
			: ($a['status'] === 'confirmed' ? $a['service_lines'] : []);
		$this->replace('a:' . $a['id'], $lines);
		if ($order) {
			$this->replace('o:' . $order['id'], []);
		}
	}
	public function order(array $order): void {
		$a = $this->store->find('appointments', ['order_id' => $order['id']], 1)[0] ?? null;
		if ($a) {
			$this->appointment($a);
		} else {
			$this->replace('o:' . $order['id'], in_array($order['status'], ['draft', 'cancelled'], true) ? [] : ($order['service_lines'] ?? []));
		}
	}
	private function replace(string $origin, array $lines): void {
		$ids = array_values(array_unique(array_filter(array_column($lines, 'service_id'))));
		$existing = $this->store->find('service_usage', ['origin' => $origin], 500);
		foreach ($existing as $row) {
			if (!in_array($row['service_id'], $ids, true)) {
				$this->store->delete('service_usage', $row['id']);
			}
		}
		$known = array_column($existing, 'service_id');
		foreach ($ids as $id) {
			if (!in_array($id, $known, true)) {
				$this->store->insert('service_usage', ['origin' => $origin, 'service_id' => $id]);
			}
		}
	}
	/** Cursor scan with bounded transactions, safe to repeat and concurrent with bookings. */
	public function backfill(string $kind, string $cursor = ''): array {
		$rows = $this->store->usageBackfillPage($kind, $cursor);
		foreach ($rows as $row) {
			$this->store->transaction(function () use ($kind, $row) {
				$lock = $this->store->get('planning', PlanningLedger::ID);
				$this->store->update('planning', $lock, []);
				$current = $this->store->find($kind, ['id' => $row['id']], 1)[0] ?? null;
				if (!$current) {
					return;
				}
				$kind === 'appointments' ? $this->appointment($current) : $this->order($current);
			});
		}
		return ['cursor' => $rows ? $rows[array_key_last($rows)]['id'] : $cursor, 'more' => count($rows) === 100];
	}
	public function counts(): array {
		return $this->store->serviceCounts();
	}
}
