<?php
namespace OCA\TimeTracker\Controller;
use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\IUserSession;

class ReportsController extends Controller {
	
/** @var IUserSession */
	protected $userSession;

	public function __construct($AppName, IRequest $request, IUserSession $userSession){
		parent::__construct($AppName, $request);
		
		$this->userSession = $userSession;
		$user = $this->userSession->getUser();

	}

	/**
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index() {
		return new TemplateResponse('timetracker', 'index',['appPage' => 'content/reports', 'script' => 'dist/reports']);  // templates/index.php
	}


}
