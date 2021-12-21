#!/bin/bash

if [[ -z ${LAGOON_GIT_SHA} ]]; then
    echo "Cannot detect LAGOON_GIT_SHA"
    exit 1
fi

if [[ -z ${GITHUB_TOKEN} ]]; then
    echo "No Github token :("
    env
    exit 1
fi

if [[ -z ${1} ]]; then
    echo "Provide a state"
    exit 1
fi

# transient_environment: set to true if PR
# production_environment: set to true if LAGOON_ENVIRONMENT_TYPE=production
curl \
  -X POST \
  -H "Accept: application/vnd.github.v3+json" \
  -H "Content-Type: application/json" \
  -H "Authorization: token ${GITHUB_TOKEN}" \
  https://api.github.com/repos/simplytestme/website/deployments \
  -d '{"ref":"'${LAGOON_GIT_SHA}'", "environment":"'${LAGOON_ENVIRONMENT}'"}'
