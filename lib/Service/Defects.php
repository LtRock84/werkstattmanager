<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Service;

use OCA\Werkstattmanager\Db\Store;
use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Domain\Rules;
use OCP\ISession;

final class Defects {
	public function __construct(
		private Store $store,
		private Access $access,
		private Journal $journal,
		private ISession $session,
	) {
	}
	public function link(string $vehicle, bool $revoke = false): array {
		$this->access->master();
		return $this->store->transaction(function () use ($vehicle, $revoke) {
			$v = $this->store->get('vehicles', $vehicle);
			if ($v['kind'] !== 'fleet' || $v['archived']) {
				throw new Problem('vehicle', 'Fahrerzugang nur für aktive Flottenfahrzeuge.');
			}
			$this->store->update('vehicles', $v, []);
			for ($offset = 0; ; $offset += 500) {
				$links = $this->store->find('links', ['vehicle_id' => $vehicle], 500, $offset);
				foreach ($links as $l) {
					if (!$l['revoked']) {
						$this->store->update('links', $l, ['revoked' => true]);
					}
				}
				if (count($links) < 500) {
					break;
				}
			}
			$this->journal->record(
				$this->access->uid(),
				$revoke ? 'link.revoked' : 'link.rotated',
				$vehicle,
			);
			if ($revoke) {
				return ['revoked' => true];
			}
			$token = bin2hex(random_bytes(32));
			$this->store->insert('links', [
				'vehicle_id' => $vehicle,
				'token_hash' => hash('sha256', $token),
				'revoked' => false,
			]);
			return ['token' => $token];
		});
	}
	public function exchange(string $token): array {
		if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
			throw new Problem('link', 'Fahrzeuglink ungültig.', 404);
		}
		$l
			= $this->store->find(
				'links',
				['token_hash' => hash('sha256', $token), 'revoked' => false],
				1,
			)[0] ?? null;
		if (!$l) {
			throw new Problem('link', 'Fahrzeuglink ungültig oder widerrufen.', 404);
		}
		$this->session->set('wm_driver', ['link' => $l['id'], 'expires' => time() + 7200]);
		return $this->publicView();
	}
	public function vehicle(): array {
		$s = $this->session->get('wm_driver');
		if (!is_array($s) || ($s['expires'] ?? 0) < time()) {
			throw new Problem('link', 'Bitte Fahrzeug-QR-Code erneut öffnen.', 401);
		}
		$l = $this->store->get('links', $s['link']);
		$v = $this->store->get('vehicles', $l['vehicle_id']);
		if ($l['revoked'] || $v['archived'] || $v['kind'] !== 'fleet') {
			throw new Problem('link', 'Fahrzeuglink wurde widerrufen.', 403);
		}
		return $v;
	}
	public function publicView(int $offset = 0): array {
		$v = $this->vehicle();
		$rows = $this->store->find(
			'defects',
			['vehicle_id' => $v['id'], 'published' => true, 'status' => 'open'],
			101,
			$offset,
		);
		return [
			'vehicle' => [
				'plate' => $v['plate'],
				'number' => $v['number'],
				'make' => $v['make'],
				'model' => $v['model'],
			],
			'items' => array_map(
				fn ($d) => [
					'text' => $d['public_text'],
					'status' => 'open',
					'created_at' => $d['created_at'],
				],
				array_slice($rows, 0, 100),
			),
			'more' => count($rows) > 100,
		];
	}
	public function report(array $input): array {
		$v = $this->vehicle();
		$rid = Rules::text($input['request_id'] ?? '', 64, true);
		if (!preg_match('/^[a-zA-Z0-9-]{16,64}$/D', $rid)) {
			throw new Problem('validation', 'Vorgangskennung fehlt.');
		}
		$s = $this->session->get('wm_driver');
		$key = hash('sha256', 'driver:' . $s['link'] . ':' . $rid);
		$fp = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
		$previous = $this->store->find('requests', ['request_key' => $key], 1)[0] ?? null;
		if ($previous) {
			if ($previous['fingerprint'] !== $fp) {
				throw new Problem('conflict', 'Vorgangskennung bereits verwendet.', 409);
			}
			$result = $previous['response'];
		} else {
			$result = $this->store->transaction(function () use ($v, $input, $key, $fp) {
				$r = $this->store->insert('requests', [
					'request_key' => $key,
					'fingerprint' => $fp,
					'response' => [],
				]);
				$d = $this->store->insert('defects', [
					'vehicle_id' => $v['id'],
					'order_id' => null,
					'duplicate_id' => null,
					'category' => Rules::choice($input['category'] ?? 'other', [
						'engine',
						'brakes',
						'tires',
						'lights',
						'body',
						'other',
					]),
					'description' => Rules::text($input['description'] ?? '', 10000, true),
					'priority' => Rules::choice($input['priority'] ?? 'normal', [
						'normal',
						'urgent',
					]),
					'reporter' => Rules::text($input['reporter'] ?? ''),
					'mileage' => Rules::integer($input['mileage'] ?? null),
					'mileage_confirmed' => false,
					'status' => 'new',
					'public_text' => '',
					'published' => false,
				]);
				$result = ['id' => $d['id'], 'received' => true];
				$this->store->update('requests', $r, ['response' => $result]);
				$this->journal->record('driver', 'defect.reported', $v['id'], null, null, [
					'defect_id' => $d['id'],
				]);
				$this->journal->notify('defect', $d['id']);
				return $result;
			});
		}
		$this->session->set('wm_upload', [
			'defect' => $result['id'],
			'vehicle' => $v['id'],
			'expires' => time() + 3600,
		]);
		return $result;
	}
	public function mayUpload(string $id): array {
		$v = $this->vehicle();
		$g = $this->session->get('wm_upload');
		if (
			!is_array($g)
			|| $g['defect'] !== $id
			|| $g['vehicle'] !== $v['id']
			|| $g['expires'] < time()
		) {
			throw new Problem('forbidden', 'Kein Upload-Zugriff auf diese Meldung.', 403);
		}
		return $this->store->get('defects', $id);
	}
	public function update(string $id, array $input): array {
		$this->access->master();
		return $this->store->transaction(function () use ($id, $input) {
			$d = $this->store->get('defects', $id);
			if (($input['version'] ?? null) !== $d['version']) {
				throw new Problem('conflict', 'Mangel wurde inzwischen geändert.', 409);
			}
			$changes = [
				'status' => Rules::choice($input['status'] ?? 'open', [
					'new',
					'open',
					'resolved',
					'duplicate',
					'dismissed',
				]),
				'published' => ($input['published'] ?? false) === true,
				'public_text' => Rules::text($input['public_text'] ?? '', 2000),
				'duplicate_id' => null,
			];
			if (
				$changes['published']
				&& ($changes['public_text'] === '' || $changes['status'] !== 'open')
			) {
				throw new Problem(
					'validation',
					'Nur offene Mängel mit Fahrerinformation veröffentlichen.',
				);
			}
			if ($changes['status'] === 'duplicate') {
				$target = $this->store->get(
					'defects',
					Rules::text($input['duplicate_id'] ?? '', 32, true),
				);
				if (
					$target['id'] === $id
					|| $target['vehicle_id'] !== $d['vehicle_id']
					|| !in_array($target['status'], ['new', 'open'], true)
				) {
					throw new Problem('validation', 'Ungültiger Zielmangel.');
				}
				// Serialize competing merges so that A -> B and B -> A cannot both commit.
				$this->store->update('defects', $target, []);
				$changes['duplicate_id'] = $target['id'];
			}
			if (array_key_exists('order_id', $input)) {
				$oid = $input['order_id'];
				if ($oid) {
					$o = $this->store->get('orders', Rules::text($oid, 32, true));
					if (
						$o['vehicle_id'] !== $d['vehicle_id']
						|| in_array($o['status'], ['completed', 'cancelled'], true)
					) {
						throw new Problem('validation', 'Auftrag passt nicht zum Mangel.');
					}
					$this->store->update('orders', $o, []);
				}
				$changes['order_id'] = $oid ?: null;
			}
			$d = $this->store->update('defects', $d, $changes);
			$this->journal->record(
				$this->access->uid(),
				'defect.updated',
				$d['vehicle_id'],
				$d['order_id'],
				null,
				['defect_id' => $id],
			);
			return $d;
		});
	}
}
