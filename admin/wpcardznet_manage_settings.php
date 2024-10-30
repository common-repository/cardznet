<?php
/* 
Description: Code for Managing CardzNet Settings
 
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

require_once WPCARDZNET_INCLUDE_PATH.'wpcardznetlib_adminlist.php';
require_once WPCARDZNET_INCLUDE_PATH.'wpcardznetlib_admin.php';      

if (!class_exists('WPCardzNetSettingsAdminListClass')) 
{
	define('WPCARDZNET_URL_TEXTLEN', 110);
	define('WPCARDZNET_PARSERKEY_TEXTLEN', 80);
	
	class WPCardzNetSettingsAdminListClass extends WPCardzNetLibAdminListClass // Define class
	{		
		function __construct($env, $editMode = false) //constructor
		{	
			// Call base constructor
			parent::__construct($env, $editMode);
			
			$this->defaultTabId = 'wpcardznet-sounds-settings-tab';
			$this->HeadersPosn = WPCardzNetLibTableClass::HEADERPOSN_TOP;
		}
		
		function GetTableID($result)
		{
			return "wpcardznet-settings";
		}
		
		function GetRecordID($result)
		{
			return '';
		}
	
		function GetTableRowCount()
		{
			return 1;
		}
	
		function GetMainRowsDefinition()
		{
			$rowDefs = array();
		
			$this->isTabbedOutput = true;
			
			$rowDefs = array(			
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'General', WPCardzNetLibTableClass::TABLEPARAM_ID => 'wpcardznet-gen-settings-tab', ),
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Sounds', WPCardzNetLibTableClass::TABLEPARAM_ID => 'wpcardznet-sounds-settings-tab', ),
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Comms', WPCardzNetLibTableClass::TABLEPARAM_ID => 'wpcardznet-comms-settings-tab', ),
				
			);
		
			return $rowDefs;
		}		
		
		function GetDetailsRowsDefinition()
		{
			$pluginID = WPCARDZNET_FOLDER;
/*			
			$pageStyles = WPCardzNetLibMigratePHPClass::Safe_trim('
#wrapper { width: 100% !important; }
.page #wrapper { margin: 0px !important; }
.page #wrapper { padding: 0px !important; }
#main { width: 100% !important; }
#main { padding: 0px !important; }
#content { width: auto !important; }
#html  { margin: 0px !important; }
			');
*/
			$pageStyles = '';
								
			$mp3_Ready = 'chimes.mp3';
			$mp3_RevealCards = 'ding.mp3';
			$mp3_SelectCard = '';
			$mp3_PlayCard = 'foreground.mp3';
			
			$RefreshTime = 3000;
			$TimeoutTime = 600000;

			$newGameHasActivePlayerOptions = array(
				WPCARDZNET_PLAYERACTIVE_FAIL.'|'.__('Do not Add the Game', 'wpcardznet'),
				WPCARDZNET_PLAYERACTIVE_ENDGAME.'|'.__('End the Existing Game', 'wpcardznet'),
			);
			
			$nextPlayerMimicRotationOptions = array(
				WPCARDZNET_MIMICMODE_PLAYER.'|'.__('Player At Bottom', 'wpcardznet'),
				WPCARDZNET_MIMICMODE_DEALER.'|'.__('Dealer At Bottom', 'wpcardznet'),
				WPCARDZNET_MIMICMODE_ADMIN.'|'.__('Admin At Bottom', 'wpcardznet'),
			);
		
			$nextPlayerMimicDisplayOptions = array(
				WPCARDZNET_MIMICVISIBILITY_ALWAYS.'|'.__('Always Visible', 'wpcardznet'),
				WPCARDZNET_MIMICVISIBILITY_WITHHAND.'|'.__('When Hand Visible', 'wpcardznet'),
				WPCARDZNET_MIMICVISIBILITY_NEVER.'|'.__('Always Hidden', 'wpcardznet'),
			);
		
			$uploadedImagesPath = WPCARDZNETLIB_UPLOADS_PATH . '/images';
			$uploadedEMailsPath = WPCARDZNETLIB_UPLOADS_PATH . '/emails';
			$uploadedSoundsPath = WPCARDZNETLIB_UPLOADS_PATH . '/mp3';
			
			$rowDefs = array(
				array(self::TABLEPARAM_LABEL => 'Ready',                         self::TABLEPARAM_TAB => 'wpcardznet-sounds-settings-tab',      self::TABLEPARAM_ID => 'mp3_Ready',               self::TABLEPARAM_TYPE => self::TABLEENTRY_SELECT,   self::TABLEPARAM_ADDEMPTY => true, self::TABLEPARAM_DIR => $uploadedSoundsPath, self::TABLEPARAM_EXTN => 'mp3,wav', self::TABLEPARAM_DEFAULT => $mp3_Ready, ),
				array(self::TABLEPARAM_LABEL => 'Reveal Cards',                  self::TABLEPARAM_TAB => 'wpcardznet-sounds-settings-tab',      self::TABLEPARAM_ID => 'mp3_RevealCards',         self::TABLEPARAM_TYPE => self::TABLEENTRY_SELECT,   self::TABLEPARAM_ADDEMPTY => true, self::TABLEPARAM_DIR => $uploadedSoundsPath, self::TABLEPARAM_EXTN => 'mp3,wav', self::TABLEPARAM_DEFAULT => $mp3_RevealCards, ),
				array(self::TABLEPARAM_LABEL => 'Select Card',                   self::TABLEPARAM_TAB => 'wpcardznet-sounds-settings-tab',      self::TABLEPARAM_ID => 'mp3_SelectCard',          self::TABLEPARAM_TYPE => self::TABLEENTRY_SELECT,   self::TABLEPARAM_ADDEMPTY => true, self::TABLEPARAM_DIR => $uploadedSoundsPath, self::TABLEPARAM_EXTN => 'mp3,wav', self::TABLEPARAM_DEFAULT => $mp3_SelectCard, ),
				array(self::TABLEPARAM_LABEL => 'Play Card',                     self::TABLEPARAM_TAB => 'wpcardznet-sounds-settings-tab',      self::TABLEPARAM_ID => 'mp3_PlayCard',            self::TABLEPARAM_TYPE => self::TABLEENTRY_SELECT,   self::TABLEPARAM_ADDEMPTY => true, self::TABLEPARAM_DIR => $uploadedSoundsPath, self::TABLEPARAM_EXTN => 'mp3,wav', self::TABLEPARAM_DEFAULT => $mp3_PlayCard, ),
				array(self::TABLEPARAM_LABEL => 'Success',                       self::TABLEPARAM_TAB => 'wpcardznet-sounds-settings-tab',      self::TABLEPARAM_ID => 'mp3_Success',             self::TABLEPARAM_TYPE => self::TABLEENTRY_SELECT,   self::TABLEPARAM_ADDEMPTY => true, self::TABLEPARAM_DIR => $uploadedSoundsPath, self::TABLEPARAM_EXTN => 'mp3,wav', self::TABLEPARAM_DEFAULT => '', ),

				array(self::TABLEPARAM_LABEL => 'Refresh Time (ms)',             self::TABLEPARAM_TAB => 'wpcardznet-comms-settings-tab',       self::TABLEPARAM_ID => 'RefreshTime',             self::TABLEPARAM_TYPE => self::TABLEENTRY_INTEGER,  self::TABLEPARAM_LEN => 5, self::TABLEPARAM_DEFAULT => $RefreshTime, ),
				array(self::TABLEPARAM_LABEL => 'Timeout Time (ms)',             self::TABLEPARAM_TAB => 'wpcardznet-comms-settings-tab',       self::TABLEPARAM_ID => 'TimeoutTime',             self::TABLEPARAM_TYPE => self::TABLEENTRY_INTEGER,  self::TABLEPARAM_LEN => 5, self::TABLEPARAM_DEFAULT => $TimeoutTime, ),

				array(self::TABLEPARAM_LABEL => 'Logs Folder Path',              self::TABLEPARAM_TAB => 'wpcardznet-gen-settings-tab',         self::TABLEPARAM_ID => 'LogsFolderPath',          self::TABLEPARAM_TYPE => self::TABLEENTRY_TEXT,     self::TABLEPARAM_LEN => WPCARDZNET_PARSERKEY_TEXTLEN, self::TABLEPARAM_SIZE => WPCARDZNET_PARSERKEY_TEXTLEN, ),
				
				array(self::TABLEPARAM_LABEL => 'Manager is First Player',       self::TABLEPARAM_TAB => 'wpcardznet-gen-settings-tab',         self::TABLEPARAM_ID => 'FirstPlayerIsManager',    self::TABLEPARAM_TYPE => self::TABLEENTRY_CHECKBOX, self::TABLEPARAM_TEXT => '', ),
				array(self::TABLEPARAM_LABEL => 'Player already active action',  self::TABLEPARAM_TAB => 'wpcardznet-gen-settings-tab',         self::TABLEPARAM_ID => 'ActiveUserAction',        self::TABLEPARAM_TYPE => self::TABLEENTRY_SELECT,   self::TABLEPARAM_ITEMS => $newGameHasActivePlayerOptions, ),
				
				array(self::TABLEPARAM_LABEL => 'Next Player Mimic Rotation',    self::TABLEPARAM_TAB => 'wpcardznet-gen-settings-tab',         self::TABLEPARAM_ID => 'NextPlayerMimicRotation', self::TABLEPARAM_TYPE => self::TABLEENTRY_SELECT,   self::TABLEPARAM_ITEMS => $nextPlayerMimicRotationOptions, ),
				array(self::TABLEPARAM_LABEL => 'Next Player Mimic Visibility',  self::TABLEPARAM_TAB => 'wpcardznet-gen-settings-tab',         self::TABLEPARAM_ID => 'NextPlayerMimicDisplay',  self::TABLEPARAM_TYPE => self::TABLEENTRY_SELECT,   self::TABLEPARAM_ITEMS => $nextPlayerMimicDisplayOptions, ),
				array(self::TABLEPARAM_LABEL => 'Admin Items per Page',          self::TABLEPARAM_TAB => 'wpcardznet-gen-settings-tab',         self::TABLEPARAM_ID => 'PageLength',              self::TABLEPARAM_TYPE => self::TABLEENTRY_INTEGER,  self::TABLEPARAM_LEN => 3, self::TABLEPARAM_DEFAULT => 10),
				
				array(self::TABLEPARAM_LABEL => 'EMail Logo Image File',         self::TABLEPARAM_TAB => 'wpcardznet-comms-settings-tab',       self::TABLEPARAM_ID => 'PayPalLogoImageFile',     self::TABLEPARAM_TYPE => self::TABLEENTRY_SELECT,   self::TABLEPARAM_ADDEMPTY => true, self::TABLEPARAM_DIR => $uploadedImagesPath, self::TABLEPARAM_EXTN => 'gif,jpeg,jpg,png', self::TABLEPARAM_DEFAULT => 'wpcardznet_logo.png', ),

				array(self::TABLEPARAM_LABEL => 'Invitation Time Limit (Hours)', self::TABLEPARAM_TAB => 'wpcardznet-comms-settings-tab',       self::TABLEPARAM_ID => 'inviteTimeLimit',         self::TABLEPARAM_TYPE => self::TABLEENTRY_INTEGER,  self::TABLEPARAM_LEN => 3, self::TABLEPARAM_DEFAULT => 10),

				array(self::TABLEPARAM_LABEL => 'Invitation EMail',              self::TABLEPARAM_TAB => 'wpcardznet-comms-settings-tab',       self::TABLEPARAM_ID => 'inviteEMail',             self::TABLEPARAM_TYPE => self::TABLEENTRY_SELECT,   self::TABLEPARAM_DIR => $uploadedEMailsPath, self::TABLEPARAM_EXTN => 'php', self::TABLEPARAM_DEFAULT => 'wpcardznet_invitationEMail.php', WPCardzNetLibTableClass::TABLEPARAM_BUTTON => 'Edit', ),
				array(self::TABLEPARAM_LABEL => 'Login Created EMail',           self::TABLEPARAM_TAB => 'wpcardznet-comms-settings-tab',       self::TABLEPARAM_ID => 'addedLoginEMail',         self::TABLEPARAM_TYPE => self::TABLEENTRY_SELECT,   self::TABLEPARAM_DIR => $uploadedEMailsPath, self::TABLEPARAM_EXTN => 'php', self::TABLEPARAM_DEFAULT => 'wpcardznet_addedLoginEMail.php', WPCardzNetLibTableClass::TABLEPARAM_BUTTON => 'Edit', ),
				array(self::TABLEPARAM_LABEL => 'Added to Group EMail',          self::TABLEPARAM_TAB => 'wpcardznet-comms-settings-tab',       self::TABLEPARAM_ID => 'addedToGroupEMail',       self::TABLEPARAM_TYPE => self::TABLEENTRY_SELECT,   self::TABLEPARAM_DIR => $uploadedEMailsPath, self::TABLEPARAM_EXTN => 'php', self::TABLEPARAM_DEFAULT => 'wpcardznet_addedToGroupEMail.php', WPCardzNetLibTableClass::TABLEPARAM_BUTTON => 'Edit', ),
			);
			
			return $rowDefs;
		}
				
		function JS_Bottom($defaultTab)
		{
		 	$js_bottom = parent::JS_Bottom($defaultTab);
		 	$js_bottom .= "
WPCardzNetLib_addWindowsLoadHandler(wpcardznetlib_OnSettingsLoad);
				";
		 	return $js_bottom;
		}
		
	}
}
		
require_once WPCARDZNET_INCLUDE_PATH.'wpcardznetlib_settingsadmin.php';

if (!class_exists('WPCardzNetSettingsAdminClass')) 
{
	class WPCardzNetSettingsAdminClass extends WPCardzNetLibSettingsAdminClass // Define class
	{		
		function __construct($env)
		{
			// Call base constructor
			parent::__construct($env);
		}
/*		
		function GetAdminListClass()
		{
			return 'WPCardzNetSettingsAdminListClass';			
		}
*/
		function ProcessActionButtons()
		{
			$donePage = false;
			$donePage |= $this->EditTemplate('inviteEMail');
			$donePage |= $this->EditTemplate('addedLoginEMail');
			$donePage |= $this->EditTemplate('addedToGroupEMail');

			if ($donePage) return;
			
			parent::ProcessActionButtons();		
		
		}
	
	}
}
		

?>