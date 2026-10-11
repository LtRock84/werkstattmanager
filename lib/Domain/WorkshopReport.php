<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Domain;

/** Reporting uses recorded amounts, never competitor prices or inferred invoices. */
final class WorkshopReport {
	public static function build(array $data, string $from, string $to, string $scope, int $now): array {
		$zone = new \DateTimeZone('Europe/Berlin');
		$start = (new \DateTimeImmutable($from, $zone))->getTimestamp();
		$end = (new \DateTimeImmutable($to, $zone))->modify('+1 day')->getTimestamp();
		$dayOf = static fn (?string $at): ?string => $at ? (new \DateTimeImmutable($at))->setTimezone($zone)->format('Y-m-d') : null;
		$inside = static fn (?string $d): bool => $d !== null && $d >= $from && $d <= $to;
		$index = static fn (array $rows): array => array_column($rows, null, 'id');
		$orders = $index($data['orders']);
		$vehicles = $index($data['vehicles']);
		$employees = $index($data['employees']);
		$money = [];
		foreach ($data['revenues'] as $r) {
			$money[$r['source_kind'] . ':' . $r['source_id']] = $r;
		}
		$links = [];
		foreach ($data['appointments'] as $a) {
			if ($a['order_id']) {
				$links[$a['order_id']] = $a;
			}
		}
		$selected = static function (array $o) use ($vehicles, $scope): bool {
			$v = $vehicles[$o['vehicle_id']] ?? null;
			return $v !== null && ($scope === 'all' || $v['kind'] === $scope);
		};
		$months = [];
		for ($d = new \DateTimeImmutable($from); $d->format('Y-m-d') <= $to; $d = $d->modify('+1 day')) {
			$months[$d->format('Y-m')] = ['month' => $d->format('Y-m'), 'actual_cents' => 0, 'planned_cents' => 0];
		}
		$summary = ['actual_cents' => 0, 'planned_cents' => 0, 'missing_actual' => 0, 'missing_plan' => 0, 'completed' => 0, 'planned_jobs' => 0, 'work_seconds' => 0, 'fleet_seconds' => 0, 'capacity_minutes' => array_sum(array_column($data['days'], 'minutes')), 'booked_minutes' => array_sum(array_column($data['days'], 'used')), 'completed_planned_minutes' => 0, 'completed_work_seconds' => 0, 'comparable_completed' => 0, 'long_intervals' => 0, 'undated_jobs' => 0];
		$byOrder = [];
		$staff = [];
		foreach ($data['intervals'] as $i) {
			$o = $orders[$i['order_id']] ?? null;
			if (!$o || !$selected($o)) {
				continue;
			}
			$stop = min($now, $i['ended'] ?? $now);
			$whole = max(0, $stop - $i['started']);
			$byOrder[$o['id']] = ($byOrder[$o['id']] ?? 0) + $whole;
			$seconds = max(0, min($stop, $end) - max($i['started'], $start));
			if (!$seconds) {
				continue;
			}
			$summary['work_seconds'] += $seconds;
			$summary['long_intervals'] += $whole >= 36000 ? 1 : 0;
			$fleet = $vehicles[$o['vehicle_id']]['kind'] === 'fleet';
			$summary['fleet_seconds'] += $fleet ? $seconds : 0;
			$id = $i['employee_id'];
			$staff[$id] ??= ['id' => $id, 'name' => $employees[$id]['name'] ?? 'Name nicht mehr verfügbar', 'seconds' => 0, 'fleet_seconds' => 0, 'orders' => [], 'running' => 0];
			$staff[$id]['seconds'] += $seconds;
			$staff[$id]['fleet_seconds'] += $fleet ? $seconds : 0;
			$staff[$id]['orders'][$o['id']] = true;
			$staff[$id]['running'] += $i['ended'] === null ? 1 : 0;
		}
		$rows = [];
		$completed = [];
		$add = function (string $kind, array $o, ?array $a = null) use (&$rows, &$completed, &$summary, &$months, $vehicles, $money, $dayOf, $inside, $byOrder): void {
			$v = $vehicles[$o['vehicle_id']];
			$customer = $v['kind'] === 'customer';
			$value = $money[$kind . ':' . $o['id']] ?? ($a ? ($money['appointments:' . $a['id']] ?? null) : null);
			$isComplete = $kind === 'orders' && $o['status'] === 'completed';
			$done = $isComplete && $inside($dayOf($o['completed_at'] ?? null));
			$actual = $isComplete && $customer && $value && $value['actual_net_cents'] !== null && $inside($value['revenue_date']);
			$alloc = $kind === 'orders' ? ($o['planning_days'] ?? []) : ($o['allocations'] ?? []);
			if ($a && $a['status'] === 'confirmed') {
				$alloc = $a['allocations'] ?? [];
			}
			$at = $kind === 'orders' ? ($o['planned_start'] ?? $o['due_at'] ?? null) : $o['arrival'];
			if (!$alloc && $at) {
				$alloc = [['date' => $dayOf($at), 'minutes' => max(1, $o['minutes'] ?? 1)]];
			}
			$planned = !$isComplete && in_array($o['status'], ['open', 'active', 'paused', 'review', 'confirmed'], true);
			$inDays = array_filter($alloc, fn ($d) => $inside($d['date']));
			$undated = $planned && !$alloc;
			if (!$done && !$actual && !($planned && $inDays) && !$undated) {
				return;
			}
			$forecastCustomer = $customer && ($value['actual_net_cents'] ?? null) === null;
			$plannedAmount = null;
			if ($planned && $inDays) {
				$summary['planned_jobs']++;
				if ($forecastCustomer && ($value['planned_net_cents'] ?? null) === null) {
					$summary['missing_plan']++;
				}
				if ($forecastCustomer && ($value['planned_net_cents'] ?? null) !== null) {
					// Cumulative rounding preserves every cent across any partition of the days.
					usort($alloc, fn ($x, $y) => strcmp($x['date'], $y['date']));
					$total = max(1, array_sum(array_column($alloc, 'minutes')));
					$cumulative = 0;
					$before = 0;
					$plannedAmount = 0;
					foreach ($alloc as $d) {
						$cumulative += $d['minutes'];
						$next = (int)round($value['planned_net_cents'] * $cumulative / $total);
						if ($inside($d['date'])) {
							$portion = $next - $before;
							$plannedAmount += $portion;
							$months[substr($d['date'], 0, 7)]['planned_cents'] += $portion;
						}
						$before = $next;
					}
					$summary['planned_cents'] += $plannedAmount;
				}
			}
			$summary['undated_jobs'] += $undated ? 1 : 0;
			if ($done) {
				$summary['completed']++;
				$summary['missing_actual'] += $customer && ($value['actual_net_cents'] ?? null) === null ? 1 : 0;
				$work = $byOrder[$o['id']] ?? 0;
				if ($o['minutes'] !== null) {
					$summary['completed_planned_minutes'] += $o['minutes'];
					$summary['completed_work_seconds'] += $work;
					$summary['comparable_completed']++;
				}
				$completed[] = ['id' => $o['id'], 'title' => $o['title'], 'minutes' => $o['minutes'], 'seconds' => $work, 'completed_at' => $o['completed_at']];
			}
			if ($actual) {
				$summary['actual_cents'] += $value['actual_net_cents'];
				$months[substr($value['revenue_date'], 0, 7)]['actual_cents'] += $value['actual_net_cents'];
			}
			$rows[] = ['source_kind' => $kind, 'source_id' => $o['id'], 'title' => $o['title'], 'vehicle_label' => trim(($v['plate'] ?? '') . ' ' . ($v['number'] ?? '')), 'vehicle_kind' => $v['kind'], 'status' => $o['status'], 'completed_at' => $o['completed_at'] ?? null, 'date' => $alloc[0]['date'] ?? null, 'undated' => $undated, 'period_planned_cents' => $plannedAmount, 'valuation' => $value];
		};
		foreach ($orders as $o) {
			if (!$selected($o) || in_array($o['status'], ['draft', 'cancelled'], true)) {
				continue;
			}
			$add('orders', $o, $links[$o['id']] ?? null);
		}
		foreach ($data['appointments'] as $a) {
			$linked = $orders[$a['order_id'] ?? ''] ?? null;
			if ($a['status'] !== 'confirmed' || !$selected($a) || ($linked && $linked['status'] !== 'draft')) {
				continue;
			}
			$add('appointments', $a);
		}
		foreach ($staff as &$e) {
			$e['orders'] = count($e['orders']);
		}
		unset($e);
		usort($rows, fn ($a, $b) => strcmp($a['date'] ?? '', $b['date'] ?? '') ?: strcmp($a['source_id'], $b['source_id']));
		return ['from' => $from, 'to' => $to, 'scope' => $scope, 'generated_at' => $now, 'summary' => $summary, 'months' => array_values($months), 'days' => $data['days'], 'employees' => array_values($staff), 'completed' => $completed, 'jobs' => $rows];
	}
}
