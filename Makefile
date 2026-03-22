.PHONY: assets clean

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

assets:
ifeq ($(APP_DEBUG),1)
	@cd assets && if [ "$$ALLOW_ASSETS_UPDATE" = "1" ]; then yarn upgrade; fi && yarn install && yarn run watch
else
	@cd assets && if [ "$$ALLOW_ASSETS_UPDATE" = "1" ]; then yarn upgrade; fi && yarn install && yarn run prod
endif

deploy:
	@composer update
	@yarn install

linter: phpstan phpcs

phpcs:
	../../../bin/php-cs-fixer fix src
phpstan:
	../../vendor/bin/phpstan analyse

tests:
	@echo "Not implemented yet."

clean:
	@$(RM) -rf composer.lock vendor assets/build assets/package-lock.json assets/yarn.lock
