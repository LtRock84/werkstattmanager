<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000200Date20261005210000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$table = $schema->getTable('wm_services');
		if (!$table->hasColumn('seed_snapshot')) {
			$table->addColumn('seed_snapshot', Types::TEXT, ['notnull' => false]);
		}
		return $schema;
	}
}
