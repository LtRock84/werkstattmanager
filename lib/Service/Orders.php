<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Service;

use OCA\Werkstattmanager\Db\Store;
use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Domain\Rules;
use OCP\DB\Exception as DBException;

final class Orders {
	public function __construct(
		private Store $store,
		private Access $access,
		private Catalog $catalog,
		private Journal $journal,
		private ?ServiceLibrary $library = null,
		private ?PlanningLedger $ledger = null,
	) {
	}
	public function create(array $input): array {
		$this->access->master();
		return $this->idempotent($input, 'create', function () use ($input) {
			$this->ledger?->lock();
			$lines = $this->library?->lines($input['service_lines'] ?? []) ?? [];
			$v = [
				'service_lines' => $lines,
				'vehicle_id' => $this->catalog->reference(
					$input['vehicle_id'] ?? null,
					'vehicles',
					true,
				),
				'title' => Rules::text($input['title'] ?? '', 255, true),
				'description' => Rules::text($input['description'] ?? '', 10000),
				'steps' => Rules::steps($input['steps'] ?? []),
				'minutes' => Rules::integer($input['minutes'] ?? null, 1, 100000),
				'planned_start' => Rules::date($input['planned_start'] ?? null, true),
				'due_at' => Rules::date($input['due_at'] ?? null, true),
				'priority' => Rules::choice($input['priority'] ?? 'normal', [
					'low',
					'normal',
					'urgent',
				]),
				'status' => Rules::choice($input['status'] ?? 'open', ['draft', 'open']),
			];
			Rules::schedule($v['planned_start'], $v['due_at']);
			if ($v['planned_start'] && !$v['minutes']) {
				throw new Problem('validation', 'Für eine verbindliche Tagesplanung bitte die Arbeitsdauer ergänzen.');
			}
			$allocations
				= $this->ledger?->allocations(
					$input['planning_days'] ?? null,
					$v['minutes'] ?? 0,
					$v['planned_start'],
				) ?? [];
			$v['planning_days'] = $allocations;
			if ($this->ledger) {
				$v += $this->ledger->bounds($allocations, $v['planned_start']);
			}
			if ($v['status'] === 'open') {
				$this->ledger?->check(
					[
						'allocations' => $allocations,
						'planned_start' => $v['planned_start'],
						'vehicle_id' => $v['vehicle_id'],
					],
					Rules::text($input['override_reason'] ?? '', 2000),
				);
			}
			// Serialise creation against vehicle archival.
			$this->store->update('vehicles', $this->store->get('vehicles', $v['vehicle_id']), []);
			$order = $this->store->insert('orders', $v);
			$this->library?->usage()->order($order);
			if (!empty($input['defect_id'])) {
				$d = $this->store->get('defects', Rules::text($input['defect_id'], 32, true));
				if (
					$d['vehicle_id'] !== $v['vehicle_id']
					|| $d['order_id']
					|| !in_array($d['status'], ['new', 'open'], true)
				) {
					throw new Problem('defect', 'Mangel ist nicht zuordenbar.', 409);
				}
				$this->store->update('defects', $d, ['order_id' => $order['id']]);
			}
			$this->journal->record(
				$this->access->uid(),
				'order.created',
				$v['vehicle_id'],
				$order['id'],
				details: ['override_reason' => Rules::text($input['override_reason'] ?? '', 2000)],
			);
			return $order;
		});
	}
	public function action(string $id, string $action, array $input): array {
		$this->access->workshop();
		if (!in_array($action, ['start', 'pause', 'finish'], true)) {
			$this->access->master();
		}
		return $this->idempotent($input, $id . ':' . $action, function () use (
			$id,
			$action,
			$input,
		) {
			if (in_array($action, ['release','rework','cancel'], true)) {
				$this->ledger?->lock();
			}
			$o = $this->catalog->visibleOrder($id);
			if (($input['version'] ?? null) !== $o['version']) {
				throw new Problem(
					'conflict',
					'Auftrag wurde inzwischen geändert. Bitte aktualisieren.',
					409,
				);
			}
			$next = Rules::transition($o['status'], $action);
			if (
				in_array($action, ['release', 'rework'], true)
				&& !$this->store->find('appointments', ['order_id' => $id, 'status' => 'confirmed'], 1)
			) {
				$this->ledger?->check(
					[
						'allocations' => $o['planning_days'] ?? [],
						'planned_start' => $o['planned_start'],
						'vehicle_id' => $o['vehicle_id'],
					],
					Rules::text($input['override_reason'] ?? '', 2000),
					$id,
				);
			}
			if (in_array($action, ['start', 'release', 'rework'], true)) {
				$vehicle = $this->store->get('vehicles', $o['vehicle_id']);
				if ($vehicle['archived']) {
					throw new Problem('archived', 'Fahrzeug ist archiviert.', 409);
				}
				$this->store->update('vehicles', $vehicle, []);
			}
			$employee = null;
			$changes = ['status' => $next, 'pause_reason' => null];
			if ($action === 'start') {
				$employee = $this->catalog->reference(
					$input['employee_id'] ?? null,
					'employees',
					true,
				);
				$this->store->update('employees', $this->store->get('employees', $employee), []);
				$changes['last_employee_id'] = $employee;
				if ($this->store->find('active', ['employee_id' => $employee], 1)) {
					throw new Problem(
						'employee_busy',
						'Mitarbeiter hat bereits einen aktiven Auftrag.',
						409,
					);
				}
				if ($this->store->find('active', ['order_id' => $id], 1)) {
					throw new Problem('order_busy', 'Auftrag wird bereits bearbeitet.', 409);
				}
				$interval = $this->store->insert('intervals', [
					'order_id' => $id,
					'employee_id' => $employee,
					'started' => time(),
					'ended' => null,
					'note' => '',
					'steps' => [],
				]);
				$this->store->insert('active', [
					'order_id' => $id,
					'employee_id' => $employee,
					'interval_id' => $interval['id'],
				]);
			}
			if (in_array($action, ['pause', 'finish'], true)) {
				$active = $this->store->find('active', ['order_id' => $id], 1)[0] ?? null;
				if (!$active || $active['employee_id'] !== ($input['employee_id'] ?? null)) {
					throw new Problem('employee', 'Bitte den aktiven Mitarbeiter auswählen.', 409);
				}
				$employee = $active['employee_id'];
				$interval = $this->store->get('intervals', $active['interval_id']);
				$note = Rules::text($input['note'] ?? '', 10000);
				$steps = Rules::steps($input['steps'] ?? []);
				if ($action === 'finish' && !$steps && $note === '') {
					throw new Problem(
						'protocol',
						'Bitte erledigte Arbeiten auswählen oder eine Notiz eingeben.',
					);
				}
				if ($action === 'pause') {
					$changes['pause_reason'] = Rules::text($input['reason'] ?? '', 255, true);
				}
				$this->store->update('intervals', $interval, [
					'ended' => time(),
					'note' => $note,
					'steps' => $steps,
				]);
				$this->store->delete('active', $active['id']);
			}
			if ($action === 'approve') {
				if (
					$this->store->find(
						'parts',
						['order_id' => $id, 'status' => ['requested', 'ordered']],
						1,
					)
				) {
					throw new Problem(
						'parts_open',
						'Offene Teileanforderungen zuerst erledigen oder verwerfen.',
						409,
					);
				}
				$resolved = $input['resolved_defects'] ?? [];
				if (!is_array($resolved) || count($resolved) > 100) {
					throw new Problem('validation', 'Ungültige Mängelauswahl.');
				}
				foreach ($resolved as $defectId) {
					$d = $this->store->get('defects', Rules::text($defectId, 32, true));
					if ($d['order_id'] !== $id) {
						throw new Problem('defect', 'Mangel gehört nicht zum Auftrag.');
					}
					$this->store->update('defects', $d, [
						'status' => 'resolved',
						'published' => false,
					]);
				}
				$changes['completed_at'] = gmdate('Y-m-d\TH:i:s\Z');
			}
			if (in_array($action, ['cancel', 'rework'], true)) {
				Rules::text($input['reason'] ?? '', 1000, true);
				$changes['completed_at'] = null;
			}
			$o = $this->store->update('orders', $o, $changes);
			if (in_array($action, ['release', 'cancel'], true)) {
				$this->library?->usage()->order($o);
			}
			$this->journal->record(
				$this->access->uid(),
				'order.' . $action,
				$o['vehicle_id'],
				$id,
				$employee,
				[
					'override_reason' => in_array($action, ['release', 'rework'], true) ? Rules::text($input['override_reason'] ?? '', 2000) : '',
					'reason' => in_array($action, ['pause', 'cancel', 'rework'], true)
						? Rules::text($input['reason'] ?? '', 1000)
						: '',
					'note' => in_array($action, ['pause', 'finish'], true) ? $note ?? '' : '',
					'steps' => in_array($action, ['pause', 'finish'], true) ? $steps ?? [] : [],
				],
			);
			if ($action === 'finish') {
				$this->journal->notify('review', $id);
			}
			return $o;
		});
	}
	public function requestPart(string $id, array $input): array {
		$this->access->workshop();
		return $this->idempotent($input, $id . ':part', function () use ($id, $input) {
			$o = $this->catalog->visibleOrder($id);
			$a = $this->store->find('active', ['order_id' => $id], 1)[0] ?? null;
			if (!$a || $a['employee_id'] !== ($input['employee_id'] ?? null)) {
				throw new Problem('employee', 'Aktiven Mitarbeiter auswählen.', 409);
			}
			$p = $this->store->insert('parts', [
				'order_id' => $id,
				'employee_id' => $a['employee_id'],
				'title' => Rules::text($input['title'] ?? '', 255, true),
				'quantity' => Rules::integer($input['quantity'] ?? 1, 1, 10000) ?? 1,
				'part_number' => Rules::text($input['part_number'] ?? ''),
				'priority' => Rules::choice($input['priority'] ?? 'normal', ['normal', 'urgent']),
				'note' => Rules::text($input['note'] ?? '', 10000),
				'status' => 'requested',
			]);
			// Touch the order in both variants: concurrent finish/part requests cannot cross the acceptance boundary.
			$changes = [];
			if (($input['pause'] ?? false) === true) {
				$i = $this->store->get('intervals', $a['interval_id']);
				$this->store->update('intervals', $i, [
					'ended' => time(),
					'note' => 'Warten auf Teile',
					'steps' => [],
				]);
				$this->store->delete('active', $a['id']);
				$changes = ['status' => 'paused', 'pause_reason' => 'Warten auf Teile'];
			}
			$this->store->update('orders', $o, $changes);
			$this->journal->record(
				$this->access->uid(),
				'part.requested',
				$o['vehicle_id'],
				$id,
				$a['employee_id'],
				['part_id' => $p['id'], 'paused' => ($input['pause'] ?? false) === true],
			);
			$this->journal->notify('part', $p['id']);
			return $p;
		});
	}
	public function updatePart(string $id, string $status, int $version): array {
		$this->access->master();
		Rules::choice($status, ['ordered', 'received', 'rejected', 'cancelled']);
		return $this->store->transaction(function () use ($id, $status, $version) {
			$p = $this->store->get('parts', $id);
			if ($p['version'] !== $version) {
				throw new Problem('conflict', 'Anforderung wurde geändert.', 409);
			}
			$allowed = [
				'requested' => ['ordered', 'rejected', 'cancelled'],
				'ordered' => ['received', 'cancelled'],
			];
			if (!in_array($status, $allowed[$p['status']] ?? [], true)) {
				throw new Problem('state', 'Ungültiger Teile-Statuswechsel.', 409);
			}
			$o = $this->store->get('orders', $p['order_id']);
			$this->store->update('orders', $o, []);
			$p = $this->store->update('parts', $p, ['status' => $status]);
			$this->journal->record(
				$this->access->uid(),
				'part.' . $status,
				$o['vehicle_id'],
				$o['id'],
				null,
				['part_id' => $id],
			);
			return $p;
		});
	}
	public function correctTime(string $id, array $input): array {
		$this->access->master();
		$reason = Rules::text($input['reason'] ?? '', 1000, true);
		// Unix timestamps deliberately have a separate upper bound from mileage/counts.
		$start = filter_var($input['started'] ?? null, FILTER_VALIDATE_INT);
		$end = filter_var($input['ended'] ?? null, FILTER_VALIDATE_INT);
		if (!$start || !$end || $start < 1 || $end < $start || $end > time()) {
			throw new Problem('validation', 'Ungültiges Zeitintervall.');
		}
		return $this->store->transaction(function () use ($id, $start, $end, $reason, $input) {
			$i = $this->store->get('intervals', $id);
			if ($i['ended'] === null || $i['version'] !== ($input['version'] ?? null)) {
				throw new Problem(
					'conflict',
					'Nur unveränderte, beendete Intervalle können korrigiert werden.',
					409,
				);
			}
			$employee = $this->store->get('employees', $i['employee_id']);
			$this->store->update('employees', $employee, []);
			for ($offset = 0; ; $offset += 500) {
				$rows = $this->store->find(
					'intervals',
					['employee_id' => $i['employee_id']],
					500,
					$offset,
				);
				foreach ($rows as $other) {
					if (
						$other['id'] !== $id
						&& $start < ($other['ended'] ?? PHP_INT_MAX)
						&& $end > $other['started']
					) {
						throw new Problem(
							'overlap',
							'Zeit überschneidet sich mit einer anderen Buchung.',
							409,
						);
					}
				}
				if (count($rows) < 500) {
					break;
				}
			}
			$o = $this->store->get('orders', $i['order_id']);
			$updated = $this->store->update('intervals', $i, [
				'started' => $start,
				'ended' => $end,
			]);
			$this->journal->record(
				$this->access->uid(),
				'time.corrected',
				$o['vehicle_id'],
				$o['id'],
				$i['employee_id'],
				[
					'interval_id' => $id,
					'before' => ['started' => $i['started'], 'ended' => $i['ended']],
					'after' => ['started' => $start, 'ended' => $end],
					'reason' => $reason,
				],
			);
			return $updated;
		});
	}
	private function idempotent(array $input, string $operation, callable $work): array {
		$key = Rules::text($input['request_id'] ?? '', 64, true);
		if (!preg_match('/^[a-zA-Z0-9-]{16,64}$/D', $key)) {
			throw new Problem('request_id', 'Ungültige Vorgangskennung.');
		}
		$key = hash('sha256', $this->access->uid() . ':' . $key);
		$fingerprint = hash('sha256', $operation . json_encode($input, JSON_THROW_ON_ERROR));
		$previous = $this->store->find('requests', ['request_key' => $key], 1)[0] ?? null;
		if ($previous) {
			if ($previous['fingerprint'] !== $fingerprint) {
				throw new Problem('request_id', 'Vorgangskennung bereits anders verwendet.', 409);
			}
			return $previous['response'];
		}
		try {
			return $this->store->transaction(function () use ($key, $fingerprint, $work) {
				$r = $this->store->insert('requests', [
					'request_key' => $key,
					'fingerprint' => $fingerprint,
					'response' => [],
				]);
				$result = $work();
				$this->store->update('requests', $r, ['response' => $result]);
				return $result;
			});
		} catch (DBException $e) {
			if (
				in_array(
					$e->getReason(),
					[
						DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION,
						DBException::REASON_DEADLOCK,
						DBException::REASON_LOCK_WAIT_TIMEOUT,
					],
					true,
				)
			) {
				$previous = $this->store->find('requests', ['request_key' => $key], 1)[0] ?? null;
				if ($previous && $previous['fingerprint'] === $fingerprint) {
					return $previous['response'];
				}
				throw new Problem(
					'conflict',
					'Gleichzeitige Buchung erkannt. Bitte aktualisieren.',
					409,
				);
			}
			throw $e;
		}
	}
}
