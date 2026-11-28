<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Service;

use OCA\TimeTracker\Db\Absence;
use OCA\TimeTracker\Db\AbsenceMapper;
use OCA\TimeTracker\Db\AbsenceTypeMapper;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Sets a user's Nextcloud out-of-office period (Nextcloud 28+) to their
 * current or next approved absence, so their status, Talk, Mail and
 * Calendar show it (#377).
 *
 * Nextcloud keeps one out-of-office period per user and has no public API
 * to set it, so this uses the DAV app's AbsenceService if it exists. A
 * period the user set themselves is never changed: the app remembers the
 * period it set and only replaces or clears that one.
 */
class OutOfOfficeSync {

	private const DAV_SERVICE = 'OCA\\DAV\\Service\\AbsenceService';

	public function __construct(
		private AbsenceMapper $absenceMapper,
		private AbsenceTypeMapper $typeMapper,
		private IAppConfig $appConfig,
		private IConfig $config,
		private IUserManager $userManager,
		private UserTimeZone $userTimeZone,
		private LoggerInterface $logger,
	) {
	}

	public function isEnabled(): bool {
		return $this->appConfig->getValueString('timetracker', 'sync_out_of_office', 'yes') === 'yes';
	}

	public function setEnabled(bool $enabled): void {
		$this->appConfig->setValueString('timetracker', 'sync_out_of_office', $enabled ? 'yes' : 'no');
	}

	/** Users whose out-of-office period this app set. */
	public function syncedUsers(): array {
		return $this->config->getUsersForUserValue('timetracker', 'ooo_synced', 'yes');
	}

	/**
	 * Updates a user's out-of-office period after their absences changed.
	 */
	public function sync(string $userId): void {
		if (!$this->isEnabled() || !class_exists(self::DAV_SERVICE)) {
			return;
		}
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return;
		}
		try {
			$dav = \OCP\Server::get(self::DAV_SERVICE);
			$current = $dav->getAbsence($userId);
			$ours = $this->config->getUserValue($userId, 'timetracker', 'ooo_period', '');
			if ($current !== null && $current->getFirstDay() . '|' . $current->getLastDay() !== $ours) {
				// set by the user (or changed by them): leave it alone
				return;
			}
			$next = $this->nextAbsence($userId);
			if ($next === null) {
				if ($current !== null) {
					$dav->clearAbsence($user);
				}
				$this->forget($userId);
				return;
			}
			[$absence, $status] = $next;
			if ($ours === $absence->startDate . '|' . $absence->endDate) {
				return;
			}
			$dav->createOrUpdateAbsence($user, $absence->startDate, $absence->endDate, $status,
				sprintf('I am out of office until %s.', $absence->endDate));
			$this->config->setUserValue($userId, 'timetracker', 'ooo_period', $absence->startDate . '|' . $absence->endDate);
			$this->config->setUserValue($userId, 'timetracker', 'ooo_synced', 'yes');
		} catch (\Throwable $e) {
			$this->logger->warning('Could not update the out-of-office period of ' . $userId . ': ' . $e->getMessage(), ['exception' => $e]);
		}
	}

	private function forget(string $userId): void {
		$this->config->deleteUserValue($userId, 'timetracker', 'ooo_period');
		$this->config->deleteUserValue($userId, 'timetracker', 'ooo_synced');
	}

	/**
	 * The user's current or next approved absence that reduces the target
	 * (business trips and training aren't "out of office"), and the status
	 * text for it.
	 *
	 * @return array{Absence, string}|null
	 */
	private function nextAbsence(string $userId): ?array {
		$today = (new \DateTimeImmutable('now', $this->userTimeZone->get($userId)))->format('Y-m-d');
		$types = $this->typeMapper->findAllById();
		foreach ($this->absenceMapper->findOverlapping($userId, $today, '9999-12-31', [Absence::STATUS_APPROVED]) as $absence) {
			$type = $types[(int)$absence->typeId] ?? null;
			if ($type === null || $type->countsAs !== 'target_reduction') {
				continue;
			}
			// the type is only shown if colleagues may see it
			return [$absence, $type->visibility === 'reason' ? $type->name : 'Out of office'];
		}
		return null;
	}
}
