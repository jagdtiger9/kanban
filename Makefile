.PHONY: console cc migrate db-shell ping test fe-install fe-dev fe-build

##
## —— 🏰 Caban Makefile ——————————————————————————————————————————————————
help: ## Print this help screen
	@grep -E '(^[a-zA-Z0-9\./_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

##
## —— Permissions 🔐 ——————————————————————————————————————————————————————
# Usage: make permissions
permissions: ## Set filesystem ACLs for web-user on public/uploads/ and var/
	HTTPDUSER=web-user && HTTPDGROUP=web-group \
	&& echo $$HTTPDUSER \
	&& sudo chown -R "$$HTTPDUSER:$$HTTPDGROUP" public/uploads/ var/ \
	&& sudo setfacl -R -m d:u:"$$HTTPDUSER":rwX,u:"$$HTTPDUSER":rwX -m d:u:$$(whoami):rwX,u:$$(whoami):rwX public/uploads/ \
	&& sudo setfacl -R -m d:u:"$$HTTPDUSER":rwX,u:"$$HTTPDUSER":rwX -m d:u:$$(whoami):rwX,u:$$(whoami):rwX public/var/

##
## —— Packages 📦 ————————————————————————————————————————————————————————
## Useful for PROD to avoid composer.lock conflicts when using local filesystem packages
packages: ## Update aljerom/simple-hydrator and aljerom/cqrs-events
	composer update aljerom/simple-hydrator aljerom/cqrs-events

##
## —— Doctrine Migrations 🗄️ ————————————————————————————————————————————
migrations.diff: ## Generate main DB migration diff
	php bin/console doctrine:migrations:diff --configuration=config/migrations/main.php
migrations.diff.tenant: ## Generate tenant DB migration diff
	php bin/console doctrine:migrations:diff --configuration=config/migrations/tenant.php

migrations.migrate: ## Run main DB migrations
	php bin/console doctrine:migrations:migrate --configuration=config/migrations/main.php --no-interaction --allow-no-migration
migrations.migrate.tenant: ## Run tenant migrations on the base copies of tenant tables
	php bin/console doctrine:migrations:migrate --configuration=config/migrations/tenant.php --no-interaction --allow-no-migration
migrations.migrate.schema: ## Run tenant migrations on a specific tenant schema, e.g. make migrations.migrate.schema SCHEMA=shop__aljerom
	php bin/console app:tenant:migrate $(SCHEMA)
migrations.migrate.tenants: ## Run tenant migrations on all tenant schemas (shop__*)
	php bin/console app:tenant:migrate --all

console: ## Run a Symfony console command, e.g. make console ARGS="debug:router"
	make -C ../../Docker run PROJECT=canban CMD="bin/console $(ARGS)"

cc: ## Clear the Symfony cache
	make -C ../../Docker run PROJECT=canban CMD="bin/console cache:clear"

migrate: ## Run pending Doctrine migrations
	make -C ../../Docker run PROJECT=canban CMD="bin/console doctrine:migrations:migrate --no-interaction"

db-shell: ## Open a psql shell on the dev database (base env pg)
	docker exec -it pg psql -U canban canban

ping: ## Check the API ping endpoint (needs canban.local in /etc/hosts)
	curl -s https://canban.local/api/ping

test: ## Run the PHPUnit suite in the fpm container
	make -C ../../Docker run PROJECT=canban CMD="bin/phpunit"

fe-install: ## Install frontend dependencies
	cd frontend && yarn install

fe-build: ## Type-check and build the frontend into public/build (served by angie)
	cd frontend && yarn build
