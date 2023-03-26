<?php

namespace OCA\TimeTracker\AppInfo;

require_once __DIR__ . '/../../vendor/autoload.php';

use \OCP\AppFramework\App;
use \OCP\AppFramework\IAppContainer;


use \OCA\TimeTracker\Controller\AjaxController;
//use \OCA\TimeTracker\Service\AuthorService;
//use \OCA\TimeTracker\Db\AuthorMapper;
use OCA\TimeTracker\Db\WorkIntervalMapper;
use OCA\TimeTracker\Controller\PageController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use OCP\IL10N;

class Application extends App {


  /**
   * Define your dependencies in here
   */
  public function __construct(array $urlParams=array()){
    parent::__construct('timetracker', $urlParams);

    if (!\class_exists('\OCA\TimeTracker\AppFramework\Db\CompatibleMapper')) {
        if (\class_exists(\OCP\AppFramework\Db\Mapper::class)) {
            \class_alias(\OCP\AppFramework\Db\Mapper::class, 'OCA\TimeTracker\AppFramework\Db\CompatibleMapper');
        } else {
            \class_alias(\OCA\TimeTracker\AppFramework\Db\OldNextcloudMapper::class, 'OCA\TimeTracker\AppFramework\Db\CompatibleMapper');
        }
    }

    $container = $this->getContainer();
    /**
     * Controllers
     */
    
    

    $container->registerService('WorkIntervalMapper', function($c){
      return new WorkIntervalMapper(
        $c->query('ServerContainer')->getDatabaseConnection()
      );
    });
    $container->registerService('ReportItemMapper', function($c){
      return new WorkIntervalMapper(
        $c->query('ServerContainer')->getDatabaseConnection()
      );
    });

  }
}
