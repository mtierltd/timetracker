<?php

namespace OCA\TimeTracker\Tests\Integration\Controller;

use OCA\TimeTracker\Db\Client;
use OCA\TimeTracker\Db\ClientMapper;
use OCA\TimeTracker\Db\ProjectMapper;
use OCA\TimeTracker\Db\WorkInterval;
use OCA\TimeTracker\Db\WorkIntervalMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * Entries starting exactly at "from" are part of the range, and add-project
 * only accepts clients the user may use.
 *
 * @group DB
 */
#[\PHPUnit\Framework\Attributes\Group('DB')]
class RangeStartAndProjectClientTest extends TestCase {
	use CreatesAjaxController;

	/** @var string */
	private $user;
	/** @var int[] */
	private $clientIds = [];

	protected function setUp(): void {
		parent::setUp();
		$this->user = 'tt-range-' . bin2hex(random_bytes(4));
	}

	protected function tearDown(): void {
		$db = \OCP\Server::get(IDBConnection::class);
		foreach (['timetracker_work_interval', 'timetracker_user_to_project'] as $table) {
			$qb = $db->getQueryBuilder();
			$qb->delete($table)->where($qb->expr()->eq('user_uid', $qb->createNamedParameter($this->user)))->executeStatement();
		}
		$qb = $db->getQueryBuilder();
		$qb->delete('timetracker_project')->where($qb->expr()->eq('created_by_user_uid', $qb->createNamedParameter($this->user)))->executeStatement();
		if ($this->clientIds) {
			$qb = $db->getQueryBuilder();
			$qb->delete('timetracker_client')
				->where($qb->expr()->in('id', $qb->createNamedParameter($this->clientIds, IQueryBuilder::PARAM_INT_ARRAY)))
				->executeStatement();
		}
		parent::tearDown();
	}

	public function testEntryStartingAtFromIsIncluded(): void {
		$from = mktime(0, 0, 0, 11, 17, 2026);
		$wi = new WorkInterval();
		$wi->setUserUid($this->user);
		$wi->setName('Midnight');
		$wi->setStart($from);
		$wi->setDuration(600);
		$wi->setRunning(0);
		\OCP\Server::get(WorkIntervalMapper::class)->insert($wi);
		$to = $from + 86399;

		$this->assertCount(1, \OCP\Server::get(WorkIntervalMapper::class)->findLatestInterval($this->user, $from, $to));
		$items = $this->ajaxController($this->user, ['name' => '', 'from' => $from, 'to' => $to, 'group1' => 'project', 'group2' => '', 'timegroup' => ''])
			->getReport()->getData()['items'];
		$this->assertCount(1, $items);
	}

	public function testAddProjectRejectsOtherUsersClient(): void {
		$c = new Client();
		$c->setName('Foreign client ' . $this->user);
		$c->setCreatedAt(time());
		$this->clientIds[] = \OCP\Server::get(ClientMapper::class)->insert($c)->getId();

		$name = 'Range project ' . $this->user;
		$response = $this->ajaxController($this->user, ['clientId' => (string)$this->clientIds[0]])->addProject($name);
		$this->assertSame(404, $response->getStatus());
		$this->assertNull(\OCP\Server::get(ProjectMapper::class)->findByName($name));

		// no client is fine
		$this->ajaxController($this->user, ['clientId' => ''])->addProject($name);
		$this->assertNull(\OCP\Server::get(ProjectMapper::class)->findByName($name)->getClientId());
	}
}
