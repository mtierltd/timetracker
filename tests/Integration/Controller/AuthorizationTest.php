<?php

namespace OCA\TimeTracker\Tests\Integration\Controller;

use OCA\TimeTracker\Controller\AjaxController;
use OCA\TimeTracker\Db\Client;
use OCA\TimeTracker\Db\ClientMapper;
use OCA\TimeTracker\Db\GoalMapper;
use OCA\TimeTracker\Db\GoalSkipMapper;
use OCA\TimeTracker\Db\Project;
use OCA\TimeTracker\Db\ProjectMapper;
use OCA\TimeTracker\Db\ReportItemMapper;
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
use OCA\TimeTracker\Db\WorkIntervalToTagMapper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Mail\IAttachment;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use Test\TestCase;

/**
 * Users can only read and change their own data (or data shared with them);
 * other users' IDs answer 404 and change nothing. Admins keep their rights.
 *
 * Two non-admin users with unique ids (alice, bob) own the test data; "admin"
 * is the instance's admin account.
 *
 * @group DB
 */
#[\PHPUnit\Framework\Attributes\Group('DB')]
class AuthorizationTest extends TestCase {

	private const TABLES = [
		'timetracker_work_interval', 'timetracker_workint_to_tag', 'timetracker_tag', 'timetracker_client',
		'timetracker_user_to_client', 'timetracker_project', 'timetracker_user_to_project',
		'timetracker_timeline', 'timetracker_timeline_entry', 'timetracker_goal',
	];

	/** @var IDBConnection */
	private $db;
	/** @var string */
	private $alice;
	/** @var string */
	private $bob;
	/** @var array<string, int[]> */
	private $created = [];
	/** @var IMailer&\PHPUnit\Framework\MockObject\MockObject */
	private $mailer;
	/** @var IMessage[] sent messages */
	private $sent = [];
	/** @var string[] temporary files with JSON request bodies */
	private $jsonBodies = [];

	protected function setUp(): void {
		parent::setUp();
		$this->db = \OCP\Server::get(IDBConnection::class);
		$prefix = 'tt-sec-' . bin2hex(random_bytes(4)) . '-';
		$this->alice = $prefix . 'alice';
		$this->bob = $prefix . 'bob';
		$this->mailer = $this->createMock(IMailer::class);
		$this->mailer->method('validateMailAddress')->willReturnCallback(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL) !== false);
		$this->mailer->method('createMessage')->willReturnCallback(fn () => $this->createMock(IMessage::class));
		$this->mailer->method('createAttachment')->willReturn($this->createMock(IAttachment::class));
		$this->mailer->method('send')->willReturnCallback(function ($m) {
			$this->sent[] = $m;
			return [];
		});
	}

	protected function tearDown(): void {
		foreach ($this->jsonBodies as $file) {
			@unlink($file);
		}
		if (!empty($this->created['timetracker_work_interval'])) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('timetracker_workint_to_tag')
				->where($qb->expr()->in('work_interval_id', $qb->createNamedParameter($this->created['timetracker_work_interval'], IQueryBuilder::PARAM_INT_ARRAY)))
				->executeStatement();
		}
		foreach ([['timetracker_work_interval', 'user_uid'], ['timetracker_tag', 'user_uid'], ['timetracker_user_to_client', 'user_uid'],
			['timetracker_user_to_project', 'user_uid'], ['timetracker_timeline', 'user_uid'], ['timetracker_timeline_entry', 'user_uid'],
			['timetracker_goal', 'user_uid']] as [$table, $column]) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete($table)
				->where($qb->expr()->in($column, $qb->createNamedParameter([$this->alice, $this->bob], IQueryBuilder::PARAM_STR_ARRAY)))
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

	// --- time entries ---

	public function testOtherUsersEntriesCannotBeChangedOrDeleted(): void {
		$entry = $this->entry($this->alice, 'Alice work');

		$this->assertNotFound($this->as($this->bob, ['name' => 'by bob'])->updateWorkInterval($entry->getId()));
		$this->assertNotFound($this->as($this->bob, ['cost' => '99'])->addCost($entry->getId()));
		$this->assertNotFound($this->as($this->bob)->deleteWorkInterval($entry->getId()));

		$stored = \OCP\Server::get(WorkIntervalMapper::class)->find($entry->getId());
		$this->assertSame('Alice work', $stored->getName());
		$this->assertNull($stored->getCost());

		// the owner still can
		$this->as($this->alice, ['name' => 'renamed'])->updateWorkInterval($entry->getId());
		$this->assertSame('renamed', \OCP\Server::get(WorkIntervalMapper::class)->find($entry->getId())->getName());
		$this->as($this->alice)->deleteWorkInterval($entry->getId());
		$this->expectException(\OCP\AppFramework\Db\DoesNotExistException::class);
		\OCP\Server::get(WorkIntervalMapper::class)->find($entry->getId());
	}

	public function testEntriesOnlyGetUsableProjectsAndOwnTags(): void {
		$aliceProject = $this->project('Alice only', $this->alice);
		$aliceTag = $this->tag('alice-tag', $this->alice);
		$bobTag = $this->tag('bob-tag', $this->bob);
		$bobEntry = $this->entry($this->bob, 'Bob work');

		$this->assertNotFound($this->as($this->bob, ['projectId' => $aliceProject->getId()])->updateWorkInterval($bobEntry->getId()));
		$this->assertNotFound($this->as($this->bob, ['projectId' => $aliceProject->getId()])->startTimer('Sneaky'));
		$this->assertNotFound($this->as($this->bob, ['name' => 'x', 'projectId' => $aliceProject->getId(),
			'start' => '01/01/26 10:00', 'end' => '01/01/26 11:00', 'tzoffset' => 0])->addWorkInterval());

		// foreign tag ids are dropped, own ones kept
		$this->as($this->bob, ['tagId' => $aliceTag->getId() . ',' . $bobTag->getId()])->updateWorkInterval($bobEntry->getId());
		$this->assertSame([$bobTag->getId()], $this->tagIdsOf($bobEntry->getId()));

		$this->as($this->bob, ['tags' => $aliceTag->getId() . ',' . $bobTag->getId()])->startTimer('Tagged');
		$started = \OCP\Server::get(WorkIntervalMapper::class)->findLatestByName($this->bob, 'Tagged');
		$this->created['timetracker_work_interval'][] = $started->getId();
		$this->assertSame([$bobTag->getId()], $this->tagIdsOf($started->getId()));

		// a manual entry gets its tags (this used an undefined id before)
		$this->as($this->bob, ['name' => 'Manual', 'tagId' => (string)$bobTag->getId(),
			'start' => '01/01/26 10:00', 'end' => '01/01/26 11:00', 'tzoffset' => 0])->addWorkInterval();
		$manual = \OCP\Server::get(WorkIntervalMapper::class)->findLatestByName($this->bob, 'Manual');
		$this->created['timetracker_work_interval'][] = $manual->getId();
		$this->assertSame([$bobTag->getId()], $this->tagIdsOf($manual->getId()));

		// get-tags with someone else's entry falls back to the caller's own tags
		$aliceEntry = $this->entry($this->alice, 'Alice work', $aliceProject->getId());
		$tags = $this->as($this->bob, ['workItem' => $aliceEntry->getId()])->getTags()->getData()['Tags'];
		$this->assertSame(['bob-tag'], array_column($tags, 'name'));
	}

	// --- reports and timelines ---

	public function testReportsOnlyCoverTheCaller(): void {
		$this->entry($this->alice, 'Alice private');
		$params = ['name' => $this->alice, 'from' => 0, 'to' => time() + 3600, 'group1' => 'none', 'group2' => 'none', 'timegroup' => 'none'];
		$items = $this->as($this->bob, $params)->getReport()->getData()['items'];
		$this->assertSame([], array_filter($items, fn ($i) => $i['userUid'] === $this->alice));

		$own = $this->as($this->alice, $params)->getReport()->getData()['items'];
		$this->assertContains('Alice private', array_column($own, 'name'));
	}

	public function testOtherUsersTimelinesAreNotAccessible(): void {
		$timeline = $this->timeline($this->alice);

		$this->assertNotFound($this->as($this->bob)->downloadTimeline($timeline->getId()));
		$this->assertNotFound($this->as($this->bob, ['email' => 'bob@example.com', 'subject' => 's', 'content' => 'c'])->emailTimeline($timeline->getId()));
		$this->assertSame([], $this->sent);

		$this->assertSame(Http::STATUS_FORBIDDEN, $this->as($this->bob, ['status' => 'approved'])->editTimeline($timeline->getId())->getStatus());
		$this->assertSame('pending', \OCP\Server::get(TimelineMapper::class)->find($timeline->getId())->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $this->as($this->bob)->getTimelinesAdmin()->getStatus());

		// admins keep their rights
		$this->as('admin', ['status' => 'approved'])->editTimeline($timeline->getId());
		$this->assertSame('approved', \OCP\Server::get(TimelineMapper::class)->find($timeline->getId())->getStatus());
		$this->assertContains($timeline->getId(), array_column($this->as('admin')->getTimelinesAdmin()->getData()['Timelines'], 'id'));
	}

	public function testEmailTimelineValidatesRecipients(): void {
		$timeline = $this->timeline($this->alice);
		$mail = fn ($email) => $this->as($this->alice, ['email' => $email, 'subject' => 'Timeline', 'content' => 'Hi'])->emailTimeline($timeline->getId());

		$this->assertSame(Http::STATUS_BAD_REQUEST, $mail('not-an-address')->getStatus());
		$this->assertSame(Http::STATUS_BAD_REQUEST, $mail(implode(';', array_map(fn ($i) => "u$i@example.com", range(1, 11))))->getStatus());
		$this->assertSame(Http::STATUS_BAD_REQUEST, $mail('')->getStatus());
		$this->assertSame([], $this->sent);

		$this->assertSame(Http::STATUS_OK, $mail('a@example.com; b@example.com')->getStatus());
		$this->assertCount(1, $this->sent);
	}

	public function testCsvCellsCannotStartFormulas(): void {
		$this->assertSame("'=1+1", AjaxController::csvCell('=1+1'));
		$this->assertSame("'+cmd", AjaxController::csvCell('+cmd'));
		$this->assertSame("'-2", AjaxController::csvCell('-2'));
		$this->assertSame("'@SUM(A1)", AjaxController::csvCell('@SUM(A1)'));
		$this->assertSame('Coding', AjaxController::csvCell('Coding'));
		$this->assertSame('', AjaxController::csvCell(''));
		$this->assertSame(5, AjaxController::csvCell(5));
	}

	// --- tags, clients, projects, goals ---

	public function testOtherUsersTagsCannotBeChangedOrDeleted(): void {
		$tag = $this->tag('alice-tag', $this->alice);
		$this->assertNotFound($this->as($this->bob, ['name' => 'bob was here'])->editTag($tag->getId()));
		$this->assertNotFound($this->as($this->bob)->deleteTag($tag->getId()));
		$this->assertSame('alice-tag', \OCP\Server::get(TagMapper::class)->find($tag->getId())->getName());

		$this->as($this->alice, ['name' => 'renamed'])->editTag($tag->getId());
		$this->assertSame('renamed', \OCP\Server::get(TagMapper::class)->find($tag->getId())->getName());
	}

	public function testClientsCanOnlyBeEditedByLinkedUsers(): void {
		$client = $this->client('Alice client ' . $this->alice, $this->alice);
		$this->assertNotFound($this->as($this->bob, ['name' => 'by bob ' . $this->bob])->editClient($client->getId()));
		$this->assertSame('Alice client ' . $this->alice, \OCP\Server::get(ClientMapper::class)->find($client->getId())->getName());

		$this->as($this->alice, ['name' => 'Renamed ' . $this->alice])->editClient($client->getId());
		$this->assertSame('Renamed ' . $this->alice, \OCP\Server::get(ClientMapper::class)->find($client->getId())->getName());
	}

	public function testProjectsCanOnlyBeEditedByCreatorLinkedUsersOrAdmins(): void {
		$project = $this->project('Alice project ' . $this->alice, $this->alice);
		$bobClient = $this->client('Bob client ' . $this->bob, $this->bob);
		$aliceClient = $this->client('Alice client ' . $this->alice, $this->alice);

		$this->assertNotFound($this->as($this->bob, ['name' => 'Bob owns this ' . $this->bob, 'color' => '#000000'])->editProject($project->getId()));
		$stored = \OCP\Server::get(ProjectMapper::class)->find($project->getId());
		$this->assertSame('Alice project ' . $this->alice, $stored->getName());

		// the creator can, but not with someone else's client
		$this->assertNotFound($this->as($this->alice, ['clientId' => $bobClient->getId()])->editProject($project->getId()));
		$this->as($this->alice, ['name' => 'Renamed ' . $this->alice, 'clientId' => $aliceClient->getId()])->editProject($project->getId());
		$stored = \OCP\Server::get(ProjectMapper::class)->find($project->getId());
		$this->assertSame(['Renamed ' . $this->alice, $aliceClient->getId()], [$stored->getName(), $stored->getClientId()]);
	}

	public function testGoalsNeedAUsableProject(): void {
		$project = $this->project('Alice goal project ' . $this->alice, $this->alice);
		$this->assertNotFound($this->as($this->bob, ['projectId' => $project->getId(), 'hours' => 10, 'interval' => 'Weekly'])->addGoal());
		$this->assertNull(\OCP\Server::get(GoalMapper::class)->findByUserProject($this->bob, $project->getId()));

		$this->as($this->alice, ['projectId' => $project->getId(), 'hours' => 10, 'interval' => 'Weekly'])->addGoal();
		$this->assertNotNull(\OCP\Server::get(GoalMapper::class)->findByUserProject($this->alice, $project->getId()));
	}

	public function testAddGoalFromJsonBody(): void {
		// the web app sends JSON; addGoal() used to read only form parameters
		$project = $this->project('Alice JSON goal project ' . $this->alice, $this->alice);
		$this->as($this->alice, ['projectId' => $project->getId(), 'hours' => '12', 'interval' => 'Monthly'], true)->addGoal();

		$goal = \OCP\Server::get(GoalMapper::class)->findByUserProject($this->alice, $project->getId());
		$this->assertNotNull($goal);
		$this->assertSame([12, 'Monthly'], [(int)$goal->getHours(), $goal->getInterval()]);
	}

	// --- helpers ---

	/**
	 * The controller as $user, with these request parameters.
	 */
	private function as(string $user, array $params = [], bool $json = false): AjaxController {
		// Nextcloud's real request class: $request->name resolves like in production.
		// $json sends the parameters as a JSON body, like the web app does.
		if ($json) {
			$stream = tempnam(sys_get_temp_dir(), 'tt-json-');
			file_put_contents($stream, json_encode($params));
			$this->jsonBodies[] = $stream;
			$vars = ['method' => 'POST', 'server' => ['CONTENT_TYPE' => 'application/json', 'REQUEST_METHOD' => 'POST']];
		} else {
			$stream = 'php://input';
			$vars = ['post' => $params, 'method' => 'POST'];
		}
		$request = new \OC\AppFramework\Http\Request(
			$vars,
			\OCP\Server::get(\OCP\IRequestId::class),
			\OCP\Server::get(\OCP\IConfig::class),
			null,
			$stream
		);

		return new AjaxController('timetracker', $request,
			\OCP\Server::get(IUserSession::class),
			\OCP\Server::get(IFactory::class)->get('timetracker'),
			\OCP\Server::get(WorkIntervalMapper::class), \OCP\Server::get(ClientMapper::class), \OCP\Server::get(UserToClientMapper::class),
			\OCP\Server::get(ProjectMapper::class), \OCP\Server::get(UserToProjectMapper::class), \OCP\Server::get(TagMapper::class),
			\OCP\Server::get(WorkIntervalToTagMapper::class), \OCP\Server::get(ReportItemMapper::class),
			\OCP\Server::get(TimelineMapper::class), \OCP\Server::get(TimelineEntryMapper::class), \OCP\Server::get(GoalMapper::class), \OCP\Server::get(GoalSkipMapper::class),
			\OCP\Server::get(IGroupManager::class), $this->mailer, $this->db, $user);
	}

	private function assertNotFound($response): void {
		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	private function entry(string $user, string $name, ?int $projectId = null): WorkInterval {
		$wi = new WorkInterval();
		$wi->setUserUid($user);
		$wi->setName($name);
		$wi->setStart(time() - 7200);
		$wi->setDuration(3600);
		$wi->setRunning(0);
		$wi->setProjectId($projectId);
		$wi = \OCP\Server::get(WorkIntervalMapper::class)->insert($wi);
		$this->created['timetracker_work_interval'][] = $wi->getId();
		return $wi;
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

	private function client(string $name, string $user): Client {
		$client = new Client();
		$client->setName($name);
		$client->setCreatedAt(time());
		$client = \OCP\Server::get(ClientMapper::class)->insert($client);
		$this->created['timetracker_client'][] = $client->getId();
		$link = new UserToClient();
		$link->setUserUid($user);
		$link->setClientId($client->getId());
		$link->setAdmin(1);
		$link->setCreatedAt(time());
		\OCP\Server::get(UserToClientMapper::class)->insert($link);
		return $client;
	}

	private function project(string $name, string $user): Project {
		$project = new Project();
		$project->setName($name);
		$project->setCreatedByUserUid($user);
		$project->setCreatedAt(time());
		$project = \OCP\Server::get(ProjectMapper::class)->insert($project);
		$this->created['timetracker_project'][] = $project->getId();
		$link = new UserToProject();
		$link->setUserUid($user);
		$link->setProjectId($project->getId());
		$link->setAdmin(1);
		$link->setAccess(1);
		$link->setCreatedAt(time());
		\OCP\Server::get(UserToProjectMapper::class)->insert($link);
		return $project;
	}

	private function timeline(string $user): Timeline {
		$timeline = new Timeline();
		$timeline->setUserUid($user);
		$timeline->setStatus('pending');
		$timeline->setCreatedAt(time());
		$timeline->setTotalDuration(3600);
		$timeline = \OCP\Server::get(TimelineMapper::class)->insert($timeline);
		$this->created['timetracker_timeline'][] = $timeline->getId();
		$entry = new TimelineEntry();
		$entry->setTimelineId($timeline->getId());
		$entry->setUserUid($user);
		$entry->setName('=HYPERLINK("x")');
		$entry->setProjectName('');
		$entry->setClientName('');
		$entry->setTimeInterval('2026-01-01');
		$entry->setTotalDuration(3600);
		$entry->setCreatedAt(time());
		\OCP\Server::get(TimelineEntryMapper::class)->insert($entry);
		return $timeline;
	}

	/** @return int[] */
	private function tagIdsOf(int $workIntervalId): array {
		return array_map(fn ($l) => (int)$l->getTagId(), \OCP\Server::get(WorkIntervalToTagMapper::class)->findAllForWorkInterval($workIntervalId));
	}
}
