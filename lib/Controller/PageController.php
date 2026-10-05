<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Controller;

use OCA\Werkstattmanager\Service\Access;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;

final class PageController extends Controller {
	public function __construct(
		IRequest $request,
		private Access $access,
		private IInitialState $initialState,
	) {
		parent::__construct('werkstattmanager', $request);
	}
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		try {
			$role = $this->access->role();
		} catch (\OCA\Werkstattmanager\Domain\Problem) {
			return new TemplateResponse('werkstattmanager', 'denied', [], 'user', 403);
		}
		$this->initialState->provideInitialState('mode', $role);
		return new TemplateResponse('werkstattmanager', 'main');
	}
	#[PublicPage]
	#[NoCSRFRequired]
	public function driver(): TemplateResponse {
		$this->initialState->provideInitialState('mode', 'driver');
		$r = new TemplateResponse('werkstattmanager', 'main', [], 'public');
		$r->addHeader('Referrer-Policy', 'no-referrer');
		$r->addHeader('Cache-Control', 'no-store');
		return $r;
	}
}
