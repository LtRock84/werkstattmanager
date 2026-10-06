<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Controller;

use OCA\Werkstattmanager\Domain\Problem;
use OCA\Werkstattmanager\Domain\Rules;
use OCA\Werkstattmanager\Service\Access;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;

/** Administrator-only grant management; directory results never go to reception/tablets. */
final class AccessController extends Controller {
	public function __construct(
		IRequest $request,
		private Access $access,
		private IConfig $config,
		private IGroupManager $groups,
		private IUserManager $users,
	) {
		parent::__construct('werkstattmanager', $request);
	}
	public function settings(): JSONResponse {
		$this->access->admin();
		$data = [];
		foreach (['master' => 'werkstatt-meister', 'tablet' => 'werkstatt-tablet', 'reception' => ''] as $role => $default) {
			$data[$role . '_group'] = $this->config->getAppValue('werkstattmanager', $role . '_group', $default);
		}
		foreach (['master', 'reception'] as $role) {
			$data[$role . '_users'] = array_map(fn ($uid) => ['id' => $uid, 'name' => $this->users->get($uid)?->getDisplayName() ?? $uid], $this->access->users($role));
		}
		$response = new JSONResponse($data);
		$response->addHeader('Cache-Control', 'no-store');
		return $response;
	}
	public function search(string $q = ''): JSONResponse {
		$this->access->admin();
		$q = Rules::text($q, 100);
		$items = [];
		if (mb_strlen(trim($q)) >= 2) {
			foreach ($this->users->searchDisplayName($q, 20) as $user) {
				if ($user->isEnabled()) {
					$items[] = ['id' => $user->getUID(), 'name' => $user->getDisplayName()];
				}
			}
		}
		$response = new JSONResponse(['items' => $items]);
		$response->addHeader('Cache-Control', 'no-store');
		return $response;
	}
	public function save(array $data): JSONResponse {
		$this->access->admin();
		try {
			$values = [];
			foreach (['master', 'tablet', 'reception'] as $role) {
				$g = Rules::text($data[$role . '_group'] ?? '', 255, $role !== 'reception');
				if ($g !== '' && !$this->groups->get($g)) {
					throw new Problem('group', 'Gruppe existiert nicht. Bitte in Nextcloud anlegen.');
				}
				$values[$role . '_group'] = $g;
			}
			$groups = array_filter($values, fn ($v) => $v !== '');
			if (count(array_unique($groups)) !== count($groups)) {
				throw new Problem('group', 'Meister-, Tablet- und Terminannahmegruppe müssen verschieden sein.');
			}
			foreach (['master', 'reception'] as $role) {
				$list = $data[$role . '_users'] ?? [];
				if (!is_array($list) || count($list) > 200) {
					throw new Problem('validation', 'Maximal 200 zusätzliche Benutzer pro Rolle.');
				}
				$ids = [];
				foreach ($list as $uid) {
					$uid = Rules::text($uid, 255, true);
					$user = $this->users->get($uid);
					if (!$user || !$user->isEnabled()) {
						throw new Problem('user', 'Benutzer fehlt oder ist deaktiviert.');
					}
					$ids[] = $uid;
				}
				$values[$role . '_users'] = json_encode(array_values(array_unique($ids)), JSON_THROW_ON_ERROR);
			}
			if (array_intersect(json_decode($values['master_users'], true), json_decode($values['reception_users'], true))) {
				throw new Problem('user', 'Bitte jeden Benutzer nur einer zusätzlichen Rolle zuordnen.');
			}
			foreach ($values as $key => $value) {
				$this->config->setAppValue('werkstattmanager', $key, $value);
			}
			return new JSONResponse(['saved' => true]);
		} catch (Problem $e) {
			return new JSONResponse(['code' => $e->error, 'message' => $e->getMessage()], $e->status);
		}
	}
}
