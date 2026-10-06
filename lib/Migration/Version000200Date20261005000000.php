<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000200Date20261005000000 extends SimpleMigrationStep {
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$tables = [
			'services' => [
				'seed_key' => 's',
				'title' => 's',
				'category' => 's',
				'description' => 't',
				'steps' => 'j',
				'minutes' => 'i',
				'time_min' => 'i',
				'time_max' => 'i',
				'time_source' => 't',
				'time_kind' => 's',
				'unit' => 's',
				'vehicle_classes' => 'j',
				'synonyms' => 'j',
				'includes' => 'j',
				'favorite' => 'b',
				'position' => 'i',
				'archived' => 'b',
				'own_price' => 'i',
			],
			'prices' => [
				'seed_key' => 's',
				'service_key' => 's',
				'provider' => 's',
				'source' => 't',
				'city' => 's',
				'region' => 's',
				'checked_date' => 's',
				'valid_from' => 's',
				'valid_until' => 's',
				'price_kind' => 's',
				'scope' => 's',
				'material' => 's',
				'unit' => 's',
				'vat' => 'i',
				'gross_cents' => 'i',
				'vehicle_class' => 's',
				'segment' => 's',
				'eligible' => 'b',
				'exclusion' => 't',
				'undated' => 'b',
			],
			'planning' => ['settings' => 'j'],
			'appointments' => [
				'vehicle_id' => 'id',
				'title' => 's',
				'customer_email' => 's',
				'arrival' => 's',
				'work_start' => 's',
				'pickup' => 's',
				'service_lines' => 'j',
				'minutes' => 'i',
				'allocations' => 'j',
				'status' => 's',
				'stage' => 's',
				'note' => 't',
				'override_reason' => 't',
				'reminder' => 'b',
				'order_id' => 'id',
				'first_day' => 's',
				'last_day' => 's',
			],
			'capacity' => ['date' => 's', 'minutes' => 'i', 'max_vehicles' => 'i', 'closed' => 'b'],
			'cost_profiles' => [
				'name' => 's',
				'costs' => 'j',
				'billable_hours' => 'i',
				'profit_percent' => 'i',
				'material_percent' => 'i',
				'vat_percent' => 'i',
			],
			'deliveries' => [
				'event_key' => 's',
				'appointment_id' => 'id',
				'appointment_version' => 'i',
				'channel' => 's',
				'kind' => 's',
				'status' => 's',
				'attempts' => 'i',
				'next_try' => 'i',
				'last_error' => 's',
				'payload' => 'j',
			],
		];
		$unique = [
			'services' => [['seed_key']],
			'prices' => [['seed_key']],
			'capacity' => [['date']],
			'appointments' => [['order_id']],
			'deliveries' => [['event_key']],
		];
		foreach ($tables as $name => $columns) {
			if ($schema->hasTable('wm_' . $name)) {
				continue;
			}
			$table = $schema->createTable('wm_' . $name);
			foreach (
				['id' => 'id', 'version' => 'i', 'created_at' => 'i', 'updated_at' => 'i']
					+ $columns as $field => $kind
			) {
				$type = match ($kind) {
					'i' => Types::BIGINT,
					'b' => Types::BOOLEAN,
					'j', 't' => Types::TEXT,
					default => Types::STRING,
				};
				$opts = [
					'notnull' => in_array(
						$field,
						['id', 'version', 'created_at', 'updated_at'],
						true,
					),
				];
				if ($type === Types::STRING) {
					$opts['length'] = $kind === 'id' ? 32 : 255;
				}
				$table->addColumn($field, $type, $opts);
			}
			$table->setPrimaryKey(['id']);
			foreach ($unique[$name] ?? [] as $i => $fields) {
				$table->addUniqueIndex($fields, 'wm_' . $name . '_u' . $i);
			}
			foreach (
				[
					'vehicle_id',
					'status',
					'next_try',
					'service_key',
					'arrival',
					'first_day',
					'last_day',
				] as $field
			) {
				if (isset($columns[$field])) {
					$table->addIndex([$field], 'wm_' . $name . '_' . substr($field, 0, 5));
				}
			}
		}
		$orders = $schema->getTable('wm_orders');
		foreach (['service_lines', 'planning_days'] as $field) {
			if (!$orders->hasColumn($field)) {
				$orders->addColumn($field, Types::TEXT, ['notnull' => false]);
			}
		}
		foreach (['first_day', 'last_day'] as $field) {
			if (!$orders->hasColumn($field)) {
				$orders->addColumn($field, Types::STRING, ['length' => 10, 'notnull' => false]);
				$orders->addIndex([$field], 'wm_orders_' . $field);
			}
		}
		$vehicles = $schema->getTable('wm_vehicles');
		if (!$vehicles->hasColumn('vehicle_class')) {
			$vehicles->addColumn('vehicle_class', Types::STRING, [
				'length' => 32,
				'notnull' => false,
			]);
		}
		return $schema;
	}
}
