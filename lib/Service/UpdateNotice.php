<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Service;

use OCA\Werkstattmanager\Domain\Problem;
use OCP\App\IAppManager;
use OCP\IConfig;

final class UpdateNotice {
	public function __construct(private Access $access, private IConfig $config, private IAppManager $apps) {
	}
	public function view(): array {
		$this->access->role();
		$version = $this->apps->getAppVersion('werkstattmanager');
		$seen = $this->config->getUserValue($this->access->uid(), 'werkstattmanager', 'update_notice_seen', '');
		return ['version' => $version, 'pending' => $seen !== $version];
	}
	public function confirm(string $version): array {
		$this->access->role();
		$current = $this->apps->getAppVersion('werkstattmanager');
		if ($version !== $current) {
			throw new Problem('conflict', 'Die App-Version hat sich geändert. Bitte neu laden.', 409);
		}
		$this->config->setUserValue($this->access->uid(), 'werkstattmanager', 'update_notice_seen', $current);
		return ['version' => $current, 'pending' => false];
	}
}
