<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Service;

use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;

final class PickupQrCode {
	public function png(string $url): string {
		$options = [
			'outputInterface' => QRGdImagePNG::class,
			'outputBase64' => false,
			'scale' => 10,
			'quietzoneSize' => 4,
			'addQuietzone' => true,
		];
		$result = (new QRCode($options))->render($url);
		if (!is_string($result) || !str_starts_with($result, "\x89PNG\r\n\x1a\n") || strlen($result) > 2_000_000) {
			throw new \RuntimeException('Could not render pickup QR code');
		}
		return $result;
	}
}
