#!/bin/sh
set -eu

# Generate runtime definitions.json with user credentials from environment variables.
# RabbitMQ's load_definitions skips RABBITMQ_DEFAULT_USER creation, so we inject
# the user directly into the definitions file at container startup.
#
# The password is provided in plaintext; RabbitMQ hashes it internally upon import.

TEMPLATE="/etc/rabbitmq/definitions.template.json"
RUNTIME="/tmp/definitions.json"

if [ ! -f "$TEMPLATE" ]; then
    echo "ERROR: definitions template not found at $TEMPLATE"
    exit 1
fi

RMQUSER="${RABBITMQ_DEFAULT_USER:?RABBITMQ_DEFAULT_USER is required}"
RMQPASS="${RABBITMQ_DEFAULT_PASS:?RABBITMQ_DEFAULT_PASS is required}"
RMQVHOST="${RABBITMQ_DEFAULT_VHOST:-/}"

# Inject users and permissions before the closing brace of the JSON template.
# Uses sed to append the sections - safe because the template has a known structure.
sed '$ s/}$//' "$TEMPLATE" > "$RUNTIME"
cat >> "$RUNTIME" <<DEFINITIONS
,
  "users": [
    {
      "name": "${RMQUSER}",
      "password": "${RMQPASS}",
      "tags": "administrator"
    }
  ],
  "permissions": [
    {
      "user": "${RMQUSER}",
      "vhost": "${RMQVHOST}",
      "configure": ".*",
      "write": ".*",
      "read": ".*"
    }
  ]
}
DEFINITIONS

echo "RabbitMQ definitions generated with user '${RMQUSER}' on vhost '${RMQVHOST}'"

# Delegate to the official Docker entrypoint
exec docker-entrypoint.sh "$@"
