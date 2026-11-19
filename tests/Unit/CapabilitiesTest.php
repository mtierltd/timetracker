<?php

namespace OCA\TimeTracker\Tests\Unit;

use OCA\TimeTracker\Capabilities;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;

class CapabilitiesTest extends TestCase {

	public function testBlock(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppVersion')->with('timetracker')->willReturn('1.2.3');

		$caps = (new Capabilities($appManager))->getCapabilities();

		$this->assertSame(['timetracker'], array_keys($caps));
		$this->assertSame('1.2.3', $caps['timetracker']['version']);
		$this->assertSame(['v1'], $caps['timetracker']['api']);
		$this->assertSame(Capabilities::FEATURES, $caps['timetracker']['features']);
	}

	public function testFeatureIdsAreUniqueAndSnakeCase(): void {
		$this->assertSame(Capabilities::FEATURES, array_values(array_unique(Capabilities::FEATURES)));
		foreach (Capabilities::FEATURES as $id) {
			$this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $id);
		}
	}

	/**
	 * Every published id is documented in the "Capability ids" table of
	 * FEATURES.md, and every id documented there as available is published.
	 */
	public function testFeatureIdsMatchFeaturesMd(): void {
		$md = file_get_contents(__DIR__ . '/../../FEATURES.md');
		$this->assertIsString($md);
		$section = substr($md, strpos($md, '## Capability ids'));
		$section = substr($section, 0, strpos($section, "\n## ", 4) ?: strlen($section));

		// table rows: | `id` | meaning | since |
		preg_match_all('/^\| `([a-z0-9_]+)` \| [^|]+ \| ([^|]+) \|$/m', $section, $m, PREG_SET_ORDER);
		$this->assertNotEmpty($m, 'No ids found in the "Capability ids" table of FEATURES.md');
		$documented = [];
		$available = [];
		foreach ($m as [, $id, $since]) {
			$documented[] = $id;
			if (!str_starts_with(trim($since), 'with #')) { // "with #310": not merged yet
				$available[] = $id;
			}
		}

		foreach (Capabilities::FEATURES as $id) {
			$this->assertContains($id, $documented, "Capability id '$id' isn't documented in FEATURES.md");
		}
		sort($available);
		$published = Capabilities::FEATURES;
		sort($published);
		$this->assertSame($available, $published, 'FEATURES.md lists available ids that the capabilities don\'t publish (or the other way round)');
	}
}
