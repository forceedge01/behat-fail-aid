vendor: composer.json
	composer install

.PHONY: tests
tests:
	./vendor/bin/phpunit -c tests
	./vendor/bin/behat
