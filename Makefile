install:
	composer install

PORT ?= 8000

start:
	PHP_CLI_SERVER_WORKERS=5 php -S 0.0.0.0:$(PORT) -t public

lint:
	vendor/bin/phpcs --standard=PSR12 public src src/migration/migrate.php


push:
	git add .
	git commit -m 'fix'
	git push origin main