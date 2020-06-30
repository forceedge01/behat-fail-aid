install: composer.lock
	docker-compose run --rm tests-php-5.6 composer install

.PHONY: update
update:
	docker-compose run --rm tests-php-5.6 composer update

.PHONY: tests
tests:
	docker-compose run --rm tests-php-5.6
	docker-compose run --rm tests-php-7.1

.PHONY: run
run:
	docker-compose run --rm tests-php-5.6 ./vendor/bin/behat
	docker-compose run --rm tests-php-7.1 ./vendor/bin/behat
