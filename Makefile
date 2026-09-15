.PHONY: assets watch build clean

ROOT_DIR := $(abspath ../../../)

APP_ENV_BAK   := $(APP_ENV)
APP_DEBUG_BAK := $(APP_DEBUG)
ifneq (,$(wildcard $(ROOT_DIR)/.env))
	include $(ROOT_DIR)/.env
endif
ifneq (,$(wildcard $(ROOT_DIR)/.env.$(APP_ENV)))
	include $(ROOT_DIR)/.env.$(APP_ENV)
endif
ifneq ($(strip $(APP_ENV_BAK)),)
	APP_ENV := $(APP_ENV_BAK)
endif
ifneq ($(strip $(APP_DEBUG_BAK)),)
	APP_DEBUG := $(APP_DEBUG_BAK)
endif
export APP_ENV APP_DEBUG

# Compiled assets, what the application serves from /bundles/base.
#
# Built at the bundle root, where package.json and node_modules live (not in
# assets/, which only holds the sources). This is the target the application's
# `make build-vendor glitchr/base-bundle` runs; it used to be missing, so that
# command printed "Nothing to be done for 'assets'", exited 0 and left the old
# bundle in place.
#
# Always a production build, whatever APP_DEBUG says: webpack.config.js cleans
# the output directory before every build and only versions filenames in
# production, so a dev build would delete the hashed files the pages reference.
# A production build is deterministic - unchanged sources rebuild byte-identical.
assets:
	@if [ "$$ALLOW_ASSETS_UPDATE" = "1" ]; then yarn upgrade; fi
	@yarn install
	@yarn run prod

# Rebuild on change while working on the bundle's own sources. Never against a
# checkout a site is serving from, for the reason above: the unversioned dev
# output replaces the hashed files. Run `make assets` when done.
watch:
	@yarn install
	@yarn run watch

build: assets

deploy:
	@composer update
	@yarn install

linter: phpstan phpcs

phpcs:
	../../../bin/php-cs-fixer fix src
phpstan:
	../../vendor/bin/phpstan analyse

tests:
	@if [ -x vendor/bin/phpunit ]; then vendor/bin/phpunit; else $(ROOT_DIR)/bin/phpunit -c phpunit.xml.dist; fi

clean:
	@$(RM) -rf composer.lock vendor assets/build assets/package-lock.json assets/yarn.lock
