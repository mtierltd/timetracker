# Releasing Time Tracker

How versions are numbered, and how a release gets from `master` to the
Nextcloud App Store. Background and decisions: #315.

## Version numbers

Time Tracker uses [semantic versioning](https://semver.org): `MAJOR.MINOR.PATCH`.

| Raise | When | Example |
|---|---|---|
| PATCH | bug and security fixes only | 1.0.0 → 1.0.1 |
| MINOR | new features; database changes that keep existing data and APIs working | 1.0.1 → 1.1.0 |
| MAJOR | breaking changes, e.g. a changed or removed API the Android app depends on, or removed data or settings | 1.4.2 → 2.0.0 |

- When in doubt between two levels, take the higher one.
- The App Store only offers updates to higher versions, so a version is never reused or lowered.
- Pre-release suffixes (`1.1.0-beta.1`) aren't used.
- The supported Nextcloud versions are a separate setting (`<nextcloud min-version max-version>` in `appinfo/info.xml`) and don't affect the app version.

### Supported Nextcloud and PHP versions

The app supports the Nextcloud versions that Nextcloud itself maintains ([schedule](https://github.com/nextcloud/server/wiki/Maintenance-and-Release-Schedule)), and the PHP versions those support that are still maintained by PHP.

- When a Nextcloud version reaches its end of life, raise `<nextcloud min-version>` in the next minor release. The App Store then no longer offers new releases to that version; mention it in the release notes.
- When a new Nextcloud version is released, test it and raise `max-version`.
- Raise `<php min-version>` when a PHP version reaches its end of life (or when the oldest supported Nextcloud drops it).
- Keep the CI matrices in sync: integration tests (`integration.yml`) and phpstan stubs (`static-analysis.yml`) on the oldest and newest supported Nextcloud; lint and unit tests (`lint-php.yml`, `phpunit.yml`) on all supported PHP versions; `phpVersion` in `phpstan.neon`. When raising the minimum PHP, also update `config.platform.php` in `composer.json`, then run `composer update --lock`. The phpstan job removes that pin before installing newer stubs (see the comment in `static-analysis.yml`), so keep that step.

## Where the version lives

- **`appinfo/info.xml` (`<version>`) is the single source of truth.**
- `js/package.json` (`version`) has the same value.
- The Git tag is `v` + the version, e.g. `v1.1.0`, on the release commit on `master`.

**Feature and fix PRs don't change the version.** Only a release PR does. CI enforces this (`version-check.yml`, using `.github/scripts/check-version.sh`):
- both files have the same `MAJOR.MINOR.PATCH` version;
- if a PR changes the version, the new version is higher than on `master` and than the newest `v*` tag.

The release workflow also checks that the tag matches the version.

### Database migrations

Nextcloud runs an app's new migrations when the installed version is lower than the code's version.
- **For users,** this happens with the release that contains the migration. Nothing to do.
- **On a development or test instance,** a merged PR with a migration doesn't raise the version, so its migration doesn't run on an existing installation. Raise the version by hand in `appinfo/info.xml` (don't commit it) and run `occ upgrade`, or test on a fresh installation.

## Making a release

### 1. Release PR

On a branch `release/X.Y.Z` from `master`:
1. Set the version in `appinfo/info.xml` and `js/package.json`.
2. Add a section `## X.Y.Z – YYYY-MM-DD` at the top of `CHANGELOG.md`. Use the headings **Security**, **Added**, **Changed**, **Fixed** and **Removed** as needed, with one line per change and its issue or PR number.
3. Open the PR "Release X.Y.Z" and merge it once CI passes.

### 2. Tag and GitHub release

On the merge commit of the release PR:

```sh
git switch master && git pull
git tag -a vX.Y.Z -m "Time Tracker X.Y.Z"
git push origin vX.Y.Z
```

Then create a GitHub release from the tag (Releases → Draft a new release). Release notes: what changed for users, security fixes first, with a link to `CHANGELOG.md`.

### 3. Archive, signing and App Store

**Automatically:** publishing the GitHub release starts `.github/workflows/appstore-build-publish.yml`. It:
1. checks that the tag matches the version;
2. builds the archive with `make appstore`;
3. code-signs it (`appinfo/signature.json`);
4. attaches `timetracker.tar.gz` to the release;
5. uploads it to the App Store.

The run summary on the Actions page says what was done. It needs two repository secrets, which only repository admins can set:

| Secret | Content |
|---|---|
| `APP_PRIVATE_KEY` | the app's private signing key (`timetracker.key`) |
| `APPSTORE_TOKEN` | App Store API token of an account allowed to publish the app (apps.nextcloud.com → Account → API token) |

Without `APP_PRIVATE_KEY`, the workflow only builds and keeps the unsigned archive as a workflow artifact. Without `APPSTORE_TOKEN`, the summary shows the download URL and the signature for uploading by hand. "Run workflow" on the Actions page is a dry run that publishes nothing.

**By hand,** if the secrets aren't set. You need the private key `timetracker.key` and a Nextcloud server checkout (any installed or uninstalled server of the minimum supported version, in a writable folder).

```sh
git switch master && git pull && git checkout vX.Y.Z

# 1. Build: files committed at the tag + the built frontend, in build/artifacts/appstore/
make appstore

# 2. Code-sign the app (writes appinfo/signature.json); the public certificate is
#    https://github.com/nextcloud/app-certificate-requests/raw/master/timetracker/timetracker.crt
php <server>/occ integrity:sign-app \
  --privateKey="$HOME/.nextcloud/certificates/timetracker.key" \
  --certificate="$HOME/.nextcloud/certificates/timetracker.crt" \
  --path="$PWD/build/artifacts/appstore/timetracker"

# 3. Pack again, now with signature.json
tar --owner=0 --group=0 --sort=name -czf build/artifacts/appstore/timetracker.tar.gz \
  -C build/artifacts/appstore timetracker

# 4. Signature of the archive, for the App Store
openssl dgst -sha512 -sign "$HOME/.nextcloud/certificates/timetracker.key" \
  build/artifacts/appstore/timetracker.tar.gz | openssl base64 -A
```

Then:
1. Attach `build/artifacts/appstore/timetracker.tar.gz` to the GitHub release.
2. At [apps.nextcloud.com → Upload app release](https://apps.nextcloud.com/developer/apps/releases/new), enter the asset's download URL (`https://github.com/mtierltd/timetracker/releases/download/vX.Y.Z/timetracker.tar.gz`) and the signature from step 4.

Don't use GitHub's automatic "Source code" archives for the App Store. Their top folder is `timetracker-X.Y.Z/` instead of `timetracker/`, and they lack the built frontend (`js/dist/`).

### 4. Check

- The App Store shows the new version (apps.nextcloud.com/apps/timetracker).
- An instance updates to it, and the Timer view loads without errors.
