<?php

namespace OCA\TimeTracker\AppInfo;

use OCP\AppFramework\App;

class Application extends App {

  public function __construct(array $urlParams=array()){
    parent::__construct('timetracker', $urlParams);

    // Mappers and controllers are resolved by Nextcloud's autowiring from their
    // constructor type hints, so no services have to be registered by hand.
  }
}
