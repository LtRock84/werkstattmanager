<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Add order-bound, revocable pickup passes and queue references. */
final class Version000600Date20261102000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('wm_pickup_links')) {
			$table = $schema->createTable('wm_pickup_links');
			$table->addColumn('id', Types::STRING, ['notnull' => true, 'length' => 32]);
			$table->addColumn('version', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('order_id', Types::STRING, ['notnull' => true, 'length' => 32]);
			$table->addColumn('vehicle_id', Types::STRING, ['notnull' => true, 'length' => 32]);
			$table->addColumn('token_hash', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('token_cipher', Types::TEXT, ['notnull' => true]);
			$table->addColumn('revoked', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$table->addColumn('ready_at', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('collected_at', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('email_on_ready', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$table->addColumn('recipient', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('initial_sent_at', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('ready_sent_at', Types::BIGINT, ['notnull' => false]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['order_id'], 'wm_pickup_order_u');
			$table->addUniqueIndex(['token_hash'], 'wm_pickup_token_u');
			$table->addIndex(['vehicle_id'], 'wm_pickup_vehicle');
		}
		$deliveries = $schema->getTable('wm_deliveries');
		foreach (['order_id', 'pickup_link_id'] as $column) {
			if (!$deliveries->hasColumn($column)) {
				$deliveries->addColumn($column, Types::STRING, ['notnull' => false, 'length' => 32]);
			}
		}
		if (!$deliveries->hasIndex('wm_delivery_pickup')) {
			$deliveries->addIndex(['pickup_link_id', 'status'], 'wm_delivery_pickup');
		}
		return $schema;
	}
}
