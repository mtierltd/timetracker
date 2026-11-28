<?php

namespace OCA\TimeTracker\Tests\Integration\Controller;

use OCA\TimeTracker\Db\Goal;
use OCA\TimeTracker\Db\GoalMapper;
use OCA\TimeTracker\Db\GoalSkipMapper;
use OCA\TimeTracker\Db\Project;
use OCA\TimeTracker\Db\ProjectMapper;
use OCA\TimeTracker\Db\UserToProject;
use OCA\TimeTracker\Db\UserToProjectMapper;
use OCA\TimeTracker\Db\WorkInterval;
use OCA\TimeTracker\Db\WorkIntervalMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * Goals with an unknown interval or without hours (add-goal accepted any
 * value before) are returned as invalid instead of breaking the goal list,
 * and add-goal only accepts valid goals.
 *
 * @group DB
 */
#[\PHPUnit\Framework\Attributes\Group('DB')]
class GoalsTest extends TestCase {
	use CreatesAjaxController;

	/** @var string */
	private $user;
	/** @var int[] */
	private $projectIds = [];
	/** @var int[] */
	private $goalIds = [];

	protected function setUp(): void {
		parent::setUp();
		$this->user = 'tt-goals-' . bin2hex(random_bytes(4));
	}

	protected function tearDown(): void {
		$db = \OCP\Server::get(IDBConnection::class);
		if ($this->goalIds) {
			$qb = $db->getQueryBuilder();
			$qb->delete('timetracker_goal_skip')
				->where($qb->expr()->in('goal_id', $qb->createNamedParameter($this->goalIds, IQueryBuilder::PARAM_INT_ARRAY)))
				->executeStatement();
		}
		foreach (['timetracker_goal', 'timetracker_work_interval', 'timetracker_user_to_project'] as $table) {
			$qb = $db->getQueryBuilder();
			$qb->delete($table)->where($qb->expr()->eq('user_uid', $qb->createNamedParameter($this->user)))->executeStatement();
		}
		if ($this->projectIds) {
			$qb = $db->getQueryBuilder();
			$qb->delete('timetracker_project')
				->where($qb->expr()->in('id', $qb->createNamedParameter($this->projectIds, IQueryBuilder::PARAM_INT_ARRAY)))
				->executeStatement();
		}
		parent::tearDown();
	}

	public function testIncompleteGoalsAreMarkedInvalid(): void {
		$project = $this->project('Goals project ' . $this->user);
		// 2 hours worked this week on the project. The entry starts a minute
		// ago, so that it's in the current week even just after it began.
		$wi = new WorkInterval();
		$wi->setUserUid($this->user);
		$wi->setName('Work');
		$wi->setStart(time() - 60);
		$wi->setDuration(7200);
		$wi->setRunning(0);
		$wi->setProjectId($project->getId());
		\OCP\Server::get(WorkIntervalMapper::class)->insert($wi);

		$other = $this->project('Goals project 2 ' . $this->user);
		$third = $this->project('Goals project 3 ' . $this->user);
		// listed newest first: invalid (first in the list), valid, invalid (after the valid one)
		$newest = $this->goal($other->getId(), 5, '', time());
		$valid = $this->goal($project->getId(), 10, 'Weekly', time() - 60);
		$oldest = $this->goal($third->getId(), 0, 'Daily', time() - 120);

		$goals = array_column($this->ajaxController($this->user)->getGoals()->getData()['Goals'], null, 'id');

		$this->assertFalse($goals[$valid]['invalid']);
		$this->assertEquals(2, $goals[$valid]['workedHoursCurrentPeriod']);
		$this->assertEquals(8, $goals[$valid]['remainingHours']);
		foreach ([$newest, $oldest] as $id) {
			$this->assertTrue($goals[$id]['invalid']);
			// not the valid goal's figures (the old code reused them for the goal after it)
			$this->assertSame([0, 0, 0, 0], [$goals[$id]['workedHoursCurrentPeriod'], $goals[$id]['debtHours'],
				$goals[$id]['remainingHours'], $goals[$id]['totalRemainingHours']]);
		}
	}

	public function testAddGoalOnlyAcceptsValidGoals(): void {
		$project = $this->project('Goals project ' . $this->user);
		$add = fn (array $params) => $this->ajaxController($this->user, ['projectId' => $project->getId()] + $params)->addGoal()->getData();

		foreach ([
			['hours' => 10, 'interval' => ''],
			['hours' => 10, 'interval' => 'Daily'],
			['hours' => 0, 'interval' => 'Weekly'],
			['hours' => 'many', 'interval' => 'Weekly'],
		] as $params) {
			$this->assertArrayHasKey('Error', $add($params), json_encode($params));
		}
		$this->assertNull(\OCP\Server::get(GoalMapper::class)->findByUserProject($this->user, $project->getId()));

		$goals = $add(['hours' => '12', 'interval' => 'Monthly'])['Goals'];
		$this->assertSame([12, 'Monthly', false], [$goals[0]['hours'], $goals[0]['interval'], $goals[0]['invalid']]);
	}

	/**
	 * A skipped week has no target: it adds no debt, and time worked in it
	 * still counts (#379).
	 */
	public function testSkippedWeeks(): void {
		$project = $this->project('Goals project ' . $this->user);
		// created 3 weeks ago: 3 past weeks with 10 h each and nothing worked
		$id = $this->goal($project->getId(), 10, 'Weekly', time() - 21 * 86400);
		$goal = fn () => array_column($this->ajaxController($this->user)->getGoals()->getData()['Goals'], null, 'id')[$id];
		$this->assertEquals(30, $goal()['debtHours']);

		$skip = fn (array $params) => $this->ajaxController($this->user, $params)->skipGoal($id)->getData();
		$twoWeeksAgo = date('Y-m-d', time() - 14 * 86400);
		$skip(['date' => $twoWeeksAgo]);
		$skip(['date' => $twoWeeksAgo]); // twice is the same as once
		$this->assertEquals(20, $goal()['debtHours']);
		$this->assertCount(1, $goal()['skippedPeriods']);

		// the current week: no target, no remaining hours
		$skip([]);
		$this->assertTrue($goal()['currentPeriodSkipped']);
		$this->assertEquals(0, $goal()['remainingHours']);
		$this->assertEquals(20, $goal()['totalRemainingHours']);

		$skip(['skip' => 'false']);
		$this->assertFalse($goal()['currentPeriodSkipped']);
		$this->assertEquals(10, $goal()['remainingHours']);

		// before the goal existed, invalid dates, and other users' goals
		$this->assertArrayHasKey('Error', $skip(['date' => date('Y-m-d', time() - 60 * 86400)]));
		$this->assertArrayHasKey('Error', $skip(['date' => '2026-02-30']));
		$this->assertSame(404, $this->ajaxController('someone-else')->skipGoal($id)->getStatus());

		// deleting the goal deletes its skipped weeks
		$this->ajaxController($this->user)->deleteGoal($id);
		$this->assertSame([], \OCP\Server::get(GoalSkipMapper::class)->findPeriodsForGoals([$id]));
	}

	private function project(string $name): Project {
		$p = new Project();
		$p->setName($name);
		$p->setColor('#000000');
		$p->setCreatedByUserUid($this->user);
		$p->setCreatedAt(time());
		$p = \OCP\Server::get(ProjectMapper::class)->insert($p);
		$this->projectIds[] = $p->getId();
		$link = new UserToProject();
		$link->setUserUid($this->user);
		$link->setProjectId($p->getId());
		$link->setAdmin(1);
		$link->setAccess(1);
		$link->setCreatedAt(time());
		\OCP\Server::get(UserToProjectMapper::class)->insert($link);
		return $p;
	}

	private function goal(int $projectId, int $hours, string $interval, int $createdAt): int {
		$g = new Goal();
		$g->setUserUid($this->user);
		$g->setProjectId($projectId);
		$g->setHours($hours);
		$g->setInterval($interval);
		$g->setCreatedAt($createdAt);
		$id = \OCP\Server::get(GoalMapper::class)->insert($g)->getId();
		$this->goalIds[] = $id;
		return $id;
	}
}
