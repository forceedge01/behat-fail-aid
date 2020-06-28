vendor: composer.json
	composer install

.PHONY: tests
tests:
	docker-compose run --rm tests-php-5.6
	docker-compose run --rm tests-php-7.1

.PHONY: run
run:
	./vendor/bin/behat
