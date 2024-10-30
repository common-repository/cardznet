<?php
/* 
Description: Code for Admin Tools
 
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

if (!class_exists('WPCardzNetToolsAdminClass')) 
{
	define('WPCARDZNET_DBEXPORT_TARGET', 'wpcardznet_db_export.php');

	class WPCardzNetToolsAdminClass extends WPCardzNetLibAdminClass // Define class
	{
		function __construct($env) //constructor	
		{
			$this->pageTitle = 'Tools';
			$this->adminClassPrefix = $env['PluginObj']->adminClassPrefix;
			
			// Call base constructor
			parent::__construct($env);
		}
		
		function ProcessActionButtons()
		{
			// Hook to add custom action to tools page
			// Will set donePage if other tools are to be hidden
			do_action('wpcardznet_toolspage_output', $this);
		}
		
		function Output_MainPage($updateFailed)
		{			
?>
<div class="wrap">
	<div class="wpcardznet-admin-form">
	</div>
</div>
<div class="wrap">
	<div class="wpcardznet-admin-form">
<?php
			$this->Tools_Backup();
			$this->Tools_TestEMail();
?>
	</div>
</div>
<?php
		}

		function OutputExportFormatOptions()
		{
			$this->WPCardzNetWPOrgToolsAdminClass_OutputExportFormatOptions();
?>	
	<option value="tdt" selected="selected"><?php _e('Tab Delimited Text', 'wpcardznet'); ?> </option>
	<option value="ofx"><?php _e('OFX', 'wpcardznet'); ?>&nbsp;&nbsp;</option>
<?php
		}
		
		static function OutputExportOptions($myDBaseObj)
		{
			self::WPCardzNetPlusToolsAdminClass_OutputExportOptions($myDBaseObj);
			

?>	
<tr id="wpcardznet-export_filter-row" style="display: none;">
<th><?php _e('Filter', 'wpcardznet'); ?></th>
<td>
<select name="export_filter" id="export_filter" class="wpcardznetlib-tools-ui">
<?php
			WPCardzNetLibEscapingClass::Safe_EchoHTML('<option value="" selected="selected">'.__('None', 'wpcardznet')."</option>\n");
			
			$pluginID = WPCARDZNET_FOLDER;
			$dir = WP_CONTENT_DIR . '/uploads/'.$pluginID.'/exports/*.tab';
			$filesList = glob($dir);
		
			$optionIndex = 1;
			foreach ($filesList as $index => $filePath)
			{
				$fileName = basename($filePath);
				
				$startPosn = WPCardzNetLibMigratePHPClass::Safe_strpos($fileName, "_");
				if ($startPosn === false) continue;
				$startPosn = WPCardzNetLibMigratePHPClass::Safe_strpos($fileName, "_", $startPosn+1);
				if ($startPosn === false) continue;		
						
				$endPosn = WPCardzNetLibMigratePHPClass::Safe_strlen($fileName) - 4;
				$exportName = WPCardzNetLibMigratePHPClass::Safe_substr($fileName, $startPosn, $endPosn-$startPosn);
				$exportName = WPCardzNetLibMigratePHPClass::Safe_str_replace("_", " ", $exportName);
				
				$filterID = 'filterSelect'.$optionIndex; 
				WPCardzNetLibEscapingClass::Safe_EchoHTML('<option id='.$filterID.' id='.$filterID.' value="'.$fileName.'">'.$exportName."</option>\n");
				
				$optionIndex++;
			}	

?>	
</select>
</td>
</tr>
<?php
		}

		static function WPCardzNetPlusToolsAdminClass_OutputExportOptions($myDBaseObj)
		{

					
			$showsList = $myDBaseObj->GetAllShowsList();
			$perfsList = $myDBaseObj->GetAllPerformancesList();

			WPCardzNetLibEscapingClass::Safe_EchoScript('
<script type="text/javascript">
var perfselect_id = [];
var perfselect_text = [];
perfselect_id[0] = "0";
			');

        	WPCardzNetLibEscapingClass::Safe_EchoScript('perfselect_text[0] = "'.esc_html__('All', 'wpcardznet').'";'."\n");
			foreach ($perfsList as $perfEntry)
			{
	        	WPCardzNetLibEscapingClass::Safe_EchoScript('perfselect_id[perfselect_id.length] = "'.$perfEntry->showID.'.'.$perfEntry->perfID.'";'."\n");
	        	WPCardzNetLibEscapingClass::Safe_EchoScript('perfselect_text[perfselect_text.length] = "'.$perfEntry->perfDateTime.'";'."\n");
	}
	
			WPCardzNetLibEscapingClass::Safe_EchoScript('
	WPCardzNetLib_addWindowsLoadHandler(wpcardznet_updateExportOptions); 
</script>
				');
	
			WPCardzNetLibEscapingClass::Safe_EchoHTML('<
<tr id="wpcardznet-export_show-row">
<th>'.__('Show', 'wpcardznet').'</th>
<td>
<select name="export_showid" id="export_showid" class="wpcardznetlib-tools-ui" onchange=wpcardznet_onSelectShow(this)>
				');

			WPCardzNetLibEscapingClass::Safe_EchoHTML('<option value="0" selected="selected">'.__('All', 'wpcardznet')."</option>\n");
			foreach ($showsList as $showEntry)
			{
				WPCardzNetLibEscapingClass::Safe_EchoHTML('<option value="'.$showEntry->showID.'">'.$showEntry->showName."</option>\n");
			}	
?>	
</select>
</td>
</tr>

<tr id="wpcardznet-export_performance-row" style="display: none;">
<th><?php _e('Performance', 'wpcardznet'); ?></th>
<td>
<select name="export_perfid" id="export_perfid" class="wpcardznetlib-tools-ui">
<?php
			WPCardzNetLibEscapingClass::Safe_EchoHTML('<option value="0" selected="selected">'.__('All', 'wpcardznet')."</option>\n");
			foreach ($perfsList as $perfEntry)
			{
				// showID is included in the value because wpcardznet_onSelectShow() uses it
				WPCardzNetLibEscapingClass::Safe_EchoHTML('<option value="'.$perfEntry->showID.'.'.$perfEntry->perfID.'">'.$perfEntry->perfDateTime."</option>\n");
			}	
?>	
</select>
</td>
</tr>
<?php

		}

		function Tools_Backup()
		{
			$toolOutput = '';

			//$ourNOnce = WPCardzNetLibNonce::GetWPCardzNetLibNonce(WPCARDZNET_DBEXPORT_TARGET);
			$actionURL = WPCardzNetLibUtilsClass::GetCallbackURL(WPCARDZNET_DBEXPORT_TARGET);
?>			
<h3><?php _e('Backup', 'wpcardznet'); ?></h3>
<form action="<?php WPCardzNetLibEscapingClass::Safe_EchoAttr($actionURL); ?>" method="POST">
<?php $this->WPNonceField('wpcardznetlib_export.php'); ?>
<?php
			if ($this->myDBaseObj->IsSessionElemSet('wpcardznetlib_debug_test'))
			{
?>
<table class="form-table">
	<tr>
		<td width=150px ><?php _e('EMail Addresses', 'wpcardznet');?></td>
		<td><input type="checkbox" id="dest_DB_removeEMail" name="dest_DB_removeEMail" class="wpcardznetlib-tools-ui" /> <?php _e('Exclude User EMail Addresses', 'wpcardznet');?></td>
	</tr>
	<tr>
		<td width=150px ><?php _e('DB Table Prefix', 'wpcardznet');?></td>
		<td><input type="text" id="dest_DB_prefix" name="dest_DB_prefix" class="wpcardznetlib-tools-ui" maxlength="10" size="11" /> (<?php _e('Leave Blank to use default', 'wpcardznet');?>)</td>
	</tr>
	
</table>
<?php
			}
?>
<p class="submit">
<input type="submit" name="downloadexport" class="button wpcardznetlib-tools-ui" value="<?php esc_attr_e('Export WPCardzNet Database', 'wpcardznet'); ?>" />
<input type="hidden" name="download" value="true" />
</p>
</form>
<?php
			
		}
		
		function Tools_TestEMail()
		{
			$myDBaseObj = $this->myDBaseObj;
		
			WPCardzNetLibEscapingClass::Safe_EchoHTML('<h3>'.__('EMail Test', 'wpcardznet').'</h3>');
			
			$groupsList = $myDBaseObj->GetGroups();
			if (count($groupsList) == 0)
			{
				WPCardzNetLibEscapingClass::Safe_EchoHTML(__("Add a group first", 'wpcardznet'));
				return;
			}
			
			$this->destEMail = WPCardzNetLibUtilsClass::GetHTTPEMail('post', 'DestEMail');
			if ($this->destEMail == '')
			{
				$this->destEMail = get_bloginfo('admin_email');
			}

			if (WPCardzNetLibUtilsClass::IsElementSet('post', 'testbutton_EMailSale')) 
			{
				$this->CheckAdminReferer();
					
				// Run EMail Test	
				$optionGatewaySuffix = '';
					
				if (WPCardzNetLibUtilsClass::IsElementSet('post', 'EMailSale_DebugEnabled')) 
					$myDBaseObj->dbgOptions['Dev_ShowEMailMsgs'] = true;
				
				$groupId = WPCardzNetLibUtilsClass::GetHTTPInteger('post', 'TestGroupId');
				$groupData = $myDBaseObj->GetGroupById($groupId);

				$testDataElem = new stdClass();
				$testDataElem->groupUserId = $groupData[0]->groupUserId;
				$testDataElem->groupName = $groupData[0]->groupName;
				$testDataElem->inviteFirstName = 'Micky';
				$testDataElem->inviteLastName = 'Mouse';
				$testDataElem->inviteEMail = 'somebody@noemail.com';
				$testDataElem->username = 'newuser';
				$testDataElem->password = 'undefinedPASSWORD';
				
				$testData[] = $testDataElem;
				
				$templateId = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'EMailTemplateId');

				if ($myDBaseObj->SendEMailByTemplateID($testData, $templateId, 'emails', $this->destEMail))
					WPCardzNetLibEscapingClass::Safe_EchoHTML('<div id="message" class="updated"><p>'.__('EMail Sent to', 'wpcardznet').' '.$this->destEMail.'</p></div>');
			}
			
			WPCardzNetLibEscapingClass::Safe_EchoHTML('<form method="post">'."\n");
			WPCardzNetLibEscapingClass::Safe_EchoHTML($this->WPNonceField());

			WPCardzNetLibEscapingClass::Safe_EchoHTML('<table class="form-table">'."\n");

?>
		<tr valign="top">
			<td vertical-align="middle"><?php _e('Group Name', 'wpcardznet'); ?>:</td>
			<td>
				<select name="TestGroupId" id="TestGroupId" class="wpcardznetlib-tools-ui">
<?php
			foreach ($groupsList as $groupIndex => $groupData)
			{
				$groupName = $groupData->groupName;
				$groupId = $groupData->groupId;
				WPCardzNetLibEscapingClass::Safe_EchoHTML("<option value=$groupId >$groupName</option> \n");
			}
?>
				</select>
			</td>
		</tr>
<?php

			$templatesList = array(
				'addedToGroupEMail' => __('Added To Group', 'wpcardznet'),
				'addedLoginEMail' => __('Added Login', 'wpcardznet'),
				'inviteEMail' => __('Invitation', 'wpcardznet'),
			);

?>
		<tr valign="top">
			<td vertical-align="middle"><?php _e('EMail Template', 'wpcardznet'); ?>:</td>
			<td>
				<select name="EMailTemplateId" id="EMailTemplateId" class="wpcardznetlib-tools-ui">
<?php
			foreach ($templatesList as $templateId => $templateName)
			{
				WPCardzNetLibEscapingClass::Safe_EchoHTML("<option value=$templateId >$templateName</option> \n");
			}
?>
				</select>
			</td>
		</tr>
		<tr valign="top">
			<td vertical-align="middle"><?php _e('Divert EMail To', 'wpcardznet'); ?>:</td>
			<td>
				<input name="DivertEMailTo" id="DivertEMailTo" class="wpcardznetlib-tools-ui" type="text" maxlength="110" size="50" value="<?php WPCardzNetLibEscapingClass::Safe_EchoAttr($this->destEMail); ?>" />
			</td>
		</tr>
		<tr valign="top">
			<td vertical-align="middle"><?php _e('Add Diagnostics', 'wpcardznet'); ?>:</td>
			<td>
				<input name="EMailSale_DebugEnabled" class="wpcardznetlib-tools-ui" type="checkbox" value="1"  />&nbsp;<?php _e('Enable', 'wpcardznet'); ?>
			</td>
		</tr>
		<tr valign="top">
			<td>
				<?php /* $myDBaseObj->OutputViewTicketButton(); */ ?>
			</td>
			<td>
				<input class="button-primary wpcardznetlib-tools-ui" type="submit" name="testbutton_EMailSale" value="<?php _e('Send Test EMail', 'wpcardznet'); ?>"/>
			</td>
		</tr>
<?php		
?>
	</table>
<?php		

			WPCardzNetLibEscapingClass::Safe_EchoHTML('</form>'."\n");
		}
		
	}
}

