<?php

namespace OCA\TimeTracker\Controller;

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
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Pinned timers (#290): start a timer with a saved name, project and tags in
 * one click. Stored on the server, so the web and Android apps share them.
 *
 * Errors are returned like in AjaxController, as {"Error": "..."}.
 *
 * A pin's project and tags are checked when it is saved and again when it is
 * read or started: a project the user can no longer access, or a deleted tag,
 * is left out instead of breaking the pin.
 */
class PinnedTimerController extends Controller {

	private $pinnedTimerMapper;
	private $projectMapper;
	private $tagMapper;
	private $workIntervalMapper;
	private $workIntervalToTagMapper;
	private $userTimeZone;
	private $userId;
	/** @var Project[]|null by id */
	private $projects = null;

	public function __construct($AppName, IRequest $request, PinnedTimerMapper $pinnedTimerMapper,
							ProjectMapper $projectMapper, TagMapper $tagMapper, WorkIntervalMapper $workIntervalMapper,
							WorkIntervalToTagMapper $workIntervalToTagMapper, UserTimeZone $userTimeZone, $userId) {
		parent::__construct($AppName, $request);
		$this->pinnedTimerMapper = $pinnedTimerMapper;
		$this->projectMapper = $projectMapper;
		$this->tagMapper = $tagMapper;
		$this->workIntervalMapper = $workIntervalMapper;
		$this->workIntervalToTagMapper = $workIntervalToTagMapper;
		$this->userTimeZone = $userTimeZone;
		$this->userId = $userId;
	}

	/**
	 * The user's pins in their order, each with `todayDuration`: the seconds of
	 * today's finished entries with the pin's name and project. "Today" uses
	 * `tzoffset` (minutes, as JavaScript's getTimezoneOffset()) if given, like
	 * the work-intervals endpoint, otherwise the user's time zone.
	 */
	#[NoAdminRequired]
	public function index(): JSONResponse {
		return new JSONResponse(['PinnedTimers' => $this->serializeAll(), 'now' => time()]);
	}

	/**
	 * Params: name, projectId (optional), tagIds ("1,2" or array, optional).
	 * A pin with the same name and project can't be added twice.
	 */
	#[NoAdminRequired]
	public function create(): JSONResponse {
		$name = $this->validName($this->request->getParam('name'));
		if ($name === null) {
			return new JSONResponse(['Error' => 'A pin needs a name of at most 255 characters']);
		}
		$project = $this->accessibleProject($this->request->getParam('projectId'));
		if ($project === false) {
			return new JSONResponse(['Error' => 'Unknown project']);
		}
		$projectId = $project === null ? null : $project->id;
		if ($this->pinnedTimerMapper->findByNameAndProject($this->userId, $name, $projectId) !== null) {
			return new JSONResponse(['Error' => 'This timer is already pinned']);
		}

		$pin = new PinnedTimer();
		$pin->setUserUid($this->userId);
		$pin->setName($name);
		$pin->setProjectId($projectId);
		$pin->setTagIds(implode(',', $this->allowedTagIds($this->request->getParam('tagIds'), $project)));
		$pin->setSortOrder($this->pinnedTimerMapper->maxSortOrder($this->userId) + 1);
		$pin->setCreatedAt(time());
		$this->pinnedTimerMapper->insert($pin);

		return $this->index();
	}

	/**
	 * Params like create; only the given ones change.
	 */
	#[NoAdminRequired]
	public function update($id): JSONResponse {
		$pin = $this->pinnedTimerMapper->findForUser($id, $this->userId);
		if ($pin === null) {
			return new JSONResponse(['Error' => 'Pin not found']);
		}
		$name = $pin->name;
		if ($this->request->getParam('name') !== null) {
			$name = $this->validName($this->request->getParam('name'));
			if ($name === null) {
				return new JSONResponse(['Error' => 'A pin needs a name of at most 255 characters']);
			}
		}
		$project = $this->accessibleProject($pin->projectId);
		if ($this->request->getParam('projectId') !== null) {
			$project = $this->accessibleProject($this->request->getParam('projectId'));
			if ($project === false) {
				return new JSONResponse(['Error' => 'Unknown project']);
			}
		}
		$project = $project ?: null;
		$projectId = $project === null ? null : $project->id;
		$same = $this->pinnedTimerMapper->findByNameAndProject($this->userId, $name, $projectId);
		if ($same !== null && $same->id !== $pin->id) {
			return new JSONResponse(['Error' => 'This timer is already pinned']);
		}
		$tagIds = $this->request->getParam('tagIds') ?? $pin->getTagIdList();

		$pin->setName($name);
		$pin->setProjectId($projectId);
		$pin->setTagIds(implode(',', $this->allowedTagIds($tagIds, $project)));
		$this->pinnedTimerMapper->update($pin);

		return $this->index();
	}

	#[NoAdminRequired]
	public function destroy($id): JSONResponse {
		$pin = $this->pinnedTimerMapper->findForUser($id, $this->userId);
		if ($pin === null) {
			return new JSONResponse(['Error' => 'Pin not found']);
		}
		$this->pinnedTimerMapper->delete($pin);
		return $this->index();
	}

	/**
	 * Param ids: the user's pin ids in the new order ("3,1,2" or array).
	 * Pins that aren't listed keep their relative order after the listed ones.
	 */
	#[NoAdminRequired]
	public function reorder(): JSONResponse {
		$ids = PinnedTimer::parseTagIds($this->request->getParam('ids'));
		$pins = $this->pinnedTimerMapper->findAllForUser($this->userId);
		$position = array_flip($ids);
		usort($pins, function ($a, $b) use ($position) {
			$pa = $position[$a->id] ?? PHP_INT_MAX;
			$pb = $position[$b->id] ?? PHP_INT_MAX;
			return $pa === $pb ? $a->sortOrder <=> $b->sortOrder : $pa <=> $pb;
		});
		foreach ($pins as $i => $pin) {
			if ($pin->sortOrder !== $i) {
				$pin->setSortOrder($i);
				$this->pinnedTimerMapper->update($pin);
			}
		}
		return $this->index();
	}

	/**
	 * Stops the user's running timers and starts one with the pin's name,
	 * project and tags. Unlike start-timer, an empty project or tag list stays
	 * empty instead of being copied from the last entry with the same name.
	 */
	#[NoAdminRequired]
	public function start($id): JSONResponse {
		$pin = $this->pinnedTimerMapper->findForUser($id, $this->userId);
		if ($pin === null) {
			return new JSONResponse(['Error' => 'Pin not found']);
		}
		$now = time();
		foreach ($this->workIntervalMapper->findAllRunning($this->userId) as $running) {
			$running->setRunning(0);
			$running->setDuration($now - $running->start);
			$this->workIntervalMapper->update($running);
		}

		$project = $this->accessibleProject($pin->projectId) ?: null;
		$wi = new WorkInterval();
		$wi->setStart($now);
		$wi->setRunning(1);
		$wi->setName($pin->name);
		$wi->setUserUid($this->userId);
		$wi->setProjectId($project === null ? null : $project->id);
		$this->workIntervalMapper->insert($wi);
		foreach ($this->allowedTagIds($pin->getTagIdList(), $project) as $tagId) {
			$link = new WorkIntervalToTag();
			$link->setWorkIntervalId($wi->id);
			$link->setTagId($tagId);
			$link->setCreatedAt($now);
			$this->workIntervalToTagMapper->insert($link);
		}

		return new JSONResponse(['WorkIntervals' => $wi, 'running' => 1]);
	}

	private function validName($name): ?string {
		$name = trim((string)$name);
		return ($name === '' || mb_strlen($name) > 255) ? null : $name;
	}

	/**
	 * @return Project[] the projects the user can use, by id
	 */
	private function accessibleProjects(): array {
		if ($this->projects === null) {
			$this->projects = [];
			foreach ($this->projectMapper->findAll($this->userId, 1) as $p) {
				$this->projects[$p->id] = $p;
			}
		}
		return $this->projects;
	}

	/**
	 * @return Project|null|false the project, null for none (empty value), false if unknown or not accessible
	 */
	private function accessibleProject($projectId) {
		if ($projectId === null || $projectId === '' || (int)$projectId <= 0) {
			return null;
		}
		return $this->accessibleProjects()[(int)$projectId] ?? false;
	}

	/**
	 * The given tag ids that are the user's tags and, for a locked project,
	 * allowed for it.
	 *
	 * @param string|array|null $tagIds
	 * @return int[]
	 */
	private function allowedTagIds($tagIds, ?Project $project): array {
		$allowed = array_map(fn (Tag $t) => $t->id, ($project !== null && $project->locked)
			? $this->tagMapper->findAllAlowedForProject($project->id)
			: $this->tagMapper->findAll($this->userId));
		return array_values(array_intersect(PinnedTimer::parseTagIds($tagIds), $allowed));
	}

	private function serializeAll(): array {
		$pins = $this->pinnedTimerMapper->findAllForUser($this->userId);
		if ($pins === []) {
			return [];
		}
		$tags = [];
		foreach ($this->tagMapper->findAll($this->userId) as $t) {
			$tags[$t->id] = ['id' => $t->id, 'name' => $t->name];
		}

		// Today's finished entries, summed per name and project.
		$today = [];
		$now = time();
		foreach ($this->workIntervalMapper->findLatestInterval($this->userId, $this->startOfToday($now) - 1, $now + 1) as $wi) {
			if (!$wi->running) {
				$key = $wi->name . "\0" . ($wi->projectId ?? '');
				$today[$key] = ($today[$key] ?? 0) + (int)$wi->duration;
			}
		}

		$result = [];
		foreach ($pins as $pin) {
			$project = $this->accessibleProject($pin->projectId) ?: null;
			$result[] = [
				'id' => $pin->id,
				'name' => $pin->name,
				'projectId' => $project === null ? null : $project->id,
				'projectName' => $project === null ? null : $project->name,
				'projectColor' => $project === null ? null : $project->color,
				'tags' => array_values(array_filter(array_map(fn ($id) => $tags[$id] ?? null, $pin->getTagIdList()))),
				'sortOrder' => $pin->sortOrder,
				'todayDuration' => $today[$pin->name . "\0" . ($project === null ? '' : $project->id)] ?? 0,
			];
		}
		return $result;
	}

	private function startOfToday(int $now): int {
		$tzoffset = $this->request->getParam('tzoffset');
		if (is_numeric($tzoffset)) {
			$local = $now - (int)$tzoffset * 60;
			return $local - ($local % 86400) + (int)$tzoffset * 60;
		}
		$day = new \DateTime('@' . $now);
		$day->setTimezone($this->userTimeZone->get($this->userId));
		$day->setTime(0, 0);
		return $day->getTimestamp();
	}
}
