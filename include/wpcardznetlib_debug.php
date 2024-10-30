<?php
/* 
Description: Code for Managing Debug Options
 
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

include WPCARDZNETLIB_INCLUDE_PATH.'wpcardznetlib_admin.php';
include WPCARDZNETLIB_INCLUDE_PATH.'wpcardznetlib_table.php';

if (!class_exists('WPCardzNetLibDebugSettingsClass')) 
{
	class WPCardzNetLibDebugSettingsClass extends WPCardzNetLibAdminClass // Define class
	{
		function __construct($env) //constructor	
		{
			$this->pageTitle = 'Diagnostics';
			
			// Call base constructor
			parent::__construct($env);			
		}
		
		function ProcessActionButtons()
		{
		}
		
		function Output_MainPage($updateFailed)
		{
			$customTestPath = dirname(dirname(__FILE__)).'/test/wpcardznetlib_customtest.php';
			if (file_exists($customTestPath))
			{
				// wpcardznetlib_customtest.php must create and run test class object
				include $customTestPath;
				if (class_exists('WPCardzNetLibCustomTestClass'))
				{
					new WPCardzNetLibCustomTestClass($this->env);	
					return;						
				}
			}
			
			$myPluginObj = $this->myPluginObj;
			$myDBaseObj = $this->myDBaseObj;			
					
			$this->submitButtonID = $myDBaseObj->get_name()."_testsettings";
			
			// TEST Settings HTML Output - Start 			
			WPCardzNetLibEscapingClass::Safe_EchoHTML('<form method="post">'."\n");
			$this->WPNonceField();
			
			$this->Test_DebugSettings(); 
			$this->Test_AllowedHTML();

			WPCardzNetLibEscapingClass::Safe_EchoHTML('</form>'."\n");
			// TEST HTML Output - End
		}
		
		static function GetOptionsDefs($inherit = true)
		{
			$testOptionDefs = array(
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Show SQL',          WPCardzNetLibTableClass::TABLEPARAM_NAME => 'cbShowSQL',          WPCardzNetLibTableClass::TABLEPARAM_ID => 'Dev_ShowSQL', ),				
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Show DB Output',    WPCardzNetLibTableClass::TABLEPARAM_NAME => 'cbShowDBOutput',     WPCardzNetLibTableClass::TABLEPARAM_ID => 'Dev_ShowDBOutput', ),
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Debug To Log',      WPCardzNetLibTableClass::TABLEPARAM_NAME => 'cbDebugToLog',       WPCardzNetLibTableClass::TABLEPARAM_ID => 'Dev_DebugToLog', ),
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Show Memory Usage', WPCardzNetLibTableClass::TABLEPARAM_NAME => 'cbShowMemUsage',     WPCardzNetLibTableClass::TABLEPARAM_ID => 'Dev_ShowMemUsage', ),
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Show EMail Msgs',   WPCardzNetLibTableClass::TABLEPARAM_NAME => 'cbShowEMailMsgs',    WPCardzNetLibTableClass::TABLEPARAM_ID => 'Dev_ShowEMailMsgs', ),				
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Block EMail Send',  WPCardzNetLibTableClass::TABLEPARAM_NAME => 'cbBlockEMailSend',   WPCardzNetLibTableClass::TABLEPARAM_ID => 'Dev_BlockEMailSend', ),				
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Log EMail Msgs',    WPCardzNetLibTableClass::TABLEPARAM_NAME => 'cbLogEMailMsgs',     WPCardzNetLibTableClass::TABLEPARAM_ID => 'Dev_LogEMailMsgs', ),				
			);
		
			return $testOptionDefs;
		}
		
		function GetOptionsDescription($optionName)
		{
			switch ($optionName)
			{
				case 'Show SQL':		return 'Show SQL Query Strings';
				case 'Show DB Output':	return 'Show SQL Query Output';
				case 'Show EMail Msgs': return 'Output EMail Message Content to Screen';
				
				default:	
					return "No Description Available for $optionName";					
			}
		}
		
		function Test_DebugSettings() 
		{
			$doneCheckboxes = false;
			
			$myDBaseObj = $this->myDBaseObj;
			
			if (WPCardzNetLibUtilsClass::IsElementSet('post', 'testbutton_SaveDebugSettings')) 
			{
				$this->CheckAdminReferer();
					
				$optDefs = $this->GetOptionsDefs();
				foreach ($optDefs as $optDef)
				{
					$label = $optDef[WPCardzNetLibTableClass::TABLEPARAM_LABEL];
					if ($label === '') continue;
					
					$settingId = $optDef[WPCardzNetLibTableClass::TABLEPARAM_ID];
					$ctrlId = isset($optDef[WPCardzNetLibTableClass::TABLEPARAM_NAME]) ? $optDef[WPCardzNetLibTableClass::TABLEPARAM_NAME] : 'ctrl'.$settingId;
					$settingValue = WPCardzNetLibUtilsClass::GetHTTPTextElem('post',$ctrlId);
					$myDBaseObj->dbgOptions[$settingId] = $settingValue;
				}
					
				$myDBaseObj->saveOptions();
				
				WPCardzNetLibEscapingClass::Safe_EchoHTML('<div id="message" class="updated"><p>Debug options updated</p></div>');
			}
			
			if (WPCardzNetLibUtilsClass::IsElementSet('post', 'testbutton_DescribeDebugSettings')) 
			{
				$optDefs = $this->GetOptionsDefs();
				WPCardzNetLibEscapingClass::Safe_EchoHTML("<table>\n");
				foreach ($optDefs as $optDef)
				{
					$label = $optDef[WPCardzNetLibTableClass::TABLEPARAM_LABEL];
					$ctrlDesc = $this->GetOptionsDescription($label);
					WPCardzNetLibEscapingClass::Safe_EchoHTML("<tr><td><strong>$label</strong></td><td>$ctrlDesc</td></tr>\n");
				}
				WPCardzNetLibEscapingClass::Safe_EchoHTML("</table>\n");
			}
		
		$tableClass = 'wpcardznet'."-settings-table";
		
		WPCardzNetLibEscapingClass::Safe_EchoHTML('
		<h3>Debug Settings</h3>
		<table class="'.$tableClass.'">
		');
			
		if ($myDBaseObj->ShowDebugModes())
			WPCardzNetLibEscapingClass::Safe_EchoHTML('<br>');
		
		$optDefs = $this->GetOptionsDefs();
		$count = 0;
		$checkboxesPerLine = 4;

		foreach ($optDefs as $optDef)
		{
			$label = $optDef[WPCardzNetLibTableClass::TABLEPARAM_LABEL];
			
			if ($count == 0) WPCardzNetLibEscapingClass::Safe_EchoHTML('<tr valign="top">'."\n");
			if ($label !== '')
			{
				$settingId = $optDef[WPCardzNetLibTableClass::TABLEPARAM_ID];
				$ctrlId = isset($optDef[WPCardzNetLibTableClass::TABLEPARAM_NAME]) ? $optDef[WPCardzNetLibTableClass::TABLEPARAM_NAME] : 'ctrl'.$settingId;
				$optValue = WPCardzNetLibUtilsClass::GetArrayElement($myDBaseObj->dbgOptions, $settingId);
				if (!isset($optDef[WPCardzNetLibTableClass::TABLEPARAM_TYPE]))
				{
					$optDef[WPCardzNetLibTableClass::TABLEPARAM_TYPE] = WPCardzNetLibTableClass::TABLEENTRY_CHECKBOX;
				}
				
				$html_dbg = '';
				switch ($optDef[WPCardzNetLibTableClass::TABLEPARAM_TYPE])
				{
					case 'TBD':
						$optText = ($optValue == 1) ? __('Enabled') : __('Disabled');
						$optEntry = $label. '&nbsp;('.$optText.')';
						break;
					
					case WPCardzNetLibTableClass::TABLEENTRY_TEXT:
						$label .= '&nbsp;';
						if ($count != 0) $html_dbg .= '</tr>';
						if (!$doneCheckboxes) $html_dbg .= '<tr><td>&nbsp;</td></tr>';
						$html_dbg .= '<tr valign="top">'."\n";
						$html_dbg .= '<td align="left" valign="middle" width="25%">'.($label).'</td>'."\n";
						$html_dbg .= '<td align="left" colspan='.($checkboxesPerLine).'>';
						$html_dbg .= '<input name="'.($ctrlId).'" type="input" autocomplete="off" maxlength="127" size="60" value="'.($optValue).'" />'."\n";
						$html_dbg .= '</td>'."\n";
						WPCardzNetLibEscapingClass::Safe_EchoHTML($html_dbg);
						$count = $checkboxesPerLine;
						break;
	
					case WPCardzNetLibTableClass::TABLEENTRY_CHECKBOX:
						$optIsChecked = ($optValue == 1) ? 'checked="yes" ' : '';
						$html_dbg  = '<td align="left" width="25%">';
						$html_dbg .= '<input name="'.$ctrlId.'" type="checkbox" value="1" '.($optIsChecked).' />&nbsp;'.($label);
						$html_dbg .= '</td>'."\n";
						WPCardzNetLibEscapingClass::Safe_EchoHTML($html_dbg);
						break;
				}
			}
			else
				WPCardzNetLibEscapingClass::Safe_EchoHTML('<td align="left">&nbsp;</td>'."\n");
			$count++;
			if ($count >= $checkboxesPerLine) 
			{
				WPCardzNetLibEscapingClass::Safe_EchoHTML('</tr>'."\n");
				$count = 0;
			}
		}

?>			
			<tr valign="top">
				<td>
				</td>
				<td>&nbsp;</td>
				<td>
				</td>
			</tr>
		</table>
		
		<input class="button-primary" type="submit" name="testbutton_SaveDebugSettings" value="Save Debug Settings"/>
		<input class="button-secondary" type="submit" name="testbutton_DescribeDebugSettings" value="Describe Debug Settings"/>
	<br>
<?php
		}
		
		function Test_AllowedHTML()
		{
			$doneCheckboxes = false;

			$myDBaseObj = $this->myDBaseObj;

			if (WPCardzNetLibUtilsClass::IsElementSet('post', 'testbutton_AllowedHTML'))
			{
				$this->CheckAdminReferer();

				$wpHTMLTags = wp_kses_allowed_html('post');
				$ourHTMLTags = WPCardzNetLibUtilsClass::GetAllowedHTMLTags();

				$allowed_protocols = WPCardzNetLibUtilsClass::GetAllowedProtocols();
				WPCardzNetLibUtilsClass::print_r($allowed_protocols, 'allowed_protocols');

				WPCardzNetLibEscapingClass::Safe_EchoHTML('
				<style>
				strong
				{
					color: red;
					font-weight: bold;
	}
				</style>
				');

				WPCardzNetLibEscapingClass::Safe_EchoHTML("Array<br>\n");
				WPCardzNetLibEscapingClass::Safe_EchoHTML("{<br>\n");

				foreach ($ourHTMLTags as $elem => $tags)
				{
					if (!isset($wpHTMLTags[$elem])) WPCardzNetLibEscapingClass::Safe_EchoHTML("<strong>");
					WPCardzNetLibEscapingClass::Safe_EchoHTML("[$elem] = Array<br>\n");
					if (!isset($wpHTMLTags[$elem])) WPCardzNetLibEscapingClass::Safe_EchoHTML("</strong>");
					WPCardzNetLibEscapingClass::Safe_EchoHTML("{<br>\n");

					foreach ($tags as $tag => $val)
					{
						if (!isset($wpHTMLTags[$elem][$tag])) WPCardzNetLibEscapingClass::Safe_EchoHTML("<strong>");
						WPCardzNetLibEscapingClass::Safe_EchoHTML("[$tag] = 1<br>\n");
						if (!isset($wpHTMLTags[$elem][$tag])) WPCardzNetLibEscapingClass::Safe_EchoHTML("</strong>");
					}
					WPCardzNetLibEscapingClass::Safe_EchoHTML("}<br>\n");
				}
				WPCardzNetLibEscapingClass::Safe_EchoHTML("}<br>\n");
				//WPCardzNetLibUtilsClass::print_r($htmlTags, 'allowedHTMLTags');
			}

		WPCardzNetLibEscapingClass::Safe_EchoHTML('
		<h3>Allowed HTML</h3>
		');

?>
		<input class="button-primary" type="submit" name="testbutton_AllowedHTML" value="Show Allowed HTML"/>
	<br>
<?php
		}

}
}


