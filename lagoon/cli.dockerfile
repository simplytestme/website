FROM composer:latest as composer
WORKDIR /app

COPY . .

RUN set -eux; \
  composer install --no-dev --ignore-platform-reqs; \
  composer dump-autoload --optimize --apcu; \
  mkdir -p -v -m775 /app/web/sites/default/files

FROM node:18-slim as node

WORKDIR /app

COPY . .

RUN cd web/themes/simplytest_theme \
  && npm ci \
  && npx update-browserslist-db@latest \
  && npm run build \
  && rm -rf node_modules


FROM uselagoon/php-8.1-cli-drupal:latest

COPY --from=composer /app /app
COPY --from=node /app/web/themes/simplytest_theme /app/web/themes/simplytest_theme

# Define where the Drupal Root is located
ENV WEBROOT=web
