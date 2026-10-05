<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000100Date20261001000000 extends SimpleMigrationStep {
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		$schema = $schemaClosure();
		// The initial schema is copied here deliberately: later domain changes must not alter this migration.
		$tables = [
			'customers' => [
				'name' => 's',
				'contact' => 's',
				'email' => 's',
				'phone' => 's',
				'notes' => 't',
				'archived' => 'b',
			],
			'vehicles' => [
				'customer_id' => 'id',
				'kind' => 's',
				'plate' => 's',
				'number' => 's',
				'vin' => 's',
				'make' => 's',
				'model' => 's',
				'registration' => 's',
				'mileage' => 'i',
				'mileage_date' => 's',
				'notes' => 't',
				'archived' => 'b',
			],
			'employees' => ['name' => 's', 'archived' => 'b'],
			'templates' => [
				'title' => 's',
				'description' => 't',
				'steps' => 'j',
				'minutes' => 'i',
				'favorite' => 'b',
				'position' => 'i',
				'archived' => 'b',
			],
			'snippets' => [
				'title' => 's',
				'text' => 't',
				'favorite' => 'b',
				'position' => 'i',
				'archived' => 'b',
			],
			'orders' => [
				'vehicle_id' => 'id',
				'title' => 's',
				'description' => 't',
				'steps' => 'j',
				'minutes' => 'i',
				'planned_start' => 's',
				'due_at' => 's',
				'priority' => 's',
				'status' => 's',
				'pause_reason' => 's',
				'completed_at' => 's',
			],
			'intervals' => [
				'order_id' => 'id',
				'employee_id' => 'id',
				'started' => 'i',
				'ended' => 'i',
				'note' => 't',
				'steps' => 'j',
			],
			'active' => ['order_id' => 'id', 'employee_id' => 'id', 'interval_id' => 'id'],
			'parts' => [
				'order_id' => 'id',
				'employee_id' => 'id',
				'title' => 's',
				'quantity' => 'i',
				'part_number' => 's',
				'priority' => 's',
				'note' => 't',
				'status' => 's',
			],
			'defects' => [
				'vehicle_id' => 'id',
				'order_id' => 'id',
				'duplicate_id' => 'id',
				'category' => 's',
				'description' => 't',
				'priority' => 's',
				'reporter' => 's',
				'mileage' => 'i',
				'mileage_confirmed' => 'b',
				'status' => 's',
				'public_text' => 't',
				'published' => 'b',
			],
			'deadlines' => [
				'vehicle_id' => 'id',
				'title' => 's',
				'due_date' => 's',
				'due_km' => 'i',
				'repeat_months' => 'i',
				'repeat_km' => 'i',
				'warning_days' => 'j',
				'status' => 's',
				'completed_date' => 's',
			],
			'mileage' => ['vehicle_id' => 'id', 'value' => 'i', 'date' => 's', 'source' => 's'],
			'links' => ['vehicle_id' => 'id', 'token_hash' => 's', 'revoked' => 'b'],
			'attachments' => [
				'vehicle_id' => 'id',
				'order_id' => 'id',
				'defect_id' => 'id',
				'part_id' => 'id',
				'name' => 's',
				'mime' => 's',
				'size' => 'i',
				'storage_key' => 's',
				'content_hash' => 's',
				'ready' => 'b',
			],
			'audit' => [
				'vehicle_id' => 'id',
				'order_id' => 'id',
				'actor' => 's',
				'employee_id' => 'id',
				'action' => 's',
				'details' => 'j',
			],
			'requests' => ['request_key' => 's', 'fingerprint' => 's', 'response' => 'j'],
			'outbox' => [
				'event_key' => 's',
				'object_id' => 'id',
				'subject' => 's',
				'recipient' => 's',
				'status' => 's',
				'attempts' => 'i',
				'next_try' => 'i',
			],
		];
		$unique = [
			'active' => [['employee_id'], ['order_id']],
			'links' => [['token_hash']],
			'requests' => [['request_key']],
			'outbox' => [['event_key']],
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
			foreach ($unique[$name] ?? [] as $n => $cols) {
				$table->addUniqueIndex($cols, 'wm_' . $name . '_u' . $n);
			}
			foreach (['vehicle_id', 'order_id', 'status', 'created_at'] as $field) {
				if (isset($columns[$field]) || $field === 'created_at') {
					$table->addIndex([$field], 'wm_' . $name . '_' . substr($field, 0, 5));
				}
			}
		}
		return $schema;
	}
}
