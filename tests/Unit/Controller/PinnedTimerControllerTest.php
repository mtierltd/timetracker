<?php

namespace OCA\TimeTracker\Tests\Unit\Controller;

use OCA\TimeTracker\Controller\PinnedTimerController;
use OCA\TimeTracker\Db\PinnedTimer;
use OCA\TimeTracker\Db\PinnedTimerMapper;
use OCA\TimeTracker\Db\Project;
use OCA\TimeTracker\Db\ProjectMapper;
use OCA\TimeTracker\Db\Tag;
use OCA\TimeTracker\Db\TagMapper;
use OCA\TimeTracker\Db\WorkInterval;
use OCA\TimeTracker\Db\WorkIntervalMapper;
use OCA\TimeTracker\Db\WorkIntervalToTag;
use OCA\TimeTracker\Db\WorkIntervalToTagMapper;
use OCA\TimeTracker\Service\UserTimeZone;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PinnedTimerControllerTest extends TestCase {

	/** @var array<string, mixed> request parameters */
	private $params = [];
	/** @var PinnedTimer[] the user's pins */
	private $pins = [];
	/** @var PinnedTimerMapper&MockObject */
	private $pinMapper;
	/** @var WorkIntervalMapper&MockObject */
	private $wiMapper;
	/** @var WorkIntervalToTagMapper&MockObject */
	private $wiTagMapper;
	/** @var WorkInterval[] */
	private $running = [];
	/** @var WorkInterval[] */
	private $today = [];
	private $controller;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(fn ($key, $default = null) => $this->params[$key] ?? $default);

		$this->pinMapper = $this->createMock(PinnedTimerMapper::class);
		$this->pinMapper->method('findAllForUser')->willReturnCallback(fn () => $this->pins);
		$this->pinMapper->method('findForUser')->willReturnCallback(function ($id, $user) {
			foreach ($this->pins as $p) {
				if ($p->id === (int)$id && $user === 'alice') {
					return $p;
				}
			}
			return null;
		});
		$this->pinMapper->method('findByNameAndProject')->willReturnCallback(function ($user, $name, $projectId) {
			foreach ($this->pins as $p) {
				if ($p->name === $name && $p->projectId === $projectId) {
					return $p;
				}
			}
			return null;
		});
		$this->pinMapper->method('maxSortOrder')->willReturnCallback(fn () => count($this->pins) - 1);
		$this->pinMapper->method('insert')->willReturnCallback(function (PinnedTimer $p) {
			$p->setId(100 + count($this->pins));
			$this->pins[] = $p;
			return $p;
		});

		$projectMapper = $this->createMock(ProjectMapper::class);
		$projectMapper->method('findAll')->willReturn([
			$this->project(1, 'Website', 0),
			$this->project(2, 'Locked', 1),
		]);
		$tagMapper = $this->createMock(TagMapper::class);
		$tagMapper->method('findAll')->willReturn([$this->tag(10, 'frontend'), $this->tag(11, 'backend')]);
		$tagMapper->method('findAllAlowedForProject')->willReturn([$this->tag(11, 'backend')]);

		$this->wiMapper = $this->createMock(WorkIntervalMapper::class);
		$this->wiMapper->method('findAllRunning')->willReturnCallback(fn () => $this->running);
		$this->wiMapper->method('findLatestInterval')->willReturnCallback(fn () => $this->today);
		$this->wiMapper->method('insert')->willReturnCallback(function (WorkInterval $wi) {
			$wi->setId(500);
			return $wi;
		});
		$this->wiTagMapper = $this->createMock(WorkIntervalToTagMapper::class);

		$this->controller = new PinnedTimerController('timetracker', $request, $this->pinMapper, $projectMapper,
			$tagMapper, $this->wiMapper, $this->wiTagMapper, $this->createMock(UserTimeZone::class), 'alice');
	}

	public function testCreateKeepsOnlyAccessibleTags(): void {
		$this->params = ['name' => '  Coding ', 'projectId' => '1', 'tagIds' => '10,99,11'];
		$data = $this->controller->create()->getData();

		$this->assertCount(1, $data['PinnedTimers']);
		$pin = $data['PinnedTimers'][0];
		$this->assertSame('Coding', $pin['name']);
		$this->assertSame('Website', $pin['projectName']);
		$this->assertSame([10, 11], array_column($pin['tags'], 'id'));
		$this->assertSame('10,11', $this->pins[0]->tagIds);
		$this->assertSame(0, $this->pins[0]->sortOrder);
	}

	public function testCreateOnLockedProjectKeepsOnlyAllowedTags(): void {
		$this->params = ['name' => 'Coding', 'projectId' => 2, 'tagIds' => [10, 11]];
		$this->controller->create();
		$this->assertSame('11', $this->pins[0]->tagIds);
	}

	public function testCreateRejectsInvalidInput(): void {
		$this->params = ['name' => ' '];
		$this->assertArrayHasKey('Error', $this->controller->create()->getData());
		$this->params = ['name' => str_repeat('x', 256)];
		$this->assertArrayHasKey('Error', $this->controller->create()->getData());
		$this->params = ['name' => 'Coding', 'projectId' => 3];
		$this->assertSame(['Error' => 'Unknown project'], $this->controller->create()->getData());
		$this->assertSame([], $this->pins);
	}

	public function testCreateRejectsDuplicate(): void {
		$this->pins = [$this->pin(1, 'Coding', 1, '', 0)];
		$this->params = ['name' => 'Coding', 'projectId' => 1];
		$this->assertSame(['Error' => 'This timer is already pinned'], $this->controller->create()->getData());
		// same name without project is a different pin
		$this->params = ['name' => 'Coding'];
		$this->assertCount(2, $this->controller->create()->getData()['PinnedTimers']);
	}

	public function testUpdateChangesOnlyGivenFields(): void {
		$this->pins = [$this->pin(1, 'Coding', 1, '10', 0)];
		$this->pinMapper->expects($this->once())->method('update');
		$this->params = ['name' => 'Coding v2'];
		$pin = $this->controller->update(1)->getData()['PinnedTimers'][0];
		$this->assertSame(['Coding v2', 1, [10]], [$pin['name'], $pin['projectId'], array_column($pin['tags'], 'id')]);
	}

	public function testUpdateRejectsDuplicateAndForeignPins(): void {
		$this->pins = [$this->pin(1, 'Coding', 1, '', 0), $this->pin(2, 'Review', 1, '', 1)];
		$this->params = ['name' => 'Coding'];
		$this->assertSame(['Error' => 'This timer is already pinned'], $this->controller->update(2)->getData());
		$this->assertSame(['Error' => 'Pin not found'], $this->controller->update(99)->getData());
		$this->assertSame(['Error' => 'Pin not found'], $this->controller->destroy(99)->getData());
		$this->assertSame(['Error' => 'Pin not found'], $this->controller->start(99)->getData());
	}

	public function testReadHidesInaccessibleProjectAndDeletedTags(): void {
		$this->pins = [$this->pin(1, 'Coding', 42, '10,77', 0)];
		$pin = $this->controller->index()->getData()['PinnedTimers'][0];
		$this->assertNull($pin['projectId']);
		$this->assertSame([10], array_column($pin['tags'], 'id'));
	}

	public function testTodayDurationCountsFinishedEntriesOfSameNameAndProject(): void {
		$this->pins = [$this->pin(1, 'Coding', 1, '', 0), $this->pin(2, 'Coding', null, '', 1)];
		$this->today = [
			$this->interval('Coding', 1, 600, 0),
			$this->interval('Coding', 1, 300, 0),
			$this->interval('Coding', 1, 0, 1), // running: the client adds its live time
			$this->interval('Coding', null, 120, 0),
			$this->interval('Review', 1, 900, 0),
		];
		$this->params = ['tzoffset' => '-60'];
		$pins = $this->controller->index()->getData()['PinnedTimers'];
		$this->assertSame([900, 120], array_column($pins, 'todayDuration'));
	}

	public function testReorder(): void {
		$this->pins = [$this->pin(1, 'A', null, '', 0), $this->pin(2, 'B', null, '', 1), $this->pin(3, 'C', null, '', 2)];
		$this->params = ['ids' => '3,1'];
		$this->controller->reorder();
		$order = [];
		foreach ($this->pins as $p) {
			$order[$p->sortOrder] = $p->name;
		}
		ksort($order);
		$this->assertSame(['C', 'A', 'B'], array_values($order));
	}

	public function testStartStopsRunningTimerAndDoesNotCopyProject(): void {
		$this->pins = [$this->pin(1, 'Support', null, '11,77', 0)];
		$running = $this->interval('Coding', 1, null, 1);
		$running->setStart(time() - 60);
		$this->running = [$running];

		$updated = [];
		$this->wiMapper->method('update')->willReturnCallback(function ($wi) use (&$updated) {
			$updated[] = $wi;
			return $wi;
		});
		$links = [];
		$this->wiTagMapper->method('insert')->willReturnCallback(function (WorkIntervalToTag $l) use (&$links) {
			$links[] = $l->tagId;
			return $l;
		});

		$data = $this->controller->start(1)->getData();

		$this->assertSame([$running], $updated);
		$this->assertSame(0, $running->running);
		$this->assertGreaterThanOrEqual(60, $running->duration);
		$this->assertSame('Support', $data['WorkIntervals']->name);
		$this->assertNull($data['WorkIntervals']->projectId);
		$this->assertSame(1, $data['WorkIntervals']->running);
		$this->assertSame([11], $links);
	}

	private function pin(int $id, string $name, ?int $projectId, string $tagIds, int $sortOrder): PinnedTimer {
		$p = new PinnedTimer();
		$p->setId($id);
		$p->setUserUid('alice');
		$p->setName($name);
		$p->setProjectId($projectId);
		$p->setTagIds($tagIds);
		$p->setSortOrder($sortOrder);
		$p->setCreatedAt(0);
		return $p;
	}

	private function project(int $id, string $name, int $locked): Project {
		$p = new Project();
		$p->setId($id);
		$p->setName($name);
		$p->setColor('#123456');
		$p->setLocked($locked);
		return $p;
	}

	private function tag(int $id, string $name): Tag {
		$t = new Tag();
		$t->setId($id);
		$t->setName($name);
		return $t;
	}

	private function interval(string $name, ?int $projectId, ?int $duration, int $running): WorkInterval {
		$wi = new WorkInterval();
		$wi->setName($name);
		$wi->setProjectId($projectId);
		$wi->setDuration($duration);
		$wi->setRunning($running);
		$wi->setStart(time());
		return $wi;
	}
}
