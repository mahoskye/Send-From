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

# Run single-site PHPUnit tests
echo ""
echo "Running single-site PHPUnit tests..."
docker compose exec -T phpunit ./vendor/bin/phpunit

singleSiteExitCode=$?

if [ $singleSiteExitCode -ne 0 ]; then
    echo ""
    echo "Single-site tests failed with exit code: $singleSiteExitCode"
    exit $singleSiteExitCode
fi

# Run multisite PHPUnit tests
echo ""
echo "Running multisite PHPUnit tests..."
docker compose exec -T phpunit ./vendor/bin/phpunit -c tests/phpunit/multisite.xml

multisiteExitCode=$?

if [ $multisiteExitCode -eq 0 ]; then
    echo ""
    echo "All tests completed successfully!"
else
    echo ""
    echo "Multisite tests failed with exit code: $multisiteExitCode"
fi

exit $multisiteExitCode
