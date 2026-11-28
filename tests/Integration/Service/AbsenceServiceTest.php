<?php

namespace OCA\TimeTracker\Tests\Integration\Service;

use OCA\TimeTracker\Db\Absence;
use OCA\TimeTracker\Db\AbsenceType;
use OCA\TimeTracker\Db\AbsenceTypeMapper;
use OCA\TimeTracker\Service\AbsenceException;
use OCA\TimeTracker\Service\AbsenceService;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * Absences with the real database: statuses, overlaps, the vacation
 * balance and the time account (#374 - #377).
 *
 * @group DB
 */
#[\PHPUnit\Framework\Attributes\Group('DB')]
class AbsenceServiceTest extends TestCase {

	private string $user;
	private AbsenceService $service;
	/** @var int[] */
	private array $typeIds = [];

	protected function setUp(): void {
		parent::setUp();
		$this->user = 'tt-absences-' . bin2hex(random_bytes(4));
		$this->service = \OCP\Server::get(AbsenceService::class);
	}

	protected function tearDown(): void {
		$db = \OCP\Server::get(IDBConnection::class);
		foreach (['timetracker_absence', 'timetracker_vacation_entitlement', 'timetracker_work_model', 'timetracker_work_interval'] as $table) {
			$qb = $db->getQueryBuilder();
			$qb->delete($table)->where($qb->expr()->eq('user_uid', $qb->createNamedParameter($this->user)))->executeStatement();
		}
		if ($this->typeIds) {
			$qb = $db->getQueryBuilder();
			$qb->delete('timetracker_absence_type')
				->where($qb->expr()->in('id', $qb->createNamedParameter($this->typeIds, IQueryBuilder::PARAM_INT_ARRAY)))
				->executeStatement();
		}
		parent::tearDown();
	}

	public function testRequestApproveAndBalance(): void {
		$vacation = $this->type('Vacation', true, true);
		$illness = $this->type('Illness', false, false);
		$this->service->saveEntitlement($this->user, 2030, 30, 0, null);

		// Mon 1 to Fri 5 July 2030
		$absence = $this->service->create($this->user, $vacation->id, '2030-07-01', '2030-07-05', false, false, 'Holiday');
		$this->assertSame(Absence::STATUS_REQUESTED, $absence->status);
		$this->assertSame(5.0, $this->service->vacationBalance($this->user, 2030)['requested']);

		$this->service->decide('approver', (int)$absence->id, true, 'OK');
		// illness during the vacation is allowed, and gives the day back
		$sick = $this->service->create($this->user, $illness->id, '2030-07-03', '2030-07-03', false, false, null);
		$this->assertSame(Absence::STATUS_APPROVED, $sick->status);

		$balance = $this->service->vacationBalance($this->user, 2030);
		$this->assertSame(4.0, $balance['approved']);
		$this->assertSame(0.0, $balance['requested']);
		$this->assertSame(26.0, $balance['remaining']);
	}

	public function testOverlappingVacationIsRejected(): void {
		$vacation = $this->type('Vacation', true, true);
		$this->service->create($this->user, $vacation->id, '2030-07-01', '2030-07-05', false, false, null);
		$this->expectException(AbsenceException::class);
		$this->service->create($this->user, $vacation->id, '2030-07-05', '2030-07-08', false, false, null);
	}

	public function testCancelledAbsencesDontCount(): void {
		$vacation = $this->type('Vacation', true, true);
		$absence = $this->service->create($this->user, $vacation->id, '2030-07-01', '2030-07-05', false, false, null);
		$this->service->cancel($this->user, (int)$absence->id);
		$this->assertSame(0.0, $this->service->vacationBalance($this->user, 2030)['requested']);
		// the period is free again
		$this->service->create($this->user, $vacation->id, '2030-07-01', '2030-07-05', false, false, null);
		$this->assertSame(5.0, $this->service->vacationBalance($this->user, 2030)['requested']);
	}

	public function testOthersCantCancel(): void {
		$vacation = $this->type('Vacation', true, true);
		$absence = $this->service->create($this->user, $vacation->id, '2030-07-01', '2030-07-01', false, false, null);
		$this->expectException(AbsenceException::class);
		$this->service->cancel('someone-else', (int)$absence->id);
	}

	public function testTimeAccount(): void {
		$illness = $this->type('Illness', false, false);
		// part time: 6 h Monday to Wednesday, counted from 1 July 2030
		$this->service->saveWorkModel($this->user, [1 => 360, 2 => 360, 3 => 360], '2030-07-01');
		$this->service->create($this->user, $illness->id, '2030-07-02', '2030-07-02', false, false, null);

		$account = $this->service->timeAccount($this->user, '2030-07', '2030-07-07');
		// Mon 1, Wed 3 count (Tue 2 is illness); nothing recorded
		$this->assertSame(720, $account['target']);
		$this->assertSame(0, $account['actual']);
		$this->assertSame(-720, $account['yearBalance']);
	}

	private function type(string $name, bool $deductsVacation, bool $needsApproval): AbsenceType {
		$type = new AbsenceType();
		$type->setName($name . ' ' . $this->user);
		$type->setColor('#123456');
		$type->setCountsAs('target_reduction');
		$type->setDeductsVacation($deductsVacation ? 1 : 0);
		$type->setNeedsApproval($needsApproval ? 1 : 0);
		$type->setVisibility('reason');
		$type->setArchived(0);
		$type = \OCP\Server::get(AbsenceTypeMapper::class)->insert($type);
		$this->typeIds[] = (int)$type->id;
		return $type;
	}
}
