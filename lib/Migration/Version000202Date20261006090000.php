<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000202Date20261006090000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('wm_service_usage')) {
			$t = $schema->createTable('wm_service_usage');
			foreach (['id' => 32, 'origin' => 34, 'service_id' => 32] as $name => $length) {
				$t->addColumn($name, Types::STRING, ['notnull' => true, 'length' => $length]);
			}
			foreach (['version', 'created_at', 'updated_at'] as $name) {
				$t->addColumn($name, Types::BIGINT, ['notnull' => true]);
			}
			$t->setPrimaryKey(['id']);
			$t->addUniqueIndex(['origin', 'service_id'], 'wm_usage_origin_service');
			$t->addIndex(['service_id'], 'wm_usage_service');
		}
		return $schema;
	}
}
