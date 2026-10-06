<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Service;

use OCA\Werkstattmanager\Db\Store;
use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Domain\Rules;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;

final class Attachments {
	public function __construct(
		private IAppData $appData,
		private Store $store,
		private Access $access,
		private Catalog $catalog,
		private Defects $defects,
	) {
	}
	public function upload(array $file, array $target, bool $public = false): array {
		if (
			($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
			|| !is_uploaded_file($file['tmp_name'] ?? '')
			|| ($file['size'] ?? 0) > 8 * 1024 * 1024
		) {
			throw new Problem('upload', 'Upload fehlgeschlagen oder größer als 8 MB.');
		}
		$ids = ['vehicle_id' => null, 'order_id' => null, 'defect_id' => null, 'part_id' => null];
		$parentTable = '';
		$parent = null;
		if ($public) {
			$d = $this->defects->mayUpload(Rules::text($target['defect_id'] ?? '', 32, true));
			$ids['defect_id'] = $d['id'];
			$ids['vehicle_id'] = $d['vehicle_id'];
			$parentTable = 'defects';
			$parent = $d;
		} else {
			$this->access->workshop();
			$role = $this->access->role();
			if (!empty($target['order_id'])) {
				$o = $this->catalog->visibleOrder(Rules::text($target['order_id'], 32, true));
				$ids['order_id'] = $o['id'];
				$ids['vehicle_id'] = $o['vehicle_id'];
				$parentTable = 'orders';
				$parent = $o;
				if (!empty($target['part_id'])) {
					$p = $this->store->get('parts', Rules::text($target['part_id'], 32, true));
					if ($p['order_id'] !== $o['id']) {
						throw new Problem('forbidden', 'Falscher Auftrag.', 403);
					}
					$ids['part_id'] = $p['id'];
				}
			} else {
				$this->access->master();
				$v = $this->store->get(
					'vehicles',
					Rules::text($target['vehicle_id'] ?? '', 32, true),
				);
				$ids['vehicle_id'] = $v['id'];
				$parentTable = 'vehicles';
				$parent = $v;
			}
		}
		$raw = file_get_contents($file['tmp_name']);
		if ($raw === false) {
			throw new Problem('upload', 'Datei kann nicht gelesen werden.');
		}
		$mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($raw);
		$ext = '';
		if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
			$size = getimagesizefromstring($raw);
			if (!$size || $size[0] * $size[1] > 12000000) {
				throw new Problem('upload', 'Bild darf höchstens 12 Megapixel haben.');
			}
			$image = imagecreatefromstring($raw);
			if (!$image) {
				throw new Problem('upload', 'Ungültiges Bild.');
			}
			ob_start();
			$ok = imagejpeg($image, null, 88);
			$raw = ob_get_clean();
			imagedestroy($image);
			if (!$ok || !is_string($raw)) {
				throw new Problem('upload', 'Bildverarbeitung fehlgeschlagen.');
			}
			$mime = 'image/jpeg';
			$ext = '.jpg';
		} elseif (!$public && $mime === 'application/pdf') {
			$ext = '.pdf';
		} else {
			throw new Problem('upload', 'Erlaubt sind JPEG, PNG, WebP und intern PDF.');
		}
		$name = Rules::text(pathinfo(basename($file['name']), PATHINFO_FILENAME), 180, true) . $ext;
		$hash = hash('sha256', $raw);
		if ($public) {
			$existing
				= $this->store->find(
					'attachments',
					['defect_id' => $ids['defect_id'], 'content_hash' => $hash],
					1,
				)[0] ?? null;
			if ($existing) {
				if (!$existing['ready']) {
					throw new Problem(
						'upload_pending',
						'Foto wird noch verarbeitet. Bitte später erneut versuchen.',
						409,
					);
				}
				return ['id' => $existing['id'], 'name' => $existing['name']];
			}
		}
		$key = bin2hex(random_bytes(24));
		$a = $this->store->transaction(function () use (
			$public,
			$ids,
			$parentTable,
			$parent,
			$name,
			$mime,
			$raw,
			$key,
			$hash,
		) {
			$this->store->update($parentTable, $parent, []);
			if (
				$public
				&& count($this->store->find('attachments', ['defect_id' => $ids['defect_id']], 6)) >= 5
			) {
				throw new Problem('upload', 'Maximal fünf Fotos pro Meldung.');
			}
			return $this->store->insert(
				'attachments',
				$ids + [
					'name' => $name,
					'mime' => $mime,
					'size' => strlen($raw),
					'storage_key' => $key,
					'content_hash' => $hash,
					'ready' => false,
				],
			);
		});
		try {
			$folder = $this->folder();
			$f = $folder->newFile($key);
			$f->putContent($raw);
			$a = $this->store->update('attachments', $a, ['ready' => true]);
		} catch (\Throwable $e) {
			$this->store->delete('attachments', $a['id']);
			try {
				$this->folder()->getFile($key)->delete();
			} catch (NotFoundException) {
			}
			throw $e;
		}
		return ['id' => $a['id'], 'name' => $a['name']];
	}
	/** @return array{meta: array{name: string, mime: string}, content: string} */
	public function download(string $id): array {
		$this->access->workshop();
		$role = $this->access->role();
		$a = $this->store->get('attachments', $id);
		if (!$a['ready']) {
			throw new Problem('not_found', 'Datei nicht verfügbar.', 404);
		}
		if ($role === 'tablet') {
			if (!$a['order_id']) {
				throw new Problem('forbidden', 'Kein Zugriff.', 403);
			}
			$this->catalog->visibleOrder($a['order_id']);
		}
		if (!is_string($a['name']) || !is_string($a['mime'])) {
			throw new \RuntimeException('Invalid attachment metadata');
		}
		return [
			'meta' => ['name' => $a['name'], 'mime' => $a['mime']],
			'content' => $this->folder()->getFile($a['storage_key'])->getContent(),
		];
	}
	public function cleanup(): void {
		foreach ($this->store->pendingWork('attachments', time() - 86400) as $a) {
			try {
				$this->folder()->getFile($a['storage_key'])->delete();
			} catch (NotFoundException) {
				// An interrupted upload may never have created its reserved file.
			}
			$this->store->delete('attachments', $a['id']);
		}
	}
	private function folder(): \OCP\Files\SimpleFS\ISimpleFolder {
		try {
			return $this->appData->getFolder('attachments');
		} catch (NotFoundException) {
			return $this->appData->newFolder('attachments');
		}
	}
}
