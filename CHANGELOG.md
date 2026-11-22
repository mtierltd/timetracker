# Changelog

All notable changes to Time Tracker are listed here. Versions follow
[semantic versioning](https://semver.org): patch releases fix bugs,
minor releases add features, major releases contain breaking changes
(see [RELEASING.md](RELEASING.md)).

## Unreleased

### Changed
- Requires Nextcloud 33 to 35 and PHP 8.2 or newer (#329). Nextcloud 31 and 32 are no longer maintained by Nextcloud; instances on those versions stay on 1.0.3.

## 1.0.3 – 2026-11-22

The last release for Nextcloud 31 and 32: the next minor release requires Nextcloud 33 (#329).

### Fixed
- Dashboard, Reports and Timelines: entries of the chosen "to" date were missing, because the range ended at 00:00 of that day (#323, #352).
- Timelines: the period was formatted in UTC, so it started a day early east of UTC and ended a day late west of it. Report and timeline rows are now grouped by the days of the browser's time zone (#323, #354).
- Timeline CSV exports showed a timestamp instead of a date in the "Time Interval" column (#354).
- Timer list, Reports, Dashboard and Timelines: entries starting exactly at the beginning of the range (e.g. a manual entry at 00:00) were missing (#358).
- Goals: a goal with an interval other than weekly/monthly, or without hours, showed the figures of another goal and caused PHP warnings. Such goals are now marked as invalid, and adding a goal checks the interval and hours (#356, #357).

### Security
- Adding a project accepted a client of another user (#358).

## 1.0.2 – 2026-11-19

### Fixed
- Reports and timelines included at most 1,000 rows: rows were missing in the table and in downloads, totals were too low, and saved timelines were incomplete. Now all rows are included; the table shows the first 1,000 with a notice (#336, #343).
- Timer: the list of entries loads much faster with many entries (projects and tags are loaded at once instead of per entry), and an entry whose project no longer exists no longer breaks the list (#338, #344).
- Timer: loading the list no longer changes PHP's default time zone for the rest of the request (#339, #342).

### Changed
- The release archive is about four times smaller: production builds no longer include source maps (#331, #348).
- The licence is declared as AGPL-3.0-or-later in `package.json` and `composer.json` (#334, #345).
- Removed dead code, unused files and unused build dependencies; the Makefile and README describe the current development setup (#332, #335, #346, #347).
- CI builds the frontend on every pull request (#340, #341).

## 1.0.1 – 2026-11-18

### Fixed
- Goals: adding a goal in the web app failed with "Not found" in 1.0.0. Before 1.0.0, goals added there were saved without project, hours and interval (#322).

### Changed
- App Store and Apps page: English-only, up-to-date description with links to the website and repository; `info.xml` now validates against the App Store schema (#311, #320).
- New screenshots of the current interface (#324).
- Releases are built, code-signed and published to the App Store by a GitHub workflow, and PRs check the version (#318). The release process is documented in `RELEASING.md` (#319).

## 1.0.0 – 2026-11-18

First stable release. Changes since 0.0.94.

### Security
- Endpoints now check that data belongs to the logged-in user (#316). Before, any logged-in user could:
  - read other users' entries through reports;
  - download, email or list other users' timelines;
  - change or delete other users' time entries, tags and clients;
  - rename projects and change their colour and client;
  - change the status of any timeline.
- Creating timelines and changing their status require Nextcloud's CSRF token again (#316).
- Emailing a timeline: 1–10 valid recipients, at most 10 emails per hour (#316).
- CSV exports no longer let entry names run as spreadsheet formulas (#316).

### Added
- Timer: entries with the same name on the same day are grouped, with the number of entries and a subtotal (#299).
- Timer: groups can be folded, one at a time or all at once; folded groups are remembered per browser.
- Timer: start and end time of every entry, clickable to edit (#307).
- Timer: total time per day next to the date, live while a timer runs (#307).

### Fixed
- Times entered or edited for a date on the other side of a daylight saving change were shifted by an hour (#151).
- Timer: entries started after opening the page (start bar or resume) didn't appear until the date range was changed.
- Manual entries created through the API with tags (e.g. by the Android app) lost them.
- Manual entries and timeline filters ignored JSON request fields (#306).
- Goals: weekly goals now use the user's time zone and the ISO week year.
- Reports: grouping now gives the same results on every database.

### Changed
- All database access uses Nextcloud's query builder (QBMapper) (#304).
- Integration tests run on SQLite, MySQL, MariaDB and PostgreSQL.
