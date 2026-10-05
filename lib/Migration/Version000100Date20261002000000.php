<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Initial editable library; later template edits are never overwritten. */
final class Version000100Date20261002000000 extends SimpleMigrationStep {

	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$orders = $schema->getTable('wm_orders');
		if (!$orders->hasColumn('last_employee_id')) {
			$orders->addColumn('last_employee_id', Types::STRING, ['length' => 32, 'notnull' => false]);
			$orders->addIndex(['last_employee_id'], 'wm_orders_employee');
		}
		foreach ([['outbox', 'wm_outbox_due', ['status', 'next_try']], ['attachments', 'wm_attach_stale', ['ready', 'created_at']]] as [$table, $index, $columns]) {
			$t = $schema->getTable('wm_' . $table);
			if (!$t->hasIndex($index)) {
				$t->addIndex($columns, $index);
			}
		}
		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
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
