#!/bin/bash
# Run PHPUnit tests in Docker container
# Usage: bash scripts/run-tests.sh

set -e

echo "Starting PHPUnit tests in Docker..."

# Ensure containers are running
echo "Ensuring Docker containers are running..."
docker compose up -d

# Wait for database to be ready
echo "Waiting for database to be ready..."
sleep 5

# Check if WordPress test suite is installed
echo "Checking WordPress test suite..."
if ! docker compose exec -T phpunit test -d /tmp/wordpress-tests-lib; then
    echo "Installing WordPress test suite..."

    # Install required packages
    docker compose exec -T phpunit sh -c "apk add --no-cache bash subversion mysql-client"

    # Run the install script
    docker compose exec -T phpunit bash bin/install-wp-tests.sh wordpress_test wp wp db latest true
fi

# Install Composer dependencies if needed
echo "Installing Composer dependencies..."
docker compose exec -T phpunit sh -c "if ! command -v composer >/dev/null 2>&1; then apk add --no-cache composer; fi"
docker compose exec -T phpunit composer install --no-interaction --prefer-dist

# Run PHPUnit tests
echo ""
echo "Running PHPUnit tests..."
docker compose exec -T phpunit ./vendor/bin/phpunit

# Capture exit code
exitCode=$?

if [ $exitCode -eq 0 ]; then
    echo ""
    echo "Tests completed successfully!"
else
    echo ""
    echo "Tests failed with exit code: $exitCode"
fi

exit $exitCode
