ARG CLI_IMAGE
FROM ${CLI_IMAGE} as cli

FROM uselagoon/php-7.4-fpm:latest

COPY --from=cli /app /app
