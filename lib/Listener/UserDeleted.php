<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Listener;

use OCA\Werkstattmanager\Service\Access;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;

/** @implements IEventListener<UserDeletedEvent> */
final class UserDeleted implements IEventListener {
	public function __construct(
		private Access $access,
		private \OCA\Werkstattmanager\Service\WorkshopMailer $mailer,
	) {
	}
	public function handle(Event $event): void {
		if ($event instanceof UserDeletedEvent) {
			$this->access->removeUser($event->getUid());
			$this->mailer->removeOwner($event->getUid());
		}
	}
}
