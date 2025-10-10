# Send From

Contributors: Benjamin Buddle

Tags: #mail, #mailer, #phpmailer, #change, #from, #send, #email

Requires at least: 5.9

Tested up to: 6.4

Stable tag: 2.3

Plugin for modifying the from line on all emails coming from WordPress.

## Description

I have issues with my hosting service not allowing me to easily set the 'From line' for my server email. Whenever a new user signs up they see username@hostingservice.com even though they should see user@site.com. Before Send From you would be required to modify your installation of Wordpress just about every time you do an update. No longer! With Send From, you simply go into your admin panel and set what the end user will see on their emails from line.

## Project Structure

This repository is organized as follows:

- `plugin/` - Core WordPress plugin files (distributed to WordPress.org)
  - `send-from.php` - Main plugin file
  - `README.txt` - WordPress.org readme
  - `screenshot-1.png` - Plugin screenshot
  - `LICENSE` - GPLv2 license
- `tests/` - PHPUnit test suite
- `scripts/` - Test runner scripts
- `bin/` - WordPress test suite installation script
- Development configuration files (docker-compose.yml, phpunit.xml, etc.)

## Installation

1. Download the plugin from WordPress.org or this repository
2. Upload the `send-from` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress

For development setup, see TESTING_PHPUNIT.md

## Set Defaults

1. Under the 'Plugins' area, click the link for 'Send From'
2. Change the From Name and From Email
3. Click Update Options

## Frequently Asked Questions

### Why create this?

My host is Host Monster, who does not allow me to easily set the email address my server communicates with. I was not willing to outright hack Wordpress; so, I created a plugin that has worked solidly for me since Wordpress version 3.

### What is the minimum version that is useable with this plugin?

I'm told that WordPress 2.7 changed several settings, including the way things are sent via mail. So I can't guarentee support before version 2.7.

### This plugin doesn't work with x, y, z. Will you add support?

Chances are if it's not working with another plugin then I have not encountered the issue and am not likly to fix it. So no, I'm sorry but I will not be adding support for x, y, z.

## Screenshots

1. Screenshot of the Plugins > Send From panel.

## Support Questions

If there are any issues that crop up, I will be happy to take a look at solving them. However, due to many factors, I can't offer active support for the plugin.

## Changelog

- 2.3 - Security: Fixed stored XSS (CVE-2025-46469). Added input sanitization and output escaping; validated test-send addresses. Implemented comprehensive PHPUnit test suite with 26+ tests. Added Docker-based testing environment. Enhanced email validation with stricter domain requirements. Improved code quality and error handling. Bumped compatibility flags.
- 2.0 - Updated the code to fix naming conventions, reduce size, and fix and issue with the options page
- 1.3 - Fixed typo
- 1.2 - Fixed issue with update message not displaying properly
- 1.1 - Fixed Error where default address was not properly used
- 1.0 - Send Test Working and showing proper messages
- 0.9 - Send Test Implemented and working, showing 'Settings Saved.'
- 0.8 - Working without Send Test option
- 0.7 - Added Options Page
- 0.5 - Revision / working draft
- 0.1 - Initial approact to content

## Security

**CVE:** CVE-2025-46469 - Cross-site scripting (Stored XSS) in plugin settings.

**Summary:** A stored XSS issue was reported in older versions of this plugin where un-sanitized input saved in plugin options could later be rendered into the admin interface without proper escaping. The repository has been updated to sanitize incoming option values and escape output when rendering form fields. The plugin also validates the test-send email address.

**Mitigation applied in this repository:**

- Sanitize email values with WordPress' `sanitize_email()` before saving.
- Sanitize name fields with `sanitize_text_field()` before saving.
- Escape values when printed into HTML attributes using `esc_attr()`.
- Validate test-send addresses with `is_email()` and refuse to save invalid addresses.

Testing instructions are provided in `TESTING_PHPUNIT.md`. A GitHub Actions workflow runs `php -l` on the plugin file to catch syntax issues.

## WordPress.org Deployment

When deploying to WordPress.org via SVN, point your SVN trunk to the `plugin/` directory. This directory contains only the files that should be distributed to end users. All development files (tests, Docker configs, etc.) are kept in the repository root.

### Quick Deployment

Use the deployment script:
```bash
./scripts/deploy-to-wordpress.sh
```

Then manually commit:
```bash
cd send-from
svn ci -m "Update to version 2.3 - Security fix for CVE-2025-46469 (Stored XSS)"
svn cp trunk tags/2.3
svn ci -m "Tagging version 2.3"
```
