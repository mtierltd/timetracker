<?php
/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\TimeTracker\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */

 
return [
    'routes' => [
       ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
       ['name' => 'page#do_echo', 'url' => '/echo', 'verb' => 'POST'],
       ['name' => 'clients#index', 'url' => '/clients', 'verb' => 'GET'],
       ['name' => 'projects#index', 'url' => '/projects', 'verb' => 'GET'],
       ['name' => 'dashboard#index', 'url' => '/dashboard', 'verb' => 'GET'],
       ['name' => 'reports#index', 'url' => '/reports', 'verb' => 'GET'],
       ['name' => 'tags#index', 'url' => '/tags', 'verb' => 'GET'],

       ['name' => 'ajax#start_timer', 'url' => '/ajax/start-timer/{name}', 'verb' => 'POST'],
       ['name' => 'ajax#stop_timer', 'url' => '/ajax/stop-timer/{name}', 'verb' => 'POST'],
       ['name' => 'ajax#index', 'url' => '/ajax/', 'verb' => 'GET'],
       ['name' => 'ajax#work_intervals', 'url' => '/ajax/work-intervals', 'verb' => 'GET'],
       ['name' => 'ajax#update_work_interval', 'url' => '/ajax/update-work-interval/{id}', 'verb' => 'POST'],
       ['name' => 'ajax#add_work_interval', 'url' => '/ajax/add-work-interval/{name}', 'verb' => 'POST'],
       ['name' => 'ajax#delete_work_interval', 'url' => '/ajax/delete-work-interval/{id}', 'verb' => 'POST'],

       ['name' => 'ajax#get_clients', 'url' => '/ajax/clients', 'verb' => 'GET'],
       ['name' => 'ajax#add_client', 'url' => '/ajax/add-client/{name}', 'verb' => 'POST'],
       ['name' => 'ajax#edit_client', 'url' => '/ajax/edit-client/{id}', 'verb' => 'POST'],
       ['name' => 'ajax#delete_client', 'url' => '/ajax/delete-client/{id}', 'verb' => 'POST'],

       ['name' => 'ajax#get_projects', 'url' => '/ajax/projects', 'verb' => 'GET'],
       ['name' => 'ajax#get_projects_table', 'url' => '/ajax/projects-table', 'verb' => 'GET'],
       ['name' => 'ajax#add_project', 'url' => '/ajax/add-project/{name}', 'verb' => 'POST'],
       ['name' => 'ajax#edit_project', 'url' => '/ajax/edit-project/{id}', 'verb' => 'POST'],
       ['name' => 'ajax#delete_project', 'url' => '/ajax/delete-project/{id}', 'verb' => 'POST'],


       ['name' => 'ajax#get_tags', 'url' => '/ajax/tags', 'verb' => 'GET'],
       ['name' => 'ajax#add_tag', 'url' => '/ajax/add-tag/{name}', 'verb' => 'POST'],
       ['name' => 'ajax#edit_tag', 'url' => '/ajax/edit-tag/{id}', 'verb' => 'POST'],
       ['name' => 'ajax#delete_tag', 'url' => '/ajax/delete-tag/{id}', 'verb' => 'POST'],

       ['name' => 'ajax#get_report', 'url' => '/ajax/report', 'verb' => 'GET'],
    ]
];
