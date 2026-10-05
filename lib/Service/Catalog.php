<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Service;

use OCA\Werkstattmanager\Db\Store;
use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Domain\Rules;

final class Catalog {
	public const EDITABLE = [
		'customers',
		'vehicles',
		'employees',
		'templates',
		'snippets',
		'deadlines',
	];
	public function __construct(
		private Store $store,
		private Access $access,
		private Journal $journal,
	) {
	}
	public function list(
		string $kind,
		int $offset = 0,
		array $filters = [],
		string $search = '',
	): array {
		$role = $this->access->role();
		if (
			!in_array(
				$kind,
				array_merge(self::EDITABLE, [
					'defects',
					'parts',
					'orders',
					'intervals',
					'active',
					'audit',
					'attachments',
					'mileage',
					'outbox',
				]),
				true,
			)
		) {
			throw new Problem('not_found', 'Unbekannter Bereich.', 404);
		}
		if (
			$role === 'tablet'
			&& !in_array(
				$kind,
				[
					'vehicles',
					'employees',
					'snippets',
					'orders',
					'parts',
					'intervals',
					'active',
					'attachments',
				],
				true,
			)
		) {
			throw new Problem('forbidden', 'Kein Zugriff.', 403);
		}
		if ($role === 'tablet' && in_array($kind, ['parts', 'intervals', 'attachments'], true)) {
			if (empty($filters['order_id'])) {
				throw new Problem('forbidden', 'Auftrag erforderlich.', 403);
			}
			$this->visibleOrder($filters['order_id']);
		}
		if ($kind === 'orders' && $role === 'tablet') {
			$allowed = ['open', 'active', 'paused', 'review'];
			if (isset($filters['status']) && !in_array($filters['status'], $allowed, true)) {
				throw new Problem('forbidden', 'Status nicht verfügbar.', 403);
			}
			$filters['status'] = $filters['status'] ?? $allowed;
		}
		if ($kind === 'orders' && isset($filters['employee_id'])) {
			$filters['last_employee_id'] = $filters['employee_id'];
			unset($filters['employee_id']);
		}
		if ($kind === 'outbox') {
			$filters['status'] = 'failed';
		}
		$rows = $this->store->find($kind, $filters, 101, $offset, Rules::text($search, 100));
		$more = count($rows) > 100;
		$rows = array_slice($rows, 0, 100);
		if ($kind === 'outbox') {
			$rows = array_map(fn ($row) => array_intersect_key($row, array_flip(['id', 'version', 'created_at', 'recipient', 'subject', 'attempts'])), $rows);
		}
		if ($role === 'tablet') {
			$allow = match ($kind) {
				'vehicles' => ['id', 'plate', 'number', 'make', 'model', 'archived'],
				'employees' => ['id', 'name', 'archived'],
				'orders' => [
					'id',
					'last_employee_id',
					'version',
					'vehicle_id',
					'title',
					'description',
					'steps',
					'minutes',
					'planned_start',
					'due_at',
					'priority',
					'status',
					'pause_reason',
					'created_at',
				],
				'attachments' => ['id', 'name', 'mime', 'size', 'order_id'],
				default => null,
			};
			if ($allow) {
				$rows = array_map(fn ($r) => array_intersect_key($r, array_flip($allow)), $rows);
			}
		}
		return ['items' => $rows, 'more' => $more, 'offset' => $offset];
	}
	public function retryNotification(string $id, int $version): array {
		$this->access->master();
		return $this->store->transaction(function () use ($id, $version) {
			$row = $this->store->get('outbox', $id);
			if ($row['version'] !== $version) {
				throw new Problem('conflict', 'Benachrichtigung wurde inzwischen geändert.', 409);
			}
			if ($row['status'] !== 'failed') {
				throw new Problem('state', 'Nur fehlgeschlagene Zustellungen können wiederholt werden.', 409);
			}
			$allowed = $this->access->isMaster($row['recipient']);
			$this->store->update('outbox', $row, $allowed
				? ['status' => 'pending', 'attempts' => 0, 'next_try' => time()]
				: ['status' => 'cancelled']);
			$this->journal->record($this->access->uid(), $allowed ? 'notification.retry' : 'notification.cancelled', details: [
				'notification_id' => $id, 'previous_attempts' => $row['attempts'],
			]);
			return ['queued' => $allowed, 'cancelled' => !$allowed];
		});
	}
	public function visibleOrder(string $id): array {
		$role = $this->access->role();
		$o = $this->store->get('orders', $id);
		if (
			$role === 'tablet'
			&& !in_array($o['status'], ['open', 'active', 'paused', 'review'], true)
		) {
			throw new Problem('not_found', 'Auftrag nicht verfügbar.', 404);
		}
		return $o;
	}
	public function save(string $kind, array $input, ?string $id = null): array {
		$this->access->master();
		if (!in_array($kind, self::EDITABLE, true)) {
			throw new Problem('validation', 'Bereich nicht bearbeitbar.');
		}
		$old = $id ? $this->store->get($kind, $id) : null;
		if ($old && ($input['version'] ?? null) !== $old['version']) {
			throw new Problem('conflict', 'Eintrag inzwischen geändert.', 409);
		}
		$v = [];
		$texts = match ($kind) {
			'customers' => ['name', 'contact', 'email', 'phone', 'notes'],
			'vehicles' => ['plate', 'number', 'vin', 'make', 'model', 'notes'],
			'employees' => ['name'],
			'templates' => ['title', 'description'],
			'snippets' => ['title', 'text'],
			'deadlines' => ['title'],
		};
		foreach ($texts as $key) {
			$v[$key] = Rules::text(
				$input[$key] ?? '',
				in_array($key, ['description', 'notes', 'text'], true) ? 10000 : 255,
				in_array($key, ['name', 'title'], true),
			);
		}
		if (
			in_array($kind, ['customers', 'vehicles', 'employees', 'templates', 'snippets'], true)
		) {
			$v['archived'] = ($input['archived'] ?? false) === true;
		}
		if ($kind === 'vehicles') {
			$v['kind'] = Rules::choice($input['kind'] ?? 'fleet', ['fleet', 'customer']);
			$v['customer_id'] = $this->reference($input['customer_id'] ?? null, 'customers');
			if ($v['kind'] === 'customer' && !$v['customer_id']) {
				throw new Problem('validation', 'Kunde erforderlich.');
			}
			if ($v['plate'] === '' && $v['number'] === '') {
				throw new Problem('validation', 'Kennzeichen oder Fahrzeugnummer erforderlich.');
			}
			$v['registration'] = Rules::date($input['registration'] ?? null);
			// Mileage is recorded through an explicit, journalled action only.
			if (!$old) {
				$v['mileage'] = null;
				$v['mileage_date'] = null;
			}
		}
		if (in_array($kind, ['templates', 'snippets'], true)) {
			$v['favorite'] = ($input['favorite'] ?? false) === true;
			$v['position'] = Rules::integer($input['position'] ?? 0, 0, 10000);
		}
		if ($kind === 'templates') {
			$v['steps'] = Rules::steps($input['steps'] ?? []);
			$v['minutes'] = Rules::integer($input['minutes'] ?? null, 1, 100000);
		}
		if ($kind === 'deadlines') {
			$v['vehicle_id'] = $this->reference($input['vehicle_id'] ?? null, 'vehicles', true);
			$v['due_date'] = Rules::date($input['due_date'] ?? null);
			$v['due_km'] = Rules::integer($input['due_km'] ?? null);
			if ($v['due_date'] === null && $v['due_km'] === null) {
				throw new Problem('validation', 'Datum oder Kilometergrenze erforderlich.');
			}
			$v['repeat_months'] = Rules::integer($input['repeat_months'] ?? null, 1, 120);
			$v['repeat_km'] = Rules::integer($input['repeat_km'] ?? null, 1);
			$days = $input['warning_days'] ?? [30, 7];
			if (!is_array($days) || count($days) > 10) {
				throw new Problem('validation', 'Ungültige Vorwarnungen.');
			}
			$v['warning_days'] = array_values(
				array_unique(array_map(function ($d): int {
					return Rules::integer($d, 0, 365)
						?? throw new Problem('validation', 'Vorwarnungen benötigen eine Tageszahl.');
				}, $days)),
			);
			$v['status'] = $old['status'] ?? 'open';
		}
		return $this->store->transaction(function () use ($kind, $old, $v) {
			if (
				($v['archived'] ?? false)
				&& $kind === 'employees'
				&& $this->store->find('active', ['employee_id' => $old['id']], 1)
			) {
				throw new Problem('active', 'Mitarbeiter hat einen aktiven Auftrag.', 409);
			}
			if (
				($v['archived'] ?? false)
				&& $kind === 'vehicles'
				&& $this->store->find(
					'orders',
					[
						'vehicle_id' => $old['id'],
						'status' => ['draft', 'open', 'active', 'paused', 'review'],
					],
					1,
				)
			) {
				throw new Problem('active', 'Fahrzeug hat offene Aufträge.', 409);
			}
			$row = $old ? $this->store->update($kind, $old, $v) : $this->store->insert($kind, $v);
			$this->journal->record(
				$this->access->uid(),
				$kind . '.saved',
				$kind === 'vehicles' ? $row['id'] : $row['vehicle_id'] ?? null,
				null,
				null,
				['id' => $row['id']],
			);
			return $row;
		});
	}
	public function reference(mixed $id, string $table, bool $required = false): ?string {
		if ($id === null || $id === '') {
			if ($required) {
				throw new Problem('validation', 'Verknüpfung fehlt.');
			}
			return null;
		}
		$id = Rules::text($id, 32, true);
		$row = $this->store->get($table, $id);
		if ($row['archived'] ?? false) {
			throw new Problem('archived', 'Eintrag ist archiviert.', 409);
		}
		return $id;
	}
	public function mileage(
		string $vehicle,
		int $value,
		string $date,
		?string $defect = null,
	): array {
		$this->access->master();
		$value = Rules::integer($value) ?? 0;
		$date = Rules::date($date) ?? '';
		return $this->store->transaction(function () use ($vehicle, $value, $date, $defect) {
			$v = $this->store->get('vehicles', $vehicle);
			if (
				($v['mileage'] !== null && $value < $v['mileage'])
				|| ($v['mileage_date'] && $date < $v['mileage_date'])
			) {
				throw new Problem(
					'mileage',
					'Kilometerstand oder Datum liegt vor dem bestätigten Stand.',
				);
			}
			if ($defect) {
				$d = $this->store->get('defects', $defect);
				if (
					$d['vehicle_id'] !== $vehicle
					|| $d['mileage'] !== $value
					|| $d['mileage_confirmed']
				) {
					throw new Problem('validation', 'Meldung passt nicht zum Kilometerstand.');
				}
				$this->store->update('defects', $d, ['mileage_confirmed' => true]);
			}
			$v = $this->store->update('vehicles', $v, [
				'mileage' => $value,
				'mileage_date' => $date,
			]);
			$this->store->insert('mileage', [
				'vehicle_id' => $vehicle,
				'value' => $value,
				'date' => $date,
				'source' => $defect ? 'driver-confirmed' : 'master',
			]);
			$this->journal->record(
				$this->access->uid(),
				'mileage.confirmed',
				$vehicle,
				null,
				null,
				['value' => $value, 'date' => $date],
			);
			return $v;
		});
	}
	public function completeDeadline(string $id, string $date, ?int $km): array {
		$this->access->master();
		$date = Rules::date($date);
		if (!$date) {
			throw new Problem('validation', 'Erledigungsdatum fehlt.');
		}
		return $this->store->transaction(function () use ($id, $date, $km) {
			$d = $this->store->get('deadlines', $id);
			if ($d['status'] !== 'open') {
				throw new Problem('state', 'Frist ist bereits erledigt.', 409);
			}
			$v = $this->store->get('vehicles', $d['vehicle_id']);
			$base = $km ?? $v['mileage'];
			if ($d['repeat_km'] && $base === null) {
				throw new Problem('validation', 'Kilometerstand für Folgefrist erforderlich.');
			}
			if ($km !== null) {
				Rules::integer($km);
				if ($v['mileage'] !== null && $km < $v['mileage']) {
					throw new Problem(
						'validation',
						'Kilometerstand liegt vor dem bestätigten Stand.',
					);
				}
			}
			$done = $this->store->update('deadlines', $d, [
				'status' => 'completed',
				'completed_date' => $date,
			]);
			if ($d['repeat_months'] || $d['repeat_km']) {
				$this->store->insert('deadlines', [
					'vehicle_id' => $d['vehicle_id'],
					'title' => $d['title'],
					'due_date' => $d['repeat_months']
						? Rules::nextMonth($date, $d['repeat_months'])
						: null,
					'due_km' => $d['repeat_km'] ? $base + $d['repeat_km'] : null,
					'repeat_months' => $d['repeat_months'],
					'repeat_km' => $d['repeat_km'],
					'warning_days' => $d['warning_days'],
					'status' => 'open',
				]);
			}
			$this->journal->record(
				$this->access->uid(),
				'deadline.completed',
				$d['vehicle_id'],
				null,
				null,
				['id' => $id, 'date' => $date],
			);
			return $done;
		});
	}
}
