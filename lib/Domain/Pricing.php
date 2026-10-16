<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Domain;

final class Pricing {
	/** Preserve provider independence while withholding names and links, including legacy rows. */
	public static function publicObservations(array $observations): array {
		foreach ($observations as &$row) {
			if (!preg_match('/^Preisgeber [a-f0-9]{12}$/D', $row['provider'])) {
				$row['provider'] = 'Preisgeber ' . substr(hash('sha256', $row['provider']), 0, 12);
			}
			$row['source'] = '';
			$row['scope'] = str_replace(['FSP-HU', 'MGS5+'], ['HU bei derselben Prüfstelle', 'Aktionsinspektion'], $row['scope']);
		}
		unset($row);
		return $observations;
	}
	public static function benchmark(array $observations, string $today): array {
		$groups = [];
		foreach ($observations as $o) {
			if (
				!$o['eligible']
				|| $o['vat'] === null
				|| $o['gross_cents'] === null
				|| $o['gross_cents'] < 0
				|| ($o['valid_until'] && $o['valid_until'] < $today)
				|| ($o['valid_from'] && $o['valid_from'] > $today)
			) {
				continue;
			}
			$key = implode('|', [
				$o['scope'],
				$o['material'],
				$o['unit'],
				$o['vehicle_class'],
				$o['segment'],
				$o['price_kind'],
			]);
			$groups[$key][] = $o;
		}
		$result = [];
		foreach ($groups as $key => $items) {
			$local = array_values(
				array_filter(
					$items,
					fn ($o) => in_array($o['region'], ['Berlin', 'Brandenburg'], true),
				),
			);
			$distinct = function (array $rows): array {
				// One price per pricing provider: no inflation by identical branch lists.
				usort(
					$rows,
					fn ($a, $b) => strcmp($b['checked_date'], $a['checked_date'])
					?: strcmp($a['seed_key'], $b['seed_key']),
				);
				$unique = [];
				foreach ($rows as $row) {
					$unique[$row['provider']] ??= $row;
				}
				return array_values($unique);
			};
			$local = $distinct($local);
			$selected
				= count($local) >= 5
					? $local
					: $distinct(
						array_values(
							array_filter(
								$items,
								fn ($o) => in_array(
									$o['region'],
									[
										'Berlin',
										'Brandenburg',
										'Sachsen',
										'Sachsen-Anhalt',
										'Thüringen',
										'Mecklenburg-Vorpommern',
									],
									true,
								),
							),
						),
					);
			$n = count($selected);
			$values = array_column($selected, 'gross_cents');
			sort($values, SORT_NUMERIC);
			$result[] = [
				'key' => $key,
				'scope' => $items[0]['scope'],
				'material' => $items[0]['material'],
				'unit' => $items[0]['unit'],
				'vehicle_class' => $items[0]['vehicle_class'],
				'segment' => $items[0]['segment'],
				'price_kind' => $items[0]['price_kind'],
				'region' => count($local) >= 5 ? 'Berlin/Brandenburg' : 'Ostdeutschland',
				'count' => $n,
				'local_count' => count($local),
				'quality' => $n >= 5 ? 'comparison' : ($n >= 3 ? 'limited' : 'individual'),
				'mean' => $n >= 5 ? (int)round(array_sum($values) / $n) : null,
				'median'
					=> $n >= 3
						? (int)round(($values[intdiv($n - 1, 2)] + $values[intdiv($n, 2)]) / 2)
						: null,
				'min' => $n ? $values[0] : null,
				'max' => $n ? $values[$n - 1] : null,
				'undated' => count(array_filter($selected, fn ($o) => $o['undated'])) > 0,
				'observations' => $selected,
			];
		}
		return $result;
	}
	public static function calculate(array $profile, array $lines): array {
		$hours
			= Rules::integer($profile['billable_hours'] ?? null, 1, 100000)
			?? throw new Problem('validation', 'Abrechenbare Jahresstunden fehlen.');
		$costs = $profile['costs'] ?? [];
		if (!is_array($costs) || count($costs) > 40) {
			throw new Problem('validation', 'Ungültige Kosten.');
		}
		$total = 0;
		foreach ($costs as $cost) {
			$total += Rules::integer($cost, 0, 10000000000) ?? 0;
		}
		$profit = Rules::integer($profile['profit_percent'] ?? 0, 0, 1000) ?? 0;
		$material = Rules::integer($profile['material_percent'] ?? 0, 0, 1000) ?? 0;
		$vat = Rules::integer($profile['vat_percent'] ?? 19, 0, 100) ?? 19;
		$costRate = $total / $hours;
		$rate = $costRate * (1 + $profit / 100);
		$net = 0;
		$results = [];
		if (count($lines) > 100) {
			throw new Problem('validation', 'Maximal 100 Leistungen.');
		}
		foreach ($lines as $line) {
			$minutes
				= Rules::integer($line['minutes'] ?? null, 0, 100000)
				?? throw new Problem('validation', 'Arbeitsdauer ergänzen.');
			$quantity = Rules::integer($line['quantity'] ?? 1, 1, 1000) ?? 1;
			$parts = Rules::integer($line['material_cents'] ?? 0, 0, 100000000) ?? 0;
			$external = Rules::integer($line['external_cents'] ?? 0, 0, 100000000) ?? 0;
			$labor = (int)round(($rate * $minutes) / 60) * $quantity;
			$partPrice = (int)round($parts * (1 + $material / 100)) * $quantity;
			$sum = $labor + $partPrice + $external * $quantity;
			$net += $sum;
			$results[] = [
				'labor_cents' => $labor,
				'material_cents' => $partPrice,
				'net_cents' => $sum,
			];
		}
		$tax = (int)round(($net * $vat) / 100);
		return [
			'annual_cents' => $total,
			'cost_rate_cents' => (int)round($costRate),
			'rate_cents' => (int)round($rate),
			'lines' => $results,
			'net_cents' => $net,
			'vat_cents' => $tax,
			'gross_cents' => $net + $tax,
		];
	}
}
