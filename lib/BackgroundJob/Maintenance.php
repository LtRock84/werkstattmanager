<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\BackgroundJob;

use OCA\Werkstattmanager\Db\Store;
use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Service\Access;
use OCA\Werkstattmanager\Service\Attachments;
use OCA\Werkstattmanager\Service\Journal;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use OCP\Notification\IManager;
use Psr\Log\LoggerInterface;

final class Maintenance extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private Store $store,
		private Access $access,
		private Journal $journal,
		private Attachments $attachments,
		private IManager $notifications,
		private IConfig $config,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(300);
		$this->setAllowParallelRuns(false);
	}
	protected function run($argument): void {
		$this->attachments->cleanup();
		$offset = (int)$this->config->getAppValue('werkstattmanager', 'deadline_cursor', '0');
		$rows = $this->store->find('deadlines', ['status' => 'open'], 100, $offset);
		$today = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')))->format(
			'Y-m-d',
		);
		foreach ($rows as $d) {
			try {
				$v = $this->store->get('vehicles', $d['vehicle_id']);
			} catch (Problem) {
				continue;
			}
			if ($v['archived']) {
				continue;
			}
			if ($d['due_date']) {
				$days = (int)(new \DateTimeImmutable($today))
					->diff(new \DateTimeImmutable($d['due_date']))
					->format('%r%a');
				$thresholds = array_values(
					array_filter($d['warning_days'] ?? [30, 7], fn ($x) => $x !== null),
				);
				$thresholds[] = 0;
				sort($thresholds);
				foreach ($thresholds as $t) {
					if ($days <= $t) {
						$this->journal->notify('deadline-' . $t . '-v' . $d['version'], $d['id']);
						break;
					}
				}
			}
			if ($d['due_km'] !== null && $v['mileage'] !== null && $v['mileage'] >= $d['due_km']) {
				$this->journal->notify('deadline-km-v' . $d['version'], $d['id']);
			}
		}
		$this->config->setAppValue(
			'werkstattmanager',
			'deadline_cursor',
			count($rows) === 100 ? (string)($offset + 100) : '0',
		);
		foreach ($this->store->pendingWork('outbox', time()) as $r) {
			if (!$this->access->isMaster($r['recipient'])) {
				$this->store->update('outbox', $r, ['status' => 'cancelled']);
				continue;
			}
			try {
				$n = $this->notifications->createNotification();
				$n->setApp('werkstattmanager')
					->setUser($r['recipient'])
					->setDateTime(new \DateTime('@' . $r['created_at']))
					->setObject('workshop', $r['object_id'])
					->setSubject($r['subject']);
				$this->notifications->notify($n);
				$this->store->update('outbox', $r, ['status' => 'sent']);
			} catch (\Throwable $e) {
				$attempt = $r['attempts'] + 1;
				$this->store->update('outbox', $r, [
					'attempts' => $attempt,
					'next_try' => time() + min(86400, 60 * 2 ** min($attempt, 10)),
					'status' => $attempt >= 10 ? 'failed' : 'pending',
				]);
				$this->logger->warning('Werkstatt notification delivery failed', [
					'app' => 'werkstattmanager',
					'exception_class' => get_class($e),
					'attempt' => $attempt,
				]);
			}
		}
	}
}
