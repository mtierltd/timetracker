<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Service;

/** Invalid input for an absence; the message is shown to the user. */
class AbsenceException extends \Exception {

	public function __construct(string $message, private int $status = 400) {
		parent::__construct($message);
	}

	public function getStatus(): int {
		return $this->status;
	}
}
