# Time Tracker

Time tracking for Nextcloud: a timer, projects, clients and tags, reports, timelines and goals. Install it from the [Nextcloud App Store](https://apps.nextcloud.com/apps/timetracker) or the Apps page of your Nextcloud.

## Development setup

Clone the repository into the `apps/` (or `custom_apps/`) directory of a Nextcloud server (see `appinfo/info.xml` for the supported versions), then build the frontend. In the app directory:

    (cd js && npm ci && npm run build)

or `make`, which does the same. Then enable the app, from the Nextcloud root directory and as the web server user (e.g. `www-data`):

    sudo -u www-data php occ app:enable timetracker

With Docker, run `occ` in the Nextcloud container instead, e.g. for Nextcloud AIO: `docker exec --user www-data nextcloud-aio-nextcloud php occ app:enable timetracker`.

A git checkout gets no updates from the App Store (Nextcloud skips app directories that contain `.git`), so use it for development only.

The frontend is a Vue 3 app in `js/src`, built with webpack into `js/dist`. Run `npm run build` again after changing it. The PHP code is in `lib/` and needs no build step.

Requirements: PHP and Composer (for the development tools and tests), Node.js with npm (the release workflow uses Node.js 24).


## Features and clients

Which features exist in the web app, the Android app and planned clients, and how clients find out what a server supports: [FEATURES.md](FEATURES.md).

## Versioning and releases

Time Tracker uses semantic versioning. How versions are numbered and how a release is built, signed and published to the App Store is described in [RELEASING.md](RELEASING.md).

## Running tests

**Unit tests** (no Nextcloud server needed, they use the `nextcloud/ocp` stubs):

    composer install
    composer run test:unit

**Integration tests** run against a real database, inside a Nextcloud server: the app must be installed and enabled there, and the server's PHPUnit installed (`composer install --working-dir=<server>/vendor-bin/phpunit`). Then, in the app directory:

    make test-integration

**Static analysis and linting:**

    composer run phpstan
    composer run lint

CI runs all of these on every pull request: integration tests on SQLite, MySQL, MariaDB and PostgreSQL, with the oldest and newest supported Nextcloud versions (`.github/workflows/`).

# Android App

You can try to use the new Android app for the TimeTracker. Please provide feedback, as it is still in development: https://github.com/Martin-1997/nc-timetracker-android
