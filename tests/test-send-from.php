<?php
/**
 * Class Test_Send_From
 *
 * @package Send_From
 */

/**
 * Test case for the Send_From plugin
 */
class Test_Send_From extends WP_UnitTestCase {

    /**
     * Plugin instance
     *
     * @var Send_From
     */
    private $plugin;

    /**
     * Set up test fixtures
     */
    public function setUp(): void {
        parent::setUp();

        // Clean up any existing options
        delete_option('Send_From_Options');
        delete_option('smf_options');
        delete_transient('send_from_normalized');

        // Create fresh plugin instance
        $this->plugin = new Send_From();
    }

    /**
     * Clean up after tests
     */
    public function tearDown(): void {
        delete_option('Send_From_Options');
        delete_option('smf_options');
        delete_transient('send_from_normalized');

        parent::tearDown();
    }

    /**
     * Test that plugin class exists
     */
    public function test_class_exists() {
        $this->assertTrue(class_exists('Send_From'));
    }

    /**
     * Test plugin instantiation
     */
    public function test_plugin_instantiation() {
        $this->assertInstanceOf('Send_From', $this->plugin);
    }

    /**
     * Test default options are created on first run
     */
    public function test_default_options_created() {
        $options = get_option('Send_From_Options');

        $this->assertIsArray($options);
        $this->assertArrayHasKey('mail_from', $options);
        $this->assertArrayHasKey('mail_from_name', $options);
        $this->assertEquals('WordPress', $options['mail_from_name']);
        $this->assertStringContainsString('@', $options['mail_from']);
    }

    /**
     * Test legacy options migration
     */
    public function test_legacy_options_migration() {
        // Clean up
        delete_option('Send_From_Options');

        // Set up legacy options
        $legacy_options = array(
            'mail_from' => 'legacy@example.com',
            'mail_from_name' => 'Legacy Name'
        );
        add_option('smf_options', $legacy_options);

        // Create new plugin instance to trigger migration
        $plugin = new Send_From();

        // Check that legacy options were migrated
        $options = get_option('Send_From_Options');
        $this->assertEquals('legacy@example.com', $options['mail_from']);
        $this->assertEquals('Legacy Name', $options['mail_from_name']);

        // Check that legacy options were deleted
        $this->assertFalse(get_option('smf_options'));
    }

    /**
     * Test email validation in options
     */
    public function test_email_validation() {
        $test_cases = array(
            // Valid emails
            array('input' => 'valid@example.com', 'expected' => 'valid@example.com'),
            array('input' => 'user+tag@domain.co.uk', 'expected' => 'user+tag@domain.co.uk'),

            // Invalid emails should fall back to default
            array('input' => 'invalid-email', 'expected_type' => 'default'),
            array('input' => '@nodomain.com', 'expected_type' => 'default'),
            array('input' => '', 'expected_type' => 'default'),
        );

        foreach ($test_cases as $case) {
            $input = array('mail_from' => $case['input'], 'mail_from_name' => 'Test');
            $result = $this->plugin->validate_options($input);

            if (isset($case['expected'])) {
                $this->assertEquals($case['expected'], $result['mail_from'],
                    "Failed for input: {$case['input']}");
            } else {
                // Should be a default email with @ symbol
                $this->assertStringContainsString('@', $result['mail_from'],
                    "Failed to set default for invalid input: {$case['input']}");
            }
        }
    }

    /**
     * Test stricter email validation with is_reasonable_email
     */
    public function test_stricter_email_validation() {
        $test_cases = array(
            // Valid emails with proper domain structure
            array('input' => 'test@example.com', 'expected' => 'test@example.com'),
            array('input' => 'user@sub.domain.co.uk', 'expected' => 'user@sub.domain.co.uk'),
            array('input' => 'test@a.co', 'expected' => 'test@a.co'), // "a.co" is 4 chars, passes

            // Invalid: domain too short (e.g., "a@b.c" fails because "b.c" is only 3 chars)
            array('input' => 'test@b.c', 'expected_type' => 'default'),

            // Invalid: no dot in domain
            array('input' => 'test@localhost', 'expected_type' => 'default'),

            // Invalid: fails filter_var
            array('input' => 'not..valid@example.com', 'expected_type' => 'default'),
        );

        foreach ($test_cases as $case) {
            $input = array('mail_from' => $case['input'], 'mail_from_name' => 'Test');
            $result = $this->plugin->validate_options($input);

            if (isset($case['expected'])) {
                $this->assertEquals($case['expected'], $result['mail_from'],
                    "Failed for input: {$case['input']}");
            } else {
                // Should be a default email with @ symbol
                $this->assertStringContainsString('@', $result['mail_from'],
                    "Failed to set default for invalid input: {$case['input']}");
                // Should NOT be the invalid email
                $this->assertNotEquals($case['input'], $result['mail_from'],
                    "Should have rejected invalid email: {$case['input']}");
            }
        }
    }

    /**
     * Test name sanitization in options
     */
    public function test_name_sanitization() {
        // First set a known value so fallback works consistently
        update_option('Send_From_Options', array(
            'mail_from' => 'test@example.com',
            'mail_from_name' => 'Previous Name'
        ));

        // Recreate plugin to load the options
        $this->plugin = new Send_From();

        $test_cases = array(
            array('input' => 'Valid Name', 'expected' => 'Valid Name'),
            // sanitize_text_field removes all tags, result is empty, falls back to previous
            array('input' => '<script>alert("xss")</script>', 'expected' => 'Previous Name'),
            array('input' => 'Name  with   spaces', 'expected' => 'Name with spaces'),
            array('input' => "Name\nwith\nnewlines", 'expected' => 'Name with newlines'),
        );

        foreach ($test_cases as $case) {
            $input = array(
                'mail_from' => 'test@example.com',
                'mail_from_name' => $case['input']
            );
            $result = $this->plugin->validate_options($input);

            $this->assertEquals($case['expected'], $result['mail_from_name'],
                "Failed for input: {$case['input']}");
        }
    }

    /**
     * Test get_mail_from_address filter
     */
    public function test_get_mail_from_address() {
        // Set custom email
        update_option('Send_From_Options', array(
            'mail_from' => 'custom@example.com',
            'mail_from_name' => 'Custom Name'
        ));

        $address = $this->plugin->get_mail_from_address();
        $this->assertEquals('custom@example.com', $address);
    }

    /**
     * Test get_mail_from_name filter
     */
    public function test_get_mail_from_name() {
        // Set custom name
        update_option('Send_From_Options', array(
            'mail_from' => 'test@example.com',
            'mail_from_name' => 'Custom Sender Name'
        ));

        $name = $this->plugin->get_mail_from_name();
        $this->assertEquals('Custom Sender Name', $name);
    }

    /**
     * Test WordPress filters are applied
     */
    public function test_wordpress_filters_applied() {
        $this->assertNotFalse(has_filter('wp_mail_from', array($this->plugin, 'get_mail_from_address')));
        $this->assertNotFalse(has_filter('wp_mail_from_name', array($this->plugin, 'get_mail_from_name')));
    }

    /**
     * Test WordPress actions are registered
     */
    public function test_wordpress_actions_registered() {
        $this->assertNotFalse(has_action('admin_notices', array($this->plugin, 'maybe_show_normalized_notice')));
        $this->assertNotFalse(has_action('admin_init', array($this->plugin, 'admin_init')));
        $this->assertNotFalse(has_action('init', array($this->plugin, 'load_textdomain')));
        $this->assertNotFalse(has_action('admin_menu', array($this->plugin, 'add_menu')));
    }

    /**
     * Test test email validation
     */
    public function test_validate_test_email() {
        // Valid test email
        $valid_input = array('Send_From_Send_To' => 'test@example.com');
        $result = $this->plugin->validate_test_email($valid_input);

        $this->assertEquals('true', $result['Send_From_Send_Test']);
        $this->assertEquals('test@example.com', $result['Send_From_Send_To']);

        // Invalid test email
        $invalid_input = array('Send_From_Send_To' => 'invalid-email');
        $result = $this->plugin->validate_test_email($invalid_input);

        $this->assertEquals('false', $result['Send_From_Send_Test']);
        $this->assertArrayNotHasKey('Send_From_Send_To', $result);

        // Empty input
        $empty_input = array();
        $result = $this->plugin->validate_test_email($empty_input);

        $this->assertEquals('false', $result['Send_From_Send_Test']);
    }

    /**
     * Test that normalization notice transient is set when needed
     */
    public function test_normalization_notice() {
        delete_option('Send_From_Options');
        delete_transient('send_from_normalized');

        // Add invalid options directly to database
        add_option('Send_From_Options', array(
            'mail_from' => 'invalid-email',
            'mail_from_name' => 'Test'
        ));

        // Create new instance to trigger validation
        $plugin = new Send_From();

        // Check that transient was set
        $this->assertTrue((bool) get_transient('send_from_normalized'));

        // Check that email was fixed
        $options = get_option('Send_From_Options');
        $this->assertStringContainsString('@', $options['mail_from']);
    }

    /**
     * Test XSS prevention in output
     */
    public function test_xss_prevention() {
        $malicious_input = array(
            'mail_from' => 'test@example.com',
            'mail_from_name' => '<script>alert("XSS")</script>'
        );

        $sanitized = $this->plugin->validate_options($malicious_input);

        // Name should be sanitized (script tags removed)
        $this->assertStringNotContainsString('<script>', $sanitized['mail_from_name']);
        $this->assertStringNotContainsString('</script>', $sanitized['mail_from_name']);
    }

    /**
     * Test that empty name falls back to previous value
     */
    public function test_empty_name_fallback() {
        // Set initial options
        update_option('Send_From_Options', array(
            'mail_from' => 'test@example.com',
            'mail_from_name' => 'Original Name'
        ));

        // Recreate plugin to load options
        $this->plugin = new Send_From();

        // Try to save with empty name
        $input = array(
            'mail_from' => 'test@example.com',
            'mail_from_name' => ''
        );

        $result = $this->plugin->validate_options($input);

        // Should keep the original name
        $this->assertEquals('Original Name', $result['mail_from_name']);
    }
}
