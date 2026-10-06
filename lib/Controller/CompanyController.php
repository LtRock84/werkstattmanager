<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Controller;

use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Service\Company;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

final class CompanyController extends Controller {
	public function __construct(
		IRequest $request,
		private Company $company,
		private IL10N $l10n,
		private LoggerInterface $logger,
	) {
		parent::__construct('werkstattmanager', $request);
	}
	#[PublicPage]
	#[NoCSRFRequired]
	public function profile(): JSONResponse {
		$r = new JSONResponse($this->company->profile());
		$r->addHeader('Cache-Control', 'no-store');
		return $r;
	}
	#[NoAdminRequired]
	public function save(string $name = '', bool $remove_logo = false): JSONResponse {
		try {
			return new JSONResponse($this->company->save($name, $this->request->getUploadedFile('logo'), $remove_logo));
		} catch (Problem $e) {
			return new JSONResponse(['error' => $e->error, 'message' => $this->l10n->t($e->getMessage())], $e->status);
		} catch (\Throwable $e) {
			$this->logger->error('Werkstatt company save failed', ['exception_class' => get_class($e)]);
			return new JSONResponse(['error' => 'internal', 'message' => $this->l10n->t('Firmenprofil konnte nicht gespeichert werden.')], 500);
		}
	}
	#[PublicPage]
	#[NoCSRFRequired]
	public function logo(string $hash): DataDisplayResponse {
		try {
			$r = new DataDisplayResponse($this->company->logo($hash), 200, ['Content-Type' => 'image/png']);
		} catch (Problem $e) {
			$r = new DataDisplayResponse('', $e->status);
		}
		$r->addHeader('X-Content-Type-Options', 'nosniff');
		$r->addHeader('Cache-Control', 'no-store');
		return $r;
	}
}
