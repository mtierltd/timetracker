<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Notification;

use OCA\TimeTracker\Db\Absence;
use OCP\IGroupManager;
use OCP\Notification\IManager;

/**
 * Sends the notifications about absence requests and decisions (#375).
 * Approvers are the Nextcloud admins.
 */
class AbsenceNotifications {

	public function __construct(
		private IManager $manager,
		private IGroupManager $groupManager,
	) {
	}

	/** Tells the approvers about a new request. */
	public function requested(Absence $absence): void {
		$admin = $this->groupManager->get('admin');
		if ($admin === null) {
			return;
		}
		foreach ($admin->getUsers() as $approver) {
			if ($approver->getUID() === $absence->userUid) {
				continue;
			}
			$notification = $this->base($absence, 'absence_requested')
				->setUser($approver->getUID());
			$this->manager->notify($notification);
		}
	}

	/** Tells the user about the decision, and removes the approvers' request notifications. */
	public function decided(Absence $absence): void {
		$this->removeRequest($absence);
		$this->manager->notify($this->base($absence, 'absence_decided')->setUser($absence->userUid));
	}

	/** Removes the approvers' notifications, e.g. when a request is cancelled. */
	public function removeRequest(Absence $absence): void {
		$notification = $this->manager->createNotification();
		$notification->setApp('timetracker')
			->setObject('absence', (string)$absence->id)
			->setSubject('absence_requested');
		$this->manager->markProcessed($notification);
	}

	private function base(Absence $absence, string $subject): \OCP\Notification\INotification {
		return $this->manager->createNotification()
			->setApp('timetracker')
			->setDateTime(new \DateTime())
			->setObject('absence', (string)$absence->id)
			->setSubject($subject, [
				'user' => $absence->userUid,
				'start' => $absence->startDate,
				'end' => $absence->endDate,
				'status' => $absence->status,
			]);
	}
}
