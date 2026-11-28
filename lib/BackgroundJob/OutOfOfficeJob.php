<?php

declare(strict_types=1);

namespace OCA\TimeTracker\BackgroundJob;

use OCA\TimeTracker\Service\OutOfOfficeSync;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

/**
 * Moves the out-of-office periods set by the app on to the next absence
 * once one has ended (#377).
 */
class OutOfOfficeJob extends TimedJob {

	public function __construct(
		ITimeFactory $time,
		private OutOfOfficeSync $sync,
	) {
		parent::__construct($time);
		$this->setInterval(6 * 3600);
	}

	protected function run($argument): void {
		foreach ($this->sync->syncedUsers() as $userId) {
			$this->sync->sync($userId);
		}
	}
}
