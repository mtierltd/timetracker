<?php

namespace OCA\TimeTracker\Tests\Integration\Controller;

use OCA\TimeTracker\Controller\AjaxController;
use OCA\TimeTracker\Db\ClientMapper;
use OCA\TimeTracker\Db\GoalMapper;
use OCA\TimeTracker\Db\GoalSkipMapper;
use OCA\TimeTracker\Db\ProjectMapper;
use OCA\TimeTracker\Db\ReportItemMapper;
use OCA\TimeTracker\Db\TagMapper;
use OCA\TimeTracker\Db\TimelineEntryMapper;
use OCA\TimeTracker\Db\TimelineMapper;
use OCA\TimeTracker\Db\UserToClientMapper;
use OCA\TimeTracker\Db\UserToProjectMapper;
use OCA\TimeTracker\Db\WorkIntervalMapper;
use OCA\TimeTracker\Db\WorkIntervalToTagMapper;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;

/**
 * Creates an AjaxController for a user, with Nextcloud's real request class
 * and real mappers, like in production.
 */
trait CreatesAjaxController {

	/**
	 * @param array<string, mixed> $params request parameters (form fields)
	 */
	private function ajaxController(string $user, array $params = [], ?IMailer $mailer = null): AjaxController {
		$request = new \OC\AppFramework\Http\Request(
			['post' => $params, 'get' => $params, 'method' => 'GET'],
			\OCP\Server::get(\OCP\IRequestId::class),
			\OCP\Server::get(\OCP\IConfig::class)
		);
		return new AjaxController('timetracker', $request,
			\OCP\Server::get(IUserSession::class),
			\OCP\Server::get(IFactory::class)->get('timetracker'),
			\OCP\Server::get(WorkIntervalMapper::class), \OCP\Server::get(ClientMapper::class), \OCP\Server::get(UserToClientMapper::class),
			\OCP\Server::get(ProjectMapper::class), \OCP\Server::get(UserToProjectMapper::class), \OCP\Server::get(TagMapper::class),
			\OCP\Server::get(WorkIntervalToTagMapper::class), \OCP\Server::get(ReportItemMapper::class),
			\OCP\Server::get(TimelineMapper::class), \OCP\Server::get(TimelineEntryMapper::class), \OCP\Server::get(GoalMapper::class), \OCP\Server::get(GoalSkipMapper::class),
			\OCP\Server::get(IGroupManager::class), $mailer ?? \OCP\Server::get(IMailer::class), \OCP\Server::get(IDBConnection::class), $user);
	}
}
