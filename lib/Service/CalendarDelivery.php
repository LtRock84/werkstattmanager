<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Service;

use OCA\Werkstattmanager\Domain\AppointmentCalendar;
use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Domain\Rules;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\Security\ICrypto;

final class CalendarDelivery {
	public function __construct(
		private Access $access,
		private IConfig $config,
		private ICrypto $crypto,
		private IURLGenerator $urls,
		private IClientService $http,
	) {
	}
	public function settings(): array {
		$this->access->master();
		return [
			'calendar_url' => $this->config->getAppValue('werkstattmanager', 'calendar_url', ''),
			'calendar_user' => $this->config->getAppValue('werkstattmanager', 'calendar_user', ''),
			'has_password'
				=> $this->config->getAppValue('werkstattmanager', 'calendar_secret', '') !== '',
		];
	}
	public function configure(array $data): array {
		$this->access->admin();
		$url = Rules::text($data['calendar_url'] ?? '', 2000);
		$user = Rules::text($data['calendar_user'] ?? '', 255);
		if ($url !== '') {
			$this->checkUrl($url, $user);
		}
		$encrypted
			= isset($data['password']) && $data['password'] !== ''
				? $this->crypto->encrypt(Rules::text($data['password'], 1024, true))
				: null;
		$this->config->setAppValue('werkstattmanager', 'calendar_url', $url);
		$this->config->setAppValue('werkstattmanager', 'calendar_user', $user);
		if (($data['remove_password'] ?? false) === true) {
			$this->config->deleteAppValue('werkstattmanager', 'calendar_secret');
		} elseif ($encrypted !== null) {
			$this->config->setAppValue('werkstattmanager', 'calendar_secret', $encrypted);
		}
		return $this->settings();
	}
	private function checkUrl(string $url, string $user): void {
		$origin = parse_url($this->urls->getAbsoluteURL('/'));
		$target = parse_url($url);
		if (
			!$target
			|| ($target['scheme'] ?? '') !== 'https'
			|| ($target['host'] ?? '') !== ($origin['host'] ?? '')
			|| ($target['port'] ?? 443) !== ($origin['port'] ?? 443)
			|| isset($target['user'])
			|| isset($target['pass'])
			|| isset($target['query'])
			|| isset($target['fragment'])
		) {
			throw new Problem(
				'calendar',
				'Kalenderadresse muss zur eigenen Nextcloud gehören und HTTPS verwenden.',
			);
		}
		$base = rtrim($origin['path'] ?? '', '/');
		$expected = $base . '/remote.php/dav/calendars/' . rawurlencode($user) . '/';
		if (
			$user === ''
			|| !str_starts_with($target['path'] ?? '', $expected)
			|| !preg_match('~^[a-zA-Z0-9_.%-]+/$~D', substr($target['path'], strlen($expected)))
			|| str_contains(rawurldecode($target['path']), '..')
		) {
			throw new Problem(
				'calendar',
				'Bitte die CalDAV-Adresse eines Kalenders des Integrationskontos angeben.',
			);
		}
	}
	public function send(array $payload, string $kind): void {
		$url = $this->config->getAppValue('werkstattmanager', 'calendar_url', '');
		$user = $this->config->getAppValue('werkstattmanager', 'calendar_user', '');
		$secret = $this->config->getAppValue('werkstattmanager', 'calendar_secret', '');
		if ($url === '' || $secret === '') {
			throw new Problem('calendar_config', 'Kalenderzugang noch nicht eingerichtet.');
		}
		$this->checkUrl($url, $user);
		// CalDAV permits one event UID per resource. Stable per-event paths make PUT/DELETE repeatable.
		$suffix = $payload['event_suffix'];
		if (!preg_match('/^(arrival|pickup|work-\d{4}-\d{2}-\d{2})$/D', $suffix)) {
			throw new Problem('calendar', 'Ungültige Ereigniskennung.');
		}
		$target = $url . 'werkstatt-' . $payload['id'] . '-' . $suffix . '.ics';
		$method = $kind === 'cancel' ? 'DELETE' : 'PUT';
		$response = $this->http
			->newClient()
			->request($method, $target, [
				'auth' => [$user, $this->crypto->decrypt($secret)],
				'allow_redirects' => false,
				'verify' => true,
				'timeout' => 15,
				'connect_timeout' => 5,
				'http_errors' => false,
				'headers' => ['Content-Type' => 'text/calendar; charset=utf-8'],
				'body'
					=> $method === 'PUT'
						? AppointmentCalendar::build($payload, $kind, true, null, $suffix)
						: '',
			]);
		if (
			!in_array(
				$response->getStatusCode(),
				$method === 'DELETE' ? [200, 204, 404] : [200, 201, 204],
				true,
			)
		) {
			throw new Problem(
				'calendar_http',
				'Kalender konnte nicht aktualisiert werden. Bitte Zugang und Schreibrechte prüfen.',
			);
		}
	}
}
