<?php
/**
 * Multisite-focused test cases for the Send From plugin.
 *
 * @package Send_From
 */

/**
 * @group multisite
 */
class Test_Send_From_Multisite extends WP_UnitTestCase {

    /**
     * Stores original list of network active plugins so we can restore between tests.
     *
     * @var array
     */
    private $original_active_sitewide_plugins = array();

    /**
     * Set up multisite fixtures.
     */
    public function setUp(): void {
        parent::setUp();

        if (!is_multisite()) {
            $this->markTestSkipped('Multisite is required for these tests.');
        }

        $this->original_active_sitewide_plugins = get_site_option('active_sitewide_plugins', array());

        $this->reset_plugin_state();
    }

    /**
     * Clean up after each test.
     */
    public function tearDown(): void {
        $this->reset_plugin_state();

        // Restore original network active plugins so other tests are unaffected.
        update_site_option('active_sitewide_plugins', $this->original_active_sitewide_plugins);

        parent::tearDown();
    }

    /**
     * Ensure default options are created in the site (network) options table.
     */
    public function test_network_defaults_use_site_option() {
    $this->activate_network_plugin();

    $plugin = new Send_From();

        $site_options = get_site_option(Send_From::OPTION_KEY);
        $this->assertIsArray($site_options);
        $this->assertArrayHasKey('mail_from', $site_options);
        $this->assertArrayHasKey('mail_from_name', $site_options);

        $this->assertFalse(get_option(Send_From::OPTION_KEY), 'Network mode should not populate the per-site option.');
    }

    /**
     * Ensure existing invalid options set at network scope are normalized and flagged via site transient.
     */
    public function test_network_normalization_sets_site_transient() {
        $this->activate_network_plugin();

        update_site_option(Send_From::OPTION_KEY, array(
            'mail_from' => 'not-an-email',
            'mail_from_name' => 'Test'
        ));

        $plugin = new Send_From();

        $this->assertNotFalse(get_site_transient(Send_From::NORMALIZATION_TRANSIENT));
        $this->assertFalse(get_transient(Send_From::NORMALIZATION_TRANSIENT));

        $options = get_site_option(Send_From::OPTION_KEY);
        $this->assertStringContainsString('@', $options['mail_from']);
    }

    /**
     * Confirm network-specific hooks are registered when the plugin runs in network mode.
     */
    public function test_network_hooks_registered() {
    $this->activate_network_plugin();

    $plugin = new Send_From();

        $this->assertNotFalse(has_action('network_admin_menu', array($plugin, 'add_menu')));
        $this->assertNotFalse(has_action('network_admin_edit_' . Send_From::NETWORK_UPDATE_ACTION, array($plugin, 'handle_network_options_update')));
        $this->assertNotFalse(has_action('network_admin_edit_' . Send_From::NETWORK_TEST_ACTION, array($plugin, 'handle_network_test_email')));
    }

    /**
     * Verify getters read from the network-scoped option store.
     */
    public function test_network_getters_use_site_option_values() {
    $this->activate_network_plugin();

    $plugin = new Send_From();

        update_site_option(Send_From::OPTION_KEY, array(
            'mail_from' => 'network@example.com',
            'mail_from_name' => 'Network Sender'
        ));

        $this->assertEquals('network@example.com', $plugin->get_mail_from_address());
        $this->assertEquals('Network Sender', $plugin->get_mail_from_name());
    }

    /**
     * Ensure legacy options stored at network scope are migrated and cleaned up.
     */
    public function test_network_legacy_options_migrated() {
        $this->activate_network_plugin();

        update_site_option(Send_From::LEGACY_OPTION_KEY, array(
            'mail_from' => 'legacy@example.com',
            'mail_from_name' => 'Legacy Network'
        ));

        $plugin = new Send_From();

        $options = get_site_option(Send_From::OPTION_KEY);
        $this->assertEquals('legacy@example.com', $options['mail_from']);
        $this->assertEquals('Legacy Network', $options['mail_from_name']);
        $this->assertFalse(get_site_option(Send_From::LEGACY_OPTION_KEY));
    }

    /**
     * Clear all plugin-related network storage so each test starts clean.
     */
    private function reset_plugin_state(): void {
        delete_site_option(Send_From::OPTION_KEY);
        delete_option(Send_From::OPTION_KEY);
        delete_site_option(Send_From::LEGACY_OPTION_KEY);
        delete_option(Send_From::LEGACY_OPTION_KEY);
        delete_site_transient(Send_From::NORMALIZATION_TRANSIENT);
        delete_transient(Send_From::NORMALIZATION_TRANSIENT);

        $basename = $this->get_plugin_basename();

        $active = get_site_option('active_sitewide_plugins', array());
        unset($active[$basename]);
        update_site_option('active_sitewide_plugins', $active);
    }

    /**
     * Mark the plugin as network activated for the current test run.
     */
    private function activate_network_plugin(): void {
        $basename = $this->get_plugin_basename();

        $active = get_site_option('active_sitewide_plugins', array());
        $active[$basename] = time();
        update_site_option('active_sitewide_plugins', $active);
    }

    /**
     * Resolve the plugin basename using the WordPress helper to ensure tests match runtime behavior.
     *
     * @return string
     */
    private function get_plugin_basename(): string {
        $plugin_file = dirname(__DIR__) . '/plugin/send-from.php';
        return plugin_basename($plugin_file);
    }
}
