<?php
/*
Plugin Name: Send From
Plugin URI: http://wordpress.org/plugins/send-from/
Description: Plugin for modifying the from line on all emails coming from WordPress.
Version: 2.4
Author: Benjamin Buddle
Author URI: https://github.com/mahoskye
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
SPDX-License-Identifier: GPL-2.0-or-later
*/

// Plugin constants
if(!defined('SEND_FROM_VERSION')) {
	define('SEND_FROM_VERSION', '2.4');
}
if(!defined('SEND_FROM_TEXTDOMAIN')) {
	define('SEND_FROM_TEXTDOMAIN', 'send-from');
}

if(!class_exists('Send_From')){
	class Send_From{

		/**
		 * Stores the plugin options.
		 *
		 * @var array
		 */
		private $send_from_options;

		/**
		 * Initialize the plugin.
		 */
		public function __construct(){
			$this->initialize_options();
			$this->register_hooks();
			$this->apply_mail_filters();
		}

		/**
		 * Initialize plugin options with defaults and migrate legacy settings.
		 */
		private function initialize_options(){
			$this->send_from_options = get_option('Send_From_Options');

			if(!get_option('Send_From_Options')){
				$this->create_default_options();
				$this->migrate_legacy_options();
				add_option('Send_From_Options', $this->send_from_options);
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
			$old_options = get_option('smf_options');
			if($old_options != false){
				$this->send_from_options = $old_options;
				delete_option('smf_options');
			}
		}

		/**
		 * Validate and normalize existing stored options for security.
		 */
		private function validate_and_normalize_options(){
			$current = get_option('Send_From_Options');
			if(is_array($current)){
				$validated = $this->validate_options($current);
				if($validated !== $current){
					update_option('Send_From_Options', $validated);
					$this->send_from_options = $validated;
					set_transient('send_from_normalized', true, 30);
				}
			}
		}

		/**
		 * Register WordPress hooks and actions.
		 */
		private function register_hooks(){
			add_action('admin_notices', [ $this, 'maybe_show_normalized_notice' ]);
			add_action('admin_init', [ $this, 'admin_init' ]);
			add_action('init', [ $this, 'load_textdomain' ]);
			add_action('admin_menu', [ $this, 'add_menu' ]);
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
			$options = get_option('Send_From_Options');
			return isset($options['mail_from']) ? $options['mail_from'] : '';
		}

		/**
		 * Get the mail from name.
		 *
		 * @return string The from name.
		 */
		public function get_mail_from_name(){
			// Always fetch the latest saved options to avoid stale values.
			$options = get_option('Send_From_Options');
			return isset($options['mail_from_name']) ? $options['mail_from_name'] : '';
		}

		/**
		 * Handle admin initialization.
		 */
		public function admin_init(){
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
			register_setting('Send_From_Settings_Group', 'Send_From_Options', [ $this, 'validate_options' ]);
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
			$options = get_option('Send_From_Options');
			// Escape attribute output to prevent stored XSS when rendering saved option values.
			$mail_from_val = isset($options['mail_from']) ? esc_attr($options['mail_from']) : '';
			echo "<input name='Send_From_Options_Update' type='hidden' value='updated' />";
			echo "<input id='Send_From_Settings_From' name='Send_From_Options[mail_from]' size='40' type='text' value='" . $mail_from_val . "' />";
		}

		/**
		 * Render the from name input field.
		 */
		public function render_from_name_input() {
			$options = get_option('Send_From_Options');
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
			if(get_transient('send_from_normalized')){
				delete_transient('send_from_normalized');
				echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Send From: plugin sanitized and normalized stored settings for security. Please review settings.', 'send-from') . '</p></div>';
			}
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
			add_submenu_page('plugins.php', 'Send From', 'Send From', 'manage_options', 'send-from', [ $this, 'render_settings_page' ]);
		}

		/**
		 * Render the plugin settings page.
		 */
		public function render_settings_page(){
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

				if ( isset( $_GET['settings-updated'] ) ) {
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
					$post_url = isset( $_GET['settings-updated'] ) ? remove_query_arg('settings-updated', wp_get_referer()) : "" ;
					echo esc_url($post_url); ?>">
					<?php settings_fields('Send_From_Send_Test_Group');
					do_settings_sections('Send_From_Send_Test');
					submit_button( esc_html__('Send Test', 'send-from') . ' &raquo;', 'secondary', 'Send_From_Send_Test'); ?>
				</form>
			</div>
<?php
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

			// Safely read and validate the posted test-send email address.
			$raw = '';
			if(isset($_POST['Send_From_Send_Test_Opts']['Send_From_Send_To'])){
				$raw = trim(wp_unslash($_POST['Send_From_Send_Test_Opts']['Send_From_Send_To']));
			}
			$to = sanitize_email($raw);
			if($to != '' && is_email($to)){
				/* translators: %s: recipient email address */
				$subject = sprintf( esc_html__('Send From: Test mail to %s', 'send-from'), $to );
				$message = esc_html__('This is a test email generated by the Send From WordPress plugin.', 'send-from');
				// Send the test mail with error handling
				ob_start();
				$result = wp_mail($to, $subject, $message);
				ob_get_clean();

				// Check if wp_mail succeeded
				if($result){
					echo '<div class="updated fade"><p>' . esc_html__('Test message has been sent.', 'send-from') . '</p></div>';
				} else {
					echo '<div class="error fade"><p>' . esc_html__('Failed to send test message. Please check your mail server configuration.', 'send-from') . '</p></div>';
				}
			} else {
				echo '<div class="error fade"><p>' . esc_html__('There was no valid email to send the message to, please fill out the Send Test To field with a valid address and try again.', 'send-from') . '</p></div>';
			}
		}
	}
}

if(class_exists('Send_From')){
	$send_from = new Send_From();
}
