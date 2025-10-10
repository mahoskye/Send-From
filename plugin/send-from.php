<?php
/*
Plugin Name: Send From
Plugin URI: http://wordpress.org/plugins/send-from/
Description: Plugin for modifying the from line on all emails coming from WordPress.
Version: 2.5
Author: Benjamin Buddle
Author URI: https://github.com/mahoskye
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
SPDX-License-Identifier: GPL-2.0-or-later
*/

// Plugin constants
if(!defined('SEND_FROM_VERSION')) {
	define('SEND_FROM_VERSION', '2.5');
}
if(!defined('SEND_FROM_TEXTDOMAIN')) {
	define('SEND_FROM_TEXTDOMAIN', 'send-from');
}

if(!class_exists('Send_From')){
	class Send_From{

		const OPTION_KEY = 'Send_From_Options';
		const LEGACY_OPTION_KEY = 'smf_options';
		const NORMALIZATION_TRANSIENT = 'send_from_normalized';
		const NETWORK_UPDATE_ACTION = 'send_from_update_options';
		const NETWORK_TEST_ACTION = 'send_from_send_test';

		/**
		 * Stores the plugin options.
		 *
		 * @var array
		 */
		private $send_from_options;

		/**
		 * Indicates whether the plugin is running in network (multisite) mode.
		 *
		 * @var bool
		 */
		private $is_network_mode = false;

		/**
		 * Cached plugin basename for network checks.
		 *
		 * @var string
		 */
		private $plugin_basename = '';

		/**
		 * Initialize the plugin.
		 */
		public function __construct(){
			$this->determine_network_mode();
			$this->initialize_options();
			$this->register_hooks();
			$this->apply_mail_filters();
		}

		/**
		 * Determine whether the plugin is network activated in a multisite environment.
		 */
		private function determine_network_mode(){
			$this->plugin_basename = plugin_basename(__FILE__);

			if(function_exists('is_multisite') && is_multisite()){
				if(!function_exists('is_plugin_active_for_network') && defined('ABSPATH')){
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				if(function_exists('is_plugin_active_for_network') && is_plugin_active_for_network($this->plugin_basename)){
					$this->is_network_mode = true;
				}
			}
		}

		/**
		 * Retrieve an option from the appropriate storage depending on activation mode.
		 *
		 * @param string $option_name Option key to retrieve.
		 * @param mixed  $default     Default fallback when no value stored.
		 * @return mixed Stored option value or default.
		 */
		private function get_storage_option($option_name, $default = false){
			if($this->is_network_mode){
				$value = get_site_option($option_name, null);
				if(null !== $value){
					return $value;
				}
				return get_option($option_name, $default);
			}

			return get_option($option_name, $default);
		}

		/**
		 * Add or update an option in the correct storage.
		 *
		 * @param string $option_name Option key to save.
		 * @param mixed  $value       Option value to persist.
		 */
		private function add_storage_option($option_name, $value){
			if($this->is_network_mode){
				if(false === get_site_option($option_name, false)){
					add_site_option($option_name, $value);
				} else {
					update_site_option($option_name, $value);
				}
				return;
			}

			add_option($option_name, $value);
		}

		/**
		 * Update an existing option in storage.
		 *
		 * @param string $option_name Option key to update.
		 * @param mixed  $value       New option value.
		 */
		private function update_storage_option($option_name, $value){
			if($this->is_network_mode){
				update_site_option($option_name, $value);
				return;
			}

			update_option($option_name, $value);
		}

		/**
		 * Delete an option from storage, cleaning up both scopes when in network mode.
		 *
		 * @param string $option_name Option key to delete.
		 */
		private function delete_storage_option($option_name){
			if($this->is_network_mode){
				delete_site_option($option_name);
				// Also remove any site-level copy to avoid unintended fallbacks.
				delete_option($option_name);
				return;
			}

			delete_option($option_name);
		}

		/**
		 * Set a transient flag for normalization feedback in the correct scope.
		 */
		private function set_normalization_flag(){
			if($this->is_network_mode){
				set_site_transient(self::NORMALIZATION_TRANSIENT, true, 30);
				return;
			}

			set_transient(self::NORMALIZATION_TRANSIENT, true, 30);
		}

		/**
		 * Retrieve the normalization feedback flag.
		 *
		 * @return mixed False when no flag is present.
		 */
		private function get_normalization_flag(){
			if($this->is_network_mode){
				return get_site_transient(self::NORMALIZATION_TRANSIENT);
			}

			return get_transient(self::NORMALIZATION_TRANSIENT);
		}

		/**
		 * Clear the normalization feedback flag.
		 */
		private function clear_normalization_flag(){
			if($this->is_network_mode){
				delete_site_transient(self::NORMALIZATION_TRANSIENT);
				return;
			}

			delete_transient(self::NORMALIZATION_TRANSIENT);
		}

		/**
		 * Redirect within the network admin including a status message.
		 *
		 * @param string $message_key   Query value indicating which notice to display.
		 * @param array  $extra_params  Additional query args for redirect.
		 */
		private function redirect_network_with_message($message_key, array $extra_params = array()){
			$args = array_merge(array(
				'page' => 'send-from',
				'send-from-message' => $message_key,
			), $extra_params);

			wp_safe_redirect(add_query_arg($args, network_admin_url('settings.php')));
			exit;
		}

		/**
		 * Initialize plugin options with defaults and migrate legacy settings.
		 */
		private function initialize_options(){
			$stored_options = $this->get_storage_option(self::OPTION_KEY, false);

			if(false === $stored_options){
				$this->create_default_options();
				$this->migrate_legacy_options();
				$this->add_storage_option(self::OPTION_KEY, $this->send_from_options);
			} else {
				$this->send_from_options = $stored_options;
			}

			$this->validate_and_normalize_options();
		}

		/**
		 * Create default plugin options.
		 */
		private function create_default_options(){
			$default_email = $this->get_default_email();
			$this->send_from_options = array(
				'mail_from' => $default_email,
				'mail_from_name' => 'WordPress'
			);
		}

		/**
		 * Migrate options from legacy version (smf_options).
		 */
		private function migrate_legacy_options(){
			$old_options = $this->get_storage_option(self::LEGACY_OPTION_KEY, false);
			if($old_options !== false && is_array($old_options)){
				$this->send_from_options = $old_options;
				$this->delete_storage_option(self::LEGACY_OPTION_KEY);
			}
		}

		/**
		 * Validate and normalize existing stored options for security.
		 */
		private function validate_and_normalize_options(){
			$current = $this->get_storage_option(self::OPTION_KEY, array());
			if(is_array($current)){
				$validated = $this->validate_options($current);
				if($validated !== $current){
					$this->update_storage_option(self::OPTION_KEY, $validated);
					$this->send_from_options = $validated;
					$this->set_normalization_flag();
				} else {
					$this->send_from_options = $current;
				}
			}
		}

		/**
		 * Register WordPress hooks and actions.
		 */
		private function register_hooks(){
			add_action('admin_notices', [ $this, 'maybe_show_normalized_notice' ]);
			if(function_exists('is_multisite') && is_multisite()){
				add_action('network_admin_notices', [ $this, 'maybe_show_normalized_notice' ]);
			}

			if($this->is_network_mode){
				add_action('network_admin_menu', [ $this, 'add_menu' ]);
				add_action('network_admin_edit_' . self::NETWORK_UPDATE_ACTION, [ $this, 'handle_network_options_update' ]);
				add_action('network_admin_edit_' . self::NETWORK_TEST_ACTION, [ $this, 'handle_network_test_email' ]);
			} else {
				add_action('admin_init', [ $this, 'admin_init' ]);
				add_action('admin_menu', [ $this, 'add_menu' ]);
			}

			add_action('init', [ $this, 'load_textdomain' ]);
		}

		/**
		 * Apply filters to modify WordPress mail from address and name.
		 */
		private function apply_mail_filters(){
			add_filter('wp_mail_from', [ $this, 'get_mail_from_address' ]);
			add_filter('wp_mail_from_name', [ $this, 'get_mail_from_name' ]);
		}

		/**
		 * Get the mail from email address.
		 *
		 * @return string The from email address.
		 */
		public function get_mail_from_address(){
			// Always fetch the latest saved options to avoid stale values.
			$options = $this->get_storage_option(self::OPTION_KEY, array());
			return isset($options['mail_from']) ? $options['mail_from'] : '';
		}

		/**
		 * Get the mail from name.
		 *
		 * @return string The from name.
		 */
		public function get_mail_from_name(){
			// Always fetch the latest saved options to avoid stale values.
			$options = $this->get_storage_option(self::OPTION_KEY, array());
			return isset($options['mail_from_name']) ? $options['mail_from_name'] : '';
		}

		/**
		 * Handle admin initialization.
		 */
		public function admin_init(){
			if($this->is_network_mode){
				return;
			}

			$this->init_settings();
		}

		/**
		 * Load plugin textdomain for translations.
		 */
		public function load_textdomain(){
			load_plugin_textdomain('send-from', false, dirname(plugin_basename(__FILE__)) . '/languages');
		}

		/**
		 * Register plugin settings and sections.
		 */
		public function init_settings(){
			if($this->is_network_mode){
				return;
			}

			register_setting('Send_From_Settings_Group', self::OPTION_KEY, [ $this, 'validate_options' ]);
			add_settings_section('Send_From_Settings_Main', '', [ $this,'render_settings_main_text' ], 'Send_From_Settings');
			add_settings_field('Send_From_Settings_From_Name', esc_html__('From Name:', 'send-from'), [ $this,'render_from_name_input' ],'Send_From_Settings', 'Send_From_Settings_Main');
			add_settings_field('Send_From_Settings_From', esc_html__('From Email:', 'send-from'), [ $this,'render_from_email_input' ],'Send_From_Settings', 'Send_From_Settings_Main');

			register_setting('Send_From_Send_Test_Group', 'Send_From_Send_Test_Opts', [ $this,'validate_test_email' ]);
			add_settings_section('Send_From_Send_Test_Main', esc_html__('Send a test message', 'send-from'), [ $this,'render_test_section_text' ],'Send_From_Send_Test');
			add_settings_field('Send_From_Send_Test_To', esc_html__('Send Test To:', 'send-from'), [ $this,'render_test_email_input' ], 'Send_From_Send_Test', 'Send_From_Send_Test_Main');
		}

		/**
		 * Render the main settings section description text.
		 */
		public function render_settings_main_text(){
			echo '<p>' . esc_html__('Here you have the opportunity to configure the From Name and Email that the server sends from. You will need to use a valid email account from your server otherwise this WILL NOT WORK. If left blank this will use the default name of WordPress and the default address wordpress@domain.', 'send-from') . '</p>';
		}

		/**
		 * Render the from email input field.
		 */
		public function render_from_email_input() {
			$options = $this->get_storage_option(self::OPTION_KEY, array());
			// Escape attribute output to prevent stored XSS when rendering saved option values.
			$mail_from_val = isset($options['mail_from']) ? esc_attr($options['mail_from']) : '';
			echo "<input name='Send_From_Options_Update' type='hidden' value='updated' />";
			echo "<input id='Send_From_Settings_From' name='Send_From_Options[mail_from]' size='40' type='text' value='" . $mail_from_val . "' />";
		}

		/**
		 * Render the from name input field.
		 */
		public function render_from_name_input() {
			$options = $this->get_storage_option(self::OPTION_KEY, array());
			// Escape attribute output to prevent stored XSS when rendering saved option values.
			$mail_from_name_val = isset($options['mail_from_name']) ? esc_attr($options['mail_from_name']) : '';
			echo "<input id='Send_From_Settings_From_Name' name='Send_From_Options[mail_from_name]' size='40' type='text' value='" . $mail_from_name_val . "' />";
		}

		/**
		 * Validate and sanitize plugin options.
		 *
		 * @param array $input The input options to validate.
		 * @return array The validated and sanitized options.
		 */
		public function validate_options($input){
			// Ensure expected keys exist and sanitize values before storing to prevent stored XSS.
			$newinput = array();
			// Sanitize email; if invalid or empty, fall back to previous option value.
			if(isset($input['mail_from'])){
				$san_email = sanitize_email(trim($input['mail_from']));
				// Only accept sanitized email if it's a valid email address; otherwise fall back to previous/default.
				if(!empty($san_email) && is_email($san_email) && $this->is_reasonable_email($san_email)){
					$newinput['mail_from'] = $san_email;
				} else {
					// Fall back to a safe default address using noreply@host to avoid storing invalid addresses.
					$newinput['mail_from'] = $this->get_default_email();
				}
			} else {
				$newinput['mail_from'] = isset($this->send_from_options['mail_from']) ? $this->send_from_options['mail_from'] : '';
			}
			// Sanitize name as plain text (strip tags, remove invalid UTF-8, etc.)
			if(isset($input['mail_from_name'])){
				$newinput['mail_from_name'] = sanitize_text_field(trim($input['mail_from_name']));
				if($newinput['mail_from_name'] == ''){
					$newinput['mail_from_name'] = isset($this->send_from_options['mail_from_name']) ? $this->send_from_options['mail_from_name'] : '';
				}
			} else {
				$newinput['mail_from_name'] = isset($this->send_from_options['mail_from_name']) ? $this->send_from_options['mail_from_name'] : '';
			}
			return $newinput;
		}

		/**
		 * Build a sane default email address using site host, preferring noreply@
		 *
		 * @return string The default email address.
		 */
		private function get_default_email(){
			$sitename = '';
			if(function_exists('home_url')){
				if(function_exists('wp_parse_url')){
					$host = wp_parse_url(home_url(), PHP_URL_HOST);
				} else {
					$parsed = parse_url(home_url());
					$host = isset($parsed['host']) ? $parsed['host'] : false;
				}
				if($host){
					$sitename = strtolower($host);
				}
			}
			if(empty($sitename) && isset($_SERVER['SERVER_NAME'])){
				$sitename = strtolower($_SERVER['SERVER_NAME']);
			}
			if(empty($sitename)){
				$sitename = 'example.com';
			}
			$sitename = substr($sitename, 0, 4) == 'www.' ? substr($sitename, 4) : $sitename;
			return 'noreply@' . $sitename;
		}

		/**
		 * Perform stricter email validation beyond WordPress is_email().
		 *
		 * Ensures the email has a proper domain structure with at least one dot
		 * and uses PHP's filter_var for additional validation.
		 *
		 * @param string $email The email address to validate.
		 * @return bool True if email passes strict validation, false otherwise.
		 */
		private function is_reasonable_email($email){
			// First check with PHP's built-in email filter
			if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
				return false;
			}

			// Split on @ to check domain structure
			$parts = explode('@', $email);
			if(count($parts) !== 2){
				return false;
			}

			$domain = $parts[1];

			// Domain must have at least one dot and be longer than 3 chars (e.g., "a.co")
			return (strpos($domain, '.') !== false) && (strlen($domain) > 3);
		}

		/**
		 * Show an admin notice if normalization occurred on load.
		 */
		public function maybe_show_normalized_notice(){
			if(!$this->get_normalization_flag()){
				return;
			}

			$this->clear_normalization_flag();
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Send From: plugin sanitized and normalized stored settings for security. Please review settings.', 'send-from') . '</p></div>';
		}

		/**
		 * Render the test email section description text.
		 */
		public function render_test_section_text(){
			echo '<p>' . esc_html__('Enter an email address to send a test message from the server.', 'send-from') . '</p>';
		}

		/**
		 * Render the test email input field.
		 */
		public function render_test_email_input() {
			// Add nonce field for CSRF protection
			wp_nonce_field('send_from_test_email_action', 'send_from_test_email_nonce');
			echo "<input name='Send_From_Send_Test_Opts_Update' type='hidden' value='updated' />";
			echo "<input id='Send_From_Send_Test_To_Input' name='Send_From_Send_Test_Opts[Send_From_Send_To]' size='40' type='text' value='' />";
		}

		/**
		 * Validate the test email address.
		 *
		 * @param array $input The input containing the test email address.
		 * @return array The validated test email data.
		 */
		public function validate_test_email($input){
			// Sanitize and validate the test recipient email address.
			$input_array = array('Send_From_Send_Test' => 'false');
			if(!isset($input['Send_From_Send_To'])){
				return $input_array;
			}
			$to = sanitize_email(trim($input['Send_From_Send_To']));
			if($to == '' || !is_email($to)){
				// invalid email address; do not mark as sent nor store unsafe value
				return $input_array;
			}
			$input_array = array('Send_From_Send_Test' => 'true', 'Send_From_Send_To' => $to );
			return $input_array;
		}

		/**
		 * Add plugin menu to WordPress admin.
		 */
		public function add_menu(){
			if($this->is_network_mode){
				add_submenu_page('settings.php', 'Send From', 'Send From', 'manage_network_options', 'send-from', [ $this, 'render_settings_page' ]);
				return;
			}

			add_submenu_page('plugins.php', 'Send From', 'Send From', 'manage_options', 'send-from', [ $this, 'render_settings_page' ]);
		}

		/**
		 * Render the plugin settings page.
		 */
		public function render_settings_page(){
			if($this->is_network_mode){
				$this->render_network_settings_screen();
				return;
			}

			$this->render_site_settings_screen();
		}

		/**
		 * Render settings UI when operating in site (non-network) context.
		 */
		private function render_site_settings_screen(){
			if(!current_user_can('manage_options')){
				wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'send-from') );
			}
?>
			<div class="wrap">
				<h2><?php echo esc_html__('Send From', 'send-from'); ?></h2>
<?php
				// When send test is clicked, attempt to send an email
				if(isset($_POST['Send_From_Send_Test_Opts_Update'])){
					$this->process_test_email_submission();
					$this->apply_mail_filters();
				}

				if(isset($_GET['settings-updated'])){
					echo '<div class="updated fade"><p>' . esc_html__('Settings saved.', 'send-from') . '</p></div>';
					$this->apply_mail_filters();
				}
				?>

				<form method="post" action="options.php">
					<?php settings_fields('Send_From_Settings_Group');
					do_settings_sections('Send_From_Settings');
					submit_button( esc_html__('Update Options', 'send-from'), 'primary', 'Submit'); ?>
				</form>

				<form method="post" action="<?php
					$post_url = isset($_GET['settings-updated']) ? remove_query_arg('settings-updated', wp_get_referer()) : '';
					echo esc_url($post_url); ?>">
					<?php settings_fields('Send_From_Send_Test_Group');
					do_settings_sections('Send_From_Send_Test');
					submit_button( esc_html__('Send Test', 'send-from') . ' &raquo;', 'secondary', 'Send_From_Send_Test'); ?>
				</form>
			</div>
<?php
		}

		/**
		 * Render settings UI when operating in network admin context.
		 */
		private function render_network_settings_screen(){
			if(!current_user_can('manage_network_options')){
				wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'send-from') );
			}

			$options = $this->get_storage_option(self::OPTION_KEY, $this->send_from_options);
?>
			<div class="wrap">
				<h1><?php echo esc_html__('Send From', 'send-from'); ?></h1>
				<?php $this->render_network_feedback_message(); ?>

				<form method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=' . self::NETWORK_UPDATE_ACTION)); ?>">
					<?php wp_nonce_field('send_from_network_update_action', 'send_from_network_update_nonce'); ?>
					<input name="Send_From_Options_Update" type="hidden" value="updated" />
					<?php $this->render_settings_main_text(); ?>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="Send_From_Settings_From_Name"><?php echo esc_html__('From Name:', 'send-from'); ?></label></th>
								<td>
									<input id="Send_From_Settings_From_Name" name="Send_From_Options[mail_from_name]" size="40" type="text" value="<?php echo isset($options['mail_from_name']) ? esc_attr($options['mail_from_name']) : ''; ?>" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="Send_From_Settings_From"><?php echo esc_html__('From Email:', 'send-from'); ?></label></th>
								<td>
									<input id="Send_From_Settings_From" name="Send_From_Options[mail_from]" size="40" type="text" value="<?php echo isset($options['mail_from']) ? esc_attr($options['mail_from']) : ''; ?>" />
								</td>
							</tr>
						</tbody>
					</table>
					<?php submit_button( esc_html__('Update Options', 'send-from'), 'primary', 'Submit'); ?>
				</form>

				<form method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=' . self::NETWORK_TEST_ACTION)); ?>">
					<?php wp_nonce_field('send_from_network_test_action', 'send_from_network_test_nonce'); ?>
					<input name="Send_From_Send_Test_Opts_Update" type="hidden" value="updated" />
					<?php $this->render_test_section_text(); ?>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="Send_From_Send_Test_To_Input"><?php echo esc_html__('Send Test To:', 'send-from'); ?></label></th>
								<td>
									<input id="Send_From_Send_Test_To_Input" name="Send_From_Send_Test_Opts[Send_From_Send_To]" size="40" type="text" value="" />
								</td>
							</tr>
						</tbody>
					</table>
					<?php submit_button( esc_html__('Send Test', 'send-from') . ' &raquo;', 'secondary', 'Send_From_Send_Test'); ?>
				</form>
			</div>
<?php
		}

		/**
		 * Display feedback messages in network admin based on query vars.
		 */
		private function render_network_feedback_message(){
			if(!isset($_GET['send-from-message'])){
				return;
			}

			$message_key = strtolower(wp_unslash($_GET['send-from-message']));
			$message_key = preg_replace('/[^a-z0-9_-]/', '', $message_key);
			$messages = array(
				'updated' => array('class' => 'notice notice-success is-dismissible', 'text' => esc_html__('Settings saved.', 'send-from')),
				'test-success' => array('class' => 'notice notice-success is-dismissible', 'text' => esc_html__('Test message has been sent.', 'send-from')),
				'test-failed' => array('class' => 'notice notice-error', 'text' => esc_html__('Failed to send test message. Please check your mail server configuration.', 'send-from')),
				'test-invalid' => array('class' => 'notice notice-error', 'text' => esc_html__('There was no valid email to send the message to, please fill out the Send Test To field with a valid address and try again.', 'send-from')),
				'security-failed' => array('class' => 'notice notice-error', 'text' => esc_html__('Security check failed. Please try again.', 'send-from')),
			);

			if(isset($messages[$message_key])){
				printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($messages[$message_key]['class']), esc_html($messages[$message_key]['text']));
			}
		}

		/**
		 * Handle saving of network-level settings.
		 */
		public function handle_network_options_update(){
			if(!current_user_can('manage_network_options')){
				wp_die(
					esc_html__('You do not have sufficient permissions to access this page.', 'send-from'),
					esc_html__('Permission denied', 'send-from'),
					array('response' => 403)
				);
			}

			if(!isset($_POST['send_from_network_update_nonce']) || !wp_verify_nonce($_POST['send_from_network_update_nonce'], 'send_from_network_update_action')){
				$this->redirect_network_with_message('security-failed');
			}

			$raw_input = array();
			if(isset($_POST['Send_From_Options'])){
				$raw_input = wp_unslash($_POST['Send_From_Options']);
				if(!is_array($raw_input)){
					$raw_input = array();
				}
			}

			$validated = $this->validate_options($raw_input);
			$this->update_storage_option(self::OPTION_KEY, $validated);
			$this->send_from_options = $validated;
			$this->apply_mail_filters();

			$this->redirect_network_with_message('updated');
		}

		/**
		 * Handle sending of a test email from the network settings UI.
		 */
		public function handle_network_test_email(){
			if(!current_user_can('manage_network_options')){
				wp_die(
					esc_html__('You do not have sufficient permissions to access this page.', 'send-from'),
					esc_html__('Permission denied', 'send-from'),
					array('response' => 403)
				);
			}

			if(!isset($_POST['send_from_network_test_nonce']) || !wp_verify_nonce($_POST['send_from_network_test_nonce'], 'send_from_network_test_action')){
				$this->redirect_network_with_message('security-failed');
			}

			$test_opts = array();
			if(isset($_POST['Send_From_Send_Test_Opts'])){
				$test_opts = wp_unslash($_POST['Send_From_Send_Test_Opts']);
				if(!is_array($test_opts)){
					$test_opts = array();
				}
			}

			$validated = $this->validate_test_email($test_opts);
			if(!isset($validated['Send_From_Send_Test']) || 'true' !== $validated['Send_From_Send_Test']){
				$this->redirect_network_with_message('test-invalid');
			}

			$this->apply_mail_filters();
			$result = $this->deliver_test_email($validated['Send_From_Send_To']);

			$this->redirect_network_with_message($result ? 'test-success' : 'test-failed');
		}

		/**
		 * Send the plugin test email and return boolean success.
		 *
		 * @param string $to Recipient email address.
		 * @return bool True when wp_mail reports success.
		 */
		private function deliver_test_email($to){
			/* translators: %s: recipient email address */
			$subject = sprintf( esc_html__('Send From: Test mail to %s', 'send-from'), $to );
			$message = esc_html__('This is a test email generated by the Send From WordPress plugin.', 'send-from');
			ob_start();
			$result = wp_mail($to, $subject, $message);
			ob_end_clean();

			return (bool) $result;
		}

		/**
		 * Process test email form submission.
		 */
		private function process_test_email_submission(){
			// Verify nonce for CSRF protection
			if(!isset($_POST['send_from_test_email_nonce']) || !wp_verify_nonce($_POST['send_from_test_email_nonce'], 'send_from_test_email_action')){
				echo '<div class="error fade"><p>' . esc_html__('Security check failed. Please try again.', 'send-from') . '</p></div>';
				return;
			}

			$test_opts = array();
			if(isset($_POST['Send_From_Send_Test_Opts'])){
				$test_opts = wp_unslash($_POST['Send_From_Send_Test_Opts']);
				if(!is_array($test_opts)){
					$test_opts = array();
				}
			}

			$validated = $this->validate_test_email($test_opts);
			if(!isset($validated['Send_From_Send_Test']) || 'true' !== $validated['Send_From_Send_Test']){
				echo '<div class="error fade"><p>' . esc_html__('There was no valid email to send the message to, please fill out the Send Test To field with a valid address and try again.', 'send-from') . '</p></div>';
				return;
			}

			$this->apply_mail_filters();
			$result = $this->deliver_test_email($validated['Send_From_Send_To']);

			if($result){
				echo '<div class="updated fade"><p>' . esc_html__('Test message has been sent.', 'send-from') . '</p></div>';
			} else {
				echo '<div class="error fade"><p>' . esc_html__('Failed to send test message. Please check your mail server configuration.', 'send-from') . '</p></div>';
			}
		}
	}
}

if(class_exists('Send_From')){
	$send_from = new Send_From();
}
