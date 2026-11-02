<?php

declare(strict_types=1);

// Release packages contain only the two audited runtime namespaces. Development
// installations may already have Composer's full autoloader; this fallback is harmless.
spl_autoload_register(static function (string $class): void {
	$root = dirname(__DIR__) . '/vendor/chillerlan/';
	foreach ([
		'chillerlan\\QRCode\\' => $root . 'php-qrcode/src/',
		'chillerlan\\Settings\\' => $root . 'php-settings-container/src/',
	] as $prefix => $directory) {
		if (str_starts_with($class, $prefix)) {
			$file = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
			if (is_file($file)) {
				require_once $file;
			}
			return;
		}
	}
});
