<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Service;

use OCA\Werkstattmanager\Db\Store;
use OCA\Werkstattmanager\Domain\CustomerMailText;
use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Domain\Rules;

final class CustomerMailTemplates {
	public const ID = '00000000000000000000000000000004';
	public function __construct(
		private Store $store,
		private Access $access,
		private PlanningLedger $ledger,
		private Journal $journal,
		private Company $company,
	) {
	}
	public function data(): array {
		$row = $this->store->find('communications', ['id' => self::ID], 1)[0] ?? null;
		return ['version' => $row['version'] ?? 0, 'reminder_days' => $row['reminder_days'] ?? 30, 'templates' => $row['templates'] ?? CustomerMailText::defaults()];
	}
	public function settings(): array {
		$this->access->master();
		return $this->data() + ['placeholders' => CustomerMailText::TOKENS];
	}
	public function save(array $input): array {
		$this->access->master();
		$templates = CustomerMailText::validate($input['templates'] ?? null);
		$days = Rules::integer($input['reminder_days'] ?? 30, 1, 365) ?? 30;
		return $this->store->transaction(function () use ($input, $templates, $days) {
			$this->ledger->lock();
			$old = $this->store->find('communications', ['id' => self::ID], 1)[0] ?? null;
			if (($old['version'] ?? 0) !== ($input['version'] ?? null)) {
				throw new Problem('conflict', 'Nachrichtenvorlagen wurden inzwischen geändert. Bitte neu laden.', 409);
			}
			$value = ['templates' => $templates, 'reminder_days' => $days];
			$old ? $this->store->update('communications', $old, $value) : $this->store->insert('communications', ['id' => self::ID] + $value);
			$this->journal->record($this->access->uid(), 'mail.templates_saved', details: ['reminder_days' => $days]);
			return $this->settings();
		});
	}
	public function render(string $kind, array $payload): array {
		$templates = $this->data()['templates'];
		if (!isset($templates[$kind])) {
			throw new Problem('validation', 'Nachrichtenart ist nicht verfügbar.');
		}
		$local = static fn (?string $at): string => $at ? (new \DateTimeImmutable($at))->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i') . ' Uhr' : 'Noch nicht vereinbart';
		return CustomerMailText::render($templates[$kind], [
			'kundenname' => $payload['customer_name'] ?? 'liebe Kundin, lieber Kunde',
			'fahrzeug' => $payload['vehicle_label'] ?? '',
			'termin' => $local($payload['arrival'] ?? null),
			'abholung' => $local($payload['pickup'] ?? null),
			'leistung' => $payload['title'] ?? '',
			'frist' => $payload['title'] ?? '',
			'faellig_am' => !empty($payload['due_date']) ? (new \DateTimeImmutable($payload['due_date']))->format('d.m.Y') : 'Nach Kilometerstand',
			'faellig_km' => isset($payload['due_km']) ? number_format($payload['due_km'], 0, ',', '.') . ' km' : 'Keine Kilometergrenze',
			'werkstatt' => $this->company->profile()['name'] ?: 'Ihre Werkstatt',
		]);
	}
}
