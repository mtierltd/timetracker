<?php

namespace OCA\TimeTracker\Tests\Integration;

use Test\TestCase;

/**
 * The "timetracker" block appears in Nextcloud's capabilities
 * (/ocs/v2.php/cloud/capabilities), with the version from appinfo/info.xml.
 *
 * @group DB
 */
#[\PHPUnit\Framework\Attributes\Group('DB')]
class CapabilitiesTest extends TestCase {

	public function testPublishedWithAppVersion(): void {
		$caps = \OCP\Server::get(\OC\CapabilitiesManager::class)->getCapabilities();

		$this->assertArrayHasKey('timetracker', $caps);
		// Nextcloud blocks libxml's file loading, so read the file first
		$info = simplexml_load_string((string)file_get_contents(__DIR__ . '/../../appinfo/info.xml'));
		$this->assertSame((string)$info->version, $caps['timetracker']['version']);
		$this->assertContains('v1', $caps['timetracker']['api']);
		$this->assertContains('timer', $caps['timetracker']['features']);
	}
}
