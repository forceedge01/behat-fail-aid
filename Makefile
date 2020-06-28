vendor: composer.json
	composer install

.PHONY: tests
tests:
	docker-compose up

.PHONY: run
run:
	./vendor/bin/behat
