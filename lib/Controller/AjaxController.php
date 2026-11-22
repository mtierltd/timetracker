<?php
namespace OCA\TimeTracker\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\Mail\IMailer;
use OCA\TimeTracker\Db\WorkIntervalMapper;
use OCA\TimeTracker\Db\WorkInterval;
use OCP\AppFramework\Http\JSONResponse;
use OCA\TimeTracker\Db\ClientMapper;
use OCA\TimeTracker\Db\UserToClientMapper;
use OCA\TimeTracker\Db\Client;
use OCA\TimeTracker\Db\UserToClient;
use OCA\TimeTracker\Db\ProjectMapper;
use OCA\TimeTracker\Db\UserToProjectMapper;
use OCA\TimeTracker\Db\Project;
use OCA\TimeTracker\Db\UserToProject;
use OCA\TimeTracker\Db\Tag;
use OCA\TimeTracker\Db\TagMapper;
use OCA\TimeTracker\Db\Goal;
use OCA\TimeTracker\Db\GoalMapper;
use OCA\TimeTracker\Db\UserToTagMapper;
use OCA\TimeTracker\Db\UserToTag;
use OCA\TimeTracker\Db\WorkIntervalToTag;
use OCA\TimeTracker\Db\WorkIntervalToTagMapper;
use OCA\TimeTracker\Db\ReportItemMapper;
use OCA\TimeTracker\Db\TimelineMapper;
use OCA\TimeTracker\Db\TimelineEntryMapper;
use OCA\TimeTracker\Db\Timeline;
use OCA\TimeTracker\Db\TimelineEntry;

class AjaxController extends Controller {
	private $userId;
/** @var IUserSession */
	protected $userSession;
	protected $workIntervalMapper;
	protected $clientMapper;
	protected $userToClientMapper;
	protected $projectMapper;
	protected $userToProjectMapper;
	protected $tagMapper;
	protected $goalMapper;
	protected $request;
	protected $workIntervalToTagMapper;
	protected $reportItemMapper;
	protected $timelineMapper;
	protected $timelineEntryMapper;
	protected $l10n;
	protected $groupManager;
	protected $mailer;
	protected $db;

	public function __construct($AppName, IRequest $request, IUserSession $userSession, IL10N $l10n,
							WorkIntervalMapper $workIntervalMapper, ClientMapper $clientMapper, UserToClientMapper $userToClientMapper,
							ProjectMapper $projectMapper, UserToProjectMapper $userToProjectMapper, TagMapper $tagMapper, WorkIntervalToTagMapper $workIntervalToTagMapper, ReportItemMapper $reportItemMapper,
							TimelineMapper $timelineMapper, TimelineEntryMapper $timelineEntryMapper, GoalMapper $goalMapper,
							IGroupManager $groupManager, IMailer $mailer, IDBConnection $db, $userId){
		parent::__construct($AppName, $request);
		$this->userId = $userId;
		$this->userSession = $userSession;
		$this->workIntervalMapper = $workIntervalMapper;
		$this->clientMapper = $clientMapper;
		$this->userToClientMapper = $userToClientMapper;

		$this->projectMapper = $projectMapper;
		$this->userToProjectMapper = $userToProjectMapper;

		$this->tagMapper = $tagMapper;
		$this->goalMapper = $goalMapper;

		$this->workIntervalToTagMapper = $workIntervalToTagMapper;
		$this->reportItemMapper = $reportItemMapper;

		$this->timelineMapper = $timelineMapper;
		$this->timelineEntryMapper = $timelineEntryMapper;

		$this->request =  $request;
		$this->l10n = $l10n;
		$this->groupManager = $groupManager;
		$this->mailer = $mailer;
		$this->db = $db;
	}

	/**
	 *
	 * @NoAdminRequired
	 */
	public function workIntervals() {
		$from = $this->request->from;
		$to = $this->request->to;
		$l = $this->workIntervalMapper->findLatestInterval($this->userId, $from, $to);

		// Projects and tags of all entries in a few queries, not per entry
		$projects = $this->projectMapper->findByIds(array_filter(array_map(fn ($wi) => (int)$wi->projectId, $l)));
		$links = $this->workIntervalToTagMapper->findAllForWorkIntervals(array_map(fn ($wi) => (int)$wi->id, $l));
		$tagsById = $this->tagMapper->findByIds(array_map(fn ($link) => (int)$link->tagId, $links));
		$tagsByInterval = [];
		foreach ($links as $link) {
			if (isset($tagsById[(int)$link->tagId])) {
				$tagsByInterval[(int)$link->workIntervalId][] = $tagsById[(int)$link->tagId];
			}
		}
		$days = [];
		$tzoffset = 0;
		if (isset($this->request->tzoffset)) {
			$tzoffset = -($this->request->tzoffset * 60);
		}
		foreach ($l as $wi){
			// The timestamp is shifted by the browser's offset and formatted in UTC.
			// An explicit UTC date (not PHP's default time zone, which other code may
			// have changed) keeps the label independent of the server's settings.
			$dt = $this->l10n->l('date', new \DateTime('@' . ($wi->start + $tzoffset)), ['width' => 'medium']);
			if (!isset($days[$dt])){
				$days[$dt] = [];
			}
			if (!isset($days[$dt][$wi->name])){
				$days[$dt][$wi->name] = ['children' => [], 'totalTime' => 0];
			}
			// a project that no longer exists gives empty project fields
			$project = $wi->projectId != null ? ($projects[(int)$wi->projectId] ?? null) : null;
			$tags = $tagsByInterval[(int)$wi->id] ?? [];

			$wa = ['duration' => $wi->duration,
					'id' => $wi->id,
					'name' =>  $wi->name,
					'details' =>  $wi->details,
					'projectId' =>  $wi->projectId,
					'running' =>  $wi->running,
					'start' => $wi->start,
					'tags' => $tags,
					'userUid' => $wi->userUid,
                    'cost' => $wi->cost,
					'projectName' => ($project === null)?null:$project->name,
					'projectColor' =>  ($project === null)?null:$project->color,
			];
			$days[$dt][$wi->name]['children'][] = $wa;
			$days[$dt][$wi->name]['totalTime'] += $wa['duration'];
		}


		$running = $this->workIntervalMapper->findAllRunning($this->userId);
		return new JSONResponse(["WorkIntervals" => $l, "running" => $running, 'days' => $days, 'now' => time()]);
	}


	public function isThisAdminUser(){
		return $this->userId !== null && $this->groupManager->isAdmin($this->userId);
	}


	// --- Authorization helpers. Each returns the object only if the current
	// user may use it, otherwise null; endpoints then answer 404 Not Found,
	// so that the existence of other users' IDs isn't revealed.

	private function notFound(): JSONResponse {
		return new JSONResponse(['Error' => 'Not found'], Http::STATUS_NOT_FOUND);
	}

	/** Runs a mapper lookup that throws when nothing is found; null instead. */
	private function findOrNull(callable $find) {
		try {
			return $find();
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
			return null;
		}
	}

	/** The current user's own time entry. */
	private function ownWorkInterval($id): ?WorkInterval {
		$wi = $this->findOrNull(fn () => $this->workIntervalMapper->find((int)$id));
		return ($wi !== null && $wi->userUid === $this->userId) ? $wi : null;
	}

	/** The current user's own tag. */
	private function ownTag($id): ?Tag {
		$tag = $this->tagMapper->find((int)$id);
		return ($tag !== null && $tag->userUid === $this->userId) ? $tag : null;
	}

	/** A client linked to the current user, or any client for admins. */
	private function accessibleClient($id): ?Client {
		$client = $this->findOrNull(fn () => $this->clientMapper->find((int)$id));
		if ($client === null) {
			return null;
		}
		if ($this->isThisAdminUser() || $this->userToClientMapper->findForUserAndClient($this->userId, $client) !== null) {
			return $client;
		}
		return null;
	}

	/** A project the current user created or is linked to, or any project for admins. */
	private function editableProject($id): ?Project {
		$project = $this->findOrNull(fn () => $this->projectMapper->find((int)$id));
		if ($project === null) {
			return null;
		}
		if ($this->isThisAdminUser() || $project->createdByUserUid === $this->userId
			|| $this->userToProjectMapper->findForUserAndProject($this->userId, $project) !== null) {
			return $project;
		}
		return null;
	}

	/** The current user's own timeline, or any timeline for admins. */
	private function viewableTimeline($id): ?Timeline {
		$timeline = $this->findOrNull(fn () => $this->timelineMapper->find((int)$id));
		if ($timeline === null) {
			return null;
		}
		return ($this->isThisAdminUser() || $timeline->userUid === $this->userId) ? $timeline : null;
	}

	/**
	 * A project ID the current user may put entries or goals into: one of the
	 * projects the user can see (including archived ones), any for admins.
	 *
	 * @return Project|null|false the project, null for "no project", false if not allowed
	 */
	private function usableProject($projectId) {
		if ($projectId === null || $projectId === '' || (int)$projectId <= 0) {
			return null;
		}
		if ($this->isThisAdminUser()) {
			return $this->findOrNull(fn () => $this->projectMapper->find((int)$projectId)) ?? false;
		}
		foreach ($this->projectMapper->findAll($this->userId, 1) as $project) {
			if ($project->id === (int)$projectId) {
				return $project;
			}
		}
		return false;
	}

	/**
	 * The given tag IDs that are the current user's tags and, for a locked
	 * project, allowed for it.
	 *
	 * @param string|array|null $tagIds "1,2" or [1, 2]
	 * @return int[]
	 */
	private function usableTagIds($tagIds, ?Project $project): array {
		if (is_string($tagIds)) {
			$tagIds = explode(',', $tagIds);
		}
		if (!is_array($tagIds)) {
			return [];
		}
		$allowed = ($project !== null && $project->locked)
			? $this->tagMapper->findAllAlowedForProject($project->id)
			: $this->tagMapper->findAll($this->userId);
		$allowedIds = array_map(fn ($t) => (int)$t->id, $allowed);
		$result = [];
		foreach ($tagIds as $id) {
			if (is_numeric($id) && in_array((int)$id, $allowedIds, true)) {
				$result[(int)$id] = (int)$id;
			}
		}
		return array_values($result);
	}

	/** Links tags to a time entry. */
	private function linkTags(int $workIntervalId, array $tagIds): void {
		foreach ($tagIds as $tagId) {
			$link = new WorkIntervalToTag();
			$link->setWorkIntervalId($workIntervalId);
			$link->setTagId($tagId);
			$link->setCreatedAt(time());
			$this->workIntervalToTagMapper->insert($link);
		}
	}

	/**
	 * A CSV cell that spreadsheet programs won't run as a formula: text
	 * starting with =, +, -, @, tab or carriage return gets a leading '.
	 */
	public static function csvCell($value) {
		if (is_string($value) && $value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) {
			return "'" . $value;
		}
		return $value;
	}


    /**
     * @NoAdminRequired
     */
	public function addCost($id)
    {
        $wi = $this->ownWorkInterval($id);
        if ($wi === null) {
            return $this->notFound();
        }
        $cost = $this->request->cost;
        $cost = str_replace(',', '.', $cost);

        if (!is_numeric($cost)) {
            return new JSONResponse(['error' => 'Non numeric value'], Http::STATUS_BAD_REQUEST);
        }

        $costInCents = $cost * 100;

        $wi->setCost($costInCents);

        try {
            $this->workIntervalMapper->update($wi);
        } catch (\Exception $e) {
            return new JSONResponse(['error' => true, 'message' => $e->getMessage()]);
        }

        return new JSONResponse(['success' => true, 'test' => $cost ?? 't']);
    }

	/**
	 *
	 * @NoAdminRequired
	 */

	public function startTimer($name) {
		$projectId = null;
		$name = urldecode($name);
		if (isset($this->request->projectId) && (!empty($this->request->projectId))){
			$projectId = $this->request->projectId;
		}

		$tags = null;
		if (isset($this->request->tags) && (!empty($this->request->tags))){
			$tags = $this->request->tags;
		}

		if (strlen($name) > 255){
			return new JSONResponse(["Error" => "Name too long"]);
		}
		$project = $this->usableProject($projectId);
		if ($project === false) {
			return $this->notFound();
		}
		$winterval = new WorkInterval();
		$winterval->setStart(time());
		$winterval->setRunning(1);
		$winterval->setName($name);
		$winterval->setUserUid($this->userId);

		// first get tags and project ids from the last work item with the same name
		$lwinterval = $this->workIntervalMapper->findLatestByName($this->userId, $name);
		if ($projectId == null && $lwinterval != null){

			$winterval->setProjectId($lwinterval->projectId);
		}

		if($projectId != null){
			$winterval->setProjectId($projectId);
		}

		$this->workIntervalMapper->insert($winterval);
		if ($tags == null && $lwinterval != null){
			$lastTags = $this->workIntervalToTagMapper->findAllForWorkInterval($lwinterval->id);
			foreach($lastTags as $t){
				$wtot = new WorkIntervalToTag();
				$wtot->setWorkIntervalId($winterval->id);
				$wtot->setTagId($t->tagId);
				$wtot->setCreatedAt(time());
				$this->workIntervalToTagMapper->insert($wtot);
			}

		}

		if ($tags != null){
			$this->linkTags($winterval->id, $this->usableTagIds($tags, $project ?: null));
		}




		return new JSONResponse(["WorkIntervals" => $winterval, "running" => 1]);

	}





	/**
	 *
	 * @NoAdminRequired
	 */

	public function stopTimer($name) {
		$name = urldecode($name);
		if (strlen($name) > 255){
			return new JSONResponse(["Error" => "Name too long"]);
		}

		$running = $this->workIntervalMapper->findAllRunning($this->userId);

		$now = time();
		foreach($running as $r){
			$r->setRunning(0);
			$r->setDuration($now - $r->start);
			if ($name != 'no description')
				$r->setName($name);
			$this->workIntervalMapper->update($r);
		}
		return new JSONResponse(["WorkIntervals" => json_decode(json_encode($running), true)]);
	}


	/**
	 *
	 * @NoAdminRequired
	 */

	public function deleteWorkInterval($id) {
		$wi = $this->ownWorkInterval($id);
		if ($wi === null) {
			return $this->notFound();
		}
		$this->workIntervalToTagMapper->deleteAllForWorkInterval($wi->id);
		$this->workIntervalMapper->delete($wi);

		$running = $this->workIntervalMapper->findAllRunning($this->userId);

		return new JSONResponse(["WorkIntervals" => json_decode(json_encode($running), true)]);
	}

	/**
	 *
	 * @NoAdminRequired
	 */

	public function updateWorkInterval($id) {

		$wi = $this->ownWorkInterval($id);
		if ($wi === null) {
			return $this->notFound();
		}
		$id = $wi->id;

		if (isset($this->request->name)) {
			if (strlen($this->request->name) > 255){
				return new JSONResponse(["Error" => "Name too long"]);
			}
			$wi->setName($this->request->name);
		}
		if (isset($this->request->details)) {
			if (strlen($this->request->details) > 1024){
				return new JSONResponse(["Error" => "Details too long"]);
			}
			$wi->setDetails($this->request->details);
		}
		if (isset($this->request->projectId)) {
			$newProject = $this->usableProject($this->request->projectId);
			if ($newProject === false) {
				return $this->notFound();
			}
			$wi->setProjectId($newProject === null ? null : $newProject->id);
			if ($wi->projectId != null){
				$project = $newProject;
				$locked = $project->locked;
				if($locked){
					$allowedTags = $this->tagMapper->findAllAlowedForProject($project->id);
					$allowedTagsIds = array_map(function($tag) { return $tag->id;}, $allowedTags);
					$currentTags = $this->workIntervalToTagMapper->findAllForWorkInterval($id);
					$currentTagsIds = array_map(function($witag) { return $witag->tagId;}, $currentTags);
					$newTags = array_intersect($allowedTagsIds,$currentTagsIds);

					$this->workIntervalToTagMapper->deleteAllForWorkInterval($id);
					foreach($newTags as $tag){
						if (empty($tag))
							continue;
						$newWiToTag = new WorkIntervalToTag();
						$newWiToTag->setWorkIntervalId($id);
						$newWiToTag->setTagId($tag);
						$newWiToTag->setCreatedAt(time());
						$this->workIntervalToTagMapper->insert($newWiToTag);

					}

				}

			}
		}

		 if (isset($this->request->tagId)) {
			 if (is_array($this->request->tagId)){
				$tags = $this->request->tagId;
			 } else {

				 $tags = \explode(",", $this->request->tagId);
			 }
			$this->workIntervalToTagMapper->deleteAllForWorkInterval($id);
			$project = null;
			$locked = 0;
			if ($wi->projectId != null) {
				$project = $this->findOrNull(fn () => $this->projectMapper->find($wi->projectId));
			}
			// existing tags: only the user's own (and, for a locked project, allowed) ones
			$usable = $this->usableTagIds(array_filter($tags, 'is_numeric'), $project);
			$tags = array_merge($usable, array_filter($tags, fn ($t) => !is_numeric($t)));


			foreach($tags as $tag){
				if (empty($tag))
					continue;
				if(!is_numeric($tag)){
					if($project && $project->locked)
						continue; // don't add new tags to locked projects
					$c = new Tag();
					$c->setName($tag);
					$c->setUserUid($this->userId);
					$c->setCreatedAt(time());
					$this->tagMapper->insert($c);
					$tag = $c->id;
				}
				$newWiToTag = new WorkIntervalToTag();
				$newWiToTag->setWorkIntervalId($id);
				$newWiToTag->setTagId($tag);
				$newWiToTag->setCreatedAt(time());
				$this->workIntervalToTagMapper->insert($newWiToTag);

			}
		 }
		 if (isset($this->request->start)) {
			[$start, $duration] = self::localTimesToInterval($this->request->getParam('start'), $this->request->getParam('end'),
				$this->request->getParam('tzoffset'), $this->request->getParam('tzoffsetEnd'));
			$wi->setStart($start);
			$wi->setDuration($duration);
		 }

		$this->workIntervalMapper->update($wi);
		$running = $this->workIntervalMapper->findAllRunning($this->userId);

		return new JSONResponse(["WorkIntervals" => json_decode(json_encode($running), true)]);
	}


	/**
	 *
	 * @NoAdminRequired
	 */

	public function addWorkInterval() {

		$wi = new WorkInterval();
		$wi->setUserUid($this->userId);
		$wi->setRunning(0);

		if ($this->request->getParam('name') !== null) {
			$wi->setName(urldecode($this->request->getParam('name')));
		}
		if ($this->request->getParam('details') !== null) {
			if (strlen($this->request->getParam('details')) > 1024){
				return new JSONResponse(["Error" => "Details too long"]);
			}
			$wi->setDetails($this->request->getParam('details'));
		}
		$project = $this->usableProject($this->request->getParam('projectId'));
		if ($project === false) {
			return $this->notFound();
		}
		$wi->setProjectId($project === null ? null : $project->id);
		// linked after the entry is inserted, when it has an ID
		$tagIds = $this->usableTagIds($this->request->getParam('tagId'), $project);
		 if ($this->request->getParam('start') !== null) {
			[$start, $duration] = self::localTimesToInterval($this->request->getParam('start'), $this->request->getParam('end'),
				$this->request->getParam('tzoffset'), $this->request->getParam('tzoffsetEnd'));
			$wi->setStart($start);
			$wi->setDuration($duration);
		 }

		$this->workIntervalMapper->insert($wi);
		$this->linkTags($wi->id, $tagIds);

		$running = $this->workIntervalMapper->findAllRunning($this->userId);

		return new JSONResponse(["WorkIntervals" => json_decode(json_encode($running), true)]);
	}


	/**
	 *
	 * @NoAdminRequired
	 */
	public function addClient($name) {
		if (trim($name) ==''){
			return;
		}

		$c = $this->clientMapper->findByName($name);
		if ($c == null){
			$c = new Client();
			$c->setName($name);
			$c->setCreatedAt(time());
			$this->clientMapper->insert($c);
		}

		$utoc = $this->userToClientMapper->findForUserAndClient($this->userId, $c);
		if ($utoc == null){
			$utoc = new UserToClient();
			$utoc->setClientId($c->id);
			$utoc->setUserUid($this->userId);
			$utoc->setCreatedAt(time());
			$utoc->setAdmin(1);
			$this->userToClientMapper->insert($utoc);
		} else {
			return new JSONResponse(["Error" => "This client is already in your list"]);
		}
		return $this->getClients();
	}
	/**
	 *
	 * @NoAdminRequired
	 */
	public function editClient($id) {

		$name = $this->request->name;
		$c = $this->accessibleClient($id);
		if ($c === null){
			return $this->notFound();
		}
		if (trim($name) != ''){
			$old = $this->clientMapper->findByName($name);
			if ($old == null || $old->id == $id){
				$c->setName($name);
				$this->clientMapper->update($c);
			} else {
				return new JSONResponse(["Error" => "This client already exists"]);
			}
		}
		return $this->getClients();
	}
	/**
	 *
	 * @NoAdminRequired
	 */
	public function deleteClient($id) {

		$c = new Client();
		$c->setId($id);
		$utoc = $this->userToClientMapper->findForUserAndClient($this->userId, $c);

		if ($utoc != null){

			$this->userToClientMapper->delete($utoc);
		}
		return $this->getClients();
	}


	/**
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getClients(){
        $clientName = $this->request->term ?? null;

        if ($clientName) {
            $clients = $this->clientMapper->searchByName($this->userId, $clientName);
        } else {
            $clients = $this->clientMapper->findAll($this->userId);
        }

		return new JSONResponse(["Clients" => json_decode(json_encode($clients), true)]);
	}

	/**
	 *
	 * @NoAdminRequired
	 */
	public function addProject($name) {
		$clientId = null;
		if (isset($this->request->clientId)) {
			$clientId = $this->request->clientId;
		}
		$color = '#ffffff';
		if (isset($this->request->color) && !empty($this->request->color)) {
			$color = $this->request->color;
		}
		if (trim($name) == ''){
			return;
		}
		$p = $this->projectMapper->findByName($name);
		if ($p == null){
			$p = new Project();
			$p->setName($name);
			$p->setColor($color);
			$p->setCreatedAt(time());
			$p->setCreatedByUserUid($this->userId);
			$p->setClientId($clientId);
			$this->projectMapper->insert($p);
		} else {
			if($p->locked && !$this->isThisAdminUser()){
				return new JSONResponse(["Error" => "This project is locked"]);
			}
		}

		$utop = $this->userToProjectMapper->findForUserAndProject($this->userId, $p);
		if ($utop == null){
			$utop = new UserToProject();
			$utop->setProjectId($p->id);
			$utop->setUserUid($this->userId);
			$utop->setCreatedAt(time());
			$utop->setAdmin(1);
			$this->userToProjectMapper->insert($utop);
		}
		return $this->getProjects();
	}
	/**

	 * @NoAdminRequired
	 */
	public function editProject($id) {

		$p = $this->editableProject($id);
		if ($p === null){
			return $this->notFound();
		}
		$id = $p->id;
		if (isset($this->request->name)){
			$name = $this->request->name;
			if (trim($name) != ''){
				$old = $this->projectMapper->findByName($name);
				if ($old != null && $old->id != $id){
					return new JSONResponse(["Error" => "A project with this name already exists"]);
				}
				$p->setName($name);
			}
		}
		if (isset($this->request->color)){
			$color = $this->request->color;
			$p->setColor($color);
		}
		if (isset($this->request->clientId)){
			$clientId = $this->request->clientId;
			if ($clientId === '' || $clientId === null || (int)$clientId <= 0) {
				$p->setClientId(null);
			} elseif ($this->accessibleClient($clientId) !== null) {
				$p->setClientId((int)$clientId);
			} else {
				return $this->notFound();
			}
		}
		if (isset($this->request->locked) && $this->isThisAdminUser()){
			$locked = $this->request->locked;
			$p->setLocked($locked);
		}

		if (isset($this->request->allowedTags) && $this->isThisAdminUser()){
			$allowedTags = $this->request->allowedTags;
			$a = explode(',',$allowedTags);
			$this->tagMapper->allowedTags($id, $a);
		}
		if (isset($this->request->allowedUsers) && $this->isThisAdminUser()){
			$allowedUsers = $this->request->allowedUsers;
			$a = explode(',',$allowedUsers);
			$this->userToProjectMapper->deleteAllForProject($id);
			foreach($a as $u){
				if (empty($u))
					continue;
				$up = new UserToProject();
				$up->setUserUid($u);
				$up->setProjectId($id);
				$up->setAccess(1);
				$up->setCreatedAt(time());
				$this->userToProjectMapper->insert($up);
			}
		}

		if (isset($this->request->archived) && $p->getArchived() != $this->request->archived){
			if (($this->isThisAdminUser() || $p->createdByUserUid == $this->userId) ){

				$archived = $this->request->archived;
				$p->setArchived($archived);
			} else {
				return new JSONResponse(["Error" => "You cannot archive/unarchive projects created by somebody else"]);
			}
		}

		$this->projectMapper->update($p);

		return $this->getProjects();
	}
	/**
	 *
	 * @NoAdminRequired
	 */
	public function deleteProject($id) {

		$p = new Project();
		$p->setId($id);
		$utop = $this->userToProjectMapper->findForUserAndProject($this->userId, $p);

		if ($utop != null){

			$this->userToProjectMapper->delete($utop);
		}
		return $this->getProjects();
	}

	public function deleteProjectWithData($id) {
		if (!$this->isThisAdminUser()){
			return;
		}
		$this->userToProjectMapper->deleteAllForProject($id);
		$wi = $this->workIntervalMapper->findAllForProject($id);
		if ($wi != null){
			foreach ($wi as $w){
				$this->workIntervalToTagMapper->deleteAllForWorkInterval($w->id);
			}
		}
		$this->workIntervalMapper->deleteAllForProject($id);
		$this->tagMapper->allowedTags($id,[]);
		$this->projectMapper->deleteById($id);


		return $this->getProjects();
	}


	/**
	 *
	 * @NoAdminRequired
	 */
	public function getProjects(){
	    $projectName = $this->request->term ?? null;

	    if ($projectName) {
	        $projects = $this->projectMapper->searchByName($this->userId, $projectName);
        } else {
            $projects = $this->projectMapper->findAll($this->userId);
        }
		$parray = json_decode(json_encode($projects), true);
		foreach($parray as $pi => $pv){
			if (isset($pv->id)) {
				$tags = $this->tagMapper->findAllAlowedForProject($pv->id);
				$parray[$pi]['allowedtags'] = $tags;
			}

		}
		return new JSONResponse(["Projects" => $parray]);
	}

	/**
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getProjectsTable(){
		$getArchived = 0;
		if (isset($this->request->archived)){
			$getArchived = $this->request->archived;
		}

		if ($this->isThisAdminUser()){
			$projects = $this->projectMapper->findAllAdmin($getArchived);
		} else {
			$projects = $this->projectMapper->findAll($this->userId,$getArchived);
		}
		$outProjects = [];
		foreach($projects as $p){
			$out = [];
			$out['id'] = $p->id;
			$out['name'] = $p->name;
			$out['locked'] = $p->locked;
			$out['archived'] = $p->archived;
			$out['color'] = $p->color;
			$out['client'] = null;
			$tags = $this->tagMapper->findAllAlowedForProject($p->id);
			$users = array_map(function ($utop) { return $utop->userUid;},$this->userToProjectMapper->findAllForProject($p->id));
			$out['allowedTags'] = json_decode(json_encode($tags));
			$out['allowedUsers'] = json_decode(json_encode($users));
			if ($p->clientId != null){
				$client = $this->clientMapper->find($p->clientId);
				if ($client != null){
					$out['client'] = $client->name;
					$out['clientId'] = $client->id;
				}
			}

			$outProjects[] = $out;
		}

		return new JSONResponse(["items" => json_decode(json_encode($outProjects), true), 'total' => count($outProjects)]);
	}



	/**
	 *
	 * @NoAdminRequired
	 */
	public function addTag($name) {

		$c = $this->tagMapper->findByNameUser($name, $this->userId);
		if ($c == null && (trim($name) != '')){
			$c = new Tag();
			$c->setName($name);
			$c->setUserUid($this->userId);
			$c->setCreatedAt(time());
			$this->tagMapper->insert($c);
		} else 	if ($c != null){
			return new JSONResponse(["Error" => "This tag name already exists"]);
		}


		return $this->getTags();
	}
	/**
	 *
	 * @NoAdminRequired
	 */
	public function editTag($id) {

		$name = $this->request->name;
		if (trim($name) == ''){
			return;
		}
		$c = $this->ownTag($id);
		if ($c === null){
			return $this->notFound();
		}
		$old = $this->tagMapper->findByNameUser($name, $this->userId);
		if ($old != null && $old->id != $id){
			return new JSONResponse(["Error" => "This tag name already exists"]);
		}
		$c->setName($name);
		$this->tagMapper->update($c);

		return $this->getTags();
	}
	/**
	 *
	 * @NoAdminRequired
	 */
	public function deleteTag($id) {
		$c = $this->ownTag($id);
		if ($c === null){
			return $this->notFound();
		}
		$this->tagMapper->delete($c);
		$this->workIntervalToTagMapper->deleteAllForTag($id);
		return $this->getTags();
	}

	/**
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getTags(){
		$workItem = $this->request->workItem;
		$project = null;
		$q = $this->request->q;
		$wi = $workItem != null ? $this->ownWorkInterval($workItem) : null;
		if ($wi !== null){
			if ($wi->projectId != null){
				$project = $this->projectMapper->find($wi->projectId);

			}
		}
		if($project != null && $project->locked){
			$tags = $this->tagMapper->findAllAlowedForProject($project->id);
		} else {
			$tags = $this->tagMapper->findAll($this->userId);
		}
		if ($q != null){
			$filteredTags = [];
			foreach($tags as $t){
				if (stripos($t->name, $q) !== FALSE){
					$filteredTags[] = $t;
				}
			}
			$tags = $filteredTags;
		}
		return new JSONResponse(["Tags" => json_decode(json_encode($tags), true)]);
	}


	/**
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getReport(){
		$name = $this->request->name;
		$from = $this->request->from;
		$timegroup = $this->request->timegroup;
		$to = $this->request->to;
		if (isset($this->request->filterProjectId) && !empty($this->request->filterProjectId)){
			$filterProjectId = explode(",",$this->request->filterProjectId);
		} else {
			$filterProjectId = [];
		}
		if (isset($this->request->filterClientId) && !empty($this->request->filterClientId)){
			$filterClientId = explode(",",$this->request->filterClientId);
		} else {
			$filterClientId = [];
		}

		// Only admins may report on other users.
		if ($name == '' || !$this->isThisAdminUser()){
			$name = $this->userId;
		}


		if(!$this->isThisAdminUser()){
			$allowedClients =  $this->clientMapper->findAll($this->userId);
			$allowedClientsId = array_map(function($client){ return $client->id;}, $allowedClients );
			if(empty($filterClientId)){
				$filterClientId = $allowedClientsId;
				$filterClientId[] = null; // allow null clientid
			} else {
				$filterClientId = array_intersect($filterClientId, $allowedClientsId);
			}
			$allowedProjects =  $this->projectMapper->findAll($this->userId);
			$allowedProjectsId = array_map(function($project){ return $project->id;}, $allowedProjects );
			if(empty($filterProjectId)){
				$filterProjectId = $allowedProjectsId;
				$filterProjectId[] = null; // allow null projectId
			} else {
				$filterProjectId = array_intersect($filterProjectId, $allowedProjectsId);
			}

		}

		$filterTagId = [];
		$groupOn1 = $this->request->group1;
		$groupOn2 = $this->request->group2;
		$items = $this->reportItemMapper->report($name, $from, $to, $filterProjectId, $filterClientId, $filterTagId, $timegroup, $groupOn1, $groupOn2, $this->isThisAdminUser(), 0, null, $this->requestTimeZone());
		return new JSONResponse(["items" => json_decode(json_encode($items), true), 'total' => count($items)]);
	}


	/**
	 *
	 * @NoAdminRequired
	 */
	public function postTimeline(){
		$name = $this->request->getParam('name');
		$from = $this->request->getParam('from');
		$timegroup = $this->request->getParam('timegroup');
		$to = $this->request->getParam('to');
		if ($this->request->getParam('filterProjectId') !== null && !empty($this->request->getParam('filterProjectId'))){
			$filterProjectId = explode(",",$this->request->getParam('filterProjectId'));
		} else {
			$filterProjectId = [];
		}
		if ($this->request->getParam('filterClientId') !== null && !empty($this->request->getParam('filterClientId'))){
			$filterClientId = explode(",",$this->request->getParam('filterClientId'));
		} else {
			$filterClientId = [];
		}

		// Only admins may report on other users.
		if ($name == '' || !$this->isThisAdminUser()){
			$name = $this->userId;
		}


		if(!$this->isThisAdminUser()){
			$allowedClients =  $this->clientMapper->findAll($this->userId);
			$allowedClientsId = array_map(function($client){ return $client->id;}, $allowedClients );
			if(empty($filterClientId)){
				$filterClientId = $allowedClientsId;
				$filterClientId[] = null; // allow null clientid
			} else {
				$filterClientId = array_intersect($filterClientId, $allowedClientsId);
			}
			$allowedProjects =  $this->projectMapper->findAll($this->userId);
			$allowedProjectsId = array_map(function($project){ return $project->id;}, $allowedProjects );
			if(empty($filterProjectId)){
				$filterProjectId = $allowedProjectsId;
				$filterProjectId[] = null; // allow null projectId
			} else {
				$filterProjectId = array_intersect($filterProjectId, $allowedProjectsId);
			}

		}

		$filterTagId = [];
		$groupOn1 = $this->request->getParam('group1');
		$groupOn2 = $this->request->getParam('group2');
		$items = $this->reportItemMapper->report($name, $from, $to, $filterProjectId, $filterClientId, $filterTagId, $timegroup, $groupOn1, $groupOn2, $this->isThisAdminUser(), 0, null, $this->requestTimeZone());

		$timeline = new Timeline();
		$timeline->setUserUid($this->userId);
		$timeline->setGroup1($this->request->getParam('group1'));
		$timeline->setGroup2($this->request->getParam('group2'));
		$timeline->setTimeGroup($this->request->getParam('timegroup'));
		$timeline->setFilterProjects(implode(', ',$filterProjectId));
		$timeline->setFilterClients(implode(', ',$filterClientId));
		$timeZone = $this->requestTimeZone();
		$timeline->setTimeInterval($this->formatDate((int)$from, $timeZone) . ' - ' . $this->formatDate((int)$to, $timeZone));
		$totalDuration = 0;
		foreach($items as $i){
			$totalDuration += $i->totalDuration;
		}

		$timeline->setTotalDuration($totalDuration);
		$timeline->setCreatedAt(time());
		$timeline->setStatus('pending');
		// Create the timeline and all its entries together, so a failing entry
		// doesn't leave an empty timeline behind.
		$this->db->beginTransaction();
		try {
			$this->timelineMapper->insert($timeline);
			foreach($items as $i){
				$te = new TimelineEntry();
				$te->setTimelineId($timeline->id);
				$te->setUserUid($timeline->userUid);
				$te->setName($i->name);
				$te->setProjectName($i->project ? $i->project : "");
				$te->setClientName($i->client ? $i->client : "");
				// The period's label (e.g. "2026-11-17" or "2026W47") when grouped by
				// time, otherwise the date of the earliest entry. Before, the raw
				// start timestamp of the earliest entry was stored here.
				$te->setTimeInterval($i->ftime !== null && $i->ftime !== '' ? (string)$i->ftime : $this->formatDate((int)$i->time, $timeZone));
				$te->setTotalDuration($i->totalDuration);
				$te->setCreatedAt(time());
				$te->setCost($i->cost);
				$this->timelineEntryMapper->insert($te);
			}
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
		return new JSONResponse(["Timeline" => json_decode(json_encode($timeline), true)]);
	}


	/**
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getTimelines(){
		$timelines = $this->timelineMapper->findAll($this->userId);
		$parray = json_decode(json_encode($timelines), true);

		return new JSONResponse(["Timelines" => $parray, 'total' => count($parray)]);
	}

	/**
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getTimelinesAdmin(){
		if (!$this->isThisAdminUser()) {
			return new JSONResponse(['Error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
		}
		$timelines = $this->timelineMapper->findLatest();
		$parray = json_decode(json_encode($timelines), true);

		return new JSONResponse(["Timelines" => $parray, 'total' => count($parray)]);
	}


	/**
	 * Start timestamp and duration from local times "d/m/y H:i".
	 *
	 * $tzoffset and $tzoffsetEnd are the minutes to add to local time to get
	 * UTC (JavaScript's Date.getTimezoneOffset()) on the start and the end
	 * date. They differ when daylight saving time applies on one date but not
	 * on the other, so each time gets its own; $tzoffsetEnd defaults to
	 * $tzoffset for clients that send only one offset.
	 *
	 * @return array{0: int, 1: int} [start, duration]
	 */
	public static function localTimesToInterval(string $start, string $end, $tzoffset, $tzoffsetEnd): array {
		$tzoffset = (int)($tzoffset ?? 0);
		$tzoffsetEnd = $tzoffsetEnd === null || $tzoffsetEnd === '' ? $tzoffset : (int)$tzoffsetEnd;
		$utc = new \DateTimeZone('UTC');
		$startTs = \DateTime::createFromFormat('d/m/y H:i', $start, $utc)->getTimestamp() + $tzoffset * 60;
		$endTs = \DateTime::createFromFormat('d/m/y H:i', $end, $utc)->getTimestamp() + $tzoffsetEnd * 60;
		return [$startTs, $endTs - $startTs];
	}

	public function secondsToTime($seconds){
		$hours = floor($seconds / 3600);
		$mins = floor($seconds / 60 % 60);
		$secs = floor($seconds % 60);
		$timeFormat = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
		return $timeFormat;
	}

	/**
	 * Costs are stored in cents; empty when an entry has no cost.
	 */
	private function formatCost($cents){
		if ($cents === null || $cents === '') {
			return '';
		}
		return number_format(((int)$cents) / 100, 2, '.', '');
	}

	/**
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function downloadTimeline($id){
		if ($this->viewableTimeline($id) === null) {
			return $this->notFound();
		}
		$id = (int)$id;
		$te = $this->timelineEntryMapper->findTimelineEntries($id);
		if (count($te) == 0){ // nothing to send
			exit(0);
		}

		$user = $te[0]->userUid;

		// output headers so that the file is downloaded rather than displayed
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=timeline-'.$user.'-'.$id.'.csv');

		// create a file pointer connected to the output stream
		$output = fopen('php://output', 'w');

		// output the column headings
		fputcsv($output, array('id', 'User Uid', 'Name', 'Project Name', 'Client Name', 'Time Interval', 'Total Duration', 'Cost'));
		$totalDuration = 0;
		$totalCost = 0;
		foreach($te as $t){

				fputcsv($output, array_map([self::class, 'csvCell'], [$t->id, $t->userUid, $t->name, $t->projectName, $t->clientName, $t->timeInterval, $this->secondsToTime($t->totalDuration), $this->formatCost($t->cost)]));
				$totalDuration += $t->totalDuration;
				$totalCost += (int)$t->cost;
		}
		fputcsv($output, ['TOTAL', '', '', '', '', '', $this->secondsToTime($totalDuration), $this->formatCost($totalCost)]);
		fclose($output);
		exit(0);
	}


	/**
	 * Changes a timeline's review status; admins only (Timelines Admin).
	 */
	public function editTimeline($id){
		if (!$this->isThisAdminUser()) {
			return new JSONResponse(['Error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
		}
		$timeline = $this->findOrNull(fn () => $this->timelineMapper->find((int)$id));
		if ($timeline === null) {
			return $this->notFound();
		}
		$timeline->setStatus($this->request->status);
		$this->timelineMapper->update($timeline);
		return new JSONResponse(["Timeline" => $timeline]);
	}

	/**
	 *
	 * @NoAdminRequired
	 */

	public function deleteTimeline($id) {
		$tl = $this->timelineMapper->find($id);
		if ($tl->userUid == $this->userId){
			$this->timelineMapper->delete($tl);
		}
		return $this->getTimelines();
	}

	/**
	 * Sends a timeline as CSV to up to 10 addresses (separated by ";").
	 *
	 * @NoAdminRequired
	 */
	#[UserRateLimit(limit: 10, period: 3600)]
	public function emailTimeline($id) {
		if ($this->viewableTimeline($id) === null) {
			return $this->notFound();
		}
		$id = (int)$id;

		$te = $this->timelineEntryMapper->findTimelineEntries($id);
		if (count($te) == 0){ // nothing to send
			return new JSONResponse(['Error' => 'This timeline is empty'], Http::STATUS_BAD_REQUEST);
		}

		$emails = array_values(array_filter(array_map('trim', explode(';', (string)$this->request->email)), fn ($e) => $e !== ''));
		if ($emails === [] || count($emails) > 10) {
			return new JSONResponse(['Error' => 'Enter between 1 and 10 email addresses, separated by ";"'], Http::STATUS_BAD_REQUEST);
		}
		foreach ($emails as $email) {
			if (!$this->mailer->validateMailAddress($email)) {
				return new JSONResponse(['Error' => 'Invalid email address: ' . $email], Http::STATUS_BAD_REQUEST);
			}
		}
		$subject = (string)$this->request->subject;
		$content = (string)$this->request->content;

		// built in memory: no temporary file
		$output = fopen('php://temp', 'w+');
		//$path = stream_get_meta_data($output)['uri'];
		// output the column headings
		fputcsv($output, array('id', 'User Uid', 'Name', 'Project Name', 'Client Name', 'Time Interval', 'Total Duration', 'Cost'));
		$totalDuration = 0;
		$totalCost = 0;
		foreach($te as $t){

				fputcsv($output, array_map([self::class, 'csvCell'], [$t->id, $t->userUid, $t->name, $t->projectName, $t->clientName, $t->timeInterval, $this->secondsToTime($t->totalDuration), $this->formatCost($t->cost)]));
				$totalDuration += $t->totalDuration;
				$totalCost += (int)$t->cost;
		}
		fputcsv($output, ['TOTAL', '', '', '', '', '', $this->secondsToTime($totalDuration), $this->formatCost($totalCost)]);



		rewind($output);
		$csv = stream_get_contents($output);
		fclose($output);

		$mailer = $this->mailer;
		$message = $mailer->createMessage();
		$message->setSubject($subject);
		$message->setTo($emails);
		$message->setPlainBody($content);
		$message->attach($mailer->createAttachment($csv, 'timeline-' . $id . '.csv', 'text/csv'));
		$mailer->send($message);

		return new JSONResponse([]);
	}





	/**
	 *
	 * @NoAdminRequired
	 */
	public function addGoal() {
		// getParam(), not $this->request->projectId: a JSON body (as the web
		// app sends) is only parsed by getParam(), and this method has no
		// parameters that would make the framework call it first.
		$project = $this->usableProject($this->request->getParam('projectId'));
		if (!$project) {
			return $this->notFound();
		}
		$projectId = $project->id;
		$hours = $this->request->getParam('hours');
		$interval = $this->request->getParam('interval');

		$g = $this->goalMapper->findByUserProject($this->userId, $projectId);
		if ($g == null){
			$g = new Goal();
			$g->setProjectId($projectId);
			$g->setUserUid($this->userId);
			$g->setCreatedAt(time());
			$g->setHours($hours);
			$g->setInterval($interval);
			$this->goalMapper->insert($g);
		} else 	if ($g != null){
			return new JSONResponse(["Error" => "There can be only one goal per project"]);
		}


		return $this->getGoals();
	}

	/**
	 *
	 * @NoAdminRequired
	 */
	public function deleteGoal($id) {
		$c = $this->goalMapper->find($id);
		if ($c == null){
			return;
		}
		if ($c->userUid != $this->userId){
			return;
		}
		$this->goalMapper->delete($c);
		return $this->getGoals();
	}

	/**
	 * The user's time zone. Goal periods use it, like the report grouping in
	 * ReportItemMapper does.
	 */
	/**
	 * The time zone for report dates and timeline periods: the browser's
	 * (request parameter "timezone", e.g. "Europe/Berlin"), so they match the
	 * day boundaries the browser sends; otherwise (older clients) the user's
	 * Nextcloud setting.
	 */
	private function requestTimeZone(): \DateTimeZone {
		$name = $this->request->getParam('timezone');
		if (is_string($name) && $name !== '') {
			try {
				return new \DateTimeZone($name);
			} catch (\Exception $e) {
				// not a valid time zone name: fall back to the user's setting
			}
		}
		return $this->userTimeZone();
	}

	/** A date ("November 1, 2026") in the given time zone and the user's language. */
	private function formatDate(int $timestamp, \DateTimeZone $timeZone): string {
		$date = (new \DateTime('@' . $timestamp))->setTimezone($timeZone);
		return (string)$this->l10n->l('date', $date);
	}

	private function userTimeZone(): \DateTimeZone {
		return \OCP\Server::get(\OCA\TimeTracker\Service\UserTimeZone::class)->get($this->userId);
	}

	/**
	 * Monday 00:00 of the ISO week containing $timestamp, in the user's time zone.
	 */
	public function getStartOfWeek($timestamp){
		$date = (new \DateTime('@'.$timestamp))->setTimezone($this->userTimeZone());
		// "o" is the ISO year that week "W" belongs to: 30 Dec 2024 is in week 1 of 2025
		$date->setISODate((int)$date->format('o'), (int)$date->format('W'));
		$date->setTime(0, 0, 0, 0);

		return $date;
	}
	/**
	 * The 1st of the month containing $timestamp, 00:00 in the user's time zone.
	 */
	public function getStartOfMonth($timestamp){
		$date = (new \DateTime('@'.$timestamp))->setTimezone($this->userTimeZone());
		$date->modify('first day of this month');
		$date->setTime(0, 0, 0, 0);

		return $date;
	}

	public function getWeeksSince($timestamp){
		$start  = $this->getStartOfWeek($timestamp);
		$end = $this->getStartOfWeek(time());
		$oneWeek = \DateInterval::createFromDateString('1 week');
		$currentWeek = $start;
		$weeks = [];
		while ($currentWeek < $end){
			$weeks[] = $currentWeek->format("Y-m-d");
			$currentWeek->add($oneWeek);
		}
		return $weeks;
	}
	public function getMonthsSince($timestamp){
		$start  = $this->getStartOfMonth($timestamp);
		$end = $this->getStartOfMonth(time());
		$oneMonth = \DateInterval::createFromDateString('1 month');
		$currentMonth = $start;
		$months = [];
		while ($currentMonth < $end){
			$months[] = $currentMonth->format("Y-m");
			$currentMonth->add($oneMonth);
		}
		return $months;
	}
	/**
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */

	public function getGoals(){
		$goals = $this->goalMapper->findAll($this->userId);
		$weekStart = $this->getStartOfWeek(time())->format('Y-m-d');
		$monthStart = $this->getStartOfMonth(time())->format('Y-m');

		$ret = [];
		foreach($goals as $goal){
			$rgoal = [];
			if ($goal->interval == 'Weekly'){
				$goalWeekStart = $this->getStartOfWeek($goal->createdAt);
				$repItems = $this->reportItemMapper->report($this->userId, $goalWeekStart->getTimestamp(),time(),[$goal->projectId],"","","week","project","",false,0,10000);
				$intervals = $this->getWeeksSince($goalWeekStart->getTimestamp());
			} elseif ($goal->interval == 'Monthly'){
				$goalMonthStart = $this->getStartOfMonth($goal->createdAt);
				$repItems = $this->reportItemMapper->report($this->userId, $goalMonthStart->getTimestamp(),time(),[$goal->projectId],"","","month","project","",false,0,10000);
				$intervals = $this->getMonthsSince($goalMonthStart->getTimestamp());

			}
			$workedSecondsCurrentPeriod = 0;
			$debtSeconds = 0;
			foreach($intervals as $interval){
				$workedInInterval = 0;
				foreach($repItems as $repItem) {

					if ($goal->interval == 'Weekly'){
						if ($interval == $this->getStartOfWeek($repItem->time)->format('Y-m-d')) {
							$workedInInterval += $repItem->totalDuration;
						}
					} elseif ($goal->interval == 'Monthly'){
						if ($interval == $this->getStartOfMonth($repItem->time)->format('Y-m')) {
							$workedInInterval += $repItem->totalDuration;
						}
					}
				}
				$debtSeconds += ($goal->hours*3600 - $workedInInterval);
			}

			foreach($repItems as $period){
				if ($goal->interval == 'Weekly' && $this->getStartOfWeek($period->time)->format('Y-m-d') == $weekStart){
					$workedSecondsCurrentPeriod += $period->totalDuration;
				} elseif ($goal->interval == 'Monthly' && $this->getStartOfMonth($period->time)->format('Y-m') == $monthStart){
					$workedSecondsCurrentPeriod += $period->totalDuration;
				}
			}

			$rgoal = [
				'id' => $goal->id,
    			'userUid' => $goal->userUid,
    			'projectId' => $goal->projectId,
    			'projectName' => $goal->projectName,
    			'hours' => $goal->hours,
    			'interval' => $goal->interval,
				'createdAt'  => $goal->createdAt,
				'workedHoursCurrentPeriod'  => round($workedSecondsCurrentPeriod / 3600, 2),
				'debtHours'  => round($debtSeconds / 3600, 2),
				'remainingHours'  => round($goal->hours - $workedSecondsCurrentPeriod / 3600, 2),
				'totalRemainingHours'  => round(($debtSeconds + $goal->hours *3600 - $workedSecondsCurrentPeriod) / 3600, 2),
			];
			$ret[] = $rgoal;
		}
		return new JSONResponse(["Goals" => json_decode(json_encode($ret), true)]);
	}
}

