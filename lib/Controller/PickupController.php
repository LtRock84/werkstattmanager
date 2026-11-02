<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Controller;

use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Service\PickupPasses;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

final class PickupController extends Controller {
	public function __construct(
		IRequest $request,
		private PickupPasses $passes,
		private IL10N $l10n,
		private LoggerInterface $logger,
	) {
		parent::__construct('werkstattmanager', $request);
	}
	#[PublicPage]
	#[UseSession]
	#[AnonRateLimit(limit: 20, period: 600)]
	#[UserRateLimit(limit: 20, period: 600)]
	public function exchange(string $token): JSONResponse {
		return $this->respond(fn () => $this->passes->exchange($token));
	}
	#[PublicPage]
	#[UseSession]
	#[AnonRateLimit(limit: 60, period: 600)]
	#[UserRateLimit(limit: 60, period: 600)]
	public function view(): JSONResponse {
		return $this->respond(fn () => $this->passes->publicView());
	}
	/** @param callable(): array<string, mixed> $work */
	private function respond(callable $work): JSONResponse {
		try {
			$r = new JSONResponse($work());
		} catch (Problem $e) {
			$r = new JSONResponse(['error' => $e->error, 'message' => $this->l10n->t($e->getMessage())], $e->status);
		} catch (\Throwable $e) {
			$this->logger->error('Werkstatt pickup operation failed', ['app' => 'werkstattmanager', 'exception_class' => get_class($e)]);
			$r = new JSONResponse(['error' => 'failed', 'message' => $this->l10n->t('Abholcode konnte nicht geprüft werden.')], 500);
		}
		$r->addHeader('Cache-Control', 'no-store');
		$r->addHeader('Referrer-Policy', 'no-referrer');
		$r->addHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
		return $r;
	}
}
