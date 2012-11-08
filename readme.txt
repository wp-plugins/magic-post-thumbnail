=== Magic Post Thumbnail ===
Plugin Name:       Magic Post Thumbnail
Version:           1.2
Tags:              automatic, thumbnail, featured, image, google, generate, google image, magic
Author URI:        alex.re
Author:            Mcurly
Requires at least: 3.0
Tested up to:      3.4.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatically add a thumbnail for your posts. Retrieve first image from Google (based on post title) and add it as featured image

== Description ==
Automatically add a thumbnail for your posts. 
Retrieve first image from Google Images based on post title and add it as your featured thumbnail when you publish/update it.
Settings allow you to configure some settings:

* Safe Search
* Country Search
* Filter only images royalty-free
* Which Post type (Posts and Pages included) it is enabled

== Translations ==
* English
* French

== Screenshots ==
1. Settings page
2. Before Updating or creating a post. No Featured Image
3. After update. New Featured Image !

== Installation ==
= General Requirements =
* PHP: = allow_url_fopen = On
1. Activate the plugin
2. Go to Settings > Magic Post Thumbnail
3. Configure your settings and which post type you want to enable it
4. Go into a post ( without any featured image ) or create one, update/create it and your thumbnail is generated as featured image !

== Changelog ==
= 1.0 =
	* First release
	* Automatically generates featured images

= 1.1 =
	* Error Message on settings with allow_url_fopen OFF
	* Less empty image generated

= 1.2 =
	* Add "tbm=isch" at the end of the scrapping Google URL. Otherwise it doesn't work any more.