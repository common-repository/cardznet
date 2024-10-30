<?php
/* 
Description: Code for CardzNet Overview Page
 
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

if (!class_exists('WPCardzNetOverviewAdminClass')) 
{
	class WPCardzNetOverviewAdminClass extends WPCardzNetLibAdminClass
	{		
		function __construct($env)
		{
			$this->pageTitle = 'Overview';
			
			// Call base constructor
			parent::__construct($env);
		}
		
		function ProcessActionButtons()
		{
		}
		
		function Output_MainPage($updateFailed)
		{
			$myDBaseObj = $this->myDBaseObj;

			if (!$myDBaseObj->SettingsOK())
				return;
				
			// CardzNet Overview HTML Output - Start 
			$this->Output_Overview();
			//$this->Output_Help();
			$this->Output_TrolleyAndShortcodesHelp();
		}
		
		function Output_Overview()
		{
			$myDBaseObj = $this->myDBaseObj;

			// CardzNet Overview HTML Output - Start 
			$html = '
<div class="wrap">
	<div id="icon-wpcardznet" class="icon32"></div>
	<br></br>
	<form method="post" action="admin.php?page=wpcardznet_settings">
		<table class="widefat" cellspacing="0">
			<tbody>
				<tr>
					<td>No Of Users</td>
					<td>'.$myDBaseObj->GetUsersCount().'</td>
				</tr>
			</tbody>
		</table>
<br></br>
		';
			if(false)
			{
				$html .= '<input class="button-primary" type="submit" name="createsample" value="'.__('Create Sample', 'wpcardznet').'"/>'."\n";
			}

			$html .= '</form>';

			WPCardzNetLibEscapingClass::Safe_EchoHTML($html);
        	// CardzNet Overview HTML Output - End
		}
		
		function Output_TrolleyAndShortcodesHelp()
		{
			WPCardzNetLibEscapingClass::Safe_EchoHTML('<h2>'.__("Plugin Info", 'wpcardznet')."</h2>\n");		
			$this->myDBaseObj->Output_PluginHelp();
			
			if (!current_user_can(WPCARDZNET_CAPABILITY_SETUPUSER)) return;
			
			WPCardzNetLibEscapingClass::Safe_EchoHTML('<h2>'.__("Shortcodes", 'wpcardznet')."</h2>\n");			
			WPCardzNetLibEscapingClass::Safe_EchoHTML('<br>'.__('CardzNet generates output to your Wordpress pages for the following shortcodes:', 'wpcardznet')."<br><br>\n");
			$this->Output_ShortcodeHelp(WPCARDZNET_SHORTCODE);
		}
		
		function Output_ShortcodeHelp($shortcode)
		{
			// FUNCTIONALITY: Overview - Show Plugin Specific Help for Shortcode(s))

			$htmlSC = '
			<div class="wpcardznet-overview-info">
			<table class="widefat" cellspacing="0">
				<thead>
					<tr>
						<th>'.__('Shortcode', 'wpcardznet').'</th>
						<th>'.__('Description', 'wpcardznet').'</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>['.$shortcode.']</td>
						<td>'.__('Outputs card table', 'wpcardznet').'</td>
					</tr>
					<tr>
						<td>['.$shortcode.'-login]</td>
						<td>'.__('Outputs remote login form', 'wpcardznet').'</td>
					</tr>
				</tbody>
			</table>
			</div>
			';
			
			WPCardzNetLibEscapingClass::Safe_EchoHTML($htmlSC);
		}	

	}
}
?>