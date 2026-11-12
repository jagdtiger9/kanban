.PHONY: up down restart ps logs console cc migrate db-shell ping fe-install fe-dev fe-build

up: ## Build and start the Docker stack (db, php-fpm, nginx)
	docker compose up -d --build

down: ## Stop the Docker stack
	docker compose down

restart: ## Restart the Docker stack
	docker compose restart

ps: ## Show container status
	docker compose ps

logs: ## Tail logs from all services
	docker compose logs -f --tail=100

console: ## Run a Symfony console command inside php, e.g. make console ARGS="debug:router"
	docker compose exec php bin/console $(ARGS)

cc: ## Clear the Symfony cache inside php
	docker compose exec php bin/console cache:clear

migrate: ## Run pending Doctrine migrations
	docker compose exec php bin/console doctrine:migrations:migrate --no-interaction

db-shell: ## Open a psql shell on the dev database
	docker compose exec db psql -U canban canban

ping: ## Check the API ping endpoint
	curl -s http://localhost:8080/api/ping

fe-install: ## Install frontend dependencies
	cd frontend && npm install

fe-dev: ## Start the Vite dev server (http://localhost:5173, /api proxied to :8080)
	cd frontend && npm run dev

fe-build: ## Type-check and build the frontend into frontend/dist
	cd frontend && npm run build
