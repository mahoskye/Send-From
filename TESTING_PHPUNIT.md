# Send From Plugin - PHPUnit Testing Guide

This guide explains how to run PHPUnit tests for the Send From WordPress plugin.

## Overview

The test suite includes:
- **Unit Tests** (`tests/test-send-from.php`) - Tests individual methods and functionality
- **Integration Tests** (`tests/test-integration.php`) - Tests WordPress integration and hooks

## Quick Start (Docker - Recommended)

The easiest way to run tests is using Docker (no local PHP/MySQL setup required):

### PowerShell (Windows)
```powershell
.\scripts\run-tests.ps1
```

### Bash (Linux/Mac/WSL)
```bash
bash scripts/run-tests.sh
```

This will:
- Start the Docker containers (WordPress, MySQL, PHPUnit)
- Install the WordPress test suite
- Install PHPUnit and dependencies
- Run all tests

## Prerequisites (Local Setup)

For running tests locally without Docker, you need:

1. **PHP 7.4 or higher** (PHP 8.0+ recommended)
2. **Composer** (for installing PHPUnit)
3. **MySQL or MariaDB** (for WordPress test database)
4. **WordPress Test Suite** (installed via script)

## Installation

### 1. Install Composer Dependencies

First, create a `composer.json` file if you don't have one:

```bash
composer init --require="phpunit/phpunit:^9.0" --no-interaction
composer install
```

Or install PHPUnit directly:

```bash
composer require --dev phpunit/phpunit:^9.0
```

### 2. Install WordPress Test Suite

Run the install script (Linux/Mac/WSL):

```bash
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

**Parameters:**
- `wordpress_test` - Test database name
- `root` - MySQL username
- `''` - MySQL password (empty in this example)
- `localhost` - MySQL host
- `latest` - WordPress version (or specific version like `6.4`)

**For Windows (PowerShell):**

You'll need to use WSL (Windows Subsystem for Linux):

```powershell
wsl
cd /mnt/c/Users/mahos/OneDrive/Documents/Projects/Send-From
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

### 3. Set Environment Variable (Optional)

If your WordPress test library is in a custom location:

```bash
export WP_TESTS_DIR=/path/to/wordpress-tests-lib
```

## Running Tests

### Docker (Recommended)

#### Run All Tests
```powershell
# PowerShell
.\scripts\run-tests.ps1

# Bash
bash scripts/run-tests.sh
```

#### Run Specific Test File
```bash
docker compose exec -T phpunit ./vendor/bin/phpunit tests/test-send-from.php
```

#### Run Specific Test Method
```bash
docker compose exec -T phpunit ./vendor/bin/phpunit --filter test_email_validation
```

#### Interactive Shell (for debugging)
```bash
docker compose exec phpunit sh
# Then inside container:
./vendor/bin/phpunit
```

### Local (Without Docker)

#### Run All Tests

```bash
./vendor/bin/phpunit
```

Or if phpunit is installed globally:

```bash
phpunit
```

#### Run Specific Test File

```bash
./vendor/bin/phpunit tests/test-send-from.php
```

#### Run Specific Test Method

```bash
./vendor/bin/phpunit --filter test_email_validation
```

#### Run with Code Coverage (requires Xdebug)

```bash
./vendor/bin/phpunit --coverage-html coverage/
```

Then open `coverage/index.html` in your browser.

## Test Coverage

### Unit Tests (`test-send-from.php`)

✅ **Plugin Initialization**
- Class exists and instantiates correctly
- Default options are created on first run
- Legacy options migration from `smf_options`

✅ **Email Validation**
- Valid email addresses are accepted
- Invalid emails fall back to default
- Email sanitization works correctly

✅ **Name Sanitization**
- XSS prevention (script tags removed)
- Whitespace normalization
- Empty names fall back to previous value

✅ **WordPress Integration**
- Filters are registered (`wp_mail_from`, `wp_mail_from_name`)
- Actions are registered (admin hooks)
- Test email validation

✅ **Security**
- XSS prevention in output
- SQL injection prevention via WordPress functions
- Input sanitization

### Integration Tests (`test-integration.php`)

✅ **WordPress Filters**
- `wp_mail` uses custom from address
- `wp_mail` uses custom from name
- Options update triggers filter reapplication

✅ **Admin Interface**
- Admin menu is added under Plugins
- Settings are registered correctly
- Non-admin users cannot access settings
- Settings sections and fields exist

✅ **Notifications**
- Normalization notice displays when needed
- Transient is deleted after display

✅ **Email Integration**
- Email headers use custom values
- Filter chain works correctly

## Writing New Tests

### Test Structure

```php
<?php
class Test_My_Feature extends WP_UnitTestCase {

    private $plugin;

    public function setUp(): void {
        parent::setUp();
        // Set up test fixtures
        delete_option('Send_From_Options');
        $this->plugin = new Send_From();
    }

    public function tearDown(): void {
        // Clean up
        delete_option('Send_From_Options');
        parent::tearDown();
    }

    public function test_my_feature() {
        // Arrange
        $expected = 'test@example.com';

        // Act
        update_option('Send_From_Options', [
            'mail_from' => $expected,
            'mail_from_name' => 'Test'
        ]);

        // Assert
        $result = $this->plugin->get_mail_from_address();
        $this->assertEquals($expected, $result);
    }
}
```

### Best Practices

1. **Clean up after tests** - Always delete options and transients in `tearDown()`
2. **Test one thing** - Each test method should test a single behavior
3. **Use descriptive names** - `test_email_validation_rejects_invalid_format()` is better than `test_email()`
4. **Test edge cases** - Empty strings, null values, XSS attempts, etc.
5. **Mock external dependencies** - Don't rely on actual email sending

## Continuous Integration

### GitHub Actions Example

Create `.github/workflows/tests.yml`:

```yaml
name: PHPUnit Tests

on:
  push:
    branches: [ master ]
  pull_request:
    branches: [ master ]

jobs:
  test:
    runs-on: ubuntu-latest

    strategy:
      matrix:
        php-version: ['7.4', '8.0', '8.1', '8.2']
        wordpress-version: ['latest', '6.3', '6.4']

    steps:
    - uses: actions/checkout@v3

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ matrix.php-version }}
        extensions: mysqli
        coverage: xdebug

    - name: Install dependencies
      run: composer install

    - name: Install WordPress Test Suite
      run: bash bin/install-wp-tests.sh wordpress_test root root localhost ${{ matrix.wordpress-version }}

    - name: Run tests
      run: ./vendor/bin/phpunit --coverage-clover coverage.xml

    - name: Upload coverage
      uses: codecov/codecov-action@v3
      with:
        file: coverage.xml
```

## Troubleshooting

### "Could not find wp-tests-config.php"

The WordPress test suite isn't installed. Run:

```bash
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

### "Connection refused" Database Errors

Check your MySQL credentials and ensure MySQL is running:

```bash
mysql -u root -p
```

### Windows Issues

Use WSL (Windows Subsystem for Linux) for the best experience:

```bash
wsl
cd /mnt/c/Users/mahos/OneDrive/Documents/Projects/Send-From
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

### PHPUnit Not Found

Install via Composer:

```bash
composer require --dev phpunit/phpunit:^9.0
./vendor/bin/phpunit --version
```

## Test Results Interpretation

### Success Output
```
PHPUnit 9.x.x by Sebastian Bergmann and contributors.

..................................                                34 / 34 (100%)

Time: 00:02.123, Memory: 24.00 MB

OK (34 tests, 89 assertions)
```

### Failure Output
```
1) Test_Send_From::test_email_validation
Failed asserting that two strings are equal.
Expected: 'test@example.com'
Actual  : 'default@example.com'

/path/to/tests/test-send-from.php:123
```

## Coverage Goals

Target coverage metrics:
- **Overall Coverage**: > 80%
- **Critical Functions**: 100% (email validation, sanitization)
- **WordPress Hooks**: 100%
- **Admin UI**: > 70%

## Additional Resources

- [WordPress Plugin Unit Tests](https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)

## Related Documentation

- See `README.md` for general plugin information and project structure
