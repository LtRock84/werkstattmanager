<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Domain;

/** Internal reception record; never a technical release or customer signature. */
final class ReceptionChecklist {
	public static function validate(mixed $value): array {
		if ($value === null) {
			$value = [];
		}
		$checks = ['key_received', 'documents_checked', 'damage_checked', 'work_agreed', 'final_checked', 'customer_informed', 'key_returned'];
		if (!is_array($value) || array_diff(array_keys($value), [...$checks, 'mileage', 'condition_note', 'callback_note'])) {
			throw new Problem('validation', 'Ungültige Annahme- oder Abholcheckliste.');
		}
		$result = [];
		foreach ($checks as $key) {
			if (array_key_exists($key, $value) && !is_bool($value[$key])) {
				throw new Problem('validation', 'Checklistenpunkte bitte mit Ja oder Nein angeben.');
			}
			$result[$key] = $value[$key] ?? false;
		}
		$result['mileage'] = Rules::integer($value['mileage'] ?? null, 0, 10000000);
		$result['condition_note'] = Rules::text($value['condition_note'] ?? '', 2000);
		$result['callback_note'] = Rules::text($value['callback_note'] ?? '', 2000);
		return $result;
	}
}
