<?php

namespace OCA\TimeTracker\AppInfo;

use OCA\TimeTracker\Capabilities;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {

  public const APP_ID = 'timetracker';

  public function __construct(array $urlParams=array()){
    parent::__construct(self::APP_ID, $urlParams);

    // Mappers and controllers are resolved by Nextcloud's autowiring from their
    // constructor type hints, so no services have to be registered by hand.
  }

  public function register(IRegistrationContext $context): void {
    // version and features for clients, see FEATURES.md
    $context->registerCapability(Capabilities::class);
  }

  public function boot(IBootContext $context): void {
  }
}
