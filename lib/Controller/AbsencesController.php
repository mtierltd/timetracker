<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Controller;

use OCA\TimeTracker\Db\AbsenceType;
use OCA\TimeTracker\Db\AbsenceTypeMapper;
use OCA\TimeTracker\Db\VacationEntitlementMapper;
use OCA\TimeTracker\Service\AbsenceDays;
use OCA\TimeTracker\Service\AbsenceException;
use OCA\TimeTracker\Service\AbsenceService;
use OCA\TimeTracker\Service\AbsenceSettings;
use OCA\TimeTracker\Service\OutOfOfficeSync;
use OCA\TimeTracker\Service\PublicHolidays;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;

/**
 * Absences, absence types and public holidays (#374), vacation
 * entitlements and approvals (#375), work models and the time account (#376),
 * the team calendar and out-of-office settings (#377).
 *
 * Methods without #[NoAdminRequired] are for admins only.
 *
 * Request bodies are JSON (as the web app sends), so parameters are read
 * with getParam().
 */
class AbsencesController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private AbsenceService $absenceService,
		private AbsenceTypeMapper $typeMapper,
		private AbsenceSettings $settings,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private VacationEntitlementMapper $entitlementMapper,
		private OutOfOfficeSync $outOfOffice,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		return new TemplateResponse('timetracker', 'spa', ['script' => 'dist/app']);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function workingTimeIndex(): TemplateResponse {
		return $this->index();
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function teamIndex(): TemplateResponse {
		return $this->index();
	}

	#[NoCSRFRequired]
	public function adminIndex(): TemplateResponse {
		return $this->index();
	}

	#[NoAdminRequired]
	public function getAbsences(): JSONResponse {
		$year = $this->year();
		return new JSONResponse([
			'Absences' => $this->absenceService->listForUser($this->userId, $year),
		]);
	}

	#[NoAdminRequired]
	public function addAbsence(): JSONResponse {
		return $this->run(fn () => $this->absenceService->present(
			$this->absenceService->create($this->userId,
				$this->request->getParam('typeId'),
				$this->request->getParam('startDate'),
				$this->request->getParam('endDate'),
				(bool)$this->request->getParam('halfDayStart', false),
				(bool)$this->request->getParam('halfDayEnd', false),
				$this->stringParam('note'))));
	}

	#[NoAdminRequired]
	public function cancelAbsence(int $id): JSONResponse {
		return $this->run(fn () => $this->absenceService->present($this->absenceService->cancel($this->userId, $id)));
	}

	#[NoAdminRequired]
	public function getVacationBalance(): JSONResponse {
		return new JSONResponse($this->absenceService->vacationBalance($this->userId, $this->year()));
	}

	/** Target, actual and balance of a month (?month=Y-m, default: this month). */
	#[NoAdminRequired]
	public function getTimeAccount(): JSONResponse {
		$month = (string)$this->request->getParam('month', '');
		if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month)) {
			$month = date('Y-m');
		}
		return new JSONResponse($this->absenceService->timeAccount($this->userId, $month));
	}

	/**
	 * Absences of a month (?month=Y-m) of the users who share a group with
	 * the viewer; admins see everyone, with all details.
	 */
	#[NoAdminRequired]
	public function getTeamAbsences(): JSONResponse {
		$month = (string)$this->request->getParam('month', '');
		if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month)) {
			$month = date('Y-m');
		}
		$from = $month . '-01';
		$to = (new \DateTimeImmutable($from))->format('Y-m-t');
		$isAdmin = $this->isAdmin();
		$users = [];
		if ($isAdmin) {
			foreach ($this->userManager->search('', 1000) as $user) {
				$users[$user->getUID()] = $user->getDisplayName();
			}
		} else {
			$viewer = $this->userManager->get($this->userId);
			if ($viewer === null) {
				return new JSONResponse(['Error' => 'Not found'], Http::STATUS_NOT_FOUND);
			}
			$users[$this->userId] = $viewer->getDisplayName();
			foreach ($this->groupManager->getUserGroupIds($viewer) as $groupId) {
				foreach ($this->groupManager->get($groupId)?->getUsers() ?? [] as $user) {
					$users[$user->getUID()] = $user->getDisplayName();
				}
			}
		}
		asort($users, SORT_NATURAL | SORT_FLAG_CASE);
		$holidays = array_filter(PublicHolidays::forYear((int)substr($month, 0, 4), $this->settings->stateFor($this->userId)),
			fn ($date) => $date >= $from && $date <= $to, ARRAY_FILTER_USE_KEY);
		return new JSONResponse([
			'month' => $month,
			'Users' => array_map(fn ($uid, $name) => ['userUid' => (string)$uid, 'displayName' => $name], array_keys($users), $users),
			'Absences' => $this->absenceService->teamAbsences(array_map('strval', array_keys($users)), $from, $to, $this->userId, $isAdmin),
			'Holidays' => $holidays,
		]);
	}

	public function saveWorkModel(): JSONResponse {
		return $this->run(function () {
			$userId = (string)$this->request->getParam('userUid', '');
			if (!$this->userManager->userExists($userId)) {
				throw new AbsenceException('Unknown user');
			}
			$this->absenceService->saveWorkModel($userId, $this->request->getParam('minutes'), $this->request->getParam('accountStart'));
			return $this->absenceService->workModelInfo($userId);
		});
	}

	public function getRequestedAbsences(): JSONResponse {
		return new JSONResponse(['Absences' => $this->withDisplayNames($this->absenceService->listRequested())]);
	}

	public function decideAbsence(int $id): JSONResponse {
		return $this->run(fn () => $this->absenceService->present($this->absenceService->decide(
			$this->userId, $id, (bool)$this->request->getParam('approve'), $this->stringParam('comment'))));
	}

	/** All users with their entitlement and balance of a year, and their work model. */
	public function getEntitlements(): JSONResponse {
		$year = $this->year();
		$users = [];
		foreach ($this->userManager->search('', 1000) as $user) {
			$users[] = [
				'userUid' => $user->getUID(),
				'displayName' => $user->getDisplayName(),
				'balance' => $this->absenceService->vacationBalance($user->getUID(), $year),
				'workModel' => $this->absenceService->workModelInfo($user->getUID()),
			];
		}
		usort($users, fn ($a, $b) => strcasecmp($a['displayName'], $b['displayName']));
		return new JSONResponse(['year' => $year, 'Users' => $users]);
	}

	public function saveEntitlement(): JSONResponse {
		return $this->run(function () {
			$userId = (string)$this->request->getParam('userUid', '');
			if (!$this->userManager->userExists($userId)) {
				throw new AbsenceException('Unknown user');
			}
			$this->absenceService->saveEntitlement($userId, $this->year(),
				$this->request->getParam('days'), $this->request->getParam('carriedOver'), $this->request->getParam('carryOverExpiry'));
			return $this->absenceService->vacationBalance($userId, $this->year());
		});
	}

	/**
	 * @param list<array<string, mixed>> $absences
	 * @return list<array<string, mixed>>
	 */
	private function withDisplayNames(array $absences): array {
		return array_map(fn ($a) => $a + ['displayName' => $this->userManager->getDisplayName($a['userUid']) ?? $a['userUid']], $absences);
	}

	#[NoAdminRequired]
	public function getAbsenceTypes(): JSONResponse {
		return new JSONResponse(['AbsenceTypes' => array_values($this->typeMapper->findAllById())]);
	}

	/** Admins add a type, or change one with an id. */
	public function saveAbsenceType(): JSONResponse {
		return $this->run(function () {
			$id = $this->request->getParam('id');
			$type = $id ? $this->typeMapper->findOrNull((int)$id) : new AbsenceType();
			if ($type === null) {
				throw new AbsenceException('Not found', Http::STATUS_NOT_FOUND);
			}
			$name = trim((string)$this->request->getParam('name', ''));
			$color = (string)$this->request->getParam('color', '#757575');
			$countsAs = (string)$this->request->getParam('countsAs', 'target_reduction');
			$visibility = (string)$this->request->getParam('visibility', 'reason');
			if ($name === '') {
				throw new AbsenceException('Please enter a name');
			}
			if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
				throw new AbsenceException('The colour must look like #1a2b3c');
			}
			if (!in_array($countsAs, AbsenceType::COUNTS_AS, true) || !in_array($visibility, AbsenceType::VISIBILITIES, true)) {
				throw new AbsenceException('Invalid type settings');
			}
			$type->setName(mb_substr($name, 0, 255));
			$type->setColor($color);
			$type->setCountsAs($countsAs);
			$type->setVisibility($visibility);
			$type->setDeductsVacation($this->request->getParam('deductsVacation') ? 1 : 0);
			$type->setNeedsApproval($this->request->getParam('needsApproval') ? 1 : 0);
			$type->setArchived($this->request->getParam('archived') ? 1 : 0);
			return $type->id ? $this->typeMapper->update($type) : $this->typeMapper->insert($type);
		});
	}

	/** Public holidays of a year for the user (or a given state). */
	#[NoAdminRequired]
	public function getHolidays(): JSONResponse {
		$state = (string)$this->request->getParam('state', $this->settings->stateFor($this->userId));
		if ($state !== '' && !isset(PublicHolidays::STATES[$state])) {
			$state = '';
		}
		$holidays = [];
		foreach (PublicHolidays::forYear($this->year(), $state) as $date => $name) {
			$holidays[] = ['date' => $date, 'name' => $name];
		}
		return new JSONResponse(['state' => $state, 'Holidays' => $holidays]);
	}

	#[NoAdminRequired]
	public function getAbsenceSettings(): JSONResponse {
		return new JSONResponse([
			'states' => PublicHolidays::STATES,
			'defaultState' => $this->settings->defaultState(),
			'userState' => $this->settings->userState($this->userId),
			'isAdmin' => $this->isAdmin(),
			'syncOutOfOffice' => $this->outOfOffice->isEnabled(),
		]);
	}

	/**
	 * Users set their own state; admins also the default state and whether
	 * absences set the out-of-office period.
	 */
	#[NoAdminRequired]
	public function saveAbsenceSettings(): JSONResponse {
		return $this->run(function () {
			$userState = $this->request->getParam('userState');
			if ($userState !== null) {
				$this->settings->setUserState($this->userId, $this->validState($userState));
			}
			$defaultState = $this->request->getParam('defaultState');
			if ($defaultState !== null) {
				if (!$this->isAdmin()) {
					throw new AbsenceException('Only admins can change the default', Http::STATUS_FORBIDDEN);
				}
				$this->settings->setDefaultState($this->validState($defaultState));
			}
			$sync = $this->request->getParam('syncOutOfOffice');
			if ($sync !== null) {
				if (!$this->isAdmin()) {
					throw new AbsenceException('Only admins can change this setting', Http::STATUS_FORBIDDEN);
				}
				$this->outOfOffice->setEnabled((bool)$sync);
			}
			return [
				'defaultState' => $this->settings->defaultState(),
				'userState' => $this->settings->userState($this->userId),
				'syncOutOfOffice' => $this->outOfOffice->isEnabled(),
			];
		});
	}

	private function validState(mixed $state): string {
		if ($state !== '' && !isset(PublicHolidays::STATES[$state])) {
			throw new AbsenceException('Unknown federal state');
		}
		return (string)$state;
	}

	private function isAdmin(): bool {
		return $this->userId !== null && $this->groupManager->isAdmin($this->userId);
	}

	private function year(): int {
		$year = $this->request->getParam('year');
		return is_numeric($year) && (int)$year >= 1970 && (int)$year <= 2200 ? (int)$year : (int)date('Y');
	}

	private function stringParam(string $name): ?string {
		$value = $this->request->getParam($name);
		return is_string($value) ? $value : null;
	}

	/** Runs an action; an AbsenceException becomes {"Error": message}. */
	private function run(callable $action): JSONResponse {
		try {
			return new JSONResponse($action());
		} catch (AbsenceException $e) {
			return new JSONResponse(['Error' => $e->getMessage()], $e->getStatus());
		}
	}
}
