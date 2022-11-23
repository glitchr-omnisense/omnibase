.PHONY: *

dev: development
development: clean
	@cd assets
	@yarn install
	@yarn run dev

assets: production
prod: production
production: clean
	@cd assets
	@yarn install
	@yarn run prod

deploy:
	@composer update
	@yarn install

linter:
	@echo "Not implemented yet."

tests:
	@echo "Not implemented yet."

clean:
	@$(RM) -rf composer.lock vendor assets/build assets/package-lock.json assets/yarn.lock
