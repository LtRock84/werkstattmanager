<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Service;

use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Domain\Rules;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\Mail\Provider\Address;
use OCP\Mail\Provider\Attachment;
use OCP\Mail\Provider\IManager;
use OCP\Mail\Provider\IMessageSend;

/** A shared sender explicitly delegated by its administrator/account owner. */
final class WorkshopMailer {
	public function __construct(
		private Access $access,
		private IConfig $config,
		private IUserManager $users,
		private IManager $providers,
		private IMailer $system,
		private Company $company,
	) {
	}
	private function selection(): array {
		$data = json_decode($this->config->getAppValue('werkstattmanager', 'workshop_mail', '{"mode":"system"}'), true);
		return is_array($data) ? $data : ['mode' => 'invalid'];
	}
	public function settings(): array {
		$this->access->admin();
		$items = [];
		foreach ($this->providers->services($this->access->uid()) as $provider => $services) {
			if ($provider !== 'mail-application') {
				continue;
			}
			foreach ($services as $service) {
				if ($service instanceof IMessageSend && $service->capable('MessageSend')) {
					$items[] = ['id' => $service->id(), 'address' => $service->getPrimaryAddress()->getAddress(), 'label' => $service->getLabel()];
				}
			}
		}
		return ['selection' => $this->selection(), 'accounts' => $items];
	}
	public function configure(array $data): array {
		$this->access->admin();
		$mode = Rules::choice($data['mode'] ?? null, ['system', 'mail']);
		$selection = ['mode' => $mode];
		if ($mode === 'mail') {
			$id = Rules::text($data['service'] ?? '', 255, true);
			$service = $this->providers->findServiceById($this->access->uid(), $id, 'mail-application');
			if (!$service instanceof IMessageSend || !$service->capable('MessageSend')) {
				throw new Problem('mail_config', 'Bitte ein eigenes sendefähiges Konto aus Nextcloud Mail auswählen.');
			}
			$address = $service->getPrimaryAddress()->getAddress();
			if (!is_string($address) || !filter_var($address, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $address)) {
				throw new Problem('mail_config', 'Das Mailkonto hat keine gültige Absenderadresse.');
			}
			$selection += ['owner' => $this->access->uid(), 'service' => $service->id(), 'address' => $address];
		}
		$this->config->setAppValue('werkstattmanager', 'workshop_mail', json_encode($selection, JSON_THROW_ON_ERROR));
		return $this->settings();
	}
	public function removeOwner(string $uid): void {
		$selection = $this->selection();
		if (($selection['owner'] ?? '') === $uid) {
			$this->config->setAppValue('werkstattmanager', 'workshop_mail', '{"mode":"mail"}');
		}
	}
	/** Validate before marking transmission as attempted; preflight failures can safely retry. */
	public function prepare(string $to, string $subject, string $body, string $ics): \Closure {
		$selection = $this->selection();
		$name = $this->company->profile()['name'];
		if (($selection['mode'] ?? '') === 'system') {
			$message = $this->system->createMessage();
			$message->setTo([$to])->setSubject($subject)->setPlainBody($body);
			$message->attach($this->system->createAttachment($ics, 'werkstatttermin.ics', 'text/calendar; charset=utf-8'));
			return function () use ($message): string {
				if ($this->system->send($message)) {
					throw new Problem('mail', 'E-Mail konnte nicht zugestellt werden.');
				} return 'sent';
			};
		}
		$owner = $selection['owner'] ?? '';
		if (($selection['mode'] ?? '') !== 'mail' || $owner === '' || !$this->users->get($owner)?->isEnabled() || !$this->access->isMaster($owner)) {
			throw new Problem('mail_config', 'Werkstatt-Mailkonto nicht verfügbar. Administrator muss die Auswahl prüfen.');
		}
		$service = $this->providers->findServiceById($owner, $selection['service'] ?? '', 'mail-application');
		if (!$service instanceof IMessageSend || !$service->capable('MessageSend') || $service->getPrimaryAddress()->getAddress() !== ($selection['address'] ?? null)) {
			throw new Problem('mail_config', 'Werkstatt-Mailkonto wurde entfernt oder geändert. Bitte erneut auswählen.');
		}
		$message = $service->initiateMessage();
		$message->setFrom(new Address($selection['address'], $name))->setTo(new Address($to))->setSubject($subject)->setBodyPlain($body)->setAttachments(new Attachment($ics, 'werkstatttermin.ics', 'text/calendar; charset=utf-8'));
		// Public API gives no remote delivery receipt or provider outbox identifier.
		return function () use ($service, $message): string {
			$service->sendMessage($message);
			return 'submitted';
		};
	}
}
