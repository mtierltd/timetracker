<?php

namespace OCA\TimeTracker\Tests\Unit\Controller;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCA\TimeTracker\Controller\PageController;
use PHPUnit\Framework\TestCase;

class PageControllerTest extends TestCase {
	private $controller;
	private $userId = 'john';

	public function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($this->userId);
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$this->controller = new PageController(
			'timetracker', $request, $userSession
		);
	}

	public function testIndex(): void {
		$result = $this->controller->index();

		$this->assertEquals('spa', $result->getTemplateName());
		$this->assertInstanceOf(TemplateResponse::class, $result);
	}

}
