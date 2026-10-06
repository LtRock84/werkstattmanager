<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Domain;

/** Three-way field merge. Local preferences and prices always belong to the workshop. */
final class CatalogueUpdate {
	public static function changes(array $row, array $incoming): array {
		$baseline = $row['seed_snapshot'] ?? null;
		$changes = [];
		$timeFields = ['minutes', 'time_min', 'time_max', 'time_source', 'time_kind'];
		$ownTime = ($row['time_kind'] ?? '') === 'own';
		foreach ($incoming as $field => $value) {
			if (in_array($field, ['seed_key', 'favorite', 'archived', 'position', 'own_price'], true)) {
				continue;
			}
			if ($ownTime && in_array($field, $timeFields, true)) {
				continue;
			}
			$untouched = is_array($baseline)
				? array_key_exists($field, $baseline) && ($row[$field] ?? null) === $baseline[$field]
				: $row['version'] === 1;
			if ($untouched && ($row[$field] ?? null) !== $value) {
				$changes[$field] = $value;
			}
		}
		if ($baseline !== $incoming) {
			$changes['seed_snapshot'] = $incoming;
		}
		return $changes;
	}
}
