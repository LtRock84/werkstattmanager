<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Service;

use OCA\Werkstattmanager\Db\Store;
use OCA\Werkstattmanager\Domain\Pricing;
use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Domain\Rules;

final class ServiceLibrary {
	private ServiceUsage $usage;
	public function __construct(
		private Store $store,
		private Access $access,
		private Journal $journal,
		?ServiceUsage $usage = null,
	) {
		$this->usage = $usage ?? new ServiceUsage($store);
	}
	public function usage(): ServiceUsage {
		return $this->usage;
	}
	public function list(): array {
		$this->access->appointments();
		$rows = [];
		for ($offset = 0; ; $offset += 500) {
			$page = $this->store->find('services', [], 500, $offset);
			array_push($rows, ...$page);
			if (count($page) < 500) {
				break;
			}
		}
		$counts = $this->usage->counts();
		foreach ($rows as &$row) {
			$row['usage_count'] = $counts[$row['id']] ?? 0;
			if ($this->access->role() === 'reception') {
				unset($row['own_price'], $row['seed_snapshot']);
			}
		}
		unset($row);
		return ['items' => $rows, 'version' => '2026-10-16.1','coverage' => json_decode((string)file_get_contents(__DIR__ . '/../Data/price-coverage.json'), true, 512, JSON_THROW_ON_ERROR)];
	}
	public function prices(string $key): array {
		$this->access->master();
		$rows = [];
		for ($offset = 0; ; $offset += 500) {
			$p = $this->store->find('prices', ['service_key' => $key], 500, $offset);
			array_push($rows, ...$p);
			if (count($p) < 500) {
				break;
			}
		}
		$rows = Pricing::publicObservations($rows);
		return ['items' => $rows, 'groups' => Pricing::benchmark($rows, gmdate('Y-m-d'))];
	}
	public function save(array $input): array {
		$this->access->master();
		return $this->store->transaction(function () use ($input) {
			$old = empty($input['id'])
				? null
				: $this->store->get('services', Rules::text($input['id'], 32, true));
			if ($old && $old['version'] !== ($input['version'] ?? null)) {
				throw new Problem('conflict', 'Leistung wurde inzwischen geändert.', 409);
			}
			$v = [
				'title' => Rules::text($input['title'] ?? '', 255, true),
				'category' => Rules::text($input['category'] ?? '', 255, true),
				'description' => Rules::text($input['description'] ?? '', 10000),
				'steps' => Rules::steps($input['steps'] ?? []),
				'minutes' => Rules::integer($input['minutes'] ?? null, 1, 100000),
				'unit' => Rules::text($input['unit'] ?? 'Leistung', 50, true),
				'synonyms' => Rules::steps($input['synonyms'] ?? []),
				'favorite' => ($input['favorite'] ?? false) === true,
				'archived' => ($input['archived'] ?? false) === true,
				'own_price' => Rules::integer($input['own_price'] ?? null, 0, 100000000),
				'position' => Rules::integer($input['position'] ?? 0, 0, 10000) ?? 0,
			];
			if (!is_array($input['vehicle_classes'] ?? ['car', 'van'])) {
				throw new Problem('validation', 'Ungültige Fahrzeugklassen.');
			}
			$includes = Rules::steps($input['includes'] ?? ($old['includes'] ?? []));
			foreach ($includes as $key) {
				if (
					!$this->store->find('services', ['seed_key' => $key], 1)
					|| $key === ($old['seed_key'] ?? null)
				) {
					throw new Problem('validation', 'Ungültige enthaltene Leistung.');
				}
			}
			$v['includes'] = $includes;
			if ($old && $v['minutes'] !== $old['minutes']) {
				$v['time_kind'] = 'own';
			}
			$v['vehicle_classes'] = array_values(
				array_unique($input['vehicle_classes'] ?? ['car', 'van']),
			);
			foreach ($v['vehicle_classes'] as $c) {
				Rules::choice($c, ['car', 'van']);
			}
			if (!$old) {
				$v += [
					'seed_key' => 'custom-' . bin2hex(random_bytes(8)),
					'time_kind' => 'own',
					'time_source' => '',
					'time_min' => null,
					'time_max' => null,
				];
			}
			$row = $old
				? $this->store->update('services', $old, $v)
				: $this->store->insert('services', $v);
			$this->journal->record(
				$this->access->uid(),
				'service.saved',
				details: ['title' => $row['title']],
			);
			return $row;
		});
	}
	/** Work-only snapshots; client input cannot carry price or secret fields to the tablet. */
	public function lines(mixed $input, bool $requireMinutes = false, array $previous = []): array {
		if (!is_array($input) || count($input) > 50) {
			throw new Problem('validation', 'Maximal 50 Leistungen pro Vorgang.');
		}
		$rows = [];
		foreach ($input as $line) {
			if (!is_array($line)) {
				throw new Problem('validation', 'Ungültige Leistung.');
			}
			$minutes = Rules::integer($line['minutes'] ?? null, 1, 100000);
			if ($requireMinutes && $minutes === null) {
				throw new Problem('validation', 'Bitte die Arbeitsdauer jeder Leistung ergänzen.');
			}
			$id = Rules::text($line['service_id'] ?? '', 32);
			if ($id !== '') {
				$s = $this->store->get('services', $id);
				if ($s['archived'] && !in_array($id, array_column($previous, 'service_id'), true)) {
					throw new Problem('archived', 'Leistung ist archiviert.', 409);
				}
			}
			$rows[] = [
				'service_id' => $id,
				'key' => Rules::text($line['key'] ?? '', 255),
				'title' => Rules::text($line['title'] ?? '', 255, true),
				'quantity' => Rules::integer($line['quantity'] ?? 1, 1, 1000) ?? 1,
				'minutes' => $minutes,
				'unit' => Rules::text($line['unit'] ?? 'Leistung', 50, true),
				'steps' => Rules::steps($line['steps'] ?? []),
				'vehicle_class' => Rules::choice($line['vehicle_class'] ?? 'car', ['car', 'van']),
			];
		}
		if (array_sum(array_map(fn ($r) => ($r['minutes'] ?? 0) * $r['quantity'], $rows)) > 100000) {
			throw new Problem('validation', 'Gesamtdauer ist zu groß.');
		}
		return $rows;
	}
	public function profile(): array {
		$this->access->master();
		return ['profile' => $this->store->find('cost_profiles', [], 1)[0] ?? null];
	}
	public function calculate(array $input, bool $save = false): array {
		$this->access->master();
		$p = $input['profile'] ?? [];
		$result = Pricing::calculate($p, $input['lines'] ?? []);
		if ($save) {
			$result['profile'] = $this->store->transaction(function () use ($p) {
				$lock = $this->store->get('planning', PlanningLedger::ID);
				$this->store->update('planning', $lock, []);
				$old = $this->store->find('cost_profiles', [], 1)[0] ?? null;
				if ($old && $old['version'] !== ($p['version'] ?? null)) {
					throw new Problem('conflict', 'Kostenprofil wurde geändert.', 409);
				}
				$v = [
					'name' => Rules::text($p['name'] ?? 'Werkstatt', 255, true),
					'costs' => $p['costs'] ?? [],
					'billable_hours' => (int)$p['billable_hours'],
					'profit_percent' => (int)($p['profit_percent'] ?? 0),
					'material_percent' => (int)($p['material_percent'] ?? 0),
					'vat_percent' => (int)($p['vat_percent'] ?? 19),
				];
				return $old
					? $this->store->update('cost_profiles', $old, $v)
					: $this->store->insert('cost_profiles', $v);
			});
		}
		return $result;
	}
}
