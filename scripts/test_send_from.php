<?php
// This is a simple WP-CLI test script. Run inside the wordpress container with:
// wp eval-file scripts/test_send_from.php --allow-root

// Prepare a malicious payload for testing stored XSS
$malicious = "<script>alert('xss')</script>victim@example.com";

// Use update_option to simulate form submission via the settings API
update_option('Send_From_Options', array('mail_from' => $malicious, 'mail_from_name' => $malicious));

// Fetch the option and display it
$options = get_option('Send_From_Options');

// Output raw and sanitized values
echo "Stored mail_from: " . var_export($options['mail_from'], true) . "\n";
echo "Stored mail_from_name: " . var_export($options['mail_from_name'], true) . "\n";

// Now run the plugin's validation callback manually to simulate saving through the settings API
$instance = null;
if ( class_exists('Send_From') ) {
    $instance = new Send_From();
    $validated = $instance->Send_From_Options_Validation(array('mail_from' => $malicious, 'mail_from_name' => $malicious));
    echo "Validated mail_from: " . var_export($validated['mail_from'], true) . "\n";
    echo "Validated mail_from_name: " . var_export($validated['mail_from_name'], true) . "\n";
} else {
    echo "Send_From class not loaded\n";
}

// Re-fetch stored option after constructing the plugin to verify constructor normalization
$options_after = get_option('Send_From_Options');
echo "Stored mail_from after constructor: " . var_export($options_after['mail_from'], true) . "\n";
echo "Stored mail_from_name after constructor: " . var_export($options_after['mail_from_name'], true) . "\n";
