# Features across clients

Which user-facing features exist in which client, and the capability id that
tells a client whether the server offers a feature. Update this file in every
PR that changes a user-facing feature or the client API (see the PR template).

| Status | Meaning |
|---|---|
| ✅ | done |
| 🚧 | in progress, with a link to the issue or PR |
| ⬜ | planned |
| ➖ | not planned for this client |

Clients: **Web** (this repository), **Android** ([nc-timetracker-android](https://github.com/Martin-1997/nc-timetracker-android)), **iOS** (planned), **Browser extension** (planned, [android#18](https://github.com/Martin-1997/nc-timetracker-android/issues/18)).

## Timer

| Feature | Capability id | API | Web | Android | iOS | Browser ext. |
|---|---|---|---|---|---|---|
| Start and stop the timer | `timer` | v1 | ✅ | ✅ | ⬜ | ⬜ |
| Resume an earlier entry (name, project, tags) | `timer` | v1 | ✅ | ✅ | ⬜ | ⬜ |
| Manual entries | `manual_entries` | v1 | ✅ | ✅ | ⬜ | ➖ |
| Edit an entry's name, details, start and end time | `timer` | v1 | ✅ | ✅ | ⬜ | ➖ |
| Project and tags per entry | `timer` | v1 | ✅ | ✅ | ⬜ | ⬜ |
| Cost per entry | `costs` | v1 | ✅ | ✅ | ⬜ | ➖ |
| Manual and edited times use the UTC offset of their own date (#151) | `entry_time_offsets` | v1 | ✅ | ⬜ | ⬜ | ➖ |
| Start and end time per entry, total per day | – | – | ✅ | ⬜ | ⬜ | ➖ |
| Group same-named entries per day, with count and subtotal | – | – | ✅ | ⬜ | ⬜ | ➖ |
| Fold groups | – | – | ✅ | ⬜ | ⬜ | ➖ |
| Pinned timers | `pinned_timers` | v1 | 🚧 [#310](https://github.com/mtierltd/timetracker/pull/310) | ⬜ [android#15](https://github.com/Martin-1997/nc-timetracker-android/issues/15) | ⬜ | ⬜ |

## Organise

| Feature | Capability id | API | Web | Android | iOS | Browser ext. |
|---|---|---|---|---|---|---|
| Projects: colour, client, archive | `projects` | v1 | ✅ | ✅ | ⬜ | ➖ |
| Locked projects with allowed users and tags (admins) | `locked_projects` | v1 | ✅ | ✅ | ⬜ | ➖ |
| Delete a project with its entries (admins) | `projects` | v1 | ✅ | ✅ | ⬜ | ➖ |
| Clients | `clients` | v1 | ✅ | ✅ | ⬜ | ➖ |
| Tags | `tags` | v1 | ✅ | ✅ | ⬜ | ➖ |

## Analyse

| Feature | Capability id | API | Web | Android | iOS | Browser ext. |
|---|---|---|---|---|---|---|
| Dashboard: time and cost per client and project | `reports` | v1 | ✅ | ✅ | ⬜ | ➖ |
| Reports: grouping, intervals, project and client filters | `reports` | v1 | ✅ | ✅ | ⬜ | ➖ |
| Export reports as CSV and JSON | – | – | ✅ | ✅ | ⬜ | ➖ |
| Timelines: save a report, download as CSV, email, delete | `timelines` | v1 | ✅ | ✅ | ⬜ | ➖ |
| Timelines Admin: all timelines, change their status (admins) | `timelines_admin` | v1 | ✅ | ✅ | ⬜ | ➖ |
| Goals: weekly or monthly hours per project | `goals` | v1 | ✅ | ✅ | ⬜ | ➖ |

## Capability ids

The server publishes its version, the API versions and these ids as Nextcloud capabilities (`lib/Capabilities.php`, #313). Clients read them after login from `/ocs/v2.php/cloud/capabilities`, and hide features whose id the server doesn't list:

```json
"timetracker": {
  "version": "1.0.1",
  "api": ["v1"],
  "features": ["timer", "manual_entries", "costs", "entry_time_offsets", "projects", "locked_projects", "clients", "tags", "reports", "timelines", "timelines_admin", "goals"]
}
```

A unit test checks that every published id is documented below, and that every available id below is published.

- **What gets an id:** a feature the server's API offers. Features that only change how one client shows data (e.g. grouping in the web Timer view) get none (`–` above).
- **Stability:** ids are never renamed. An id is only removed together with its feature, in a major release.
- **Older servers:** servers without the `timetracker` capabilities block (1.0.1 and older) offer every feature marked ✅ for Web above except `entry_time_offsets`.

| Id | Meaning | Since |
|---|---|---|
| `timer` | start, stop and resume timers; edit, delete and assign project and tags to entries | before 1.0.0 |
| `manual_entries` | add entries with start and end time (`add-work-interval`) | before 1.0.0 |
| `costs` | cost per entry (`add-cost`) | before 1.0.0 |
| `entry_time_offsets` | `add-work-interval` and `update-work-interval` accept `tzoffsetEnd`, the UTC offset on the end date (#151) | 1.0.0 |
| `projects` | projects with colour, client and archiving | before 1.0.0 |
| `locked_projects` | locked projects with allowed users and tags | before 1.0.0 |
| `clients` | clients | before 1.0.0 |
| `tags` | tags | before 1.0.0 |
| `reports` | reports (`report`), also used by the dashboard | before 1.0.0 |
| `timelines` | timelines: create, list, download, email, delete | before 1.0.0 |
| `timelines_admin` | list all timelines and change their status (admins) | before 1.0.0 |
| `goals` | goals per project | before 1.0.0 |
| `pinned_timers` | pinned timers (`pinned-timers`, `start-pinned-timer`, …) | with #310 |

## API versions

`v1` is the set of endpoints under `/apps/timetracker/ajax/` in `appinfo/routes.php`. Incompatible changes to it (removed or renamed endpoints or parameters, changed response shapes) need a new API version and a major release (see [RELEASING.md](RELEASING.md)).
