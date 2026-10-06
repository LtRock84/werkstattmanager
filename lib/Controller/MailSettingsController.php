<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Controller;

use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Service\WorkshopMailer;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

final class MailSettingsController extends Controller {
	public function __construct(
		IRequest $request,
		private WorkshopMailer $sender,
		private IL10N $l10n,
		private LoggerInterface $logger,
	) {
		parent::__construct('werkstattmanager', $request);
	}
	public function settings(): JSONResponse {
		return $this->respond(fn () => $this->sender->settings());
	}
	public function configure(array $data): JSONResponse {
		return $this->respond(fn () => $this->sender->configure($data));
	}
	/** @param callable(): array $work */
	private function respond(callable $work): JSONResponse {
		try {
			$r = new JSONResponse($work());
			$r->addHeader('Cache-Control', 'no-store');
			return $r;
		} catch (Problem $e) {
			return new JSONResponse(['error' => $e->error, 'message' => $this->l10n->t($e->getMessage())], $e->status);
		} catch (\Throwable $e) {
			$this->logger->error('Werkstatt mail settings failed', ['exception_class' => get_class($e)]);
			return new JSONResponse(['error' => 'mail_config', 'message' => $this->l10n->t('Mailkonten konnten nicht geladen werden. Bitte Nextcloud Mail prüfen.')], 503);
		}
	}
}
