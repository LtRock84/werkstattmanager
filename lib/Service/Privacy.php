<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Service;

use OCA\Werkstattmanager\Db\Store;
use OCA\Werkstattmanager\Domain\Problem;

final class Privacy {
	public function __construct(
		private Store $store,
		private Access $access,
		private Journal $journal,
	) {
	}
	public function csv(string $kind): string {
		$this->access->master();
		if (
			!in_array(
				$kind,
				[
					'customers',
					'vehicles',
					'orders',
					'intervals',
					'parts',
					'defects',
					'deadlines',
					'mileage',
					'audit',
				],
				true,
			)
		) {
			throw new Problem('export', 'Dieser Bereich kann nicht exportiert werden.');
		}
		$stream = fopen('php://temp', 'w+');
		if ($stream === false) {
			throw new \RuntimeException('Cannot create export stream');
		}
		fwrite($stream, "\xEF\xBB\xBF");
		$header = false;
		for ($offset = 0; ; $offset += 500) {
			$rows = $this->store->find($kind, [], 500, $offset);
			foreach ($rows as $row) {
				if (!$header) {
					fputcsv($stream, array_keys($row), ';', '"', '');
					$header = true;
				}
				$cells = array_map(static function (mixed $v): string {
					if (is_array($v)) {
						$s = json_encode($v, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
					} elseif (is_bool($v)) {
						$s = $v ? '1' : '0';
					} else {
						$s = (string)$v;
					}
					return preg_match('/^[\s]*[=+@-]/u', $s) ? "'" . $s : $s;
				}, $row);
				fputcsv($stream, $cells, ';', '"', '');
			}
			if (count($rows) < 500) {
				break;
			}
			if ($offset >= 99500) {
				fclose($stream);
				throw new Problem(
					'export_size',
					'Export übersteigt 100.000 Einträge. Bitte administrativen Datenbankexport verwenden.',
				);
			}
		}
		rewind($stream);
		$csv = stream_get_contents($stream);
		fclose($stream);
		if ($csv === false) {
			throw new \RuntimeException('Cannot read export stream');
		}
		return $csv;
	}
	public function anonymize(string $kind, string $id): array {
		$this->access->admin();
		if (!in_array($kind, ['customers', 'employees', 'defects'], true)) {
			throw new Problem('validation', 'Bereich nicht anonymisierbar.');
		}
		return $this->store->transaction(function () use ($kind, $id) {
			$row = $this->store->get($kind, $id);
			if ($kind !== 'defects' && !$row['archived']) {
				throw new Problem('archive', 'Bitte zuerst archivieren.', 409);
			}
			$changes = match ($kind) {
				'customers' => [
					'name' => 'Anonymisiert',
					'contact' => '',
					'email' => '',
					'phone' => '',
					'notes' => '',
				],
				'employees' => ['name' => 'Anonymisiert'],
				'defects' => ['reporter' => ''],
			};
			$this->store->update($kind, $row, $changes);
			$this->journal->record($this->access->uid(), 'privacy.anonymized', null, null, null, [
				'kind' => $kind,
				'id' => $id,
			]);
			return ['anonymized' => true];
		});
	}
	public function eraseVehicle(string $id, string $confirmation): array {
		$this->access->admin();
		if ($id !== $confirmation) {
			throw new Problem('confirmation', 'Fahrzeug-ID zur Bestätigung erforderlich.');
		}
		return $this->store->transaction(function () use ($id) {
			$v = $this->store->get('vehicles', $id);
			if (!$v['archived']) {
				throw new Problem('archive', 'Fahrzeug zuerst archivieren.', 409);
			}
			$this->store->update('vehicles', $v, []);
			$deleted = [$id => true];
			// Export/backup is an explicit operator step; attachment deletion is deferred and recoverable until cleanup.
			foreach ($this->all('orders', ['vehicle_id' => $id]) as $o) {
				if (in_array($o['status'], ['draft', 'open', 'active', 'paused', 'review'], true)) {
					throw new Problem('active', 'Offene Aufträge verhindern die Löschung.', 409);
				}
				foreach (['intervals', 'parts'] as $kind) {
					foreach ($this->all($kind, ['order_id' => $o['id']]) as $r) {
						$deleted[$r['id']] = true;
						$this->store->delete($kind, $r['id']);
					}
				}
				$deleted[$o['id']] = true;
				$this->store->delete('orders', $o['id']);
			}
			foreach ($this->all('attachments', ['vehicle_id' => $id]) as $a) {
				$this->store->update('attachments', $a, ['ready' => false]);
			}
			foreach (['defects', 'deadlines', 'mileage', 'links', 'audit'] as $kind) {
				foreach ($this->all($kind, ['vehicle_id' => $id]) as $r) {
					$deleted[$r['id']] = true;
					$this->store->delete($kind, $r['id']);
				}
			}
			foreach ($this->all('requests') as $r) {
				if (isset($deleted[$r['response']['id'] ?? ''])) {
					$this->store->update('requests', $r, ['response' => ['deleted' => true]]);
				}
			}
			foreach ($this->all('outbox') as $r) {
				if (isset($deleted[$r['object_id']])) {
					$this->store->delete('outbox', $r['id']);
				}
			}
			$this->store->delete('vehicles', $id);
			$this->journal->record(
				$this->access->uid(),
				'privacy.vehicle_erased',
				null,
				null,
				null,
				['id' => $id],
			);
			return ['erased' => true];
		});
	}
	private function all(string $table, array $filters = []): array {
		$out = [];
		for ($offset = 0; ; $offset += 500) {
			$rows = $this->store->find($table, $filters, 500, $offset);
			$out = array_merge($out, $rows);
			if (count($rows) < 500) {
				return $out;
			}
		}
	}
}
