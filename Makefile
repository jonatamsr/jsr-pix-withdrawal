.PHONY: setup up down test adminer structurizr

setup: ## Configura o .env com UID/GID do usuário atual e sobe os containers
	@if [ ! -f .env ]; then cp .env.example .env; fi
	@grep -q "^APP_UID=" .env && sed -i "s/^APP_UID=.*/APP_UID=$$(id -u)/" .env || echo "APP_UID=$$(id -u)" >> .env
	@grep -q "^APP_GID=" .env && sed -i "s/^APP_GID=.*/APP_GID=$$(id -g)/" .env || echo "APP_GID=$$(id -g)" >> .env
	@echo "✅ .env configurado com UID=$$(id -u) GID=$$(id -g)"

up: setup ## Sobe todos os containers (build se necessário)
	docker compose up -d --build

down: ## Para e remove os containers
	docker compose down

test: ## Roda os testes unitários
	bash bin/phpunit.sh $(ARGS)

cs-fix: ## Roda os testes unitários
	docker compose exec app composer cs-fix

logs: ## Mostra apenas logs da aplicação (Monolog)
	docker compose logs app -f

adminer: ## Sobe o Adminer (DB UI) em http://localhost:8081
	docker compose --profile tools up -d adminer

structurizr: ## Sobe o Structurizr Lite (diagramas C4) em http://localhost:8080
	docker compose --profile tools up -d structurizr
