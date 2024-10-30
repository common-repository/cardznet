<?php
/* 
Plugin Name: CardzNet
Plugin URI: http://www.corondeck.co.uk/
Version: 2.5.1
Author: Malcolm Shergold
Author URI: http://www.corondeck.co.uk
Description: Internet Connected Multiplayer Card Game 
 
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

define('WPCARDZNET_PLUGIN_FILE', __FILE__);

include 'wpcardznet_defs.php';
include WPCARDZNET_INCLUDE_PATH.'wpcardznetlib_escaping.php';
if (!class_exists('WPCardzNetDBaseClass')) 
	include WPCARDZNET_INCLUDE_PATH.'wpcardznet_dbase_api.php';

if (!class_exists('WPCardzNetPluginClass')) 
{
	class WPCardzNetPluginClass
	{
		var $myDBaseObj;
		
		function __construct($pluginFile, $myDBaseObj) 
		{
			$this->myDBaseObj = $myDBaseObj;
			
			//parent::__construct($pluginFile);
			
			if (!isset($this->pluginDesc))
				$this->pluginDesc = WPCARDZNET_PLUGINDESC;
				
			$this->adminPagePrefix = basename(dirname($pluginFile));
			
			// Init options & tables during activation & deregister init option
			register_activation_hook( $pluginFile, array(&$this, 'activate') );
			register_deactivation_hook( $pluginFile, array(&$this, 'deactivate') );	
			
			$this->adminClassFilePrefix = 'wpcardznet';
			$this->adminClassPrefix = 'WPCardzNet';
			
			$this->SetEnv($pluginFile);

			$this->GetWPCardzNetOptions();
			
			//Actions
			add_action('admin_menu', array(&$this, 'WPCardzNet_ap'));
			
			add_action('wp_print_styles', array(&$this, 'load_user_styles') );
			add_action('wp_print_scripts', array(&$this, 'load_user_scripts') );
			
			add_action('admin_enqueue_scripts', array(&$this, 'load_admin_styles') );

			// Add action for processing callbacks
			add_filter('the_content', array(&$this, 'OnPageLoad'), 10, 1);
						
			// Add actions for AJAX handlers
			add_action("wp_ajax_wpcardznet_ajax_request" , array(&$this, 'wpcardznet_ajax_call') );
			add_action("wp_ajax_nopriv_wpcardznet_ajax_request" , array(&$this, 'wpcardznet_ajax_call') );

			add_shortcode(WPCARDZNET_SHORTCODE, array(&$this, 'wpcardznet_wp_shortcode'));
			add_shortcode('wpcardznet', array(&$this, 'wpcardznet_wp_shortcode'));
			
			if ($myDBaseObj->checkVersion())
			{
				// Versions are different ... call activate() to do any updates
				$this->activate();
			}	

		}
		
		function SetEnv($pluginFile)
		{
			$this->env = array(
			    'Caller' => $pluginFile,
			    'PluginObj' => $this,
			    'DBaseObj' => $this->myDBaseObj,

			);
			
			return $this->env;
		}
		
		//Returns an array of admin options
		function GetWPCardzNetOptions() 
		{
			$myDBaseObj = $this->myDBaseObj;
			return $myDBaseObj->adminOptions;
		}
    
		// Saves the admin options to the options data table
		function SaveWPCardzNetOptions() 
		{
			$myDBaseObj = $this->myDBaseObj;
			$myDBaseObj->saveOptions();
		}
    
	    // ----------------------------------------------------------------------
	    // Activation / Deactivation Functions
	    // ----------------------------------------------------------------------
	    
	    function activate() 
		{
			$myDBaseObj = $this->myDBaseObj;
			$this->SaveWPCardzNetOptions();
      
			$myDBaseObj->upgradeDB();
		}

	    function deactivate()
	    {
	    }

		function load_user_styles() 
		{
			//Add Style Sheet
			$this->myDBaseObj->enqueue_style(WPCARDZNET_CODE_PREFIX.'-user', WPCARDZNET_URL.'css/wpcardznet.css'); // WPCardzNet core style
		}
		
		function load_user_scripts()
		{
			$myDBaseObj = $this->myDBaseObj;

			$reloadParam = false;
			if (defined('WPCARDZNETLIB_JS_NOCACHE')) $reloadParam = time();
			
			// Add our own Javascript
			$myDBaseObj->enqueue_script( 'wpcardznetlib-js', plugins_url( 'js/wpcardznetlib_js.js', __FILE__));
			$myDBaseObj->enqueue_script( 'wpcardznetlib-fs', plugins_url( 'js/wpcardznetlib_fullscreen.js', __FILE__));
			$myDBaseObj->enqueue_script( 'wpcardznet-js', plugins_url( 'js/wpcardznet.js', __FILE__));
			
			wp_enqueue_script('jquery');
		}	
		
		function load_admin_styles()
		{
			$myDBaseObj = $this->myDBaseObj;
			
			// Add our own style sheet
			$myDBaseObj->enqueue_style( WPCARDZNET_CODE_PREFIX.'-admin', plugins_url( 'admin/css/wpcardznet-admin.css', __FILE__ ));
			
			// Add our own Javascript
			$myDBaseObj->enqueue_script( 'wpcardznetlib_admin', plugins_url( 'admin/js/wpcardznetlib_admin.js', __FILE__));
			$myDBaseObj->enqueue_script( 'wpcardznet_admin', plugins_url( 'admin/js/wpcardznet_admin.js', __FILE__));
		}

		function adminClass($env, $classId, $fileName)
		{
			$fileName = $env['PluginObj']->adminClassFilePrefix.'_'.$fileName.'.php';
			include 'admin/'.$fileName;
			
			$classId = $env['PluginObj']->adminClassPrefix.$classId;
			return new $classId($env);
		}
		
		function printAdminPage() 
		{
			$this->adminPageActive = true;

			// Change to use Admin Database Object  
			include WPCARDZNET_INCLUDE_PATH.'wpcardznet_admin_dbase_api.php';
			
			// Use existing value for sessionCookieID to prevent session_start error
			$sessionCookieID = isset($this->myDBaseObj->sessionCookieID) ? $this->myDBaseObj->sessionCookieID : 0;
			$myDBaseObj = $this->myDBaseObj = new WPCardzNetAdminDBaseClass(__FILE__, $sessionCookieID);

			$env = $this->SetEnv(__FILE__);

			//Prints out an admin page
			$pagePrefix = $this->adminPagePrefix;			
			$pageSubTitle = WPCardzNetLibUtilsClass::GetHTTPAlphaNumericElem('get', 'page');			
      		switch ($pageSubTitle)
      		{
				case WPCARDZNET_MENUPAGE_SETTINGS :
					$this->adminClass($env, 'SettingsAdminClass', 'manage_settings');
					break;
					
				case WPCARDZNET_MENUPAGE_GROUPS :
					$this->adminClass($env, 'GroupsAdminClass', 'manage_groups');
					break;
					
				case WPCARDZNET_MENUPAGE_GAMES :
					$this->adminClass($env, 'GamesAdminClass', 'manage_games');
					break;
					
				case WPCARDZNET_MENUPAGE_DEVTEST :
					include WPCARDZNET_TEST_PATH.'wpcardznetlib_devtestcaller.php';   
					new WPCardzNetLibDevCallerClass($this->env, 'WPCardzNet');
					break;
							
				case WPCARDZNET_MENUPAGE_DIAGNOSTICS :
					$this->adminClass($env, 'DebugAdminClass', 'debug');
					break;		
										
				case WPCARDZNET_MENUPAGE_OVERVIEW:
				case WPCARDZNET_MENUPAGE_ADMINMENU:
				default :
					$this->adminClass($env, 'OverviewAdminClass', 'manage_overview');
					break;
			}
		}
		
		function GetFirstCapability($capsList) 
		{
			$firstCap = '';
			
			foreach ($capsList as $cap)
			{
				if (current_user_can($cap))
				{
					$firstCap = $cap;
					break;
				}
			}		
			
			return $firstCap;			
		}
		
		function WPCardzNet_ap() 
		{
			if (function_exists('add_menu_page'))
			{
				$wpcardznet_caps = array(
					WPCARDZNET_CAPABILITY_DEVUSER,
					WPCARDZNET_CAPABILITY_SETUPUSER,
					WPCARDZNET_CAPABILITY_ADMINUSER,
					WPCARDZNET_CAPABILITY_MANAGER,
					);
				
				$adminCap = $this->GetFirstCapability($wpcardznet_caps);

				if ($adminCap == '') return;
				
				$icon_url = '';
				$pagePrefix = $this->adminPagePrefix;
				$pluginName = 'CardzNet';
				
				if ($adminCap != '')
				{
					add_menu_page($pluginName, $pluginName, $adminCap, WPCARDZNET_MENUPAGE_ADMINMENU, array(&$this, 'printAdminPage'), $icon_url);
						
					add_submenu_page( WPCARDZNET_MENUPAGE_ADMINMENU, __($this->pluginDesc.' - Overview', 'wpcardznet'),   __('Overview', 'wpcardznet'),  $adminCap,                        WPCARDZNET_MENUPAGE_ADMINMENU,  array(&$this, 'printAdminPage'));
					add_submenu_page( WPCARDZNET_MENUPAGE_ADMINMENU, __($this->pluginDesc.' - Groups', 'wpcardznet'),     __('Groups', 'wpcardznet'),    $adminCap,                        WPCARDZNET_MENUPAGE_GROUPS,     array(&$this, 'printAdminPage'));
					add_submenu_page( WPCARDZNET_MENUPAGE_ADMINMENU, __($this->pluginDesc.' - Games', 'wpcardznet'),      __('Games', 'wpcardznet'),     $adminCap,                        WPCARDZNET_MENUPAGE_GAMES,      array(&$this, 'printAdminPage'));
					add_submenu_page( WPCARDZNET_MENUPAGE_ADMINMENU, __($this->pluginDesc.' - Settings', 'wpcardznet'),   __('Settings', 'wpcardznet'),  WPCARDZNET_CAPABILITY_SETUPUSER, WPCARDZNET_MENUPAGE_SETTINGS,   array(&$this, 'printAdminPage'));
				}
				
				// Show test menu if enabled
				if ($this->myDBaseObj->InTestMode() && current_user_can(WPCARDZNET_CAPABILITY_DEVUSER))
				{
					add_submenu_page( 'options-general.php', $pluginName.' Test', $pluginName.' Test', WPCARDZNET_CAPABILITY_DEVUSER, WPCARDZNET_MENUPAGE_DIAGNOSTICS, array(&$this, 'printAdminPage'));
				}
				
				if (current_user_can(WPCARDZNETLIB_CAPABILITY_SYSADMIN) || current_user_can(WPCARDZNETLIB_CAPABILITY_DEVUSER))
				{
					if ($this->myDBaseObj->IsSessionElemSet('wpcardznetlib_debug_test') && file_exists(WPCARDZNET_TEST_PATH.'wpcardznetlib_devtestcaller.php') ) 
					{
						include WPCARDZNET_TEST_PATH.'wpcardznetlib_devtestcaller.php';   
						$devTestFiles = WPCardzNetLibDevCallerClass::DevTestFilesList(WPCARDZNET_TEST_PATH);
						if (count($devTestFiles) > 0)
							add_submenu_page( WPCARDZNET_MENUPAGE_ADMINMENU, __('Dev TESTING', 'wpcardznet'), __('Dev TESTING', 'wpcardznet'), WPCARDZNETLIB_CAPABILITY_DEVUSER, WPCARDZNET_MENUPAGE_DEVTEST, array(&$this, 'printAdminPage'));
					}

				}
			}
		}
				
		function wpcardznet_LoginPage()
		{
		
			// Set up some defaults.
			// Set 'echo' to 'false' because we want it to always return instead of print for shortcodes. 
			$args = array(
				'label_username' => 'Username',
				'label_password' => 'Password',
				'echo' => false,
			);

			$lostmsg = __('Lost Your Password?', 'wpcardznet');
			$clickmsg = __('Click Here!', 'wpcardznet');
			$lostPasswordURL = get_option('siteurl').'/wp_login.php?action=lostpassword';
			
			$content = '
<div class=wpcardznet_msgpage>	
<div class=wpcardznet-login-container>
<h1>CardzNet Login</h1>	
<div class=wpcardznet-login-frame>';
			
			$content .= wp_login_form($args);
			$content .= "<div id=login-lost name=login-lost>$lostmsg <a href=\"$lostPasswordURL\">$clickmsg</a>";
			$content .= "</div></div></div></div>";
			
			WPCardzNetLibEscapingClass::Safe_EchoHTML($content);
		}

		function AddAJAXCode() 
		{ 
			$enableAJAX = $this->myDBaseObj->isDbgOptionSet('Dev_DisableAJAX') ? 'false' : 'true';

			$jsAjax = '
<script type="text/javascript" >
var enableAJAX = '.$enableAJAX.';

function wpcardznet_CallAJAX(data, callbackfn, errorfn)
{
    ajaxurl = "'.admin_url('admin-ajax.php').'"; // get ajaxurl
	
	data["action"] = "wpcardznet_ajax_request";

    jQuery.ajax(
    {
        url: ajaxurl, // this will point to admin-ajax.php
        type: "POST",
        data: data,
        success: function (response) 
        {
            callbackfn(response);                
        },
        error: function(jqXHR, textStatus, errorThrown) 
        {
        	errorfn(textStatus);
            return data;
        },   
    });
}
</script> 
			';
			
			return $jsAjax;
		}

		function OutputWindowOnLoadHandler($startTicker = true)
		{
			WPCardzNetLibEscapingClass::Safe_EchoScript("<script>\n");
			
			$tickerEnabled = $this->myDBaseObj->IsTickerEnabled();	
			if ($tickerEnabled)
			{
				WPCardzNetLibEscapingClass::Safe_EchoScript("var enableTicker = true; \n");
			}
			else
			{
				WPCardzNetLibEscapingClass::Safe_EchoScript("var enableTicker = false; \n");
			}
					
			if ($startTicker)
			{
				$onLoadHandler = 'wpcardznet_OnLoadTabletop';
				
				$tickerTimeout = intval($this->myDBaseObj->getOption('RefreshTime'));
				$activeTimeout = intval($this->myDBaseObj->getOption('TimeoutTime'));
				
				WPCardzNetLibEscapingClass::Safe_EchoScript( "tickerTimeout = $tickerTimeout; \n");
				WPCardzNetLibEscapingClass::Safe_EchoScript( "activeTimeout = $activeTimeout; \n");

			}
			else
			{
				$onLoadHandler = 'wpcardznet_OnLoadResponse';				
			}

			WPCardzNetLibEscapingClass::Safe_EchoScript("WPCardzNetLib_addWindowsLoadHandler($onLoadHandler); \n");
			WPCardzNetLibEscapingClass::Safe_EchoScript("</script> \n");
		}
		
		function wpcardznet_frontend($atts)
		{
			ob_start();
			$myDBaseObj = $this->myDBaseObj;
			
			// Output JS code for windows load handler before any <form> element
			$this->OutputWindowOnLoadHandler();
						
			if ( !is_user_logged_in() )
			{
				$this->wpcardznet_LoginPage();
				$html = ob_get_contents();
				ob_end_clean();
				
				return $html;
			}
			
			// Get game in progress here ....
			$gameDef = $myDBaseObj->GetGameDef($atts);
			
			if ($gameDef == null)
			{
				// Error initialising ... cannot play!
				$html  = "<form>\n";
				$html  = "<div class='wpcardznet_msgpage'>\n";
				$html .= $this->myDBaseObj->BannerMsg(__('Not currently in a game!', 'wpcardznet'), WPCARDZNET_DOMAIN_MSG_ERROR);
				$html .= "</div>\n";
				$html .= "</form>\n";
				
				return $html;
			}

			$gameClass = $gameDef->className;
			include WPCARDZNET_GAMES_PATH.$gameDef->srcfile;
	
	  		// Output the card table
			$tabletopObj = new $gameClass($myDBaseObj, $atts);

			$tabletopObj->CSS_JS_and_Includes($gameDef);

			$tabletopObj->OutputCardTable();
			
						
			$html = ob_get_contents();
			ob_end_clean();
			
			if ($myDBaseObj->isDbgOptionSet('Dev_LogHTML'))
			{
				$myDBaseObj->AddToStampedCommsLog($html);
			}
			
			return $html;
		}

		function wpcardznet_wp_shortcode($atts)
		{
			//WPCardzNetLibEscapingClass::Safe_EchoHTML("<!-- ************************ Shortcode Entry ************************ -->\n");			
			$this->wpcardznet_ajax_getpostvars();

			$html  = $this->AddAJAXCode();
			$html .= $this->wpcardznet_frontend($atts);
			//WPCardzNetLibEscapingClass::Safe_EchoHTML("<!-- ************************ Shortcode Exit ************************ -->\n");			
			return $html;
		}

		function wpcardznet_ajax_getpostvars()
		{
			// Change keys of Post vars with AJAX-******* keys
			foreach (WPCardzNetLibUtilsClass::GetArrayKeys('post') as $postId)
			{
				$postIdParts = explode('-', $postId);
				$noOfParts = count($postIdParts);
				if (($noOfParts < 2) || ($postIdParts[0] != 'AJAX'))
					continue;

				$postId1 = $postIdParts[1];				
				if ($noOfParts == 2)
				{
					$postVal = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', $postId);
					WPCardzNetLibUtilsClass::SetElement('post', $postId1, $postVal);
				}					
				else if ($noOfParts == 3)
				{
					// Post entries with keys of the form AJAX-{key1}-{key2}
					// Convert to an array in Post
					$postVal = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', $postId);
					$postId2 = $postIdParts[2];				
					WPCardzNetLibUtilsClass::SetElement('post', array($postId1, $postId2), $postVal);
				}					
				else
				{
					continue;
				}
				WPCardzNetLibUtilsClass::UnsetElement('post', $postId);
			}
		}

		function OnPageLoad($content)
		{
		    if ( !in_the_loop() || !is_main_query() ) 
		    	 return $content;
			
			if (!WPCardzNetLibUtilsClass::IsElementSet('get', WPCARDZNET_CALLBACK_ID))
				return $content;
			
			if (WPCardzNetLibUtilsClass::GetHTTPTextElem('get', 'action') != 'accept')
				return $content;
				
			$auth = WPCardzNetLibUtilsClass::GetHTTPTextElem('get', 'auth');
			if ($auth === '') die;
			
			$content = $this->OutputWindowOnLoadHandler(false);
						
			$content .= "<div class='wpcardznet_msgpage'>\n";
			$content .= "<h1>CardzNet - ".__('Invitation Response', 'wpcardznet')."</h1>\n";
			
			// Add the status of the message
			$content .= $this->AcceptInvitation($auth);
			
			$content .= "</div>\n";
			
			return $content;
		}

		function wpcardznet_ajax_call()
		{
			$request = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'request', '');			
			
			$this->wpcardznet_ajax_getpostvars();
			if (current_user_can(WPCARDZNETLIB_CAPABILITY_SYSADMIN) && WPCardzNetLibUtilsClass::IsElementSet('post', 'isSeqMode'))
				$this->myDBaseObj->isSeqMode = (WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'isSeqMode') == 'true');
				
			switch ($request)
			{
				case 'playerUI':					
					$playerName = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'thisPlayerName', '(Unknown)');
					$atts = array();
					if (WPCardzNetLibUtilsClass::IsElementSet('post', 'attslogin'))
						$atts['login'] = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'attslogin');
					$atts['stripFormElems'] = true;					
					$response['html'] = $this->wpcardznet_frontend($atts, true);
				    break;
				    
				case 'ticker':
					$playerName = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'thisPlayerName', '(Unknown)');
					$gameTicker = WPCardzNetLibUtilsClass::GetHTTPInteger('post', 'gameTicker');
					$gameId = WPCardzNetLibUtilsClass::GetHTTPInteger('post', 'gameId');
					$replyTicker = $this->myDBaseObj->GetTicker($gameId);
					$response['ticker'] = $replyTicker;
					$this->myDBaseObj->AddToStampedCommsLog("$playerName - AJAX Ticker Request Rxd - GameId: $gameId - Req Ticker: $gameTicker - Response: $replyTicker ");
					break;
					
				default:
					$response = 'Unknown AJAX Request';
					break;
			}

		    echo (json_encode($response));
		    wp_die();
		}

		function AcceptInvitation($auth)
		{
			$myDBaseObj = $this->myDBaseObj;
			
			$rtnHTML = '';
			
			$inviteRecords = $myDBaseObj->GetInvitationByAuth($auth);
			if (count($inviteRecords) != 1)
			{
				$rtnHTML .= $this->myDBaseObj->BannerMsg(__("Invalid Request", 'wpcardznet'), WPCARDZNET_DOMAIN_MSG_ERROR);
				return $rtnHTML;
			}

			$inviteRecord = $inviteRecords[0];
				
			// Check if user already exists
			$existingUser = false;
			$user = get_user_by('email', $inviteRecord->inviteEMail);
			if ($user != null)
			{
				$userId = $user->ID;
				$existingUser = true;
				
				$myDBaseObj->SendEMailByTemplateID($inviteRecords, 'addedToGroupEMail', 'emails', $inviteRecord->inviteEMail);
				
				$rtnHTML .= $this->myDBaseObj->BannerMsg(__("Invitation Accepted - Check your EMail for details", 'wpcardznet'), WPCARDZNET_DOMAIN_MSG_OK);
			}
			else
			{
				// Find a Username
				$inviteRecord->username = $basename = $inviteRecord->inviteFirstName.$inviteRecord->inviteLastName;
				for ($i=2;;$i++)
				{
					if (!username_exists($inviteRecord->username)) break;
					$inviteRecord->username = $basename.$i;
				}
				
				$inviteRecord->password = wp_generate_password( 12, true );
				
				$userdata['user_login'] = $inviteRecord->username;
				$userdata['user_pass'] = $inviteRecord->password;
				$userdata['first_name'] = $inviteRecord->inviteFirstName;
				$userdata['last_name'] = $inviteRecord->inviteLastName;
				$userdata['user_email'] = $inviteRecord->inviteEMail;
				
				// Add the User to Wordpress
				$userId = wp_insert_user($userdata);
				
				// Email the user
				$myDBaseObj->SendEMailByTemplateID($inviteRecords, 'addedLoginEMail', 'emails', $inviteRecord->inviteEMail);
			
				$rtnHTML .= $this->myDBaseObj->BannerMsg(__("Invitation Accepted - Check your EMail for details", 'wpcardznet'), WPCARDZNET_DOMAIN_MSG_OK);
/*
				$message  = "Login created on {$inviteRecord->siteName} <br> \n";
				$message .= "User Name:{$inviteRecord->username} <br> \n";
				//$message .= "Password:{$inviteRecord->password} <br> \n";
				$message .= "Login URL:<a href=\"{$inviteRecord->loginURL}\">{$inviteRecord->loginURL}</a> <br> \n";
								
				$rtnHTML .= $message;			
*/
			}						
			
			// Add the user to the Group as an unverified member
			$myDBaseObj->AddMemberToGroup($inviteRecord->inviteGroupId, $userId);
			
			$myDBaseObj->DeleteInvitationByAuth($auth);

			// Add capability to user
			$user = new WP_User($userId);
			$user->add_cap(WPCARDZNET_CAPABILITY_PLAYER);		
			
			return $rtnHTML;	
		}
		
	}

}

new WPCardzNetPluginClass(__FILE__, new WPCardzNetDBaseClass(__FILE__));

?>