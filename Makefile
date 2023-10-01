install:
	composer install

PORT ?= 8000

start:
	PHP_CLI_SERVER_WORKERS=5 php -S 0.0.0.0:$(PORT) -t public

lint:
	composer exec --verbose phpcs -- --standard=PSR12 public src

lint-fix:
	composer exec --verbose phpcbf -- --standard=PSR12 public src

migrate:
	php ./src/migration/migrate.php


push:
	git add .
	git commit -m 'fix'
	git push origin main
