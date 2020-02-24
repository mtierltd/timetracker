<?php
namespace OCA\TimeTracker\Controller;
use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\IUserSession;
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
	protected $request;
	protected $workIntervalToTagMapper;
	protected $reportItemMapper;
	protected $timelineMapper;
	protected $timelineEntryMapper;

	public function __construct($AppName, IRequest $request, IUserSession $userSession, 
							WorkIntervalMapper $workIntervalMapper, ClientMapper $clientMapper, UserToClientMapper $userToClientMapper,
							ProjectMapper $projectMapper, UserToProjectMapper $userToProjectMapper, TagMapper $tagMapper,WorkIntervalToTagMapper $workIntervalToTagMapper, ReportItemMapper $reportItemMapper, 
							TimelineMapper $timelineMapper, TimelineEntryMapper $timelineEntryMapper, $UserId){
		parent::__construct($AppName, $request);
		$this->userId = $UserId;
		$this->userSession = $userSession;
		$this->workIntervalMapper = $workIntervalMapper;
		$this->clientMapper = $clientMapper;
		$this->userToClientMapper = $userToClientMapper;

		$this->projectMapper = $projectMapper;
		$this->userToProjectMapper = $userToProjectMapper;

		$this->tagMapper = $tagMapper;

		$this->workIntervalToTagMapper = $workIntervalToTagMapper;
		$this->reportItemMapper = $reportItemMapper;

		$this->timelineMapper = $timelineMapper;
		$this->timelineEntryMapper = $timelineEntryMapper;

		$this->request =  $request;
	}

	/**
	 *
	 * @NoAdminRequired
	 */
	public function workIntervals() {
		$i = 0;
		$tryIntervals=[90,180,365,365*5];
		do {
			$l = $this->workIntervalMapper->findLatestDays($this->userId,$tryIntervals[$i],0);
			$i++;
		} while ((count($l) == 0) && ($i < count($tryIntervals)));
		$days = [];
		foreach ($l as $wi){
			//$dt = date("d/m/Y", $wi->start);
			$dt = date("D, j M", $wi->start);
			if (!isset($days[$dt])){
				$days[$dt] = [];
			}
			if (!isset($days[$dt][$wi->name])){
				$days[$dt][$wi->name] = ['children' => [], 'totalTime' => 0];
			}
			$project = null;
			if ($wi->projectId != null){
				$project = $this->projectMapper->find($wi->projectId);
			}
			
			$tags = [];
			$wiToTags = $this->workIntervalToTagMapper->findAllForWorkInterval($wi->id);
			foreach($wiToTags as $wiToTag){
				$tags[] = $this->tagMapper->find($wiToTag->tagId);
			}
			
			$wa = ['duration' => $wi->duration,
					'id' => $wi->id,
					'name' =>  $wi->name,
					'details' =>  $wi->details,
					'projectId' =>  $wi->projectId,
					'running' =>  $wi->running,
					'start' => $wi->start,
					'tags' => $tags,
					'userUid' => $wi->userUid,
					'projectName' => ($project === null)?null:$project->name,
			];
			$days[$dt][$wi->name]['children'][] = $wa;
			$days[$dt][$wi->name]['totalTime'] += $wa['duration'];
		}
		

		$running = $this->workIntervalMapper->findAllRunning($this->userId);
		return new JSONResponse(["WorkIntervals" => $l, "running" => $running, 'days' => $days, 'now' => time()]);
	}

	
	public function isThisAdminUser(){
		return \OC_User::isAdminUser(\OC_User::getUser());
	}

	public function isUserAdmin($user){
		return \OC_User::isAdminUser($user);
	}

	/**
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index() {
		
	}

	/**
	 *
	 * @NoAdminRequired
	 */

	public function startTimer($name) {
		//$this->endTimer();
		if (strlen($name) > 255){
			return new JSONResponse(["Error" => "Name too long"]);
		}
		$winterval = new WorkInterval();
		$winterval->setStart(time());
		$winterval->setRunning(1);
		$winterval->setName($name);
		$winterval->setUserUid($this->userId);
		$lwinterval = $this->workIntervalMapper->findLatestByName($this->userId, $name);
		if ($lwinterval != null){
			
			$winterval->setProjectId($lwinterval->projectId);
		}
		$this->workIntervalMapper->insert($winterval);
		if ($lwinterval != null){
			$tags = $this->workIntervalToTagMapper->findAllForWorkInterval($lwinterval->id);
			foreach($tags as $t){
				$wtot = new WorkIntervalToTag();
				$wtot->setWorkIntervalId($winterval->id);
				$wtot->setTagId($t->tagId);
				$wtot->setCreatedAt(time());
				$this->workIntervalToTagMapper->insert($wtot);
			}

		}
		
		
		//echo json_encode((array)$winterval);
		return new JSONResponse(["WorkIntervals" => $winterval, "running" => 1]);
		
	}





	/**
	 *
	 * @NoAdminRequired
	 */

	public function stopTimer($name) {
		if (strlen($name) > 255){
			return new JSONResponse(["Error" => "Name too long"]);
		}

		$running = $this->workIntervalMapper->findAllRunning($this->userId);
		
		$now = time();
		foreach($running as $r){
			$r->setRunning(0);
			$r->setDuration($now - $r->start);
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
		$wi = $this->workIntervalMapper->find($id);
		$this->workIntervalMapper->delete($wi);
		
		return new JSONResponse(["WorkIntervals" => json_decode(json_encode($running), true)]);
	}

	/**
	 *
	 * @NoAdminRequired
	 */

	public function updateWorkInterval($id) {
		
		$wi = $this->workIntervalMapper->find($id);
		
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
			$wi->setProjectId($this->request->projectId);
			if ($wi->projectId != null){
				$project = $this->projectMapper->find($wi->projectId);
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
			$tags = \explode(",", $this->request->tagId);
			$this->workIntervalToTagMapper->deleteAllForWorkInterval($id);
			$project = null;
			$locked = 0;
			

			foreach($tags as $tag){
				if (empty($tag))
					continue;
				$newWiToTag = new WorkIntervalToTag();
				$newWiToTag->setWorkIntervalId($id);
				$newWiToTag->setTagId($tag);
				$newWiToTag->setCreatedAt(time());
				//var_dump($newWiToTag);
				$this->workIntervalToTagMapper->insert($newWiToTag);

			}
		 }
		 if (isset($this->request->start)) {
			$tzoffset = 0;
			if (isset($this->request->tzoffset)) {
				$tzoffset = $this->request->tzoffset;
			}

			 date_default_timezone_set('UTC');
			 $dt = \DateTime::createFromFormat ( "d/m/y H:i",$this->request->start);
			 $dt->setTimeZone(new \DateTimeZone('UTC'));
			 $wi->setStart($dt->getTimestamp()+$tzoffset*60);
			 $de = \DateTime::createFromFormat ( "d/m/y H:i",$this->request->end);
			 $de->setTimeZone(new \DateTimeZone('UTC'));
			 $wi->setDuration($de->getTimestamp() - $dt->getTimestamp());
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
		
		if (isset($this->request->name)) {
			$wi->setName($this->request->name);
		}
		if (isset($this->request->details)) {
			if (strlen($this->request->details) > 1024){
				return new JSONResponse(["Error" => "Details too long"]);
			}
			$wi->setDetails($this->request->details);
		}
		if (isset($this->request->projectId)) {
			$wi->setProjectId($this->request->projectId);
			if ($wi->projectId != null){
				$project = $this->projectMapper->find($wi->projectId);
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
			$tags = \explode(",", $this->request->tagId);
			$this->workIntervalToTagMapper->deleteAllForWorkInterval($id);
			$project = null;
			$locked = 0;
			

			foreach($tags as $tag){
				if (empty($tag))
					continue;
				$newWiToTag = new WorkIntervalToTag();
				$newWiToTag->setWorkIntervalId($id);
				$newWiToTag->setTagId($tag);
				$newWiToTag->setCreatedAt(time());
				//var_dump($newWiToTag);
				$this->workIntervalToTagMapper->insert($newWiToTag);

			}
		 }
		 if (isset($this->request->start)) {
			$tzoffset = 0;
			if (isset($this->request->tzoffset)) {
				$tzoffset = $this->request->tzoffset;
			}

			 date_default_timezone_set('UTC');
			 $dt = \DateTime::createFromFormat ( "d/m/y H:i",$this->request->start);
			 $dt->setTimeZone(new \DateTimeZone('UTC'));
			 $wi->setStart($dt->getTimestamp()+$tzoffset*60);
			 $de = \DateTime::createFromFormat ( "d/m/y H:i",$this->request->end);
			 $de->setTimeZone(new \DateTimeZone('UTC'));
			 $wi->setDuration($de->getTimestamp() - $dt->getTimestamp());
		 }

		$this->workIntervalMapper->insert($wi);
		
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
		$c = $this->clientMapper->find($id);
		if ($c == null){
			return;
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
		$clients = $this->clientMapper->findAll($this->userId);
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
		if (trim($name) == ''){
			return;
		}
		$p = $this->projectMapper->findByName($name);
		if ($p == null){
			$p = new Project();
			$p->setName($name);
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

		$p = $this->projectMapper->find($id);
		if ($p == null){
			return;
		}
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
		if (isset($this->request->clientId)){
			$clientId = $this->request->clientId;
			$p->setClientId($clientId);
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
		$this->projectMapper->delete($id);

		
		return $this->getProjects();
	}

		
	/**
	 *
	 * @NoAdminRequired
	 */
	public function getProjects(){
		$projects = $this->projectMapper->findAll($this->userId);
		$parray = json_decode(json_encode($projects), true);
		foreach($parray as $pi => $pv){
			$tags = $this->tagMapper->findAllAlowedForProject($pv->id);
			$parray[$pi]['allowedtags'] = $tags;

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
		$c = $this->tagMapper->find($id);
		if ($c == null){
			return;
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
		$c = $this->tagMapper->find($id);
		if ($c == null){
			return;
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
		if ($workItem != null){
			$wi = $this->workIntervalMapper->find($workItem);
			if ($wi->projectId != null){
				$project = $this->projectMapper->find($wi->projectId);
				
			}
		}
		if($project != null && $project->locked){
			$tags = $this->tagMapper->findAllAlowedForProject($project->id);
		} else {
			$tags = $this->tagMapper->findAll($this->userId);
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

		if ($name == ''){
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
		$items = $this->reportItemMapper->report($name, $from, $to, $filterProjectId, $filterClientId, $filterTagId, $timegroup, $groupOn1, $groupOn2, $this->isThisAdminUser(), 0, 1000);
		return new JSONResponse(["items" => json_decode(json_encode($items), true), 'total' => count($items)]);
	}


	/**
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function postTimeline(){
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

		if ($name == ''){
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
		$items = $this->reportItemMapper->report($name, $from, $to, $filterProjectId, $filterClientId, $filterTagId, $timegroup, $groupOn1, $groupOn2, $this->isThisAdminUser(), 0, 1000);
		
		$timeline = new Timeline();
		$timeline->setUserUid($this->userId);
		$timeline->setGroup1($this->request->group1);
		$timeline->setGroup2($this->request->group2);
		$timeline->setTimeGroup($this->request->timegroup);
		$timeline->setFilterProjects(implode(', ',$filterProjectId));
		$timeline->setFilterClients(implode(', ',$filterClientId));
		$timeline->setTimeInterval(gmdate("d/m/Y", $from). ' - '. gmdate("d/m/Y", $to));
		$totalDuration = 0;
		foreach($items as $i){
			$totalDuration += $i->totalDuration;
		}

		$timeline->setTotalDuration($totalDuration);
		$timeline->setCreatedAt(time());
		$timeline->setStatus('pending');
		$this->timelineMapper->insert($timeline);
		foreach($items as $i){
			$te = new TimelineEntry();
			$te->setTimelineId($timeline->id);
			$te->setUserUid($timeline->userUid);
			$te->setName($i->name);
			$te->setProjectName($i->project);
			//$te->setClientName();
			$te->setTimeInterval($i->time);
			$te->setTotalDuration($i->totalDuration);
			$te->setCreatedAt(time());
			$this->timelineEntryMapper->insert($te);

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
		$timelines = $this->timelineMapper->findLatest();
		$parray = json_decode(json_encode($timelines), true);

		return new JSONResponse(["Timelines" => $parray, 'total' => count($parray)]);
	}


	public function secondsToTime($seconds){
		$hours = floor($seconds / 3600);
		$mins = floor($seconds / 60 % 60);
		$secs = floor($seconds % 60);
		$timeFormat = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
		return $timeFormat;
	}
	/**
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function downloadTimeline($id){
		$te = $this->timelineEntryMapper->findTimelineEntries($id);
		if (count($te) == 0){
			exit(0);
		}

		$user = $te[0]->userUid;

		// output headers so that the file is downloaded rather than displayed
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=timeline-'.$user.'-'.$id.'.csv');

		// create a file pointer connected to the output stream
		$output = fopen('php://output', 'w');

		// output the column headings
		fputcsv($output, array('id', 'User Uid', 'Name', 'Project Name', 'Client Name', 'Time Interval', 'Total Duration'));
		$totalDuration = 0;
		foreach($te as $t){
			
				fputcsv($output, [$t->id, $t->userUid, $t->name, $t->projectName, $t->clientName, $t->timeInterval, $this->secondsToTime($t->totalDuration)]);
				$totalDuration += $t->totalDuration;
		}
		fputcsv($output, ['TOTAL', '', '', '', '', '', $this->secondsToTime($totalDuration)]);
		fclose($output);
		exit(0);
	}


	/**
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function editTimeline($id){
		$timeline = $this->timelineMapper->find($id);
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
		
		return new JSONResponse(["Timeline" => $timeline]);
	}


}
