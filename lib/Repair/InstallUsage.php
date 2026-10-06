<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Repair;

use OCA\Werkstattmanager\BackgroundJob\UsageBackfill;
use OCA\Werkstattmanager\Service\ServiceUsage;
use OCP\BackgroundJob\IJobList;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

final class InstallUsage implements IRepairStep {
	public function __construct(
		private ServiceUsage $usage,
		private IJobList $jobs,
	) {
	}
	public function getName(): string {
		return 'Initialize anonymous Werkstattmanager service ranking';
	}
	public function run(IOutput $output): void {
		foreach (['appointments', 'orders'] as $kind) {
			$result = $this->usage->backfill($kind);
			if ($result['more']) {
				$this->jobs->add(UsageBackfill::class, ['kind' => $kind, 'cursor' => $result['cursor']]);
			}
		}
	}
}
