<?php

declare(strict_types=1);
require_once __DIR__ . '/vendor/autoload.php';
$config = new Nextcloud\CodingStandard\Config();
$config->getFinder()->in([__DIR__.'/lib',__DIR__.'/appinfo',__DIR__.'/templates'])->name('*.php');
return $config->setUsingCache(false);
