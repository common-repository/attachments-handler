=== Attachments Handler ===

Author: sedLex
Contributors: sedLex
Author URI: http://www.sedlex.fr/
Plugin URI: http://wordpress.org/plugins/attachments-handler/
Tags: attachments, check, validity, documents, images, duplicate, not used
Requires at least: 3.0
Tested up to: 4.5
Stable tag: trunk
License: GPLv3

Enables the supervision of your attachement, detects duplicates, detects unused files.

== Description ==

Enables the supervision of your attachement, detects duplicates, detects unused files.

You may also create a list of all attached file in the page or in the child pages by using the following shorcode [attach child=1 only_admin=1 title='Title you want' extension='pdf,doc,png'].

= Multisite - Wordpress MU =

This plugin is compatible with MU installations.

= Localization =

* English (United States), default language

= Features of the framework =

This plugin uses the SL framework. This framework eases the creation of new plugins by providing tools and frames (see dev-toolbox plugin for more info).

You may easily translate the text of the plugin and submit it to the developer, send a feedback, or choose the location of the plugin in the admin panel.

Have fun !

== Installation ==

1. Upload this folder attachments-handler to your plugin directory (for instance '/wp-content/plugins/')
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to the 'SL plugins' box
4. All plugins developed with the SL core will be listed in this box
5. Enjoy !

== Screenshots ==

1. Issue detection
2. All links
3. Parameter screen

== Changelog ==

= 1.1.7 = 
* NEW: Various improvements of the core

= 1.1.6 = 
* NEW: deletion of temp file upon desinstall

= 1.1.5 = 
* Various enhancement
* NEW : add icons

= 1.1.4 = 
* BUG: Problem with the id_media , then we upgrade to BIGINT
* BUG: the deletion of unwanted post is improved as it does not work correctly

= 1.1.3 = 
* BUG: Issue when the number of checked posts is higher than the actual number of posts

= 1.1.2 = 
* NEW: Add a How-To tab
* NEW: Add an ignore button
* IMPROVE: Change the class name of the core to avoid conflict

= 1.1.1 = 
* BUG: Pb with PHP version below 5.2 (now resolved)

= 1.1.0 = 
* NEW: Add the detection of featured image
* NEW: Regenerate the thumbnails on demand

= 1.0.0 -&gt; 1.0.9 = 
* BUG: When the attachment is deleted, update the database to indicate such removal
* BUG: Correct various problems &lt;? instead of &lt;?php
* BUG: T_PAAMAYIM_NEKUDOTAYIM problem due to a &lt;? instead of &lt;?php
* NEW: improve the look of the configuration page of the plugin
* NEW: add a notification with the number of errors
* BUG: correct the tab selection saving that does not work since the update of jQuery by WP
* NEW: few enhancements in the framework
* NEW: limit the missing files to those in the media manager
* BUG : DELETE statement was incorrect
* Release ! 

== Frequently Asked Questions ==

 
InfoVersion:6b47204906c1ef1edb0efae45f7dedaeea077dc2