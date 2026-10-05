<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Controller;

use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Domain\Rules;
use OCA\Werkstattmanager\Service\Access;
use OCA\Werkstattmanager\Service\Attachments;
use OCA\Werkstattmanager\Service\Catalog;
use OCA\Werkstattmanager\Service\Defects;
use OCA\Werkstattmanager\Service\Orders;
use OCA\Werkstattmanager\Service\Privacy;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

final class ApiController extends Controller {
	public function __construct(
		IRequest $request,
		private Access $access,
		private Catalog $catalog,
		private Orders $orders,
		private Defects $defects,
		private Attachments $attachments,
		private Privacy $privacy,
		private IConfig $config,
		private IGroupManager $groups,
		private IL10N $l10n,
		private LoggerInterface $logger,
	) {
		parent::__construct('werkstattmanager', $request);
	}
	#[NoAdminRequired]
	public function list(
		string $kind,
		int $offset = 0,
		?string $vehicle_id = null,
		?string $order_id = null,
		?string $employee_id = null,
		?string $status = null,
		string $q = '',
		?string $id = null,
		?string $ids = null,
	): JSONResponse {
		$selectedIds = null;
		if ($ids !== null) {
			$selectedIds = explode(',', $ids);
			if (count($selectedIds) > 100 || array_filter($selectedIds, fn ($value) => !preg_match('/^[a-f0-9]{32}$/D', $value))) {
				return $this->error(new Problem('validation', 'Ungültige Fahrzeugauswahl.'));
			}
		}
		return $this->respond(
			fn () => $this->catalog->list(
				$kind,
				max(0, $offset),
				array_filter(
					[
						'vehicle_id' => $vehicle_id,
						'order_id' => $order_id,
						'employee_id' => $employee_id,
						'status' => $status,
						'id' => $selectedIds ?? $id,
					],
					fn ($v) => $v !== null,
				),
				$q,
			),
		);
	}
	#[NoAdminRequired]
	public function save(string $kind, array $data, ?string $id = null): JSONResponse {
		return $this->respond(fn () => $this->catalog->save($kind, $data, $id));
	}
	#[NoAdminRequired]
	public function createOrder(array $data): JSONResponse {
		return $this->respond(fn () => $this->orders->create($data));
	}
	#[NoAdminRequired]
	public function orderAction(string $id, string $operation, array $data): JSONResponse {
		return $this->respond(fn () => $this->orders->action($id, $operation, $data));
	}
	#[NoAdminRequired]
	public function action(string $operation, array $data): JSONResponse {
		return $this->respond(
			fn () => match ($operation) {
				'notification-retry' => $this->catalog->retryNotification(
					Rules::text($data['id'] ?? '', 32, true),
					Rules::integer($data['version'] ?? null, 1) ?? 0,
				),
				'part-request' => $this->orders->requestPart(
					Rules::text($data['order_id'] ?? '', 32, true),
					$data,
				),
				'part-status' => $this->orders->updatePart(
					Rules::text($data['id'] ?? '', 32, true),
					Rules::text($data['status'] ?? ''),
					(int)($data['version'] ?? 0),
				),
				'time-correct' => $this->orders->correctTime(
					Rules::text($data['id'] ?? '', 32, true),
					$data,
				),
				'defect-update' => $this->defects->update(
					Rules::text($data['id'] ?? '', 32, true),
					$data,
				),
				'vehicle-link' => $this->defects->link(
					Rules::text($data['vehicle_id'] ?? '', 32, true),
					($data['revoke'] ?? false) === true,
				),
				'mileage' => $this->catalog->mileage(
					Rules::text($data['vehicle_id'] ?? '', 32, true),
					Rules::integer($data['value'] ?? null) ?? 0,
					Rules::text($data['date'] ?? ''),
					$data['defect_id'] ?? null,
				),
				'deadline-complete' => $this->catalog->completeDeadline(
					Rules::text($data['id'] ?? '', 32, true),
					Rules::text($data['date'] ?? ''),
					Rules::integer($data['km'] ?? null),
				),
				'anonymize' => $this->privacy->anonymize(
					Rules::text($data['kind'] ?? ''),
					Rules::text($data['id'] ?? '', 32, true),
				),
				'erase-vehicle' => $this->privacy->eraseVehicle(
					Rules::text($data['id'] ?? '', 32, true),
					Rules::text($data['confirmation'] ?? '', 32, true),
				),
				default => throw new Problem('not_found', 'Unbekannte Aktion.', 404),
			},
		);
	}
	#[NoAdminRequired]
	public function settings(): JSONResponse {
		return $this->respond(function () {
			$this->access->master();
			return [
				'master_group' => $this->config->getAppValue(
					'werkstattmanager',
					'master_group',
					'werkstatt-meister',
				),
				'tablet_group' => $this->config->getAppValue(
					'werkstattmanager',
					'tablet_group',
					'werkstatt-tablet',
				),
				'admin' => $this->groups->isAdmin($this->access->uid()),
			];
		});
	}
	public function setSettings(string $master_group, string $tablet_group): JSONResponse {
		return $this->respond(function () use ($master_group, $tablet_group) {
			$this->access->admin();
			foreach ([$master_group, $tablet_group] as $g) {
				if (!$this->groups->get(Rules::text($g, 255, true))) {
					throw new Problem(
						'group',
						'Gruppe existiert nicht. Bitte in Nextcloud anlegen.',
					);
				}
			}
			if ($master_group === $tablet_group) {
				throw new Problem('group', 'Meister- und Tabletgruppe müssen verschieden sein.');
			}
			$this->config->setAppValue('werkstattmanager', 'master_group', $master_group);
			$this->config->setAppValue('werkstattmanager', 'tablet_group', $tablet_group);
			return ['saved' => true];
		});
	}
	#[NoAdminRequired]
	public function upload(
		?string $vehicle_id = null,
		?string $order_id = null,
		?string $part_id = null,
	): JSONResponse {
		return $this->respond(
			fn () => $this->attachments->upload($this->request->getUploadedFile('file') ?? [], [
				'vehicle_id' => $vehicle_id,
				'order_id' => $order_id,
				'part_id' => $part_id,
			]),
		);
	}
	#[NoAdminRequired]
	public function download(string $id): DataDownloadResponse|JSONResponse {
		try {
			$f = $this->attachments->download($id);
			$r = new DataDownloadResponse($f['content'], $f['meta']['name'], $f['meta']['mime']);
			$r->addHeader('X-Content-Type-Options', 'nosniff');
			$r->addHeader('Cache-Control', 'no-store');
			return $r;
		} catch (Problem $e) {
			return $this->error($e);
		}
	}
	#[NoAdminRequired]
	public function export(string $kind): DataDownloadResponse|JSONResponse {
		try {
			$this->access->master();
			$content = $this->privacy->csv($kind);
			return new DataDownloadResponse(
				$content,
				'werkstatt-' . $kind . '.csv',
				'text/csv; charset=utf-8',
			);
		} catch (Problem $e) {
			return $this->error($e);
		}
	}
	/** @param callable(): array $work */
	private function respond(callable $work): JSONResponse {
		try {
			$r = new JSONResponse($work());
			$r->addHeader('Cache-Control', 'no-store');
			return $r;
		} catch (Problem $e) {
			return $this->error($e);
		} catch (\OCP\DB\Exception $e) {
			if (in_array($e->getReason(), [14, 5, 15], true)) {
				return $this->error(
					new Problem('conflict', 'Gleichzeitige Änderung. Bitte aktualisieren.', 409),
				);
			}
			$this->logger->error('Werkstatt database operation failed', [
				'app' => 'werkstattmanager',
				'reason' => $e->getReason(),
			]);
			return new JSONResponse(
				[
					'error' => 'internal',
					'message' => $this->l10n->t(
						'Speichern fehlgeschlagen. Bitte erneut versuchen.',
					),
				],
				500,
			);
		} catch (\Throwable $e) {
			$this->logger->error('Werkstatt operation failed', [
				'app' => 'werkstattmanager',
				'exception_class' => get_class($e),
			]);
			return new JSONResponse(
				[
					'error' => 'internal',
					'message' => $this->l10n->t('Vorgang fehlgeschlagen. Bitte erneut versuchen.'),
				],
				500,
			);
		}
	}
	private function error(Problem $e): JSONResponse {
		return new JSONResponse(
			['error' => $e->error, 'message' => $this->l10n->t($e->getMessage())],
			$e->status,
		);
	}
}
