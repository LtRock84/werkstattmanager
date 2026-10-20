<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Index the read-only native calendar without changing appointment data. */
final class Version000502Date20261020000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$table = $schema->getTable('wm_appointments');
		foreach ([
			'wm_appt_cal_arrival' => ['status', 'arrival'],
			'wm_appt_cal_pickup' => ['status', 'pickup'],
			'wm_appt_cal_days' => ['status', 'first_day', 'last_day'],
		] as $name => $columns) {
			if (!$table->hasIndex($name)) {
				$table->addIndex($columns, $name);
			}
		}
		return $schema;
	}
}
