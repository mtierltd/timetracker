<?php

declare(strict_types=1);

namespace OCA\TimeTracker;

use OCP\App\IAppManager;
use OCP\Capabilities\ICapability;

/**
 * Tells clients (Android, later iOS and the browser extension) which app
 * version the server runs and which features it offers, under "timetracker"
 * in /ocs/v2.php/cloud/capabilities (#313).
 *
 * Every feature id is documented in FEATURES.md; ids are never renamed.
 */
class Capabilities implements ICapability {

	/** Client API versions; "v1" is the /apps/timetracker/ajax/ endpoints. */
	public const API_VERSIONS = ['v1'];

	/** Features the server's API offers, see "Capability ids" in FEATURES.md. */
	public const FEATURES = [
		'timer',
		'manual_entries',
		'costs',
		'entry_time_offsets',
		'projects',
		'locked_projects',
		'clients',
		'tags',
		'reports',
		'timelines',
		'timelines_admin',
		'goals',
	];

	public function __construct(
		private IAppManager $appManager,
	) {
	}

	/**
	 * @return array{timetracker: array{version: string, api: list<string>, features: list<string>}}
	 */
	public function getCapabilities(): array {
		return [
			'timetracker' => [
				'version' => $this->appManager->getAppVersion('timetracker'),
				'api' => self::API_VERSIONS,
				'features' => self::FEATURES,
			],
		];
	}
}
