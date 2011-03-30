<?php
/*
Plugin Name: BuddyPress Wiki Component
Plugin URI: http://wordpress.org/extend/plugins/bp-wiki/
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=A9NEGJEZR23H4
Description: Enables site and group wiki functionality within a Buddypress install.
Version: 1.0.0
Revision Date: September 06, 2010
Requires at least: WP 3.0.1, BuddyPress 1.2.5.2
Tested up to: WP 3.0.2, BuddyPress 1.2.6
License: AGPL http://www.fsf.org/licensing/licenses/agpl-3.0.html
Author: David Cartwright
Author URI: http://namoo.co.uk
Network: true
*/

/*
This is beta software.
Don't use it yet.

Seriously!
*/

define( 'BP_DOCS_VERSION', '1.0-beta-2' );

/**
 * Loads BP Docs files only if BuddyPress is present
 *
 * @package BuddyPress Docs
 * @since 1.0-beta
 */
function bp_docs_init() {
	global $bp_docs;
	
	require( dirname( __FILE__ ) . '/bp-docs.php' );
	$bp_docs = new BP_Docs;
}
add_action( 'bp_include', 'bp_docs_init' );
?>
