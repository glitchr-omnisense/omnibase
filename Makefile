.PHONY: *

build:
	@composer update
	@yarn install

assets:
	@cd assets
	@yarn install

prod: production
production: clean
	@cd assets
	@yarn run prod
	
dev: development
development: clean
	@cd assets
	@yarn run dev

linter:
	@echo "Not implemented yet."

tests:
	@echo "Not implemented yet."

clean:
	@$(RM) -rf vendor assets/build composer.lock package-lock.json yarn.lock
