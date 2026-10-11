<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Controller;

use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Domain\Rules;
use OCA\Werkstattmanager\Service\AppointmentBook;
use OCA\Werkstattmanager\Service\CalendarDelivery;
use OCA\Werkstattmanager\Service\ServiceLibrary;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

final class PlanningController extends Controller {
	public function __construct(
		IRequest $request,
		private ServiceLibrary $library,
		private AppointmentBook $book,
		private CalendarDelivery $calendar,
		private \OCA\Werkstattmanager\Service\CustomerMailTemplates $templates,
		private IL10N $l10n,
		private LoggerInterface $logger,
	) {
		parent::__construct('werkstattmanager', $request);
	}
	#[NoAdminRequired]
	public function services(): JSONResponse {
		return $this->respond(fn () => $this->library->list());
	}
	#[NoAdminRequired]
	public function mailTemplates(): JSONResponse {
		return $this->respond(fn () => $this->templates->settings());
	}
	#[NoAdminRequired]
	public function saveMailTemplates(array $data): JSONResponse {
		return $this->respond(fn () => $this->templates->save($data));
	}
	#[NoAdminRequired]
	public function prices(string $key): JSONResponse {
		return $this->respond(fn () => $this->library->prices(Rules::text($key, 255, true)));
	}
	#[NoAdminRequired]
	public function saveService(array $data): JSONResponse {
		return $this->respond(fn () => $this->library->save($data));
	}
	#[NoAdminRequired]
	public function appointments(string $from, string $to): JSONResponse {
		return $this->respond(fn () => $this->book->view($from, $to));
	}
	#[NoAdminRequired]
	public function saveAppointment(array $data): JSONResponse {
		return $this->respond(fn () => $this->book->save($data));
	}
	#[NoAdminRequired]
	public function convert(string $id, int $version): JSONResponse {
		return $this->respond(fn () => $this->book->convert($id, $version));
	}
	#[NoAdminRequired]
	public function capacity(array $data): JSONResponse {
		return $this->respond(fn () => $this->book->capacity($data));
	}
	#[NoAdminRequired]
	public function profile(): JSONResponse {
		return $this->respond(fn () => $this->library->profile());
	}
	#[NoAdminRequired]
	public function calculate(array $data, bool $save = false): JSONResponse {
		return $this->respond(fn () => $this->library->calculate($data, $save));
	}
	#[NoAdminRequired]
	public function deliveries(): JSONResponse {
		return $this->respond(fn () => $this->book->deliveries());
	}
	#[NoAdminRequired]
	public function retry(string $id, int $version): JSONResponse {
		return $this->respond(fn () => $this->book->retry($id, $version));
	}
	#[NoAdminRequired]
	public function integration(): JSONResponse {
		return $this->respond(fn () => $this->calendar->settings());
	}
	public function configure(array $data): JSONResponse {
		return $this->respond(fn () => $this->calendar->configure($data));
	}
	private function respond(callable $work): JSONResponse {
		try {
			$data = $work();
			if (!is_array($data)) {
				throw new \LogicException('Invalid planning response');
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
			$this->logger->error('Werkstatt planning database failed', [
				'reason' => $e->getReason(),
			]);
			return new JSONResponse(
				['error' => 'internal', 'message' => $this->l10n->t('Speichern fehlgeschlagen.')],
				500,
			);
		} catch (\Throwable $e) {
			$this->logger->error('Werkstatt planning failed', ['exception_class' => get_class($e)]);
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
