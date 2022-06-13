#!/bin/bash

# This script is taken from https://aws.amazon.com/blogs/security/how-to-manage-secrets-for-amazon-ec2-container-service-based-applications-by-using-amazon-s3-and-docker/
# and is used to set up app secrets in ECS without exposing them as widely as using ECS env vars directly would.

# Check that the environment variable has been set correctly
if [ -z "$SECRETS_BUCKET_NAME" ]; then
  echo >&2 'error: missing SECRETS_BUCKET_NAME environment variable'
  exit 1
fi

# Load the S3 secrets file contents into the environment variables
export $(aws s3 cp s3://${SECRETS_BUCKET_NAME}/secrets - | grep -v '^#' | xargs)

composer doctrine:ensure-prod || exit 2

# This is a bit hack-y because on a deploy that includes a new migration, several containers may be in
# a race to try to run it. However because migrations are versioned and run transactionally, and we
# call Doctrine with `--allow-no-migration`, it *should* be safe and subsequent instances' attempts to
# migrate will just be no-ops and leave the main process to start normally.
echo "Running migrations before start if necessary..."
composer doctrine:migrate
composer doctrine:generate-proxies
composer doctrine:cache:clear

echo "Starting Apache..."
# Call the normal web server entry-point script
apache2-foreground "$@"
