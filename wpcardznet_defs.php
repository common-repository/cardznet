<?php
/* 
Description: CardzNet Defines 
 
Copyright 2020 Malcolm Shergold

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

if (defined('WPCARDZNETLIB_TRACK_INCLUDES_FILE'))
{
	include WPCARDZNETLIB_TRACK_INCLUDES_FILE;
	trackIncludes(__FILE__);
}
	
if (!defined('WPCARDZNET_DEFS_INCLUDED'))
{
	define('WPCARDZNET_FILE_PATH', dirname(__FILE__).'/');
	
	if (!defined('WP_CONTENT_DIR'))
		define ('WP_CONTENT_DIR', dirname(dirname(WPCARDZNET_FILE_PATH)));
		
	if (!isset($siteurl)) $siteurl = get_option('siteurl');
	if (is_ssl())
	{
		$siteurl = str_replace('http://', 'https://', $siteurl);
		define('WPCARDZNET_URLROOT', 'https');
	}
	else
	{
		$siteurl = str_replace('https://', 'http://', $siteurl);
		define('WPCARDZNET_URLROOT', 'http');
	}

	define('WPCARDZNET_DEFS_INCLUDED', true);

	define('WPCARDZNET_PLUGIN_ID', 'wpcardznet');

	define('WPCARDZNET_OPTIONS_NAME', 'cardznetsettings');
	define('WPCARDZNET_DBGOPTIONS_NAME', 'cardznetdbgsettings');
	
	define('WPCARDZNET_PLUGINDESC', 'WPCardzNet');
		
	define('WPCARDZNET_ADMIN_PATH', WPCARDZNET_FILE_PATH . 'admin/');
	define('WPCARDZNET_INCLUDE_PATH', WPCARDZNET_FILE_PATH . 'include/');
	define('WPCARDZNET_CARDS_PATH', WPCARDZNET_FILE_PATH . 'cards/');	
	define('WPCARDZNET_GAMES_PATH', WPCARDZNET_FILE_PATH . 'games/');	
	define('WPCARDZNET_TEMPLATES_PATH', WPCARDZNET_FILE_PATH.'templates/');
	define('WPCARDZNET_ADMINICON_PATH', WPCARDZNET_ADMIN_PATH . 'images/');
	define('WPCARDZNET_TEST_PATH', WPCARDZNET_FILE_PATH . 'test/');

	define('WPCARDZNET_FOLDER', basename(WPCARDZNET_FILE_PATH));
	define('WPCARDZNET_URL', plugin_dir_url(__FILE__));
	define('WPCARDZNET_DOWNLOADS_URL', WPCARDZNET_URL . WPCARDZNET_FOLDER . '_download.php');
	define('WPCARDZNET_ADMIN_URL', WPCARDZNET_URL . 'admin/');
	define('WPCARDZNET_ADMIN_IMAGES_URL', WPCARDZNET_ADMIN_URL . 'images/');
	define('WPCARDZNET_GAMES_URL', WPCARDZNET_URL . 'games/');	
	define('WPCARDZNET_IMAGES_URL', WPCARDZNET_URL . 'images/');	

	define('WPCARDZNETLIB_INCLUDE_PATH', WPCARDZNET_INCLUDE_PATH);
	
	define('WPCARDZNET_UPLOADS_URL', WP_CONTENT_URL.'/uploads/'.WPCARDZNET_FOLDER.'/');
	
	if (!defined('WPCARDZNET_UPLOADS_PATH'))
	{
		define('WPCARDZNET_UPLOADS_PATH', WP_CONTENT_DIR.'/uploads/'.WPCARDZNET_FOLDER);				
		define('WPCARDZNETLIB_UPLOADS_PATH', WPCARDZNET_UPLOADS_PATH);				
		//define('WPCARDZNETLIB_UPLOADS_PATH', WP_CONTENT_DIR.'/plugins/'.WPCARDZNET_FOLDER.'/templates');						
	}

	if (!defined('WPCARDZNET_SHORTCODE'))
		define('WPCARDZNET_SHORTCODE', 'cardznet');
	
	define('WPCARDZNET_CODE_PREFIX', WPCARDZNET_PLUGIN_ID);

	define('WPCARDZNET_DOMAIN_MSG_OK', 'wpcardznet'.'-ok');
	define('WPCARDZNET_DOMAIN_MSG_UPDATE', 'wpcardznet'.'-update');
	define('WPCARDZNET_DOMAIN_MSG_ERROR', 'wpcardznet'.'-error');

	define('WPCARDZNET_MENUPAGE_ADMINMENU', WPCARDZNET_CODE_PREFIX.'_adminmenu');
	define('WPCARDZNET_MENUPAGE_OVERVIEW', WPCARDZNET_CODE_PREFIX.'_overview');
	define('WPCARDZNET_MENUPAGE_GROUPS', WPCARDZNET_CODE_PREFIX.'_groups');
	define('WPCARDZNET_MENUPAGE_GAMES', WPCARDZNET_CODE_PREFIX.'_games');
	define('WPCARDZNET_MENUPAGE_TOOLS', WPCARDZNET_CODE_PREFIX.'_tools');
	define('WPCARDZNET_MENUPAGE_SETTINGS', WPCARDZNET_CODE_PREFIX.'_settings');
	define('WPCARDZNET_MENUPAGE_DEVTEST', WPCARDZNET_CODE_PREFIX.'_devtest');
	define('WPCARDZNET_MENUPAGE_DIAGNOSTICS', WPCARDZNET_CODE_PREFIX.'_diagnostics');
	define('WPCARDZNET_MENUPAGE_TESTSETTINGS', WPCARDZNET_CODE_PREFIX.'_testsettings');

	define('WPCARDZNET_PLUGIN_NAME', 'WPCardzNet');
	
	define('WPCARDZNET_CAPABILITY_PLAYER', 'wpcardznet_player');			// A user that can play a game
	define('WPCARDZNET_CAPABILITY_MANAGER', 'wpcardznet_manager');		// A user that can start a game and add players to a group
	define('WPCARDZNET_CAPABILITY_ADMINUSER', 'wpcardznet_admin');		// A user that can administer WPPlayCards
	define('WPCARDZNET_CAPABILITY_SETUPUSER', 'wpcardznet_setup');		// A user that can change WPPlayCards settings
	define('WPCARDZNET_CAPABILITY_DEVUSER', 'wpcardznet_testing');		// A user that can use test pages

	if (!defined('WPCARDZNETLIB_CAPABILITY_SETUPUSER'))
		define('WPCARDZNETLIB_CAPABILITY_SETUPUSER', WPCARDZNET_CAPABILITY_SETUPUSER);	

	if (!defined('WPCARDZNETLIB_CAPABILITY_DEVUSER'))
		define('WPCARDZNETLIB_CAPABILITY_DEVUSER', WPCARDZNET_CAPABILITY_DEVUSER);

	if (!defined('WPCARDZNET_TABLE_ROOT'))
		define('WPCARDZNET_TABLE_ROOT', 'cardznet_');
	global $wpdb;
	define('WPCARDZNET_TABLE_PREFIX', $wpdb->prefix.WPCARDZNET_TABLE_ROOT);
	
	// Set the DB tables names
	define('WPCARDZNET_GROUPS_TABLE', WPCARDZNET_TABLE_PREFIX.'groups');	
	define('WPCARDZNET_INVITES_TABLE', WPCARDZNET_TABLE_PREFIX.'invites');
	define('WPCARDZNET_MEMBERS_TABLE', WPCARDZNET_TABLE_PREFIX.'members');
	define('WPCARDZNET_GAMES_TABLE', WPCARDZNET_TABLE_PREFIX.'games');
	define('WPCARDZNET_PLAYERS_TABLE', WPCARDZNET_TABLE_PREFIX.'players');
	define('WPCARDZNET_ROUNDS_TABLE', WPCARDZNET_TABLE_PREFIX.'rounds');
	define('WPCARDZNET_HANDS_TABLE', WPCARDZNET_TABLE_PREFIX.'hands');
	define('WPCARDZNET_TRICKS_TABLE', WPCARDZNET_TABLE_PREFIX.'tricks');
	define('WPCARDZNET_SETTINGS_TABLE', WPCARDZNET_TABLE_PREFIX.'settings');

	define('WPCARDZNET_VISIBLE_NORMAL', 'Normal');
	define('WPCARDZNET_VISIBLE_ALWAYS', 'AlwaysVisible');
	define('WPCARDZNET_VISIBLE_NEVER', 'NeverVisible');

	define('WPCARDZNET_CALLBACK_ID', 'wpcardznet_cb');
	
	define('WPCARDZNETLIB_PLUGIN_ID', 'WPcardznet');
}
