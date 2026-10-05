<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Notification;

use OCA\Werkstattmanager\Db\Store;
use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Service\Access;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

final class Notifier implements INotifier {
	public function __construct(
		private IFactory $l10n,
		private IURLGenerator $url,
		private Access $access,
		private Store $store,
	) {
	}
	public function getID(): string {
		return 'werkstattmanager';
	}
	public function getName(): string {
		return $this->l10n->get('werkstattmanager')->t('Werkstattmanager');
	}
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'werkstattmanager') {
			throw new UnknownNotificationException();
		}
		if (!$this->access->isMaster($notification->getUser())) {
			throw new AlreadyProcessedException();
		}
		$s = $notification->getSubject();
		$table = match (true) {
			$s === 'part' => 'parts',
			$s === 'review' => 'orders',
			$s === 'defect' => 'defects',
			str_starts_with($s, 'deadline-') => 'deadlines',
			default => null,
		};
		if (!$table) {
			throw new UnknownNotificationException();
		}
		try {
			$row = $this->store->get($table, $notification->getObjectId());
		} catch (Problem) {
			throw new AlreadyProcessedException();
		}
		$expected = match ($table) {
			'parts' => 'requested',
			'orders' => 'review',
			'defects' => 'new',
			default => 'open',
		};
		if ($row['status'] !== $expected) {
			throw new AlreadyProcessedException();
		}
		$l = $this->l10n->get('werkstattmanager', $languageCode);
		$subject = match ($table) {
			'parts' => $l->t('Neue Teileanforderung'),
			'orders' => $l->t('Auftrag wartet auf Abnahme'),
			'defects' => $l->t('Neue Mängelmeldung'),
			default => $l->t('Fahrzeugfrist steht an'),
		};
		$notification
			->setParsedSubject($subject)
			->setParsedMessage($l->t('Bitte im Werkstattmanager prüfen.'))
			->setLink($this->url->linkToRouteAbsolute('werkstattmanager.page.index'))
			->setIcon(
				$this->url->getAbsoluteURL($this->url->imagePath('werkstattmanager', 'app.svg')),
			);
		return $notification;
	}
}
