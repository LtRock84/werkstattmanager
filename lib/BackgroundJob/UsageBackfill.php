<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\BackgroundJob;

use OCA\Werkstattmanager\Service\ServiceUsage;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;

final class UsageBackfill extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		private ServiceUsage $usage,
		private IJobList $jobs,
	) {
		parent::__construct($time);
	}
	protected function run($argument): void {
		$kind = $argument['kind'] ?? 'appointments';
		$result = $this->usage->backfill($kind, $argument['cursor'] ?? '');
		if ($result['more']) {
			$this->jobs->add(self::class, ['kind' => $kind, 'cursor' => $result['cursor']]);
		} elseif ($kind === 'appointments') {
			$this->jobs->add(self::class, ['kind' => 'orders', 'cursor' => '']);
		}
	}
}
