<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\BackgroundJob;

use OCA\Werkstattmanager\Service\AppointmentDelivery;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

final class AppointmentDispatch extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private AppointmentDelivery $delivery,
		private \OCA\Werkstattmanager\Service\CustomerReminders $reminders,
	) {
		parent::__construct($time);
		$this->setInterval(60);
		$this->setAllowParallelRuns(false);
	}
	protected function run($argument): void {
		$this->reminders->schedule();
		$this->delivery->run();
	}
}
