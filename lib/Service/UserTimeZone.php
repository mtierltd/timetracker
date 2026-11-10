<?php

namespace OCA\TimeTracker\Service;

use OCP\IConfig;
use OCP\IUserSession;

/**
 * The time zone that reports, timelines and goals use for day, week, month
 * and year boundaries: the user's Nextcloud setting, otherwise the server's
 * default time zone, otherwise UTC.
 *
 * OCP\IDateTimeZone isn't used for this: during a web request it can be
 * created before the user is logged in, and then always returns the
 * server's default.
 */
class UserTimeZone {

    private $config;
    private $userSession;

    public function __construct(IConfig $config, IUserSession $userSession) {
        $this->config = $config;
        $this->userSession = $userSession;
    }

    /**
     * @param string|null $userId default: the logged-in user
     */
    public function get(?string $userId = null): \DateTimeZone {
        if ($userId === null) {
            $user = $this->userSession->getUser();
            $userId = $user === null ? null : $user->getUID();
        }
        $name = $userId === null ? '' : $this->config->getUserValue($userId, 'core', 'timezone', '');
        foreach ([$name, $this->config->getSystemValueString('default_timezone', 'UTC')] as $candidate) {
            if ($candidate === '') {
                continue;
            }
            try {
                return new \DateTimeZone($candidate);
            } catch (\Exception $e) {
                // not a valid time zone name, try the next one
            }
        }
        return new \DateTimeZone('UTC');
    }
}
