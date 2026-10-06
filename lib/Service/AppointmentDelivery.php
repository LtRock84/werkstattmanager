<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Service;

use OCA\Werkstattmanager\Db\Store;
use OCA\Werkstattmanager\Domain\AppointmentCalendar;
use OCA\Werkstattmanager\Domain\Problem;
use Psr\Log\LoggerInterface;

final class AppointmentDelivery {
	public function __construct(
		private Store $store,
		private CalendarDelivery $calendar,
		private WorkshopMailer $mailer,
		private LoggerInterface $logger,
	) {
	}
	public function run(): void {
		// A crash while SMTP is in flight is ambiguous: require manual retry rather than quietly sending twice.
		foreach ($this->store->find('deliveries', ['status' => 'sending'], 100) as $row) {
			if ($row['updated_at'] < time() - 600) {
				$this->store->update('deliveries', $row, [
					'status' => 'uncertain',
					'last_error' => 'Versandausgang unklar. Vor Wiederholung prüfen.',
				]);
			}
		}
		$deadline = microtime(true) + 45;
		foreach ($this->store->pendingWork('deliveries', time()) as $row) {
			if (microtime(true) >= $deadline) {
				break;
			}
			$smtpAttempted = false;
			$status = 'sent';
			try {
				$a = $this->store->get('appointments', $row['appointment_id']);
				$payload = $row['payload'];
				$calendarFields = [
					'title',
					'arrival',
					'pickup',
					'work_start',
					'allocations',
					'status',
				];
				$obsolete
					= $row['channel'] === 'calendar'
					&& array_intersect_key($a, array_flip($calendarFields))
						!== array_intersect_key($payload, array_flip($calendarFields));
				if ($row['kind'] === 'reminder') {
					$obsolete
						= $a['status'] !== 'confirmed'
						|| !$a['reminder']
						|| $a['arrival'] !== $payload['arrival']
						|| $a['customer_email'] !== $payload['customer_email']
						|| strtotime($a['arrival']) <= time();
				} elseif ($row['kind'] === 'pickup_cancel') {
					$obsolete = $a['pickup'] !== null || $a['customer_email'] !== $payload['customer_email'];
				} elseif ($row['channel'] === 'email' && $row['kind'] !== 'cancel') {
					$obsolete
						= $a['status'] !== 'confirmed'
						|| $a['customer_email'] !== $payload['customer_email']
						|| $a['arrival'] !== $payload['arrival']
						|| $a['pickup'] !== $payload['pickup'];
				}
				if ($obsolete) {
					$this->store->update('deliveries', $row, ['status' => 'cancelled']);
					continue;
				}
				$row = $this->store->update('deliveries', $row, ['status' => 'sending']);
				if ($row['channel'] === 'calendar') {
					$this->calendar->send($payload, $row['kind']);
				} else {
					$labels = [
						'confirm' => 'Terminbestätigung',
						'update' => 'Terminänderung',
						'cancel' => 'Terminabsage',
						'pickup_cancel' => 'Abholzeit aufgehoben',
						'reminder' => 'Terminerinnerung',
					];
					$zone = new \DateTimeZone('Europe/Berlin');
					$arrival = (new \DateTimeImmutable($payload['arrival']))
						->setTimezone($zone)
						->format('d.m.Y H:i');
					$send = $this->mailer->prepare(
						$payload['customer_email'],
						$labels[$row['kind']] . ' · ' . $payload['vehicle_label'],
						$labels[$row['kind']]
								. "\n"
								. $payload['vehicle_label']
								. ' · '
								. $payload['title']
								. "\nAnnahme: "
								. $arrival
								. ' (Europe/Berlin)'
								. ($payload['pickup']
									? "\nAbholung: "
										. (new \DateTimeImmutable($payload['pickup']))
											->setTimezone($zone)
											->format('d.m.Y H:i')
									: '')
								. "\nBei Rückfragen wenden Sie sich bitte an Ihre Werkstatt.",
						AppointmentCalendar::build($payload, $row['kind'] === 'pickup_cancel' ? 'cancel' : $row['kind'], only: $row['kind'] === 'pickup_cancel' ? 'pickup' : null),
					);
					$smtpAttempted = true;
					$status = $send();
				}
				$this->store->update('deliveries', $row, ['status' => $status, 'last_error' => '']);
			} catch (\Throwable $e) {
				// A stale optimistic claim means another worker already owns this record.
				if ($e instanceof Problem && $e->error === 'conflict') {
					continue;
				}
				$attempt = $row['attempts'] + 1;
				$this->store->update('deliveries', $row, [
					'status' => $smtpAttempted
						? 'uncertain'
						: ($attempt >= 10
							? 'failed'
							: 'pending'),
					'attempts' => $attempt,
					'next_try' => time() + min(86400, 60 * 2 ** min($attempt, 10)),
					'last_error'
						=> $e instanceof Problem
							? $e->getMessage()
							: 'Zustellung fehlgeschlagen. Kalender- oder SMTP-Konfiguration prüfen.',
				]);
				$this->logger->warning('Werkstatt appointment delivery failed', [
					'app' => 'werkstattmanager',
					'exception_class' => get_class($e),
					'channel' => $row['channel'],
				]);
			}
		}
	}
}
