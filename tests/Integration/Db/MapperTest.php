<?php

namespace OCA\TimeTracker\Tests\Integration\Db;

use OCA\TimeTracker\Db\Client;
use OCA\TimeTracker\Db\ClientMapper;
use OCA\TimeTracker\Db\Goal;
use OCA\TimeTracker\Db\GoalMapper;
use OCA\TimeTracker\Db\Project;
use OCA\TimeTracker\Db\ProjectMapper;
use OCA\TimeTracker\Db\Tag;
use OCA\TimeTracker\Db\TagMapper;
use OCA\TimeTracker\Db\Timeline;
use OCA\TimeTracker\Db\TimelineEntry;
use OCA\TimeTracker\Db\TimelineEntryMapper;
use OCA\TimeTracker\Db\TimelineMapper;
use OCA\TimeTracker\Db\UserToClient;
use OCA\TimeTracker\Db\UserToClientMapper;
use OCA\TimeTracker\Db\UserToProject;
use OCA\TimeTracker\Db\UserToProjectMapper;
use OCA\TimeTracker\Db\WorkInterval;
use OCA\TimeTracker\Db\WorkIntervalMapper;
use OCA\TimeTracker\Db\WorkIntervalToTag;
use OCA\TimeTracker\Db\WorkIntervalToTagMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * Every mapper method the app uses, against the real database (SQLite,
 * MySQL/MariaDB or PostgreSQL).
 *
 * Each test creates its own rows with unique names and user ids and removes
 * them afterwards, so existing data in the database doesn't matter.
 *
 * @group DB
 */
#[\PHPUnit\Framework\Attributes\Group('DB')]
class MapperTest extends TestCase {

	private const TABLES = [
		'timetracker_client', 'timetracker_project', 'timetracker_user_to_client', 'timetracker_user_to_project',
		'timetracker_tag', 'timetracker_work_interval', 'timetracker_workint_to_tag', 'timetracker_goal',
		'timetracker_timeline', 'timetracker_timeline_entry',
	];

	/** @var IDBConnection */
	private $db;
	/** @var string */
	private $prefix;
	/** @var array<string, int[]> table => ids to delete afterwards */
	private $created = [];

	protected function setUp(): void {
		parent::setUp();
		$this->db = \OCP\Server::get(IDBConnection::class);
		$this->prefix = 'tt-it-' . bin2hex(random_bytes(4)) . '-';
	}

	protected function tearDown(): void {
		if (!empty($this->created['timetracker_project'])) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('timetracker_lpa_tags')
				->where($qb->expr()->in('project_id', $qb->createNamedParameter($this->created['timetracker_project'], IQueryBuilder::PARAM_INT_ARRAY)))
				->executeStatement();
		}
		foreach (self::TABLES as $table) {
			if (!empty($this->created[$table])) {
				$qb = $this->db->getQueryBuilder();
				$qb->delete($table)
					->where($qb->expr()->in('id', $qb->createNamedParameter($this->created[$table], IQueryBuilder::PARAM_INT_ARRAY)))
					->executeStatement();
			}
		}
		parent::tearDown();
	}

	// --- clients ---

	public function testClients(): void {
		$mapper = \OCP\Server::get(ClientMapper::class);
		$alice = $this->user('alice');
		$beta = $this->client('Beta ' . $this->prefix, $alice);
		$alpha = $this->client('Alpha ' . $this->prefix, $alice);
		$this->client('Gamma ' . $this->prefix, $this->user('bob'));

		$this->assertGreaterThan(0, $alpha->getId());
		$this->assertSame('Alpha ' . $this->prefix, $mapper->find($alpha->getId())->getName());
		$this->assertSame($beta->getId(), $mapper->findByName(strtolower('Beta ' . $this->prefix))->getId());
		$this->assertNull($mapper->findByName('Nobody ' . $this->prefix));
		$this->assertSame([$alpha->getId(), $beta->getId()], $this->ids($mapper->findAll($alice)));
		$this->assertSame([$beta->getId()], $this->ids($mapper->searchByName($alice, 'BETA ' . strtoupper($this->prefix))));

		$alpha->setName('Renamed ' . $this->prefix);
		$mapper->update($alpha);
		$this->assertSame('Renamed ' . $this->prefix, $mapper->find($alpha->getId())->getName());

		$this->expectException(DoesNotExistException::class);
		$mapper->find(-1);
	}

	public function testUserToClient(): void {
		$mapper = \OCP\Server::get(UserToClientMapper::class);
		$alice = $this->user('alice');
		$client = $this->client('Client ' . $this->prefix, $alice);

		$link = $mapper->findForUserAndClient($alice, $client);
		$this->assertSame($client->getId(), $link->getClientId());
		$this->assertNull($mapper->findForUserAndClient($this->user('bob'), $client));

		$mapper->delete($link);
		$this->assertNull($mapper->findForUserAndClient($alice, $client));
	}

	// --- projects ---

	public function testProjects(): void {
		$mapper = \OCP\Server::get(ProjectMapper::class);
		$alice = $this->user('alice');
		$client = $this->client('Client ' . $this->prefix, $alice);
		$beta = $this->project('Beta ' . $this->prefix, $alice, $client->getId());
		$alpha = $this->project('Alpha ' . $this->prefix, $alice);
		$archived = $this->project('Archived ' . $this->prefix, $alice);
		$archived->setArchived(1);
		$mapper->update($archived);

		$found = $mapper->find($beta->getId());
		$this->assertSame('Beta ' . $this->prefix, $found->getName());
		$this->assertSame($client->getId(), $found->getClientId());
		$this->assertSame('#ffffff', $found->getColor());
		$this->assertSame($alpha->getId(), $mapper->findByName(strtolower('Alpha ' . $this->prefix))->getId());
		$this->assertNull($mapper->findByName('Nobody ' . $this->prefix));
		$this->assertSame([$beta->getId()], $this->ids($mapper->searchByName($alice, 'beta ' . $this->prefix)));

		$this->assertSame([$alpha->getId(), $beta->getId()], $this->ids($mapper->findAll($alice)));
		$this->assertSame([$alpha->getId(), $archived->getId(), $beta->getId()], $this->ids($mapper->findAll($alice, 1)));
		$this->assertSame([], $this->ids($mapper->findAll($this->user('bob'))));

		$ours = [$alpha->getId(), $archived->getId(), $beta->getId()];
		$this->assertSame([$alpha->getId(), $beta->getId()], array_values(array_intersect($this->ids($mapper->findAllAdmin()), $ours)));
		$this->assertSame($ours, array_values(array_intersect($this->ids($mapper->findAllAdmin(1)), $ours)));

		$mapper->delete($alpha->getId());
		$this->expectException(DoesNotExistException::class);
		$mapper->find($alpha->getId());
	}

	public function testUserToProject(): void {
		$mapper = \OCP\Server::get(UserToProjectMapper::class);
		$alice = $this->user('alice');
		$bob = $this->user('bob');
		$project = $this->project('Project ' . $this->prefix, $alice);
		$this->link(UserToProject::class, UserToProjectMapper::class, 'setProjectId', $bob, $project->getId());

		$this->assertSame($project->getId(), $mapper->findForUserAndProject($alice, $project)->getProjectId());
		$this->assertNull($mapper->findForUserAndProject($this->user('carol'), $project));
		$users = array_map(fn ($l) => $l->getUserUid(), $mapper->findAllForProject($project->getId()));
		sort($users);
		$this->assertSame([$alice, $bob], $users);

		$mapper->delete($mapper->findForUserAndProject($bob, $project));
		$this->assertCount(1, $mapper->findAllForProject($project->getId()));
		$mapper->deleteAllForProject($project->getId());
		$this->assertSame([], $mapper->findAllForProject($project->getId()));
	}

	// --- tags ---

	public function testTags(): void {
		$mapper = \OCP\Server::get(TagMapper::class);
		$alice = $this->user('alice');
		$beta = $this->tag('Beta', $alice);
		$alpha = $this->tag('Alpha', $alice);
		$this->tag('Alpha', $this->user('bob'));

		$this->assertSame('Alpha', $mapper->find($alpha->getId())->getName());
		$this->assertNull($mapper->find(-1));
		$this->assertSame($alpha->getId(), $mapper->findByNameUser('ALPHA', $alice)->getId());
		$this->assertNull($mapper->findByNameUser('Gamma', $alice));
		$this->assertSame([$alpha->getId(), $beta->getId()], $this->ids($mapper->findAll($alice)));

		$beta->setName('Zeta');
		$mapper->update($beta);
		$this->assertSame('Zeta', $mapper->find($beta->getId())->getName());

		$mapper->delete($beta);
		$this->assertNull($mapper->find($beta->getId()));
	}

	public function testTagsAllowedForProject(): void {
		$mapper = \OCP\Server::get(TagMapper::class);
		$alice = $this->user('alice');
		$project = $this->project('Project ' . $this->prefix, $alice);
		$b = $this->tag('B', $alice);
		$a = $this->tag('A', $alice);
		$c = $this->tag('C', $alice);

		$before = time();
		$mapper->allowedTags($project->getId(), [$b->getId(), '', $a->getId()]);
		$this->assertSame([$a->getId(), $b->getId()], $this->ids($mapper->findAllAlowedForProject($project->getId())));

		$qb = $this->db->getQueryBuilder();
		$createdAt = $qb->select('created_at')->from('timetracker_lpa_tags')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($project->getId(), IQueryBuilder::PARAM_INT)))
			->executeQuery()->fetchAll(\PDO::FETCH_COLUMN);
		foreach ($createdAt as $time) {
			$this->assertGreaterThanOrEqual($before - 5, (int)$time);
			$this->assertLessThanOrEqual(time() + 5, (int)$time);
		}

		// replaces the previous list
		$mapper->allowedTags($project->getId(), [$c->getId()]);
		$this->assertSame([$c->getId()], $this->ids($mapper->findAllAlowedForProject($project->getId())));
		$mapper->allowedTags($project->getId(), []);
		$this->assertSame([], $mapper->findAllAlowedForProject($project->getId()));
	}

	// --- work intervals ---

	public function testWorkIntervals(): void {
		$mapper = \OCP\Server::get(WorkIntervalMapper::class);
		$alice = $this->user('alice');
		$project = $this->project('Project ' . $this->prefix, $alice);
		$old = $this->interval($alice, 'Coding', 1000, $project->getId());
		$new = $this->interval($alice, 'Coding', 2000, $project->getId());
		$other = $this->interval($alice, 'Review', 3000);
		$running = $this->interval($alice, 'Running', 4000, null, true);
		$this->interval($this->user('bob'), 'Coding', 5000, null, true);

		$found = $mapper->find($new->getId());
		$this->assertSame('Coding', $found->getName());
		$this->assertSame(2000, $found->getStart());
		$this->assertSame($project->getId(), $found->getProjectId());
		$this->assertSame($new->getId(), $mapper->findLatestByName($alice, 'Coding')->getId());
		$this->assertNull($mapper->findLatestByName($alice, 'Nothing'));

		$this->assertSame([$running->getId()], $this->ids($mapper->findAllRunning($alice)));
		$this->assertEqualsCanonicalizing([$new->getId(), $old->getId()], $this->ids($mapper->findAllForProject($project->getId())));

		// newest first, bounds excluded, limit and offset
		$this->assertSame([$running->getId(), $other->getId(), $new->getId(), $old->getId()], $this->ids($mapper->findLatestInterval($alice, null, null)));
		$this->assertSame([$other->getId(), $new->getId()], $this->ids($mapper->findLatestInterval($alice, 1000, 4000)));
		$this->assertSame([$other->getId()], $this->ids($mapper->findLatestInterval($alice, null, null, 1, 1)));

		$running->setRunning(0);
		$running->setDuration(60);
		$mapper->update($running);
		$this->assertSame([], $mapper->findAllRunning($alice));
		$this->assertSame(60, $mapper->find($running->getId())->getDuration());

		$mapper->delete($other);
		$mapper->deleteAllForProject($project->getId());
		$this->assertSame([$running->getId()], $this->ids($mapper->findLatestInterval($alice, null, null)));
	}

	public function testWorkIntervalTags(): void {
		$mapper = \OCP\Server::get(WorkIntervalToTagMapper::class);
		$alice = $this->user('alice');
		$one = $this->interval($alice, 'One', 1000);
		$two = $this->interval($alice, 'Two', 2000);
		$a = $this->tag('A', $alice);
		$b = $this->tag('B', $alice);
		foreach ([[$one, $a], [$one, $b], [$two, $a]] as [$interval, $tag]) {
			$link = new WorkIntervalToTag();
			$link->setWorkIntervalId($interval->getId());
			$link->setTagId($tag->getId());
			$link->setCreatedAt(time());
			$this->created['timetracker_workint_to_tag'][] = $mapper->insert($link)->getId();
		}

		$tagsOf = fn ($interval) => array_map(fn ($l) => $l->getTagId(), $mapper->findAllForWorkInterval($interval->getId()));
		$this->assertEqualsCanonicalizing([$a->getId(), $b->getId()], $tagsOf($one));

		$mapper->deleteAllForTag($a->getId());
		$this->assertSame([$b->getId()], $tagsOf($one));
		$this->assertSame([], $tagsOf($two));

		$mapper->deleteAllForWorkInterval($one->getId());
		$this->assertSame([], $tagsOf($one));
	}

	// --- goals ---

	public function testGoals(): void {
		$mapper = \OCP\Server::get(GoalMapper::class);
		$alice = $this->user('alice');
		$a = $this->project('Project A ' . $this->prefix, $alice);
		$b = $this->project('Project B ' . $this->prefix, $alice);
		$older = $this->goal($alice, $a->getId(), 'Weekly', 1000);
		$newer = $this->goal($alice, $b->getId(), 'Monthly', 2000);

		$found = $mapper->find($older->getId());
		$this->assertSame('Weekly', $found->getInterval());
		$this->assertSame(10, $found->getHours());
		$this->assertSame($newer->getId(), $mapper->findByUserProject($alice, $b->getId())->getId());
		$this->assertNull($mapper->findByUserProject($this->user('bob'), $b->getId()));

		$all = $mapper->findAll($alice);
		$this->assertSame([$newer->getId(), $older->getId()], $this->ids($all));
		$this->assertSame(['Project B ' . $this->prefix, 'Project A ' . $this->prefix], array_map(fn ($g) => $g->getProjectName(), $all));

		$mapper->delete($older);
		$this->assertSame([$newer->getId()], $this->ids($mapper->findAll($alice)));
	}

	// --- timelines ---

	public function testTimelines(): void {
		$mapper = \OCP\Server::get(TimelineMapper::class);
		$entryMapper = \OCP\Server::get(TimelineEntryMapper::class);
		$alice = $this->user('alice');
		$timeline = new Timeline();
		$timeline->setUserUid($alice);
		$timeline->setStatus('Pending');
		$timeline->setGroup1('project');
		$timeline->setTimeGroup('week');
		$timeline->setFilterProjects('1,2');
		$timeline->setTimeInterval('2026-01-01 - 2026-01-31');
		$timeline->setTotalDuration('3600');
		$timeline->setCreatedAt(time());
		$timeline = $mapper->insert($timeline);
		$this->created['timetracker_timeline'][] = $timeline->getId();

		foreach (['First', 'Second'] as $i => $name) {
			$entry = new TimelineEntry();
			$entry->setTimelineId((string)$timeline->getId());
			$entry->setUserUid($alice);
			$entry->setName($name);
			$entry->setProjectName('Project');
			$entry->setClientName('Client');
			$entry->setTimeInterval('2026-W0' . ($i + 1));
			$entry->setTotalDuration('1800');
			$entry->setCost(25);
			$entry->setCreatedAt(time());
			$this->created['timetracker_timeline_entry'][] = $entryMapper->insert($entry)->getId();
		}

		$found = $mapper->find($timeline->getId());
		$this->assertSame('Pending', $found->getStatus());
		$this->assertSame('1,2', $found->getFilterProjects());
		$this->assertNull($found->getGroup2());
		$this->assertSame([$timeline->getId()], $this->ids($mapper->findAll($alice)));
		$this->assertContains($timeline->getId(), $this->ids($mapper->findLatest()));
		$this->assertLessThanOrEqual(100, count($mapper->findLatest()));

		$entries = $entryMapper->findTimelineEntries($timeline->getId());
		$this->assertEqualsCanonicalizing(['First', 'Second'], array_map(fn ($e) => $e->getName(), $entries));
		$this->assertSame(25, $entries[0]->getCost());

		$found->setStatus('Sent');
		$mapper->update($found);
		$this->assertSame('Sent', $mapper->find($timeline->getId())->getStatus());

		$mapper->delete($found);
		$this->assertSame([], $mapper->findAll($alice));
	}

	// --- helpers ---

	private function user(string $name): string {
		return $this->prefix . $name;
	}

	/**
	 * @param \OCP\AppFramework\Db\Entity[] $entities
	 * @return int[]
	 */
	private function ids(array $entities): array {
		return array_map(fn ($e) => $e->getId(), $entities);
	}

	private function client(string $name, string $user): Client {
		$client = new Client();
		$client->setName($name);
		$client->setCreatedAt(time());
		$client = \OCP\Server::get(ClientMapper::class)->insert($client);
		$this->created['timetracker_client'][] = $client->getId();
		$this->link(UserToClient::class, UserToClientMapper::class, 'setClientId', $user, $client->getId());
		return $client;
	}

	private function project(string $name, string $user, ?int $clientId = null): Project {
		$project = new Project();
		$project->setName($name);
		if ($clientId !== null) {
			$project->setClientId($clientId);
		}
		$project->setCreatedByUserUid($user);
		$project->setCreatedAt(time());
		$project = \OCP\Server::get(ProjectMapper::class)->insert($project);
		$this->created['timetracker_project'][] = $project->getId();
		$this->link(UserToProject::class, UserToProjectMapper::class, 'setProjectId', $user, $project->getId());
		return $project;
	}

	/**
	 * A user_to_client or user_to_project row.
	 */
	private function link(string $class, string $mapperClass, string $setter, string $user, int $id): void {
		$link = new $class();
		$link->setUserUid($user);
		$link->$setter($id);
		$link->setAdmin(0);
		$link->setAccess(1);
		$link->setCreatedAt(time());
		$link = \OCP\Server::get($mapperClass)->insert($link);
		$this->created[$class === UserToClient::class ? 'timetracker_user_to_client' : 'timetracker_user_to_project'][] = $link->getId();
	}

	private function tag(string $name, string $user): Tag {
		$tag = new Tag();
		$tag->setName($name);
		$tag->setUserUid($user);
		$tag->setCreatedAt(time());
		$tag = \OCP\Server::get(TagMapper::class)->insert($tag);
		$this->created['timetracker_tag'][] = $tag->getId();
		return $tag;
	}

	private function interval(string $user, string $name, int $start, ?int $projectId = null, bool $running = false): WorkInterval {
		$interval = new WorkInterval();
		$interval->setName($name);
		$interval->setUserUid($user);
		$interval->setStart($start);
		if ($projectId !== null) {
			$interval->setProjectId($projectId);
		}
		$interval->setRunning($running ? 1 : 0);
		if (!$running) {
			$interval->setDuration(100);
		}
		$interval = \OCP\Server::get(WorkIntervalMapper::class)->insert($interval);
		$this->created['timetracker_work_interval'][] = $interval->getId();
		return $interval;
	}

	private function goal(string $user, int $projectId, string $interval, int $createdAt): Goal {
		$goal = new Goal();
		$goal->setUserUid($user);
		$goal->setProjectId($projectId);
		$goal->setHours(10);
		$goal->setInterval($interval);
		$goal->setCreatedAt($createdAt);
		$goal = \OCP\Server::get(GoalMapper::class)->insert($goal);
		$this->created['timetracker_goal'][] = $goal->getId();
		return $goal;
	}
}
