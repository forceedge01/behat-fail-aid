vendor: composer.json
	composer install

.PHONY: tests
tests:
	docker-compose up
