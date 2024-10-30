<?php
/* 
Description: Code for Managing WPCardzNet Debug Settings
 
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

require_once WPCARDZNET_INCLUDE_PATH.'wpcardznetlib_debug.php';      
require_once WPCARDZNET_INCLUDE_PATH.'wpcardznetlib_adminlist.php';      

if (!class_exists('WPCardzNetDebugAdminClass')) 
{
	class WPCardzNetDebugAdminClass extends WPCardzNetLibDebugSettingsClass // Define class
	{		
		function __construct($env, $editMode = false) //constructor
		{	
			// Call base constructor
			parent::__construct($env, $editMode);
		}
		
		static function GetOptionsDefs($inherit = true)
		{
			$testOptionDefs = array(
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Disable AJAX',         WPCardzNetLibTableClass::TABLEPARAM_ID => 'Dev_DisableAJAX',     WPCardzNetLibTableClass::TABLEPARAM_AFTER => 'Dev_ShowDBOutput', ),
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Show Misc Debug',      WPCardzNetLibTableClass::TABLEPARAM_ID => 'Dev_ShowMiscDebug',   WPCardzNetLibTableClass::TABLEPARAM_AFTER => 'Dev_DisableAJAX', ),
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Log HTML',             WPCardzNetLibTableClass::TABLEPARAM_ID => 'Dev_LogHTML',         WPCardzNetLibTableClass::TABLEPARAM_AFTER => 'Dev_ShowMiscDebug', ),
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Show Call Stack',      WPCardzNetLibTableClass::TABLEPARAM_ID => 'Dev_ShowCallStack',   WPCardzNetLibTableClass::TABLEPARAM_AFTER => 'Dev_LogHTML', ),
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Show Test Controls',   WPCardzNetLibTableClass::TABLEPARAM_ID => 'Dev_ShowTestCtrls', ),
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Disable JS/CSS Cache', WPCardzNetLibTableClass::TABLEPARAM_ID => 'Dev_DisableJSCache', ),
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Disable Ticker',       WPCardzNetLibTableClass::TABLEPARAM_ID => 'Dev_DisableTicker', ),
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Show Rerun Game',      WPCardzNetLibTableClass::TABLEPARAM_ID => 'Dev_RerunGame', ),
			);
		
			$testOptionDefs = WPCardzNetLibAdminListClass::MergeSettings(parent::GetOptionsDefs(), $testOptionDefs);
			
			self::RemoveOptionDef('EMail', $testOptionDefs);

			return $testOptionDefs;
		}
		
		static function RemoveOptionDef($srchLabel, &$OptionDefs)
		{
			foreach ($OptionDefs as $index =>$OptionDef)
			{
				$label = $OptionDef[WPCardzNetLibTableClass::TABLEPARAM_LABEL];
				if (WPCardzNetLibMigratePHPClass::Safe_strpos($label, $srchLabel) !== false)
				{
					unset($OptionDefs[$index]);
				}
			}
		}
		
	}
}
		
?>