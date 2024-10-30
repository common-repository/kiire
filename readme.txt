=== Kiire ===
Contributors: implenton
Tags: admin, administration, plugin, post, page, custom post type, toolbar, integration
Requires at least: 4.0
Tested up to: 4.5.3
Stable tag: 1.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Mark posts, pages or custom post types as important for easy access from the toolbar and a separate section.

== Description ==

You can mark a post type as important from the frontend, as well as from the administration section. There are several handy links inserted to do that. It also provides and undo action.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/kiire` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. No configuration needed.

== Frequently Asked Questions ==

= Can I disable the plugin for certain post types? =

You can use the `kiire_exclude_post_type` filter to do that. Return an array containing the post types.

Here is an example to exclude the player and instrument custom post type:

`add_filter( 'kiire_exclude_post_type', function() {
    return array( 'player', 'instrument' );
}, 10 );`

Add this in your `functions.php` file.

= What "kiire" means? =

Kiire is an estonian word; it means [instant, urgent](https://translate.google.com/#et/en/kiire "Kiire translation with Google Translate").

== Screenshots ==

1. Importants section listing your collected posts.
2. Mark a post as important from edit screen.
3. Handy notification with undo action.
4. Toolbar shortcut for accessing latest marked posts.

== Changelog ==

= 1.0 =
* Kiire launch.