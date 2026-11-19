# This file is licensed under the Affero General Public License version 3 or
# later. See the COPYING file.
# @author Bernhard Posselt <dev@bernhard-posselt.com>
# @copyright Bernhard Posselt 2016

# Build, package and test the Time Tracker app.
#
#   make                   build the frontend (js/dist)
#   make appstore          build the App Store archive (see RELEASING.md)
#   make test              run the unit tests
#   make test-integration  run the integration tests (inside a Nextcloud server)
#   make clean             remove build output
#
# Requires: make, git, tar, npm, composer, php.

app_id=$(shell sed -n 's:.*<id>\(.*\)</id>.*:\1:p' appinfo/info.xml | head -n 1)
appstore_build_directory=$(CURDIR)/build/artifacts/appstore
appstore_stage=$(appstore_build_directory)/$(app_id)

.PHONY: all build appstore composer-dev test test-integration clean
all: build

# Builds the frontend into js/dist
build:
	cd js && npm ci --no-audit --no-fund && npm run build

# Builds the App Store archive build/artifacts/appstore/<app id>.tar.gz: the
# files committed at HEAD plus the built frontend (js/dist), without
# development files, in a single top folder named after the app id. Using
# "git archive" keeps local, uncommitted files out of the release.
# Code signing (appinfo/signature.json) happens in the release workflow.
appstore:
	rm -rf $(appstore_build_directory)
	mkdir -p $(appstore_stage)
	rm -rf js/dist
	cd js && npm ci --no-audit --no-fund && npm run build
	git archive HEAD | tar -x -C $(appstore_stage)
	cp -r js/dist $(appstore_stage)/js/dist
	cd $(appstore_stage) && rm -rf .github .gitignore tests build Makefile \
		composer.json composer.lock phpunit.xml phpunit.integration.xml phpstan.neon phpstan-baseline.neon \
		js/src js/package.json js/package-lock.json js/webpack.config.js \
		img/screenshots
	tar --owner=0 --group=0 --sort=name -czf $(appstore_build_directory)/$(app_id).tar.gz -C $(appstore_build_directory) $(app_id)
	@echo "Built $(appstore_build_directory)/$(app_id).tar.gz"

# Installs the development dependencies (phpunit, phpstan, nextcloud/ocp stubs)
composer-dev:
	composer install --prefer-dist

# Runs the unit tests; they only need the nextcloud/ocp stubs, no server
test: composer-dev
	composer run test:unit

# Runs the integration tests. The app must be in a Nextcloud server's apps/
# (or custom_apps/) directory, installed and enabled, with the server's own
# PHPUnit installed (composer install --working-dir=<server>/vendor-bin/phpunit),
# as in .github/workflows/integration.yml.
test-integration:
	../../vendor-bin/phpunit/vendor/bin/phpunit -c phpunit.integration.xml

# Removes build output
clean:
	rm -rf build js/dist
