<?php

namespace OCA\TimeTracker\AppInfo;

use OCP\AppFramework\App;

class Application extends App {

  public function __construct(array $urlParams=array()){
    parent::__construct('timetracker', $urlParams);

    if (!\class_exists('\OCA\TimeTracker\AppFramework\Db\CompatibleMapper')) {
        if (\class_exists(\OCP\AppFramework\Db\Mapper::class)) {
            \class_alias(\OCP\AppFramework\Db\Mapper::class, 'OCA\TimeTracker\AppFramework\Db\CompatibleMapper');
        } else {
            \class_alias(\OCA\TimeTracker\AppFramework\Db\OldNextcloudMapper::class, 'OCA\TimeTracker\AppFramework\Db\CompatibleMapper');
        }
    }

    // Mappers and controllers are resolved by Nextcloud's autowiring from their
    // constructor type hints, so no services have to be registered by hand.
  }
}
