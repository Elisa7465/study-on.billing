COMPOSE=docker-compose
PHP=$(COMPOSE) exec php
CONSOLE=$(PHP) bin/console
COMPOSER=$(PHP) composer

cs:
	@${PHP} vendor/bin/phpcs

down:
	@${COMPOSE} down

clear:
	@${CONSOLE} cache:clear

migration:
	@${CONSOLE} make:migration

migrate:
	@${CONSOLE} doctrine:migrations:migrate

fixtload:
	@${CONSOLE} doctrine:fixtures:load
phpunit:
	@${PHP} bin/phpunit

payment-report:
	@${CONSOLE} payment:report

payment-ending-notification:
	@${CONSOLE} payment:ending:notification

# В файл local.mk можно добавлять дополнительные make-команды,
# которые требуются лично вам, но не нужны на проекте в целом
-include local.mk
