#!/usr/bin/env bash
if [ -n "$DEBUG_SCRIPTS" ]; then
    set -x
fi

# Create a phpstorm command
sudo cp "${GITPOD_REPO_ROOT}"/.gitpod/phpstorm.template.sh /usr/local/bin/phpstorm
