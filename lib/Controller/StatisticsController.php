<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Controller;

use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Service\Statistics;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

final class StatisticsController extends Controller {
	public function __construct(
		IRequest $request,
		private Statistics $statistics,
		private IL10N $l10n,
		private LoggerInterface $logger,
	) {
		parent::__construct('werkstattmanager', $request);
	}
	#[NoAdminRequired]
	public function view(string $from, string $to, string $scope = 'all'): JSONResponse {
		return $this->respond(fn () => $this->statistics->view($from, $to, $scope));
	}
	#[NoAdminRequired]
	public function save(array $data): JSONResponse {
		return $this->respond(fn () => $this->statistics->save($data));
	}
	private function respond(callable $work): JSONResponse {
		try {
			$data = $work();
			if (!is_array($data)) {
				throw new \LogicException('Invalid statistics response');
			}
			$r = new JSONResponse($data);
			$r->addHeader('Cache-Control', 'no-store');
			return $r;
		} catch (Problem $e) {
			return new JSONResponse(
				['error' => $e->error, 'message' => $this->l10n->t($e->getMessage())],
				$e->status,
			);
		} catch (\OCP\DB\Exception $e) {
			if (in_array($e->getReason(), [14, 5, 15], true)) {
				return new JSONResponse(
					[
						'error' => 'conflict',
						'message' => $this->l10n->t('Gleichzeitige Änderung. Bitte aktualisieren.'),
					],
					409,
				);
			}
			$this->logger->error('Werkstatt statistics database failed', [
				'reason' => $e->getReason(),
			]);
			return new JSONResponse(
				['error' => 'internal', 'message' => $this->l10n->t('Speichern fehlgeschlagen.')],
				500,
			);
		} catch (\Throwable $e) {
			$this->logger->error('Werkstatt statistics failed', ['exception_class' => get_class($e)]);
			return new JSONResponse(
				[
					'error' => 'internal',
					'message' => $this->l10n->t('Vorgang fehlgeschlagen. Bitte erneut versuchen.'),
				],
				500,
			);
		}
	}
}
