<?php

// OCA\TimeTracker\AppInfo\Application aliases CompatibleMapper at runtime,
// picking OldNextcloudMapper because OCP\AppFramework\Db\Mapper was removed
// in Nextcloud 34 (see lib/AppInfo/Application.php). Static analysis can't
// see a runtime class_alias(), so mirror it here for PHPStan's reflection.
if (!class_exists(\OCA\TimeTracker\AppFramework\Db\CompatibleMapper::class)) {
    class_alias(
        \OCA\TimeTracker\AppFramework\Db\OldNextcloudMapper::class,
        \OCA\TimeTracker\AppFramework\Db\CompatibleMapper::class
    );
}
