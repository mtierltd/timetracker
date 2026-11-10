<?php

namespace OCA\TimeTracker\Tests\Integration\Service;

use OCA\TimeTracker\Service\UserTimeZone;
use OCP\IConfig;
use Test\TestCase;

/**
 * @group DB
 */
#[\PHPUnit\Framework\Attributes\Group('DB')]
class UserTimeZoneTest extends TestCase {

    /** @var IConfig */
    private $config;
    /** @var string */
    private $user;
    /** @var mixed */
    private $defaultTimeZone;

    protected function setUp(): void {
        parent::setUp();
        $this->config = \OCP\Server::get(IConfig::class);
        $this->user = 'tt-it-' . bin2hex(random_bytes(4));
        $this->defaultTimeZone = $this->config->getSystemValue('default_timezone', null);
    }

    protected function tearDown(): void {
        $this->config->deleteUserValue($this->user, 'core', 'timezone');
        if ($this->defaultTimeZone === null) {
            $this->config->deleteSystemValue('default_timezone');
        } else {
            $this->config->setSystemValue('default_timezone', $this->defaultTimeZone);
        }
        parent::tearDown();
    }

    private function timeZone(): string {
        return \OCP\Server::get(UserTimeZone::class)->get($this->user)->getName();
    }

    public function testUsesTheUserSetting(): void {
        $this->config->setSystemValue('default_timezone', 'America/New_York');
        $this->config->setUserValue($this->user, 'core', 'timezone', 'Europe/Berlin');
        $this->assertSame('Europe/Berlin', $this->timeZone());
    }

    public function testFallsBackToTheServerDefault(): void {
        $this->config->setSystemValue('default_timezone', 'America/New_York');
        $this->assertSame('America/New_York', $this->timeZone());

        $this->config->setUserValue($this->user, 'core', 'timezone', 'Not/AZone');
        $this->assertSame('America/New_York', $this->timeZone());
    }

    public function testFallsBackToUtc(): void {
        $this->config->deleteSystemValue('default_timezone');
        $this->assertSame('UTC', $this->timeZone());

        $this->config->setSystemValue('default_timezone', 'Not/AZone');
        $this->assertSame('UTC', $this->timeZone());
    }
}
