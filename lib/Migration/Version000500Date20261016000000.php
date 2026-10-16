<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000500Date20261016000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$table = $schema->getTable('wm_orders');
		if (!$table->hasColumn('planned_employee_id')) {
			$table->addColumn('planned_employee_id', Types::STRING, ['notnull' => false, 'length' => 32]);
			$table->addIndex(['planned_employee_id'], 'wm_order_planned_employee');
		}
		return $schema;
	}
}
