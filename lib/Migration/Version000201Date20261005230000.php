<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000201Date20261005230000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$table = $schema->getTable('wm_appointments');
		if (!$table->hasColumn('checklist')) {
			$table->addColumn('checklist', Types::TEXT, ['notnull' => false]);
		}
		return $schema;
	}
}
