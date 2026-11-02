<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000601Date20261102010000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$table = $schema->getTable('wm_pickup_links');
		$table->getColumn('order_id')->setNotnull(false);
		if (!$table->hasColumn('appointment_id')) {
			$table->addColumn('appointment_id', Types::STRING, ['notnull' => false, 'length' => 32]);
		}
		if (!$table->hasIndex('wm_pickup_appt_u')) {
			$table->addUniqueIndex(['appointment_id'], 'wm_pickup_appt_u');
		}
		return $schema;
	}
}
