<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000300Date20261009000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('wm_revenues')) {
			$t = $schema->createTable('wm_revenues');
			foreach (['id' => 32, 'source_kind' => 32, 'source_id' => 32, 'vehicle_id' => 32] as $name => $length) {
				$t->addColumn($name, Types::STRING, ['notnull' => true, 'length' => $length]);
			}
			foreach (['version', 'created_at', 'updated_at'] as $name) {
				$t->addColumn($name, Types::BIGINT, ['notnull' => true]);
			}
			foreach (['planned_net_cents', 'actual_net_cents'] as $name) {
				$t->addColumn($name, Types::BIGINT, ['notnull' => false]);
			}
			$t->addColumn('revenue_date', Types::STRING, ['notnull' => false, 'length' => 10]);
			$t->setPrimaryKey(['id']);
			$t->addUniqueIndex(['source_kind', 'source_id'], 'wm_revenue_source');
			$t->addIndex(['vehicle_id'], 'wm_revenue_vehicle');
		}
		return $schema;
	}
}
