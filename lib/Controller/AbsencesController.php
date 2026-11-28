<?php

declare(strict_types=1);

namespace OCA\TimeTracker\Controller;

use OCA\TimeTracker\Db\AbsenceType;
use OCA\TimeTracker\Db\AbsenceTypeMapper;
use OCA\TimeTracker\Service\AbsenceDays;
use OCA\TimeTracker\Service\AbsenceException;
use OCA\TimeTracker\Service\AbsenceService;
use OCA\TimeTracker\Service\AbsenceSettings;
use OCA\TimeTracker\Service\PublicHolidays;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IGroupManager;
use OCP\IRequest;

/**
 * Absences, absence types and public holidays (#374).
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
		]);
	}

	/**
	 * Users set their own state; admins also the default state.
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
			return ['defaultState' => $this->settings->defaultState(), 'userState' => $this->settings->userState($this->userId)];
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
