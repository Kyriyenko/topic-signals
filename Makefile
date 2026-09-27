.PHONY: setup up down build install test cli shell logs

setup: build install

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

install:
	docker compose run --rm app composer install

test:
	docker compose run --rm app composer test

cli:
	docker compose run --rm app php bin/console $(ARGS)

shell:
	docker compose run --rm app sh

logs:
	docker compose logs -f
