<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Domain;

final class AppointmentCalendar {
	private static function escape(string $v): string {
		return str_replace(['\\', "\r", "\n", ';', ','], ['\\\\', '', '\\n', '\\;', '\\,'], $v);
	}
	/** Fold UTF-8 content lines at 75 octets, without splitting a code point. */
	private static function fold(string $line): string {
		$chunks = [];
		while (strlen($line) > 75) {
			$n = 75;
			while ($n > 0 && (ord($line[$n]) & 0xc0) === 0x80) {
				$n--;
			}
			$chunks[] = substr($line, 0, $n);
			$line = ' ' . substr($line, $n);
		}
		$chunks[] = $line;
		return implode("\r\n", $chunks);
	}
	public static function build(
		array $a,
		string $kind,
		bool $internal = false,
		?int $now = null,
		?string $only = null,
	): string {
		$cancel = $kind === 'cancel';
		$rows = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Werkstattmanager//Terminbuch//DE',
			'CALSCALE:GREGORIAN',
		];
		if (!$internal) {
			$rows[] = 'METHOD:' . ($cancel?'CANCEL':'PUBLISH');
		}
		$event = function (
			string $suffix,
			string $title,
			?string $start,
			?string $end,
			?string $day = null,
		) use (&$rows, $a, $cancel, $now, $only): void {
			if ($only !== null && $suffix !== $only) {
				return;
			}
			$rows[] = 'BEGIN:VEVENT';
			$rows[] = 'UID:werkstatt-' . $a['id'] . '-' . $suffix . '@werkstattmanager';
			$rows[] = 'SEQUENCE:' . $a['version'];
			$rows[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z', $now ?? time());
			if ($day) {
				$rows[] = 'DTSTART;VALUE=DATE:' . str_replace('-', '', $day);
				$rows[]
					= 'DTEND;VALUE=DATE:'
					. (new \DateTimeImmutable($day))->modify('+1 day')->format('Ymd');
			} else {
				$rows[] = 'DTSTART:' . gmdate('Ymd\THis\Z', strtotime($start));
				$rows[]
					= 'DTEND:' . gmdate('Ymd\THis\Z', strtotime($end ?? $start) + ($end ? 0 : 900));
			}
			$rows[] = 'SUMMARY:' . self::escape($title);
			$rows[] = 'STATUS:' . ($cancel ? 'CANCELLED' : 'CONFIRMED');
			$rows[] = 'TRANSP:' . ($suffix === 'arrival' ? 'OPAQUE' : 'TRANSPARENT');
			$rows[] = 'END:VEVENT';
		};
		$title = $a['vehicle_label'] . ' · ' . $a['title'];
		$event('arrival', 'Annahme: ' . $title, $a['arrival'], null);
		if ($a['pickup']) {
			$event('pickup', 'Abholung: ' . $title, $a['pickup'], null);
		}
		if ($internal) {
			foreach ($a['allocations'] ?? [] as $i => $allocation) {
				if ($i === 0 && $a['work_start']) {
					$event(
						'work-' . $allocation['date'],
						'Arbeitsplanung: ' . $title,
						$a['work_start'],
						gmdate(
							'Y-m-d\TH:i:s\Z',
							strtotime($a['work_start']) + $allocation['minutes'] * 60,
						),
					);
				} else {
					$event(
						'work-' . $allocation['date'],
						'Arbeitsplanung ohne feste Uhrzeit: '
							. $allocation['minutes']
							. ' min · '
							. $title,
						null,
						null,
						$allocation['date'],
					);
				}
			}
		}
		$rows[] = 'END:VCALENDAR';
		return implode("\r\n", array_map(self::fold(...), $rows)) . "\r\n";
	}
}
