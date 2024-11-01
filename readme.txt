=== Forums ===
Contributors: choppedcode
Tags: forum, bulletin board, support, discussion, social engine, groups, subscribe, mybb
Requires at least: 2.1.7
Tested up to: 3.6.1
Stable tag: 1.4.6

Forums is a plugin that integrates the powerfull myBB bulletin board software with Wordpress. It brings one of the most powerfull free forum softwares in reach of Wordpress users.
== Description ==

[MyBB](http://www.mybboard.net "MyBB") is an easy to use, powerful, multilingual, feature-packed, and free forum software.

WordPress ... well you know.

Forums provides the glue to connect both providing a fully functional proven forum & bulletin board solution. 

*** PLEASE NOTE WE ARE NO LONGER ACTIVELY SUPPORTING THIS PLUGIN AND PROVIDE IT ON AN AS-IS BASIS WITHOUT SUPPORT ***

== Installation ==

1. Upload the `zingiri-forum` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to the Wordpress Settings page and find the link to the Admininistration Panel of Forums, login with the default user admin and password admin.

Please visit the [Zingiri](http://forums.zingiri.net/forumdisplay.php?fid=22 "Zingiri Support Forum") for more information and support.

== MyBB Hacks ==

This section provides a quick overview of key MyBB files that had to be modified to integrate it seamlessly with Wordpress. Note that the list is not exhaustive.

* admin/modules/tools/system_health.php
* install/index.php
* install/upgrade.php
* admin/styles/zingiri: custom styles
* inc/wp-settings.php: path set in config.php
* inc/settings.php
* inc/config.php: force $settings['bburl'] with $_GET['zing']
* jscripts/thread.js: ajax request, pass full http
* wp-attachment.php

== Changelog ==

= 1.4.6 =
* Various changes to readme.txt
* Removed support-us file

= 1.4.5 =
* Verified compatibility with Wordpress 3.6.1
* Added end of support notice

= 1.4.4 =
* Fixed security issue (thanks to Charlie Eriksen)
* Verified compatibility with Wordpress 3.5

= 1.4.3 =
* Fixed minor bugs
* Upgrade MyBB to version 1.6.8
* Improved logging
* Updated installation instructions

= 1.4.2 =
* Replaced remote logo with local version

= 1.4.1 =
* Fixed issue with warning for PHP sessions not being displayed
* Updated new installs to MyBB version 1.6.6
* Corrected issue with minimum PHP version required

= 1.4.0 =
* Backup and restore mybb folder in case of upgrades
* Upgraded to MyBB version 1.6.5
* Ensured compatibility with Wordpress 3.3
* Added check that PHP version 5.3 or higher is installed

= 1.3.1 =
* Added possibility to upload avatars
* Security update
* Fixed lang_select issue

= 1.3.0 =
* Upgrade mybb to version 1.6.4
* Fixed issue with uploads path and avatar path
* Fixed issue with wpusers class being redeclared in case of deleting multiple WP users
* Replaced deprecated function get_settings()
* Improved pre-installation and pre-upgrade conditions checking
* Added Support Us page
* Moved uninstall function to deactivation, i.e. the plugin is now completely uninstalled when the plugin is deactivated
* Replaced deprecated function get_users_of_blog()
* Changed method of loading database
* Corrected minor syntax errors
* Added verification that PHP sessions are properly configured 
* Moved footer to appear on page only

= 1.2.1 =
* Checked compatibility with Wordpress 3.2.1
* Fixed conflict with Tracker plugin
* Renamed plugin to "Forums"

= 1.2.0 =
* Added automatic compatibility with MyBB themes
* Fixed issue with forum pagination not working
* Fixed issue with attachment not downloading correctly
* Remove unnecessary line break in forum output
* Changed display of errors & warnings and made it more prominent
* Fixed buddy popup issue
* Added check that mybb/uploads directory is writable

= 1.1.1 =
* Renamed plugin to ccForum

= 1.1.0 =
* Upgrade MyBB to version 1.6.0
* Removed Documentation folder from included MyBB folder
* Added automatic clean up of cache folder (files older than 48 hours are removed)
* Fixed issue "Warning: urlencode() expects parameter 1 to be string, array given in ..."
* When login/logout in the forum, the user is now redirected to the forum page instead of the site home page

= 1.0.9 =
* Fixed issue with warning message about session started being displayed prior to installation
* Corrected issue with footer displaying error message "Invalid argument supplied for foreach()"
* Fixed issue with session start in http class throwing a warning message
* Fixed issue with downloading of attachments not working
* Removed old duplicate menu link in Settings menu
* Verified compatibility with Wordpress 3.0.1

= 1.0.8 =
* Fixed issue with users registering via Wordpress not being created in MyBB

= 1.0.7 =
* In case of WP integration, users deleted in Wordpress are also deleted from the forum
* Fixed issue with quotes being escaped when updating data

= 1.0.6 =
* Plugin now supports WP admin users with a login name different from admin
* Added more debugging information to log
* Fixed issue with image links in MyBB style sheets

= 1.0.5 =
* Don't display administration menu if plugin is not installed yet
* Fixed link for Lite (Archive) mode
* Added possibility to use a different database for MyBB (useful when migrating an existing installation of MyBB)
* Fixed issues with login not working after installation
* Removed MyBB login attempts limit and timeout
* Added MyBB sub folder connection verification
* Added option to position footer site wide, on forum page only or to disable it

= 1.0.4 =
* Removed reference to quote_smart() function which is undefined

= 1.0.3 =
* Fixed installation issue related to the use of $blog_id
* Fixed compatibility issue with Zingiri Web Shop
* Added French language files

= 1.0.2 =
* Moved MyBB administration menu to Wordpress backend
* Fixed issue with themes not rendering probably when disk cache activated (cache/themes directory)
* Avatars can now be selected from the built in library
* Admin sub menu tabs are now showing properly instead of being listed one after the other
* Fixed issue with help hyperlink not working

= 1.0.1 =
* Added support for Wordpress 3.0 beta 1

= 1.0.0 =
* Reviewed MyBB activation process (cURL instead of database updates)
* Replaced admin logout URL's with WP URL's in case of WP user integration
* Upgraded MyBB to 1.4.13
* Fixed issue with plugin showing empty pages if local install uses an IP instead of a host name

= 0.9.2 =
* Code clean up in preparation of mybb 1.4.13 upgrade
* Added automated admin login in case of WP user integration
* Replaced login/logout URL's with WP URL's in case of WP user integration

= 0.9.1 =
* Added full user integration between Wordpress and myBB users

= 0.8 = 
* Fixed issue with file uploads

= 0.7 =
* Fixed issue with single and double quotes not working in post
* Fixed issue with new quick reply not working
* Fixed issue with pagination when reaching end of page
* Resolved issue with Open Buddy List not working
* Fixed issue with Report link not working
* Fixed issue with quoting posts not working
* Resolved issue with Help shortcut not working

= 0.6 =
* Fixed issue with registration form, click on "I agree" didn't go to the right page

= 0.5 =
* Fixed issue with captcha image not showing
* Added tmp extension to cache files

= 0.4 =
* Fixed issue with "fatal error" occuring on activation
* Improved integration caching of cookies
* Changed default database from mysqli to mysql

= 0.3 =
* Added check to see if cURL installed
* Added missing stylesheets
* Added missing required file
* Added support for PHP safe mode and open base dir configuration

= 0.2 =
* First public release