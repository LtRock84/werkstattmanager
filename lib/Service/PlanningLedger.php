<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Service;

use OCA\Werkstattmanager\Db\Store;
use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Domain\Rules;

/** One versioned row serialises capacity-changing transactions across all masters. */
final class PlanningLedger {
	public const ID = '00000000000000000000000000000001';
	public function __construct(
		private Store $store,
	) {
	}
	public function lock(): array {
		$row = $this->store->get('planning', self::ID);
		return $this->store->update('planning', $row, []);
	}
	public function settings(): array {
		return $this->store->get('planning', self::ID);
	}
	public function allocations(mixed $input, int $minutes, ?string $start): array {
		if ($input === null || $input === []) {
			if (!$start) {
				return [];
			}
			$day = (new \DateTimeImmutable($start))
				->setTimezone(new \DateTimeZone('Europe/Berlin'))
				->format('Y-m-d');
			return [['date' => $day, 'minutes' => $minutes]];
		}
		if (!is_array($input) || count($input) > 366) {
			throw new Problem('validation', 'Ungültige Tagesplanung.');
		}
		$rows = [];
		foreach ($input as $row) {
			if (!is_array($row)) {
				throw new Problem('validation', 'Ungültiger Planungstag.');
			}
			$day
				= Rules::date($row['date'] ?? null)
				?? throw new Problem('validation', 'Planungsdatum fehlt.');
			if (isset($rows[$day])) {
				throw new Problem('validation', 'Planungstag doppelt angegeben.');
			}
			$rows[$day] = [
				'date' => $day,
				'minutes'
					=> Rules::integer($row['minutes'] ?? null, 1, 100000)
					?? throw new Problem('validation', 'Tagesdauer fehlt.'),
			];
		}
		if (array_sum(array_column($rows, 'minutes')) !== $minutes) {
			throw new Problem(
				'validation',
				'Die Tagesplanung muss die gesamte Arbeitsdauer verteilen.',
			);
		}
		ksort($rows);
		return array_values($rows);
	}
	public function window(string $kind, string $from, string $to): iterable {
		for ($offset = 0; ; $offset += 500) {
			$page = $this->store->planningRows($kind, $from, $to, $offset);
			yield from $page;
			if (count($page) < 500) {
				break;
			}
		}
	}
	public function bounds(array $allocations, ?string $at): array {
		$dates = array_column($allocations, 'date');
		if ($at) {
			$dates[] = (new \DateTimeImmutable($at))
				->setTimezone(new \DateTimeZone('Europe/Berlin'))
				->format('Y-m-d');
		}
		return [
			'first_day' => $dates ? min($dates) : null,
			'last_day' => $dates ? max($dates) : null,
		];
	}
	public function days(
		string $from,
		string $to,
		?array $candidate = null,
		?string $exclude = null,
	): array {
		if (!Rules::date($from) || !Rules::date($to)) {
			throw new Problem('validation', 'Zeitraum fehlt.');
		}
		if (
			$to < $from
			|| (new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->days > 366
		) {
			throw new Problem('validation', 'Maximal ein Jahr anzeigen.');
		}
		$settings = $this->settings()['settings'];
		$days = [];
		$overrides = [];
		foreach ($this->window('capacity', $from, $to) as $r) {
			$overrides[$r['date']] = $r;
		}
		for (
			$date = new \DateTimeImmutable($from);
			$date->format('Y-m-d') <= $to;
			$date = $date->modify('+1 day')
		) {
			$d = $date->format('Y-m-d');
			$base = $settings['weekdays'][$date->format('N')] ?? [
				'minutes' => 0,
				'max_vehicles' => null,
				'closed' => true,
			];
			$v = $overrides[$d] ?? $base;
			$days[$d] = [
				'date' => $d,
				'minutes' => $v['closed'] ?? false ? 0 : (int)$v['minutes'],
				'max_vehicles' => $v['max_vehicles'] ?? null,
				'closed' => (bool)($v['closed'] ?? false),
				'used' => 0,
				'vehicles' => [],
				'override' => $overrides[$d] ?? null,
			];
		}
		$linked = [];
		$add = function (array $allocations, string $vehicle) use (&$days): void {
			foreach ($allocations as $a) {
				if (isset($days[$a['date']])) {
					$days[$a['date']]['used'] += $a['minutes'];
				}
			}
		};
		$arrival = function (?string $at, string $vehicle) use (&$days): void {
			if (!$at) {
				return;
			}
			$d = (new \DateTimeImmutable($at))
				->setTimezone(new \DateTimeZone('Europe/Berlin'))
				->format('Y-m-d');
			if (isset($days[$d])) {
				$days[$d]['vehicles'][$vehicle] = true;
			}
		};
		foreach ($this->window('appointments', $from, $to) as $a) {
			if ($a['status'] !== 'confirmed') {
				continue;
			}
			if ($a['order_id']) {
				$linked[$a['order_id']] = true;
			}
			if ($a['id'] === $exclude) {
				continue;
			}
			$add($a['allocations'] ?? [], $a['vehicle_id']);
			$arrival($a['arrival'], $a['vehicle_id']);
		}
		foreach ($this->window('orders', $from, $to) as $o) {
			if (
				isset($linked[$o['id']])
				|| $o['id'] === $exclude
				|| $this->store->find(
					'appointments',
					['order_id' => $o['id'], 'status' => 'confirmed'],
					1,
				)
			) {
				continue;
			}
			// Legacy orders keep their existing duration/start without a destructive backfill.
			$alloc
				= $o['planning_days']
				?? ($o['minutes'] ? $this->allocations(null, $o['minutes'], $o['planned_start']) : []);
			$add($alloc, $o['vehicle_id']);
			$arrival($o['planned_start'], $o['vehicle_id']);
		}
		if ($candidate) {
			$add($candidate['allocations'], $candidate['vehicle_id']);
			$arrival(
				$candidate['arrival'] ?? ($candidate['planned_start'] ?? null),
				$candidate['vehicle_id'],
			);
		}
		foreach ($days as &$d) {
			$d['vehicle_count'] = count($d['vehicles']);
			unset($d['vehicles']);
			$d['free'] = max(0, $d['minutes'] - $d['used']);
			$d['percent'] = $d['minutes']
				? (int)round((100 * $d['used']) / $d['minutes'])
				: ($d['used']
					? 100
					: 0);
			$d['overbooked']
				= $d['used'] > $d['minutes']
				|| ($d['max_vehicles'] !== null && $d['vehicle_count'] > $d['max_vehicles']);
		}
		unset($d);
		return array_values($days);
	}
	public function check(array $candidate, string $reason = '', ?string $exclude = null): void {
		$alloc = $candidate['allocations'];
		$dates = array_column($alloc, 'date');
		if ($candidate['arrival'] ?? ($candidate['planned_start'] ?? null)) {
			$dates[] = (new \DateTimeImmutable(
				$candidate['arrival'] ?? $candidate['planned_start'],
			))
				->setTimezone(new \DateTimeZone('Europe/Berlin'))
				->format('Y-m-d');
		}
		if (!$dates) {
			return;
		}
		foreach ($this->days(min($dates), max($dates), $candidate, $exclude) as $day) {
			if ($day['overbooked'] && $reason === '') {
				throw new Problem(
					'overbooked',
					'Tageskapazität überschritten. Bitte anderen Tag wählen oder eine Begründung für die Überbuchung eintragen.',
					409,
				);
			}
		}
	}
}
