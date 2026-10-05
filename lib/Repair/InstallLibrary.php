<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Repair;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/** Fresh installs run schema-only migrations, so defaults also need a repair step.
 * Stable IDs match the historical migration; existing user edits are preserved. */
final class InstallLibrary implements IRepairStep {

	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function getName(): string {
		return 'Initialize Werkstattmanager templates and snippets';
	}

	public function run(IOutput $output): void {
		$templates = [
			['Inspektion', 90, ['Wartung nach Herstellervorgaben durchführen', 'Flüssigkeitsstände prüfen', 'Ergebnis dokumentieren']],
			['Ölwechsel', 45, ['Motoröl und Ölfilter nach Herstellervorgabe wechseln', 'Ölstand und Dichtheit prüfen']],
			['Reifenwechsel', 45, ['Räder wechseln', 'Reifendruck und Profiltiefe prüfen', 'Anzugsdrehmoment dokumentieren']],
			['Bremsen prüfen', 30, ['Bremsbeläge und Bremsscheiben prüfen', 'Befund dokumentieren']],
			['Fehlerdiagnose', 60, ['Fehlerspeicher auslesen', 'Fehlerursache eingrenzen', 'Befund dokumentieren']],
		];
		foreach ($templates as $position => [$title, $minutes, $steps]) {
			$this->seed('templates', 'template-' . $position, [
				'title' => $title, 'description' => '', 'minutes' => $minutes,
				'steps' => json_encode($steps, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
				'position' => $position, 'favorite' => true, 'archived' => false,
			]);
		}
		foreach (['Fehlerspeicher auslesen', 'Bremsbeläge prüfen', 'Probefahrt durchführen', 'Flüssigkeitsstände prüfen', 'Befund mit dem Meister besprechen'] as $position => $text) {
			$this->seed('snippets', 'snippet-' . $position, [
				'title' => $text, 'text' => $text, 'position' => $position, 'favorite' => true, 'archived' => false,
			]);
		}
	}

	private function seed(string $table, string $key, array $data): void {
		$id = substr(hash('sha256', 'werkstattmanager:initial:' . $key), 0, 32);
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('id')->from('wm_' . $table)->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))->executeQuery();
		try {
			if ($result->fetchOne() !== false) {
				return;
			}
		} finally {
			$result->closeCursor();
		}
		$qb = $this->db->getQueryBuilder();
		$qb->insert('wm_' . $table);
		foreach (['id' => $id, 'version' => 1, 'created_at' => time(), 'updated_at' => time()] + $data as $field => $value) {
			$type = is_bool($value) ? IQueryBuilder::PARAM_BOOL : (is_int($value) ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_STR);
			$qb->setValue($field, $qb->createNamedParameter($value, $type));
		}
		$qb->executeStatement();
	}
}
