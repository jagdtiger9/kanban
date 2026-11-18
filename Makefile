.PHONY: console cc migrate db-shell ping test fe-install fe-dev fe-build

##
## —— 🏰 Caban Makefile ——————————————————————————————————————————————————
help: ## Print this help screen
	@grep -E '(^[a-zA-Z0-9\./_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

##
## —— Permissions 🔐 ——————————————————————————————————————————————————————
# Usage: make permissions
permissions: ## Set filesystem ACLs for web-user on var/
	HTTPDUSER=web-user && HTTPDGROUP=web-group \
	&& echo $$HTTPDUSER \
	&& mkdir -p var/media \
	&& sudo chown -R "$$HTTPDUSER:$$HTTPDGROUP" var/ \
	&& sudo setfacl -R -m d:u:"$$HTTPDUSER":rwX,u:"$$HTTPDUSER":rwX -m d:u:$$(whoami):rwX,u:$$(whoami):rwX var/

##
## —— Packages 📦 ————————————————————————————————————————————————————————
## Useful for PROD to avoid composer.lock conflicts when using local filesystem packages
packages: ## Update the local path packages (aljerom/simple-hydrator, aljerom/symfony-boilerplate)
	composer update aljerom/simple-hydrator aljerom/symfony-boilerplate

##
## —— Doctrine Migrations 🗄️ ————————————————————————————————————————————
migrations.diff: ## Generate main DB migration diff
	php bin/console doctrine:migrations:diff

migrations.migrate: ## Run main DB migrations
	php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

##
## —— Services ————————————————————————————————————————————
storage.sweep.dry: ## Remove orphaned attachments, dry-run
	bin/console app:media:sweep --dry-run

storage.sweep: ## Remove orphaned attachments
	bin/console app:media:sweep --dry-run
