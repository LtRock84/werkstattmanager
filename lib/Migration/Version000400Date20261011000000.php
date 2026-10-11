<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000400Date20261011000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		foreach (['customers' => 'deadline_emails', 'appointments' => 'notify_ready'] as $name => $column) {
			$t = $schema->getTable('wm_' . $name);
			if (!$t->hasColumn($column)) {
				// Existing customers must explicitly enable reminders; upgrades never start a campaign.
				$t->addColumn($column, Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			}
		}
		$t = $schema->getTable('wm_deadlines');
		if (!$t->hasColumn('last_completed_date')) {
			$t->addColumn('last_completed_date', Types::STRING, ['notnull' => false, 'length' => 10]);
		}
		$t = $schema->getTable('wm_deliveries');
		foreach (['vehicle_id', 'deadline_id'] as $column) {
			if (!$t->hasColumn($column)) {
				$t->addColumn($column, Types::STRING, ['notnull' => false, 'length' => 32]);
				$t->addIndex([$column], 'wm_del_' . $column);
			}
		}
		if (!$schema->hasTable('wm_communications')) {
			$t = $schema->createTable('wm_communications');
			$t->addColumn('id', Types::STRING, ['notnull' => true, 'length' => 32]);
			foreach (['version', 'created_at', 'updated_at', 'reminder_days'] as $column) {
				$t->addColumn($column, Types::BIGINT, ['notnull' => true]);
			}
			$t->addColumn('templates', Types::TEXT, ['notnull' => true]);
			$t->setPrimaryKey(['id']);
		}
		return $schema;
	}
}
