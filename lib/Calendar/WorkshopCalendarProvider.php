<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Calendar;

use OCA\Werkstattmanager\Db\Store;
use OCA\Werkstattmanager\Service\Access;
use OCP\Calendar\ICalendarProvider;
use OCP\IConfig;

/** Provides one virtual, read-only workshop calendar to every authorised master. */
final class WorkshopCalendarProvider implements ICalendarProvider {
	public const URI = 'werkstattmanager';

	public function __construct(
		private Access $access,
		private Store $store,
		private IConfig $config,
	) {
	}

	#[\Override]
	public function getCalendars(string $principalUri, array $calendarUris = []): array {
		$prefix = 'principals/users/';
		if (!str_starts_with($principalUri, $prefix)) {
			return [];
		}
		$uid = substr($principalUri, strlen($prefix));
		if ($uid === '' || str_contains($uid, '/') || preg_match('/[\x00-\x1f\x7f]/', $uid)) {
			return [];
		}
		if ($calendarUris !== [] && !in_array(self::URI, $calendarUris, true)) {
			return [];
		}
		// Preserve an explicitly configured legacy CalDAV target without creating duplicates.
		if ($this->config->getAppValue('werkstattmanager', 'calendar_url', '') !== '') {
			return [];
		}
		if (!$this->access->isMaster($uid)) {
			return [];
		}

		return [new WorkshopCalendar($uid, $this->access, $this->store)];
	}
}
