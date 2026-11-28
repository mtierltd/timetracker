<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Notification;

use OCA\TimeTracker\Db\AbsenceMapper;
use OCA\TimeTracker\Db\AbsenceTypeMapper;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

/**
 * Renders the absence notifications (#375). The type is read when the
 * notification is shown, so a type that colleagues may not see (e.g.
 * illness) is only named to approvers and to the user.
 */
class Notifier implements INotifier {

	public function __construct(
		private IFactory $l10nFactory,
		private IURLGenerator $urlGenerator,
		private IUserManager $userManager,
		private AbsenceMapper $absenceMapper,
		private AbsenceTypeMapper $typeMapper,
	) {
	}

	public function getID(): string {
		return 'timetracker';
	}

	public function getName(): string {
		return $this->l10nFactory->get('timetracker')->t('Time Tracker');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'timetracker') {
			throw new UnknownNotificationException();
		}
		$l = $this->l10nFactory->get('timetracker', $languageCode);
		$params = $notification->getSubjectParameters();
		$absence = $this->absenceMapper->findOrNull((int)$notification->getObjectId());
		if ($absence === null) {
			throw new UnknownNotificationException();
		}
		$type = $this->typeMapper->findOrNull((int)$absence->typeId);
		$typeName = $type?->name ?? $l->t('Absence');
		$period = $params['start'] === $params['end'] ? $params['start'] : $params['start'] . ' – ' . $params['end'];

		switch ($notification->getSubject()) {
			case 'absence_requested':
				$userName = $this->userManager->getDisplayName((string)$params['user']) ?? (string)$params['user'];
				$notification->setParsedSubject($l->t('%1$s requests %2$s: %3$s', [$userName, $typeName, $period]))
					->setLink($this->urlGenerator->linkToRouteAbsolute('timetracker.absences.admin_index'));
				break;
			case 'absence_decided':
				$subject = $params['status'] === 'approved'
					? $l->t('Your %1$s was approved: %2$s', [$typeName, $period])
					: $l->t('Your %1$s was rejected: %2$s', [$typeName, $period]);
				$notification->setParsedSubject($subject)
					->setLink($this->urlGenerator->linkToRouteAbsolute('timetracker.absences.index'));
				if ($absence->decisionComment) {
					$notification->setParsedMessage($absence->decisionComment);
				}
				break;
			default:
				throw new UnknownNotificationException();
		}
		$notification->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('timetracker', 'app.svg')));
		return $notification;
	}
}
