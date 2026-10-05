<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Service;

use OCA\Werkstattmanager\Domain\Problem;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserSession;

final class Access {
	public function __construct(
		private IUserSession $session,
		private IGroupManager $groups,
		private IConfig $config,
	) {
	}
	public function uid(): string {
		$u = $this->session->getUser();
		if (!$u) {
			throw new Problem('unauthorized', 'Bitte anmelden.', 401);
		}
		return $u->getUID();
	}
	public function role(): string {
		$uid = $this->uid();
		if (
			$this->groups->isAdmin($uid)
			|| $this->groups->isInGroup(
				$uid,
				$this->config->getAppValue('werkstattmanager', 'master_group', 'werkstatt-meister'),
			)
		) {
			return 'master';
		}
		if (
			$this->groups->isInGroup(
				$uid,
				$this->config->getAppValue('werkstattmanager', 'tablet_group', 'werkstatt-tablet'),
			)
		) {
			return 'tablet';
		}
		throw new Problem('forbidden', 'Kein Zugriff auf die Werkstatt.', 403);
	}
	public function master(): void {
		if ($this->role() !== 'master') {
			throw new Problem('forbidden', 'Diese Funktion ist dem Meister vorbehalten.', 403);
		}
	}
	public function admin(): void {
		if (!$this->groups->isAdmin($this->uid())) {
			throw new Problem('forbidden', 'Administratorrechte erforderlich.', 403);
		}
	}
	public function masters(): array {
		$group = $this->groups->get(
			$this->config->getAppValue('werkstattmanager', 'master_group', 'werkstatt-meister'),
		);
		return $group ? array_values(array_map(fn ($user) => $user->getUID(), $group->getUsers())) : [];
	}
	public function isMaster(string $uid): bool {
		return $this->groups->isInGroup(
			$uid,
			$this->config->getAppValue('werkstattmanager', 'master_group', 'werkstatt-meister'),
		);
	}
}
