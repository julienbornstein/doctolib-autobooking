FROM php:7.4-cli-alpine

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install dependencies
COPY composer.json composer.json
COPY composer.lock composer.lock
RUN composer install --prefer-dist --no-scripts --no-dev --no-autoloader && rm -rf /root/.composer

COPY ./ .

# Finish composer
RUN composer dump-autoload --no-scripts --no-dev --optimize

# Set default env to production
ENV APP_ENV=prod

ENTRYPOINT ["php", "bin/console", "doctolib:create-appointment"]
