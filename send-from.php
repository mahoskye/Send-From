<?php 
/*
Plugin Name: Send From
Plugin URI: http://wordpress.org/plugins/send-from/
Description: Plugin for modifying the from line on all emails coming from WordPress.
Version: 2.3
Author: Benjamin Buddle
Author URI: https://github.com/mahoskye
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

 
if(!class_exists('Send_From')){
	class Send_From{

		private $Send_From_Options;

		public function __construct(){
			$this->Send_From_Options = get_option('Send_From_Options');
			if(!get_option('Send_From_Options')){
				// Create a default email address & set for later use.
				// Prefer WordPress site URL when available, fall back to SERVER_NAME, then 'example.com'.
				$defaultEmail = $this->get_default_email();
				$this->Send_From_Options = array('mail_from' => $defaultEmail,'mail_from_name' => 'WordPress');

				// Check for variables under the old option name and set if they exist
				$oldOptions = get_option('smf_options');
				if($oldOptions != FALSE){
					$this->Send_From_Options = $oldOptions;
					delete_option('smf_options');
				} // END if($oldOptions != FALSE)

				// Ensure a default value is set & stored
				add_option('Send_From_Options', $this->Send_From_Options);
			} // END if(!get_option('Send_From_Options'))

			// Defensive: normalize and validate any existing stored options (in case they were written directly).
			$current = get_option('Send_From_Options');
			if(is_array($current)){
				$validated = $this->Send_From_Options_Validation($current);
				// If validation changed values, update the stored option to the sanitized copy.
				if($validated !== $current){
					update_option('Send_From_Options', $validated);
					$this->Send_From_Options = $validated;
					// Notify admin that values were normalized on load.
					set_transient('send_from_normalized', true, 30);
				}
			}

			// Hook admin notice to display normalization message if needed.
			add_action('admin_notices', [ $this, 'maybe_show_normalized_notice' ]);
			// Hook into the admin actions
			add_action('admin_init', [ $this, 'admin_init' ]);
			// Load plugin textdomain for translations
			add_action('init', [ $this, 'load_textdomain' ]);
			add_action('admin_menu', array(&$this, 'add_menu'));

			// Update Wordpress from options on activation
			$this->set_send_from_options();
		} // END public function __construct

		public function set_send_from_options(){
			// Attach filter callbacks. short-array syntax is supported on supported PHP versions.
			add_filter('wp_mail_from', [ $this, 'SF_Address' ]);
			add_filter('wp_mail_from_name', [ $this, 'SF_Name' ]);
		} // END public function set_send_from_options

		public function SF_Address(){
			// Always fetch the latest saved options to avoid stale values.
			$options = get_option('Send_From_Options');
			return isset($options['mail_from']) ? $options['mail_from'] : '';
		} // END public function SF_Address

		public function SF_Name(){
			// Always fetch the latest saved options to avoid stale values.
			$options = get_option('Send_From_Options');
			return isset($options['mail_from_name']) ? $options['mail_from_name'] : '';
		} // END public function SF_Name

		public function admin_init(){
			$this->init_settings();
		} // END public function admin_init

		/**
		 * Load plugin textdomain for translations.
		 */
		public function load_textdomain(){
			load_plugin_textdomain('send-from', false, dirname(plugin_basename(__FILE__)) . '/languages');
		}

		public function init_settings(){
			register_setting('Send_From_Settings_Group', 'Send_From_Options', array(&$this, 'Send_From_Options_Validation'));
			add_settings_section('Send_From_Settings_Main', '', array(&$this,'Send_From_Settings_Main_Text'), 'Send_From_Settings');
			add_settings_field('Send_From_Settings_From_Name', 'From Name: ', array(&$this,'Send_From_Settings_From_Name_Input'),'Send_From_Settings', 'Send_From_Settings_Main');
			add_settings_field('Send_From_Settings_From', 'From Email: ', array(&$this,'Send_From_Settings_From_Input'),'Send_From_Settings', 'Send_From_Settings_Main');

			register_setting('Send_From_Send_Test_Group', 'Send_From_Send_Test_Opts', array(&$this,'Send_From_Do_Send_Test'));
			add_settings_section('Send_From_Send_Test_Main', 'Send a test message', array(&$this,'Send_From_Send_Test_Main_Text'),'Send_From_Send_Test');
			add_settings_field('Send_From_Send_Test_To', 'Send Test To: ', array(&$this,'Send_From_Send_Test_To_Input'), 'Send_From_Send_Test', 'Send_From_Send_Test_Main');
		} // END public function init_settings

		public function Send_From_Settings_Main_Text(){
			echo '<p>Here you have the opportunity to configure the From Name and Email that the server sends from. You will need to use a valid email account from your server otherwise this <strong>WILL NOT WORK</strong>. If left blank this will use the default name of <code>WordPress</code> and the default address <code>wordpress@domain</code>.</p>';
		} // END public function Send_From_Settings_Main_Text

		public function Send_From_Settings_From_Input() {
			$options = get_option('Send_From_Options');
			// Escape attribute output to prevent stored XSS when rendering saved option values.
			$mail_from_val = isset($options['mail_from']) ? esc_attr($options['mail_from']) : '';
			echo "<input name='Send_From_Options_Update' type='hidden' value='updated' />";
			echo "<input id='Send_From_Settings_From' name='Send_From_Options[mail_from]' size='40' type='text' value='" . $mail_from_val . "' />";
		} // END public function Send_From_Settings_From_Input

		public function Send_From_Settings_From_Name_Input() {
			$options = get_option('Send_From_Options');
			// Escape attribute output to prevent stored XSS when rendering saved option values.
			$mail_from_name_val = isset($options['mail_from_name']) ? esc_attr($options['mail_from_name']) : '';
			echo "<input id='Send_From_Settings_From_Name' name='Send_From_Options[mail_from_name]' size='40' type='text' value='" . $mail_from_name_val . "' />";
		} // END public function Send_From_Settings_From_Name_Input

		public function Send_From_Options_Validation($input){
			// Ensure expected keys exist and sanitize values before storing to prevent stored XSS.
			$newinput = array();
			// Sanitize email; if invalid or empty, fall back to previous option value.
			if(isset($input['mail_from'])){
				$san_email = sanitize_email(trim($input['mail_from']));
				// Only accept sanitized email if it's a valid email address; otherwise fall back to previous/default.
				if(!empty($san_email) && is_email($san_email)){
					$newinput['mail_from'] = $san_email;
				} else {
					// Fall back to a safe default address using noreply@host to avoid storing invalid addresses.
					$newinput['mail_from'] = $this->get_default_email();
				}
			} else {
				$newinput['mail_from'] = isset($this->Send_From_Options['mail_from']) ? $this->Send_From_Options['mail_from'] : '';
			}
			// Sanitize name as plain text (strip tags, remove invalid UTF-8, etc.)
			if(isset($input['mail_from_name'])){
				$newinput['mail_from_name'] = sanitize_text_field(trim($input['mail_from_name']));
				if($newinput['mail_from_name'] == ''){
					$newinput['mail_from_name'] = isset($this->Send_From_Options['mail_from_name']) ? $this->Send_From_Options['mail_from_name'] : '';
				}
			} else {
				$newinput['mail_from_name'] = isset($this->Send_From_Options['mail_from_name']) ? $this->Send_From_Options['mail_from_name'] : '';
			}
			return $newinput;
		} // END public function Send_From_Options_Validation

		/**
		 * Build a sane default email address using site host, preferring noreply@
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
			$sitename = substr($sitename,0,4)=='www.' ? substr($sitename, 4) : $sitename;
			return 'noreply@' . $sitename;
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

		public function Send_From_Send_Test_Main_Text(){
			echo '<p>Enter an email address to send a test message from the server.</p>';
		} // END public function Send_From_Send_Test_Main_Text

		public function Send_From_Send_Test_To_Input() {
			$options = get_option('Send_From_Options');
			// Render an empty input for the test-to field; escaping applied if populating a default in future.
			echo "<input name='Send_From_Send_Test_Opts_Update' type='hidden' value='updated' />";
			echo "<input id='Send_From_Send_Test_To_Input' name='Send_From_Send_Test_Opts[Send_From_Send_To]' size='40' type='text' value='' />";
		} // END public function Send_From_Send_Test_To_Input

		public function Send_From_Do_Send_Test($input){
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
		} // END public function Send_From_Do_Send_Test

		public function add_menu(){
			add_submenu_page('plugins.php', 'Send From', 'Send From', 'manage_options', 'send-from', array(&$this, 'send_from_settings_page'));
		} // END public function add_menu

		public function send_from_settings_page(){
			if(!current_user_can('manage_options')){
				wp_die('You do not have sufficient permissions to access this page.');
			} // END if(!current_user_can('manage_options'))
?>
			<div class="wrap">
				<?php screen_icon(); ?>
				<h2>Send From</h2>
<?php
				// When send test is clicked, attempt to send an email 
				if(isset($_POST['Send_From_Send_Test_Opts_Update'])){
					// Safely read and validate the posted test-send email address.
					$raw = '';
					if(isset($_POST['Send_From_Send_Test_Opts']['Send_From_Send_To'])){
						$raw = trim(wp_unslash($_POST['Send_From_Send_Test_Opts']['Send_From_Send_To']));
					}
					$to = sanitize_email($raw);
					if($to != '' && is_email($to)){
						$subject = 'Send From: Test mail to ' . $to;
						$message = 'This is a test email generated by the Send From WordPress plugin.';

						// Send the test mail & display success (do not echo raw input)
						ob_start();
						$result = wp_mail($to, $subject, $message);
						ob_get_clean();
						echo '<div class="updated fade"><p>' . esc_html__('Test message has been sent.', 'send-from') . '</p></div>';
					} else {
						echo '<div class="error fade"><p>' . esc_html__('There was no valid email to send the message to, please fill out the Send Test To field with a valid address and try again.', 'send-from') . '</p></div>';
					}
					// Update Wordpress from options on activation
					$this->set_send_from_options();
				} // End Post Actions

				if ( isset( $_GET['settings-updated'] ) ) {
					echo '<div class="updated fade"><p>Settings saved.</p></div>';
					// Update Wordpress from options on activation
					$this->set_send_from_options();
				} // END if(isset($_GET['settings-updated']))
				?>

				<form method="post" action="options.php">
					<?php settings_fields('Send_From_Settings_Group');
					do_settings_sections('Send_From_Settings');
					submit_button('Update Options', 'primary', 'Submit'); ?>
				</form>

				<form method="post" action="<?php
					$post_url = isset( $_GET['settings-updated'] ) ? remove_query_arg('settings-updated', wp_get_referer()) : "" ;
					echo esc_url($post_url); ?>">
					<?php settings_fields('Send_From_Send_Test_Group');
					do_settings_sections('Send_From_Send_Test');
					submit_button('Send Test &raquo;', 'secondary', 'Send_From_Send_Test'); ?>
				</form>
			</div>
<?php
		} // END public function send_from_settings_page
	} // END class Send_From
} // END if(!class_exists('Send_From'))

if(class_exists('Send_From')){
	$send_from = new Send_From();
} // END if(class_exists('Send_From'))