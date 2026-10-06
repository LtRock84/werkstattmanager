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
			|| in_array($uid, $this->users('master'), true)
			|| $this->groups->isInGroup(
				$uid,
				$this->config->getAppValue('werkstattmanager', 'master_group', 'werkstatt-meister'),
			)
		) {
			return 'master';
		}
		if (in_array($uid, $this->users('reception'), true) || (($group = $this->config->getAppValue('werkstattmanager', 'reception_group', '')) !== '' && $this->groups->isInGroup($uid, $group))) {
			return 'reception';
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
	public function users(string $role): array {
		$value = json_decode($this->config->getAppValue('werkstattmanager', $role . '_users', '[]'), true);
		return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
	}
	public function removeUser(string $uid): void {
		foreach (['master', 'reception'] as $role) {
			$before = $this->users($role);
			$after = array_values(array_filter($before, static fn ($id) => $id !== $uid));
			if ($before !== $after) {
				$this->config->setAppValue('werkstattmanager', $role . '_users', json_encode($after, JSON_THROW_ON_ERROR));
			}
		}
	}
	public function appointments(): void {
		if (!in_array($this->role(), ['master', 'reception'], true)) {
			throw new Problem('forbidden', 'Zugriff auf die Terminannahme erforderlich.', 403);
		}
	}
	public function workshop(): void {
		if (!in_array($this->role(), ['master', 'tablet'], true)) {
			throw new Problem('forbidden', 'Kein Zugriff auf Werkstattaufträge.', 403);
		}
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
		return array_values(array_unique(array_merge($this->users('master'), $group ? array_map(fn ($user) => $user->getUID(), $group->getUsers()) : [])));
	}
	public function isMaster(string $uid): bool {
		return $this->groups->isAdmin($uid) || in_array($uid, $this->users('master'), true) || $this->groups->isInGroup(
			$uid,
			$this->config->getAppValue('werkstattmanager', 'master_group', 'werkstatt-meister'),
		);
	}
}
