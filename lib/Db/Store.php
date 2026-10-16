<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Db;

use OCA\Werkstattmanager\Domain\Problem;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class Store {
	public function __construct(
		private IDBConnection $db,
	) {
	}
	public function transaction(callable $work): mixed {
		$this->db->beginTransaction();
		try {
			$result = $work();
			$this->db->commit();
			return $result;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}
	public function get(string $table, string $id): array {
		$rows = $this->find($table, ['id' => $id], 1);
		if (!$rows) {
			throw new Problem('not_found', 'Eintrag nicht gefunden.', 404);
		}
		return $rows[0];
	}
	public function find(
		string $table,
		array $filters = [],
		int $limit = 100,
		int $offset = 0,
		string $search = '',
	): array {
		$fields = Schema::fields($table);
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('wm_' . $table)
			->orderBy('created_at', 'DESC')
			->addOrderBy('id', 'ASC')
			->setMaxResults(min(500, max(1, $limit)))
			->setFirstResult(max(0, $offset));
		foreach ($filters as $field => $value) {
			if ($table === 'orders' && $field === 'employee_id') {
				$qb->andWhere($qb->expr()->orX(
					$qb->expr()->eq('last_employee_id', $qb->createNamedParameter($value)),
					$qb->expr()->eq('planned_employee_id', $qb->createNamedParameter($value)),
				));
				continue;
			}
			if (!isset($fields[$field])) {
				throw new \InvalidArgumentException('Unknown filter');
			}
			if ($value === null) {
				$qb->andWhere($qb->expr()->isNull($field));
			} elseif (is_array($value)) {
				$qb->andWhere(
					$qb
						->expr()
						->in(
							$field,
							$qb->createNamedParameter($value, IQueryBuilder::PARAM_STR_ARRAY),
						),
				);
			} else {
				$qb->andWhere(
					$qb
						->expr()
						->eq($field, $qb->createNamedParameter($value, $this->paramType($value))),
				);
			}
		}
		if ($search !== '') {
			$columns = match ($table) {
				'vehicles' => ['plate', 'number', 'vin', 'make', 'model'],
				'customers' => ['name', 'contact'],
				'orders' => ['title', 'description'],
				'employees' => ['name'],
				'templates', 'snippets' => ['title'],
				'services' => ['title', 'category', 'description'],
				default => [],
			};
			if ($columns) {
				$conditions = [];
				foreach ($columns as $column) {
					$conditions[] = $qb
						->expr()
						->iLike(
							$column,
							$qb->createNamedParameter(
								'%' . $this->db->escapeLikeParameter($search) . '%',
							),
						);
				}
				if ($table === 'vehicles') {
					$customers = $this->find('customers', [], 100, 0, $search);
					if ($customers) {
						$conditions[] = $qb
							->expr()
							->in(
								'customer_id',
								$qb->createNamedParameter(
									array_column($customers, 'id'),
									IQueryBuilder::PARAM_STR_ARRAY,
								),
							);
					}
				}
				$qb->andWhere($qb->expr()->orX(...$conditions));
			}
		}
		$result = $qb->executeQuery();
		try {
			$rows = $result->fetchAllAssociative();
		} finally {
			$result->closeCursor();
		}
		return array_map(fn (array $row) => $this->decode($table, $row), $rows);
	}
	/** Select eligible work before applying the batch limit, oldest first. */
	public function pendingWork(string $table, int $cutoff): array {
		[$field, $stateField, $state] = match ($table) {
			'outbox', 'deliveries' => ['next_try', 'status', 'pending'],
			'attachments' => ['created_at', 'ready', false],
			default => throw new \InvalidArgumentException('Unsupported work queue'),
		};
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('wm_' . $table)
			->where(
				$qb
					->expr()
					->lte($field, $qb->createNamedParameter($cutoff, IQueryBuilder::PARAM_INT)),
			)
			->andWhere(
				$qb
					->expr()
					->eq($stateField, $qb->createNamedParameter($state, $this->paramType($state))),
			)
			->orderBy($field, 'ASC')
			->addOrderBy('id', 'ASC')
			->setMaxResults(100);
		$result = $qb->executeQuery();
		try {
			return array_map(
				fn (array $row) => $this->decode($table, $row),
				$result->fetchAllAssociative(),
			);
		} finally {
			$result->closeCursor();
		}
	}
	/** Indexed overlap window, with legacy orders selected by their UTC planned start. */
	public function planningRows(string $table, string $from, string $to, int $offset = 0): array {
		if (!in_array($table, ['orders', 'appointments', 'capacity'], true)) {
			throw new \InvalidArgumentException('Unsupported planning table');
		}
		$q = $this->db->getQueryBuilder();
		$q->select('*')
			->from('wm_' . $table)
			->orderBy('id', 'ASC')
			->setMaxResults(500)
			->setFirstResult($offset);
		$e = $q->expr();
		if ($table === 'capacity') {
			$q->where($e->gte('date', $q->createNamedParameter($from)))->andWhere(
				$e->lte('date', $q->createNamedParameter($to)),
			);
		} else {
			$overlap = $e->andX(
				$e->lte('first_day', $q->createNamedParameter($to)),
				$e->gte('last_day', $q->createNamedParameter($from)),
			);
			if ($table === 'orders') {
				$z = new \DateTimeZone('Europe/Berlin');
				$utc = new \DateTimeZone('UTC');
				$start = (new \DateTimeImmutable($from, $z))
					->setTimezone($utc)
					->format('Y-m-d\TH:i:s\Z');
				$end = (new \DateTimeImmutable($to, $z))
					->modify('+1 day')
					->setTimezone($utc)
					->format('Y-m-d\TH:i:s\Z');
				$legacy = $e->andX(
					$e->isNull('first_day'),
					$e->gte('planned_start', $q->createNamedParameter($start)),
					$e->lt('planned_start', $q->createNamedParameter($end)),
				);
				$overlap = $e->orX($overlap, $legacy);
				$q->andWhere(
					$e->in(
						'status',
						$q->createNamedParameter(
							['open', 'active', 'paused', 'review', 'completed'],
							IQueryBuilder::PARAM_STR_ARRAY,
						),
					),
				);
			}
			$q->andWhere($overlap);
		}
		$result = $q->executeQuery();
		try {
			return array_map(
				fn (array $row) => $this->decode($table, $row),
				$result->fetchAllAssociative(),
			);
		} finally {
			$result->closeCursor();
		}
	}
	public function serviceCounts(): array {
		$q = $this->db->getQueryBuilder();
		$q->select('service_id')->selectAlias($q->func()->count('*'), 'uses')->from('wm_service_usage')->groupBy('service_id');
		$result = $q->executeQuery();
		try {
			$counts = [];
			while ($row = $result->fetchAssociative()) {
				$counts[$row['service_id']] = (int)$row['uses'];
			}
			return $counts;
		} finally {
			$result->closeCursor();
		}
	}
	/** Stable keyset pages for reports: inserts never shift earlier pages. */
	public function reportPage(string $table, string $cursor): array {
		if (!in_array($table, ['orders', 'appointments', 'vehicles', 'employees', 'intervals', 'revenues'], true)) {
			throw new \InvalidArgumentException('Unsupported report table');
		}
		$q = $this->db->getQueryBuilder();
		$q->select('*')->from('wm_' . $table)->where($q->expr()->gt('id', $q->createNamedParameter($cursor)))->orderBy('id', 'ASC')->setMaxResults(500);
		$result = $q->executeQuery();
		try {
			return array_map(fn ($row) => $this->decode($table, $row), $result->fetchAllAssociative());
		} finally {
			$result->closeCursor();
		}
	}
	public function usageBackfillPage(string $table, string $cursor): array {
		if (!in_array($table, ['appointments', 'orders'], true)) {
			throw new \InvalidArgumentException('Unsupported usage source');
		}
		$q = $this->db->getQueryBuilder();
		$q->select('*')->from('wm_' . $table)->where($q->expr()->gt('id', $q->createNamedParameter($cursor)))->orderBy('id', 'ASC')->setMaxResults(100);
		$result = $q->executeQuery();
		try {
			return array_map(fn ($row) => $this->decode($table, $row), $result->fetchAllAssociative());
		} finally {
			$result->closeCursor();
		}
	}
	public function insert(string $table, array $data): array {
		$data
			= [
				'id' => $data['id'] ?? bin2hex(random_bytes(16)),
				'version' => 1,
				'created_at' => time(),
				'updated_at' => time(),
			] + $data;
		$qb = $this->db->getQueryBuilder();
		$qb->insert('wm_' . $table);
		foreach ($this->encode($table, $data) as $k => $v) {
			$qb->setValue($k, $qb->createNamedParameter($v, $this->paramType($v)));
		}
		$qb->executeStatement();
		return $this->get($table, $data['id']);
	}
	public function update(string $table, array $old, array $changes): array {
		unset($changes['id'], $changes['created_at']);
		$changes['version'] = $old['version'] + 1;
		$changes['updated_at'] = time();
		$qb = $this->db->getQueryBuilder();
		$qb->update('wm_' . $table);
		foreach ($this->encode($table, $changes) as $k => $v) {
			$qb->set($k, $qb->createNamedParameter($v, $this->paramType($v)));
		}
		$qb->where($qb->expr()->eq('id', $qb->createNamedParameter($old['id'])))->andWhere(
			$qb
				->expr()
				->eq(
					'version',
					$qb->createNamedParameter($old['version'], IQueryBuilder::PARAM_INT),
				),
		);
		if ($qb->executeStatement() !== 1) {
			throw new Problem(
				'conflict',
				'Der Eintrag wurde inzwischen geändert. Bitte neu laden.',
				409,
			);
		}
		return $this->get($table, $old['id']);
	}
	public function delete(string $table, string $id): void {
		Schema::fields($table);
		$qb = $this->db->getQueryBuilder();
		$qb->delete('wm_' . $table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->executeStatement();
	}
	private function paramType(mixed $v): mixed {
		return $v === null
			? IQueryBuilder::PARAM_NULL
			: (is_bool($v)
				? IQueryBuilder::PARAM_BOOL
				: (is_int($v)
					? IQueryBuilder::PARAM_INT
					: IQueryBuilder::PARAM_STR));
	}
	private function encode(string $table, array $data): array {
		$fields = Schema::fields($table);
		foreach ($data as $k => $v) {
			if (!isset($fields[$k])) {
				throw new \InvalidArgumentException('Unknown column');
			}
			if ($fields[$k] === 'j' && $v !== null) {
				$data[$k] = json_encode($v, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
			}
		}
		return $data;
	}
	private function decode(string $table, array $data): array {
		foreach (Schema::fields($table) as $k => $kind) {
			if (!array_key_exists($k, $data) || $data[$k] === null) {
				$data[$k] = null;
				continue;
			}
			$data[$k] = match ($kind) {
				'i' => (int)$data[$k],
				'b' => in_array($data[$k], [true, 1, '1', 't', 'true'], true),
				'j' => json_decode($data[$k], true, 512, JSON_THROW_ON_ERROR),
				default => $data[$k],
			};
		}
		return $data;
	}
}
