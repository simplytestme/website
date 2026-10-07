ARG CLI_IMAGE
FROM ${CLI_IMAGE} as cli

FROM uselagoon/nginx-drupal:latest

COPY --from=cli /app /app
COPY lagoon/nginx/server_prepend_llms.conf /etc/nginx/conf.d/drupal/server_prepend_llms.conf

# Define where the Drupal Root is located
ENV WEBROOT=web

RUN echo "~^www.simplytest.me           https://simplytest.me\$request_uri;" >> /etc/nginx/redirects-map.conf
