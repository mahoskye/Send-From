=== Send From ===
Contributors: Ben Buddle
Tags: mail, mailer, phpmailer, change, from, send, email
Requires at least: 3.2.1
Tested up to: 3.2.1
Stable tag: 1.3

Changes the From line on any email sent from the server.

== Description ==

I have issues with my hosting service not allowing me to easily set the 'From line' for my server email. When ever a new user signs up they see username@<hostingservice> even though they should see user@site.com. Before Send From you would be required to modify your installation of WordPress just about every time you do an update. No longer. With Send From, you simply go into your admin panel and set what the end user sees in their From line.

== Installation ==

1. Download
2. Upload to your `/wp-contents/plugins/` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress.

= Set Defaults =

1. Under the 'Plugins' click the link for 'Send Email'
2. Change the From Name and From Email
3. Click Update Options

== Frequently Asked Questions ==

= Why create this? =

I host through hostmonster which does not allow you to easily set the address that server communications use, and I was not willing to outright hack Wordpress (even though it does work). So, here's a plugin that preformes the same operation as the hack I would have needed.

= What is the minimum version that is useable with this plugin? =

I'm told that WordPress 2.7 changed several settings, including the way things are sent via mail. So I can't guarentee support before version 2.7.

= This pluging doesn't work with x, y, z. Will you add support? =

Chances are if it's not working with another plugin then I have not encountered the issue and am not likly to fix it. So, no, I'm sorry but I will not be adding support for x, y, z.

== Screenshots ==

1. Screenshot of the Plugins > Send From panel.
2. Screenshot of the Link in the Plugins menu.

== Support Questions ==

At this time I am not offering open support for this plugin. It should remain stable through the various WordPress updates, but if it ever becomes unstable then I will update it.

== Changelog ==
 1.3 - Fixed typo
 1.2 - Fixed issue with update message not displaying properly
 1.1 - Fixed Error where default address was not properly used
 1.0 - Send Test Working and showing proper messages
 0.9 - Send Test Implemented and working, showing 'Settings Saved.'
 0.8 - Working without Send Test option
 0.7 - Added Options Page
 0.5 - Revision / working draft
 0.1 - Initial approact to content