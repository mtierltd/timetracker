<?php

// Integration tests run inside a Nextcloud server: the app must be at
// <server>/apps/timetracker, installed and enabled.

if (!defined('PHPUNIT_RUN')) {
    define('PHPUNIT_RUN', 1);
}

require_once __DIR__ . '/../../../lib/base.php';

// Makes Test\TestCase from the server's tests available
require_once __DIR__ . '/../../../tests/autoload.php';

\OCP\Server::get(\OCP\App\IAppManager::class)->loadApp('timetracker');

OC_Hook::clear();
