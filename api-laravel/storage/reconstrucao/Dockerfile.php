FROM php:8.3-cli
RUN apt-get update -qq && apt-get install -y -qq libpq-dev >/dev/null && docker-php-ext-install pdo_pgsql >/dev/null
