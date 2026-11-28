<?php

namespace OCA\TimeTracker\AppInfo;

use OCA\TimeTracker\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {

  public function __construct(array $urlParams=array()){
    parent::__construct('timetracker', $urlParams);

    // Mappers and controllers are resolved by Nextcloud's autowiring from their
    // constructor type hints, so no services have to be registered by hand.
  }

  public function register(IRegistrationContext $context): void {
    $context->registerNotifierService(Notifier::class);
  }

  public function boot(IBootContext $context): void {
  }
}
