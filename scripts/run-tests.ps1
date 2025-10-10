# Run PHPUnit tests in Docker container
# Usage: .\scripts\run-tests.ps1

Write-Host "Starting PHPUnit tests in Docker..." -ForegroundColor Cyan

# Ensure containers are running
Write-Host "Ensuring Docker containers are running..." -ForegroundColor Yellow
docker compose up -d

# Wait for database to be ready
Write-Host "Waiting for database to be ready..." -ForegroundColor Yellow
Start-Sleep -Seconds 5

# Check if WordPress test suite is installed
Write-Host "Checking WordPress test suite..." -ForegroundColor Yellow
$testLibExists = docker compose exec -T phpunit test -d /tmp/wordpress-tests-lib
if ($LASTEXITCODE -ne 0) {
    Write-Host "Installing WordPress test suite..." -ForegroundColor Yellow

    # Install required packages
    docker compose exec -T phpunit sh -c "apk add --no-cache bash subversion mysql-client"

    # Run the install script
    docker compose exec -T phpunit bash bin/install-wp-tests.sh wordpress_test wp wp db latest true

    if ($LASTEXITCODE -ne 0) {
        Write-Host "Failed to install WordPress test suite" -ForegroundColor Red
        exit 1
    }
}

# Install Composer dependencies if needed
Write-Host "Installing Composer dependencies..." -ForegroundColor Yellow
docker compose exec -T phpunit sh -c "if ! command -v composer >/dev/null 2>&1; then apk add --no-cache composer; fi"
docker compose exec -T phpunit composer install --no-interaction --prefer-dist

# Run PHPUnit tests
Write-Host "`nRunning PHPUnit tests..." -ForegroundColor Green
docker compose exec -T phpunit ./vendor/bin/phpunit

# Capture exit code
$exitCode = $LASTEXITCODE

if ($exitCode -eq 0) {
    Write-Host "`nTests completed successfully!" -ForegroundColor Green
} else {
    Write-Host "`nTests failed with exit code: $exitCode" -ForegroundColor Red
}

exit $exitCode
