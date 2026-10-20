<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Calendar;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use OCA\Werkstattmanager\Db\Store;
use OCA\Werkstattmanager\Service\Access;
use OCP\Calendar\ICalendar;
use OCP\Calendar\ICalendarIsEnabled;
use OCP\Constants;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;

/** A per-principal view of confirmed workshop appointments. */
final class WorkshopCalendar implements ICalendar, ICalendarIsEnabled {
	private const COLOR = '#0078d4';
	private const ZONE = 'Europe/Berlin';

	public function __construct(
		private string $uid,
		private Access $access,
		private Store $store,
	) {
	}

	#[\Override]
	public function getKey(): string {
		return 'werkstattmanager:' . $this->uid;
	}

	#[\Override]
	public function getUri(): string {
		return WorkshopCalendarProvider::URI;
	}

	#[\Override]
	public function getDisplayName(): ?string {
		return 'Werkstatt';
	}

	#[\Override]
	public function getDisplayColor(): ?string {
		return self::COLOR;
	}

	#[\Override]
	public function getPermissions(): int {
		return Constants::PERMISSION_READ;
	}

	#[\Override]
	public function isDeleted(): bool {
		return false;
	}

	#[\Override]
	public function isEnabled(): bool {
		return true;
	}

	#[\Override]
	public function search(
		string $pattern,
		array $searchProperties = [],
		array $options = [],
		?int $limit = null,
		?int $offset = null,
	): array {
		// Calendar instances can outlive a group change in the same request; authorise every query.
		if (!$this->access->isMaster($this->uid)) {
			return [];
		}
		if (($options['types'] ?? []) !== [] && !in_array('VEVENT', $options['types'], true)) {
			return [];
		}

		[$start, $end] = $this->range($options);
		$from = $start->setTimezone(new DateTimeZone(self::ZONE))->format('Y-m-d');
		$to = $end->setTimezone(new DateTimeZone(self::ZONE))->format('Y-m-d');
		$vehicles = [];
		$events = [];
		foreach ($this->store->calendarAppointments($from, $to) as $appointment) {
			$vehicleId = (string)$appointment['vehicle_id'];
			if (!array_key_exists($vehicleId, $vehicles)) {
				$vehicle = $this->store->get('vehicles', $vehicleId);
				$vehicles[$vehicleId] = trim((string)($vehicle['plate'] ?: $vehicle['number']));
			}
			array_push($events, ...$this->events($appointment, $vehicles[$vehicleId]));
		}

		$events = array_values(array_filter($events, function (array $event) use ($start, $end, $pattern, $searchProperties, $options): bool {
			$object = $event['objects'][0];
			$eventStart = $object['DTSTART'][0];
			$eventEnd = $object['DTEND'][0];
			if ($eventEnd <= $start || $eventStart >= $end) {
				return false;
			}
			if (isset($options['uid']) && $event['uid'] !== $options['uid']) {
				return false;
			}
			if (isset($options['uri']) && $event['uri'] !== $options['uri']) {
				return false;
			}
			return $this->matches($object, $pattern, $searchProperties);
		}));
		usort($events, static fn (array $a, array $b): int => $a['objects'][0]['DTSTART'][0] <=> $b['objects'][0]['DTSTART'][0]);

		$events = array_slice($events, max(0, $offset ?? 0), $limit === null ? null : max(0, $limit));
		// Nextcloud's DAV wrapper asks the public provider for raw VEVENT components.
		// Other OCP consumers receive the documented search envelope.
		$dav = $options === []
			&& ($searchProperties === [] || array_diff($searchProperties, ['X-FILENAME', 'UID']) === []);
		return $dav ? array_map(fn (array $event) => $this->component($event['objects'][0]), $events) : $events;
	}

	/** @return array{DateTimeImmutable, DateTimeImmutable} */
	private function range(array $options): array {
		$zone = new DateTimeZone(self::ZONE);
		$range = $options['timerange'] ?? [];
		$start = ($range['start'] ?? null) instanceof DateTimeInterface
			? DateTimeImmutable::createFromInterface($range['start'])
			: new DateTimeImmutable('1900-01-01 00:00:00', $zone);
		$end = ($range['end'] ?? null) instanceof DateTimeInterface
			? DateTimeImmutable::createFromInterface($range['end'])
			: new DateTimeImmutable('2101-01-01 00:00:00', $zone);
		return [$start, $end > $start ? $end : $start];
	}

	private function matches(array $object, string $pattern, array $properties): bool {
		if ($pattern === '') {
			return true;
		}
		$properties = $properties === [] ? ['SUMMARY', 'DESCRIPTION', 'UID'] : $properties;
		$needle = mb_strtolower($pattern, 'UTF-8');
		foreach ($properties as $property) {
			$value = $object[strtoupper((string)$property)][0] ?? null;
			if (is_scalar($value) && str_contains(mb_strtolower((string)$value, 'UTF-8'), $needle)) {
				return true;
			}
		}
		return false;
	}

	private function events(array $appointment, string $vehicle): array {
		$title = $vehicle . ' · ' . $appointment['title'];
		$events = [$this->timed($appointment, 'arrival', 'Annahme: ' . $title, $appointment['arrival'], null, true)];
		if ($appointment['pickup']) {
			$events[] = $this->timed($appointment, 'pickup', 'Abholung: ' . $title, $appointment['pickup']);
		}
		foreach ($appointment['allocations'] ?? [] as $index => $allocation) {
			$suffix = 'work-' . $allocation['date'];
			if ($index === 0 && $appointment['work_start']) {
				$start = new DateTimeImmutable($appointment['work_start']);
				$events[] = $this->timed(
					$appointment,
					$suffix,
					'Arbeitsplanung: ' . $title,
					$appointment['work_start'],
					$start->modify('+' . (int)$allocation['minutes'] . ' minutes')->format('Y-m-d\TH:i:s\Z'),
				);
			} else {
				$events[] = $this->allDay(
					$appointment,
					$suffix,
					'Arbeitsplanung ohne feste Uhrzeit: ' . (int)$allocation['minutes'] . ' min · ' . $title,
					$allocation['date'],
				);
			}
		}
		return $events;
	}

	private function timed(array $appointment, string $suffix, string $summary, string $start, ?string $end = null, bool $busy = false): array {
		$from = new DateTimeImmutable($start);
		$until = $end ? new DateTimeImmutable($end) : $from->modify('+15 minutes');
		return $this->event($appointment, $suffix, $summary, $from, $until, [], $busy);
	}

	private function allDay(array $appointment, string $suffix, string $summary, string $day): array {
		$start = new DateTimeImmutable($day . ' 00:00:00', new DateTimeZone(self::ZONE));
		return $this->event($appointment, $suffix, $summary, $start, $start->modify('+1 day'), ['VALUE' => 'DATE']);
	}

	private function event(array $appointment, string $suffix, string $summary, DateTimeImmutable $start, DateTimeImmutable $end, array $dateParameters = [], bool $busy = false): array {
		$uid = 'werkstatt-' . $appointment['id'] . '-' . $suffix . '@werkstattmanager';
		$uri = 'werkstatt-' . $appointment['id'] . '-' . $suffix . '.ics';
		return [
			'id' => $appointment['id'] . ':' . $suffix,
			'type' => 'VEVENT',
			'uid' => $uid,
			'uri' => $uri,
			'objects' => [[
				'UID' => [$uid, []],
				'X-FILENAME' => [$uri, []],
				'DTSTAMP' => [(new DateTimeImmutable('@' . (int)$appointment['updated_at']))->setTimezone(new DateTimeZone('UTC')), []],
				'DTSTART' => [$start, $dateParameters],
				'DTEND' => [$end, $dateParameters],
				'SUMMARY' => [$summary, []],
				'DESCRIPTION' => ['Bearbeitung im Werkstattmanager', []],
				'STATUS' => ['CONFIRMED', []],
				'TRANSP' => [$busy ? 'OPAQUE' : 'TRANSPARENT', []],
				'SEQUENCE' => [(string)$appointment['version'], []],
			]],
			'timezones' => [],
		];
	}

	private function component(array $object): object {
		$calendar = new VCalendar();
		$event = $calendar->add('VEVENT');
		if (!$event instanceof VEvent) {
			throw new \RuntimeException('Could not create calendar event');
		}
		foreach ($object as $name => [$value, $parameters]) {
			$property = $event->add($name, $value);
			foreach ($parameters as $parameter => $parameterValue) {
				$property[$parameter] = $parameterValue;
			}
		}
		return $event;
	}
}
