<?php 
/*
Plugin Name: Skyword Publishing API
Description: Integration with the Skyword360 content publication platform.
Version: 1.1.3
Author: Skyword, Inc.
Author URI: http://www.skyword.com
License: GPL2
*/

/*  Copyright 2023  Skyword, Inc.     This program is free software; you can redistribute it and/or modify    it under the terms of the GNU General Public License, version 2, as    published by the Free Software Foundation.     This program is distributed in the hope that it will be useful,    but WITHOUT ANY WARRANTY; without even the implied warranty of    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the    GNU General Public License for more details.     You should have received a copy of the GNU General Public License    along with this program; if not, write to the Free Software    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA */

if ( !defined('SKYWORD_API_PATH') )
	define( 'SKYWORD_API_PATH', plugin_dir_path( __FILE__ ) );
$plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), false);
$plugin_version = $plugin_data['Version'];
define( 'SKYWORD_REST_API_VERSION', "Skyword REST API plugin " . $plugin_version );
if ( !defined('SKYWORD_VN') )
	define( 'SKYWORD_VN', "1.13" ); //This CANNOT have two decimal places.
//.1.4 is NOT valid.

register_activation_hook(__FILE__, 'get_skyword_api_defaults');

// Set defaults on initial plugin activation
function get_skyword_api_defaults() {
	$tmp = get_option('skyword_api_plugin_options');
    if(!is_array($tmp)) {
		$arr = array(
		"skyword_api_api_key"=>null,
		"skyword_api_enable_ogtags" => true,
		"skyword_api_enable_metatags" => true,
		"skyword_api_enable_googlenewstag" => true,
		"skyword_api_enable_pagetitle" => true,
		"skyword_api_enable_sitemaps" => true,
		"skyword_api_generate_all_sitemaps" => true,
		"skyword_api_generate_news_sitemaps" => true,
		"skyword_api_generate_pages_sitemaps" => true,
		"skyword_api_generate_categories_sitemaps" => true,
		"skyword_api_generate_tags_sitemaps" => true,
		"skyword_api_generate_new_users_automatically" => true
		);
		update_option('skyword_api_plugin_options', $arr);
	}
}

require SKYWORD_API_PATH.'php/class-skyword-publish.php';
require SKYWORD_API_PATH.'php/class-skyword-sitemaps.php';
require SKYWORD_API_PATH.'php/class-skyword-shortcode.php';
require SKYWORD_API_PATH.'php/class-skyword-opengraph.php';
require SKYWORD_API_PATH.'php/options.php';
require SKYWORD_API_PATH.'php/routes/class-skyword-authors.php';
require SKYWORD_API_PATH.'php/routes/class-skyword-content-types.php';
require SKYWORD_API_PATH.'php/routes/class-skyword-images.php';
require SKYWORD_API_PATH.'php/routes/class-skyword-posts.php';
require SKYWORD_API_PATH.'php/routes/class-skyword-support.php';
require SKYWORD_API_PATH.'php/routes/class-skyword-taxonomies.php';
require SKYWORD_API_PATH.'php/routes/class-skyword-version.php';
require SKYWORD_API_PATH.'php/routes/class-skyword-tags.php';
require SKYWORD_API_PATH.'php/routes/class-skyword-categories.php';