<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Controller;

use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Service\Attachments;
use OCA\Werkstattmanager\Service\Defects;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

final class DriverController extends Controller {
	public function __construct(
		IRequest $request,
		private Defects $defects,
		private Attachments $attachments,
		private IL10N $l10n,
		private LoggerInterface $logger,
	) {
		parent::__construct('werkstattmanager', $request);
	}
	#[PublicPage]
	#[UseSession]
	#[AnonRateLimit(limit: 30, period: 600)]
	#[UserRateLimit(limit: 30, period: 600)]
	public function exchange(string $token): JSONResponse {
		return $this->respond(fn () => $this->defects->exchange($token));
	}
	#[PublicPage]
	#[AnonRateLimit(limit: 120, period: 600)]
	#[UserRateLimit(limit: 120, period: 600)]
	public function view(int $offset = 0): JSONResponse {
		return $this->respond(fn () => $this->defects->publicView(max(0, $offset)));
	}
	#[PublicPage]
	#[UseSession]
	#[AnonRateLimit(limit: 10, period: 3600)]
	#[UserRateLimit(limit: 10, period: 3600)]
	public function report(array $data): JSONResponse {
		return $this->respond(fn () => $this->defects->report($data));
	}
	#[PublicPage]
	#[AnonRateLimit(limit: 50, period: 3600)]
	#[UserRateLimit(limit: 50, period: 3600)]
	public function upload(string $defect_id): JSONResponse {
		return $this->respond(
			fn () => $this->attachments->upload(
				$this->request->getUploadedFile('file') ?? [],
				['defect_id' => $defect_id],
				true,
			),
		);
	}
	/** @param callable(): array $work */
	private function respond(callable $work): JSONResponse {
		try {
			$r = new JSONResponse($work());
		} catch (Problem $e) {
			$r = new JSONResponse(
				['error' => $e->error, 'message' => $this->l10n->t($e->getMessage())],
				$e->status,
			);
		} catch (\Throwable $e) {
			$this->logger->error('Werkstatt driver operation failed', [
				'app' => 'werkstattmanager',
				'exception_class' => get_class($e),
			]);
			$r = new JSONResponse(
				[
					'error' => 'failed',
					'message' => $this->l10n->t('Vorgang fehlgeschlagen. Bitte erneut versuchen.'),
				],
				500,
			);
		}
		$r->addHeader('Cache-Control', 'no-store');
		$r->addHeader('Referrer-Policy', 'no-referrer');
		return $r;
	}
}
