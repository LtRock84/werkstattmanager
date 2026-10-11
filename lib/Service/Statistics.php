<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Service;

use OCA\Werkstattmanager\Db\Store;
use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Domain\Rules;
use OCA\Werkstattmanager\Domain\WorkshopReport;

final class Statistics {
	public function __construct(
		private Store $store,
		private Access $access,
		private PlanningLedger $ledger,
		private Journal $journal,
	) {
	}
	public function view(string $from, string $to, string $scope = 'all'): array {
		$this->access->master();
		Rules::choice($scope, ['all', 'customer', 'fleet']);
		$days = $this->ledger->days($from, $to);
		$data = ['days' => $days];
		foreach (['orders', 'appointments', 'vehicles', 'employees', 'intervals', 'revenues'] as $table) {
			$data[$table] = [];
			$cursor = '';
			while (true) {
				$page = $this->store->reportPage($table, $cursor);
				$data[$table] = array_merge($data[$table], $page);
				if (count($data[$table]) > 20000) {
					throw new Problem('report_size', 'Auswertung übersteigt 20.000 Datensätze pro Bereich. Bitte administrativen Export verwenden.');
				}
				if (count($page) < 500) {
					break;
				}
				$cursor = $page[count($page) - 1]['id'];
			}
		}
		return WorkshopReport::build($data, $from, $to, $scope, time());
	}
	public function save(array $input): array {
		$this->access->master();
		return $this->store->transaction(function () use ($input) {
			$this->ledger->lock();
			$kind = Rules::choice($input['source_kind'] ?? null, ['orders', 'appointments']);
			$id = Rules::text($input['source_id'] ?? '', 32, true);
			$source = $this->store->get($kind, $id);
			// Lock the source against concurrent completion/rework/cancellation.
			$this->store->update($kind, $source, []);
			$vehicle = $this->store->get('vehicles', $source['vehicle_id']);
			if ($vehicle['kind'] !== 'customer' || $source['status'] === 'cancelled' || $source['status'] === 'draft') {
				throw new Problem('state', 'Umsatz nur für bestätigte Kundentermine und freigegebene Kundenaufträge erfassen.', 409);
			}
			if ($kind === 'appointments' && $source['order_id']) {
				$order = $this->store->get('orders', $source['order_id']);
				if ($order['status'] !== 'draft') {
					throw new Problem('state', 'Umsatz bitte am verknüpften Arbeitsauftrag bearbeiten.', 409);
				}
			}
			$actual = Rules::integer($input['actual_net_cents'] ?? null, 0, 1000000000);
			$date = Rules::date($input['revenue_date'] ?? null);
			if ($actual !== null && ($kind !== 'orders' || $source['status'] !== 'completed' || !$date)) {
				throw new Problem('validation', 'Ist-Umsatz benötigt einen abgeschlossenen Auftrag und ein Umsatzdatum.');
			}
			$changes = ['source_kind' => $kind, 'source_id' => $id, 'vehicle_id' => $source['vehicle_id'], 'planned_net_cents' => Rules::integer($input['planned_net_cents'] ?? null, 0, 1000000000), 'actual_net_cents' => $actual, 'revenue_date' => $actual === null ? null : $date];
			$old = $this->store->find('revenues', ['source_kind' => $kind, 'source_id' => $id], 1)[0] ?? null;
			if (!$old && $kind === 'orders') {
				$a = $this->store->find('appointments', ['order_id' => $id], 1)[0] ?? null;
				$old = $a ? ($this->store->find('revenues', ['source_kind' => 'appointments', 'source_id' => $a['id']], 1)[0] ?? null) : null;
			}
			// Editing a plan during rework must not erase a previously recorded invoice value.
			if ($kind === 'orders' && $source['status'] !== 'completed' && $old) {
				$changes['actual_net_cents'] = $old['actual_net_cents'];
				$changes['revenue_date'] = $old['revenue_date'];
			}
			$version = Rules::integer($input['version'] ?? 0, 0) ?? 0;
			if (($old['version'] ?? 0) !== $version) {
				throw new Problem('conflict', 'Umsatz wurde inzwischen geändert. Bitte Auswertung aktualisieren.', 409);
			}
			$reason = Rules::text($input['reason'] ?? '', 1000, true);
			$result = $old ? $this->store->update('revenues', $old, $changes) : $this->store->insert('revenues', $changes);
			$this->journal->record($this->access->uid(), 'revenue.saved', $source['vehicle_id'], $kind === 'orders' ? $id : null, details: ['reason' => $reason, 'before' => $old ? array_intersect_key($old, $changes) : null, 'after' => $changes]);
			return $result;
		});
	}
}
