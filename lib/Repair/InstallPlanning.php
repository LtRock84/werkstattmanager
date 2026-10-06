<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Repair;

use OCA\Werkstattmanager\Db\Store;
use OCA\Werkstattmanager\Domain\CatalogueUpdate;
use OCA\Werkstattmanager\Service\PlanningLedger;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/** Stable IDs preserve edits, favourites and custom services on install/upgrade. */
final class InstallPlanning implements IRepairStep {
	public function __construct(
		private Store $store,
	) {
	}
	public function getName(): string {
		return 'Initialize Werkstattmanager service and price catalogue';
	}
	public function run(IOutput $output): void {
		$services = json_decode(
			file_get_contents(__DIR__ . '/../Data/services.json'),
			true,
			512,
			JSON_THROW_ON_ERROR,
		);
		foreach ($services['services'] as $v) {
			$key = $v['key'];
			unset($v['key']);
			$this->seed('services', 'service:' . $key, ['seed_key' => $key] + $v);
		}
		$prices = json_decode(
			file_get_contents(__DIR__ . '/../Data/prices.json'),
			true,
			512,
			JSON_THROW_ON_ERROR,
		);
		foreach ($prices['observations'] as $v) {
			$this->seed('prices', 'price:' . $v['seed_key'], $v);
		}
		if (!$this->store->find('planning', ['id' => PlanningLedger::ID], 1)) {
			$week = [];
			for ($i = 1; $i <= 7; $i++) {
				$week[$i] = [
					'minutes' => $i <= 5 ? 480 : 0,
					'max_vehicles' => null,
					'closed' => $i >= 6,
				];
			}
			$this->store->insert('planning', [
				'id' => PlanningLedger::ID,
				'settings' => ['weekdays' => $week],
			]);
		}
	}
	private function seed(string $table, string $key, array $data): void {
		$id = substr(hash('sha256', 'werkstattmanager:catalogue:' . $key), 0, 32);
		$this->store->transaction(function () use ($table, $id, $data) {
			$existing = $this->store->find($table, ['id' => $id], 1)[0] ?? null;
			if (!$existing) {
				$this->store->insert($table, ['id' => $id] + ($table === 'services' ? ['seed_snapshot' => $data] : []) + $data);
			} elseif ($table === 'services') {
				$changes = CatalogueUpdate::changes($existing, $data);
				if ($changes) {
					$this->store->update($table, $existing, $changes);
				}
			}
		});
	}
}
