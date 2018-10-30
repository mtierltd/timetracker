<?php

\OC::$server->getNavigationManager()->add(function () {
    $urlGenerator = \OC::$server->getURLGenerator();
    return [
        // the string under which your app will be referenced in Nextcloud
        'id' => 'timetracker',

        // sorting weight for the navigation. The higher the number, the higher
        // will it be listed in the navigation
        'order' => 10,

        // the route that will be shown on startup
        'href' => $urlGenerator->linkToRoute('timetracker.page.index'),

        // the icon that will be shown in the navigation
        // this file needs to exist in img/
        'icon' => $urlGenerator->imagePath('timetracker', 'app.svg'),

        // the title of your application. This will be used in the
        // navigation or on the settings page of your app
        'name' => \OC::$server->getL10N('timetracker')->t('Time Tracker'),
    ];
});

// execute OCA\MyApp\BackgroundJob\Task::run when cron is called
//\OC::$server->getJobList()->add('OCA\MyApp\BackgroundJob\Task');

// execute OCA\MyApp\Hooks\User::deleteUser before a user is being deleted
//\OCP\Util::connectHook('OC_User', 'pre_deleteUser', 'OCA\MyApp\Hooks\User', 'deleteUser');