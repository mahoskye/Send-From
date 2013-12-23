<?php 
/*
Plugin Name: Send From
Plugin URI: http://wordpress.org/plugins/send-from/
Description: Plugin for modifying the from line on all emails coming from WordPress.
Version: 2.0
Author: Benjamin Buddle
Author URI: http://www.mahoskye.com/
License: GPL2
*/

/**
 * @author Benjamin Buddle
 * @copyright Benjamin Buddle 2011-2014 All Rights Reserved
 * @license This code is released under GNU GENERAL PUBLIC LICENSE Version 3.0 <http://www.gnu.org/licenses/gpl.html>
 */

/**
 * CHANGELOG
 * 2.0 - Updated the code to fix naming conventions, reduce size, and fix and issue with the options page
 * 1.3 - Fixed typo
 * 1.2 - Fixed issue with update message not displaying properly
 * 1.1 - Fixed Error where default address was not properly used
 * 1.0 - Send Test Working and showing proper messages
 * 0.9 - Send Test Implemented and working, showing 'Settings Saved.'
 * 0.8 - Working without Send Test option
 * 0.7 - Added Options Page
 * 0.5 - Revision / working draft
 * 0.1 - Initial approact to content
 */
if(!class_exists('Send_From')){
	class Send_From{

		private $Send_From_Options;

		public function __construct(){
			$this->Send_From_Options = get_option('Send_From_Options');
			if(!get_option('Send_From_Options')){
				// Create a default email address & set for later use
				$sitename = strtolower($_SERVER['SERVER_NAME']);
				$sitename = substr($sitename,0,4)=='www.' ? substr($sitename, 4) : $sitename;
				$defaultEmail = 'wordpress@'.$sitename;
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
			// Hook into the admin actions
			add_action('admin_init', array(&$this, 'admin_init'));
			add_action('admin_menu', array(&$this, 'add_menu'));

			// Update Wordpress from options on activation
			$this->set_send_from_options();
		} // END public function __construct

		public function set_send_from_options(){
			add_filter('wp_mail_from', array(&$this, SF_Address));
			add_filter('wp_mail_from_name', array(&$this, SF_Name));
		} // END public function set_send_from_options

		public function SF_Address(){
			return $this->Send_From_Options['mail_from'];
		} // END public function SF_Address

		public function SF_Name(){
			return $this->Send_From_Options['mail_from_name'];
		} // END public function SF_Name

		public function admin_init(){
			$this->init_settings();
		} // END public function admin_init

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
			echo "<input name='Send_From_Options_Update' type='hidden' value='updated' /><input id='Send_From_Settings_From' name='Send_From_Options[mail_from]' size='40' type='text' value='{$options['mail_from']}' />";
		} // END public function Send_From_Settings_From_Input

		public function Send_From_Settings_From_Name_Input() {
			$options = get_option('Send_From_Options');
			echo "<input id='Send_From_Settings_From_Name' name='Send_From_Options[mail_from_name]' size='40' type='text' value='{$options['mail_from_name']}' />";
		} // END public function Send_From_Settings_From_Name_Input

		public function Send_From_Options_Validation($input){
			$newinput['mail_from'] = trim($input['mail_from']);
			$newinput['mail_from_name'] = trim($input['mail_from_name']);
			if($newinput['mail_from'] == '') {$newinput['mail_from'] = $this->Send_From_Options['mail_from'];}
			if($newinput['mail_from_name'] == '') {$newinput['mail_from_name'] = $this->Send_From_Options['mail_from_name'];}
			return $newinput;
		} // END public function Send_From_Options_Validation

		public function Send_From_Send_Test_Main_Text(){
			echo '<p>Enter an email address to send a test message from the server.</p>';
		} // END public function Send_From_Send_Test_Main_Text

		public function Send_From_Send_Test_To_Input() {
			$options = get_option('Send_From_Options');
			echo "<input name='Send_From_Send_Test_Opts_Update' type='hidden' value='updated' /><input id='Send_From_Send_Test_To_Input' name='Send_From_Send_Test_Opts[Send_From_Send_To]' size='40' type='text' value='' />";
		} // END public function Send_From_Send_Test_To_Input

		public function Send_From_Do_Send_Test($input){
			if($input['Send_From_Send_To'] == ''){
				$input_array = array('Send_From_Send_Test' => 'false');
				return $input_array;
			} // END if($input['Send_From_Send_To'] == '')
			$newinput = htmlspecialchars($input['Send_From_Send_To']);
			$input_array = array('Send_From_Send_Test' => 'true', 'Send_From_Send_To' => $newinput );
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
					$test_message = $_POST['Send_From_Send_Test_Opts'];
					$test_message = trim($test_message['Send_From_Send_To']);

					if($test_message != ''){
						// Set up the mail variables
						$to = $test_message;
						$subject = 'Send From: Test mail to ' . $to;
						$message = 'This is a test email generated by the Send From WordPress plugin.';

						// Send the test mail & display success
						ob_start();
						$result = wp_mail($to,$subject,$message);
						$Send_From_debug = ob_get_clean();
						echo '<div class="updated fade"><p>Test message has been sent.</p></div>';
					} else {
						echo '<div class="error fade"><p>There was no one to send the message to, please fill out the Send Test To field and try again.</p></div>';
					} // END if($test_message != '') else
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
					echo $post_url; ?>">
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