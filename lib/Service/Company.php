<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Service;

use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Domain\Rules;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\IConfig;

final class Company {
	public function __construct(
		private Access $access,
		private IConfig $config,
		private IAppData $files,
	) {
	}
	public function profile(): array {
		$data = json_decode($this->config->getAppValue('werkstattmanager', 'company', '{}'), true);
		return ['name' => is_array($data) ? ($data['name'] ?? '') : '', 'logo' => is_array($data) ? ($data['logo'] ?? '') : ''];
	}
	public function save(string $name, ?array $upload = null, bool $remove = false): array {
		$this->access->master();
		$name = Rules::text($name, 255);
		if (preg_match('/[\x00-\x1f\x7f]/', $name)) {
			throw new Problem('validation', 'Firmenname darf keine Steuerzeichen enthalten.');
		}
		$previous = $this->profile();
		$logo = $previous['logo'];
		if ($remove) {
			$logo = '';
		}
		if ($upload !== null && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
			if ($remove || ($upload['error'] ?? 1) !== UPLOAD_ERR_OK || ($upload['size'] ?? 0) > 2097152 || !is_uploaded_file($upload['tmp_name'] ?? '')) {
				throw new Problem('logo', 'Logo-Upload fehlgeschlagen. Maximal 2 MB erlaubt.');
			}
			$raw = file_get_contents($upload['tmp_name']);
			if (!is_string($raw) || strlen($raw) > 2097152 || !in_array((new \finfo(FILEINFO_MIME_TYPE))->buffer($raw), ['image/png', 'image/jpeg', 'image/webp'], true)) {
				throw new Problem('logo', 'Bitte ein PNG-, JPEG- oder WebP-Bild auswählen.');
			}
			$size = getimagesizefromstring($raw);
			if (!$size || $size[0] * $size[1] > 4000000) {
				throw new Problem('logo', 'Logo darf höchstens 4 Megapixel haben.');
			}
			$source = imagecreatefromstring($raw);
			if (!$source) {
				throw new Problem('logo', 'Ungültiges Logo.');
			}
			$factor = min(1, 1024 / max($size[0], $size[1]));
			$image = imagecreatetruecolor(max(1, (int)round($size[0] * $factor)), max(1, (int)round($size[1] * $factor)));
			imagealphablending($image, false);
			imagesavealpha($image, true);
			imagecopyresampled($image, $source, 0, 0, 0, 0, imagesx($image), imagesy($image), $size[0], $size[1]);
			ob_start();
			$ok = imagepng($image);
			$png = ob_get_clean();
			imagedestroy($source);
			imagedestroy($image);
			if (!$ok || !is_string($png)) {
				throw new Problem('logo', 'Logo konnte nicht verarbeitet werden.');
			}
			$logo = hash('sha256', $png);
			try {
				$folder = $this->files->getFolder('company');
			} catch (NotFoundException) {
				$folder = $this->files->newFolder('company');
			}
			try {
				$file = $folder->getFile($logo . '.png');
			} catch (NotFoundException) {
				$file = $folder->newFile($logo . '.png');
			}
			$file->putContent($png);
		}
		$this->config->setAppValue('werkstattmanager', 'company', json_encode(['name' => $name, 'logo' => $logo], JSON_THROW_ON_ERROR));
		// Keep old content until scheduled cleanup: concurrent readers may still reference it.
		return $this->profile();
	}
	public function cleanup(): void {
		try {
			$folder = $this->files->getFolder('company');
		} catch (NotFoundException) {
			return;
		}
		$current = $this->profile()['logo'] . '.png';
		$deleted = 0;
		foreach ($folder->getDirectoryListing() as $file) {
			if ($file->getName() !== $current && $file->getMTime() < time() - 86400) {
				$file->delete();
				if (++$deleted >= 100) {
					break;
				}
			}
		}
	}
	public function logo(string $hash): string {
		if (!preg_match('/^[a-f0-9]{64}$/D', $hash) || $hash !== $this->profile()['logo']) {
			throw new Problem('not_found', 'Logo nicht gefunden.', 404);
		}
		try {
			return $this->files->getFolder('company')->getFile($hash . '.png')->getContent();
		} catch (NotFoundException) {
			throw new Problem('not_found', 'Logo nicht gefunden.', 404);
		}
	}
}
