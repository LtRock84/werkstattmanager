<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\AppInfo;

use OCA\Werkstattmanager\Calendar\WorkshopCalendarProvider;
use OCA\Werkstattmanager\Listener\UserDeleted;
use OCA\Werkstattmanager\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\User\Events\UserDeletedEvent;

final class Application extends App implements IBootstrap {
	public const APP_ID = 'werkstattmanager';
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}
	public function register(IRegistrationContext $context): void {
		$context->registerCalendarProvider(WorkshopCalendarProvider::class);
		$context->registerNotifierService(Notifier::class);
		$context->registerEventListener(UserDeletedEvent::class, UserDeleted::class);
	}
	public function boot(IBootContext $context): void {
	}
}
