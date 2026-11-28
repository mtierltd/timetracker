<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Service;

use OCP\IAppConfig;
use OCP\IConfig;

/**
 * Settings for absences: the federal state whose public holidays apply
 * (set by admins, can be overridden per user).
 */
class AbsenceSettings {

	public function __construct(
		private IAppConfig $appConfig,
		private IConfig $config,
	) {
	}

	/** The admin's default state, '' for nationwide holidays only. */
	public function defaultState(): string {
		$state = $this->appConfig->getValueString('timetracker', 'holiday_state', '');
		return isset(PublicHolidays::STATES[$state]) ? $state : '';
	}

	public function setDefaultState(string $state): void {
		$this->appConfig->setValueString('timetracker', 'holiday_state', $state);
	}

	/** The user's own state, '' if they use the default. */
	public function userState(string $userId): string {
		$state = $this->config->getUserValue($userId, 'timetracker', 'holiday_state', '');
		return isset(PublicHolidays::STATES[$state]) ? $state : '';
	}

	public function setUserState(string $userId, string $state): void {
		if ($state === '') {
			$this->config->deleteUserValue($userId, 'timetracker', 'holiday_state');
		} else {
			$this->config->setUserValue($userId, 'timetracker', 'holiday_state', $state);
		}
	}

	/** The state whose holidays apply to a user. */
	public function stateFor(string $userId): string {
		return $this->userState($userId) ?: $this->defaultState();
	}
}
