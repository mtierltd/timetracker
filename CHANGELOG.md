# Changelog

All notable changes to Time Tracker are listed here. Versions follow
[semantic versioning](https://semver.org): patch releases fix bugs,
minor releases add features, major releases contain breaking changes
(see [RELEASING.md](RELEASING.md)).

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
