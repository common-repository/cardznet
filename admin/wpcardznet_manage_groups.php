<?php
/* 
Description: Code for Managing Groups
 
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
require_once WPCARDZNET_INCLUDE_PATH.'wpcardznetlib_htmlemail_api.php';      

if (!class_exists('WPCardzNetGroupsAdminListClass')) 
{
	// --- Define Class: WPCardzNetGroupsAdminListClass
	class WPCardzNetGroupsAdminListClass extends WPCardzNetLibAdminListClass // Define class
	{	
		function __construct($env) //constructor
		{
			$this->hiddenRowsButtonId = 'TBD';		

			// Call base constructor
			parent::__construct($env, true);
			
			$this->hiddenRowsButtonId = __('Details', 'wpcardznet');		
			
			if (current_user_can(WPCARDZNET_CAPABILITY_ADMINUSER))
			{
				$this->bulkActions = array(
					self::BULKACTION_DELETE => __('Delete', 'wpcardznet'),
					);
			}
					
			$this->HeadersPosn = WPCardzNetLibTableClass::HEADERPOSN_BOTH;
			
		}
		
		function GetRecordID($result)
		{
			return $result->groupId;
		}
		
		function GetCurrentURL() 
		{			
			$currentURL = parent::GetCurrentURL();
			return $currentURL;
		}
		
		function GetDetailsRowsFooter()
		{
			$ourOptions = array(
			);
		
			$ourOptions = self::MergeSettings(parent::GetDetailsRowsFooter(), $ourOptions);
			
			return $ourOptions;
		}
		
		function GetTableID($result)
		{
			return "wpcardznet-groups-tab";
		}
		
		function ShowGroupDetails($result)
		{
			$myDBaseObj = $this->myDBaseObj;
			
			$groupId = $result->groupId;
			
			$groupResults = $myDBaseObj->GetMembersById($groupId);				
			$invitationsList = $myDBaseObj->GetInvitationByGroupId($groupId);
			if ((count($groupResults) > 0) || (count($invitationsList) > 0))
			{
				$groupDetails = $this->BuildGroupDetails($groupId, $groupResults, $invitationsList);
			}
			else
			{
				$groupDetails = __("No Members", 'wpcardznet');
			}

			return $groupDetails;
		}
				
		function GetListDetails($result)
		{
			return $this->myDBaseObj->GetGroupById($result->groupId);
		}
		
		function BuildGroupDetails($groupId, $groupResults, $invitationsList)
		{
			$env = $this->env;

			foreach ($groupResults as $groupRec)
			{
				$groupRec->memberName = WPCardzNetAdminDBaseClass::GetUserName($groupRec->memberUserId);
				$groupRec->memberEMail = WPCardzNetAdminDBaseClass::GetUserEMail($groupRec->memberUserId);
				$groupRec->memberStatus = __("Confirmed", 'wpcardznet');
			}
			
			foreach ($invitationsList as $invitation)
			{
				$dateTime = $this->myDBaseObj->FormatDateForAdminDisplay($invitation->inviteDateTime);
				
				$inviteRec = new stdClass();
				$inviteRec->groupId = $groupId;
				$inviteRec->memberUserId = 0;
				$inviteRec->memberName = WPCardzNetLibMigratePHPClass::Safe_trim("{$invitation->inviteFirstName} {$invitation->inviteLastName}");
				$inviteRec->memberEMail = $invitation->inviteEMail;
				$inviteRec->memberStatus = __("Invited", 'wpcardznet')." - $dateTime";
				$groupResults[] = $inviteRec;
			}
			
			$groupDetailsList = $this->CreateGroupAdminDetailsListObject($env, $this->editMode, $groupResults);	
			
			// Set Rows per page to disable paging used on main page
			$groupDetailsList->enableFilter = false;
			
			ob_start();	
			$groupDetailsList->OutputList($groupResults);	
			$memberDetailsOutput = ob_get_contents();
			ob_end_clean();

			return $memberDetailsOutput;
		}
		
		function NeedsConfirmation($bulkAction)
		{
			switch ($bulkAction)
			{
				default:
					return parent::NeedsConfirmation($bulkAction);
			}
		}
		
		function ExtendedSettingsDBOpts()
		{
			return parent::ExtendedSettingsDBOpts();
		}
		
		function FormatUserName($userId, $result)
		{
			return WPCardzNetAdminDBaseClass::GetUserName($result->groupUserId);
		}	
								
		function GetMainRowsDefinition()
		{
			$columnDefs = array(
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Group Name',    WPCardzNetLibTableClass::TABLEPARAM_ID => 'groupName',    WPCardzNetLibTableClass::TABLEPARAM_TYPE => WPCardzNetLibTableClass::TABLEENTRY_VIEW, ),
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Group Manager', WPCardzNetLibTableClass::TABLEPARAM_ID => 'groupUserId',  WPCardzNetLibTableClass::TABLEPARAM_TYPE => WPCardzNetLibTableClass::TABLEENTRY_VIEW, self::TABLEPARAM_DECODE => 'FormatUserName'),
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'No of Members', WPCardzNetLibTableClass::TABLEPARAM_ID => 'noOfMembers',  WPCardzNetLibTableClass::TABLEPARAM_TYPE => WPCardzNetLibTableClass::TABLEENTRY_VIEW, ),
			);
			
			if (current_user_can(WPCARDZNET_CAPABILITY_MANAGER))
			{
				$buttonOptions = array(
					array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => '&nbsp;', WPCardzNetLibTableClass::TABLEPARAM_ID => 'groupId',  WPCardzNetLibTableClass::TABLEPARAM_TYPE => WPCardzNetLibTableClass::TABLEENTRY_FUNCTION, WPCardzNetLibTableClass::TABLEPARAM_FUNC => 'AddGroupButtons')
				);
				$columnDefs = self::MergeSettings($columnDefs,$buttonOptions);	
			}
					
			return $columnDefs;
		}		

		function GetTableRowCount()
		{
			$userId = current_user_can(WPCARDZNET_CAPABILITY_ADMINUSER) ? 0 : get_current_user_id();
			return $this->myDBaseObj->GetGroupsCount($userId);		
		}		

		function GetTableData(&$results, $rowFilter)
		{
			$sqlFilters['sqlLimit'] = $this->GetLimitSQL();
/*
			if ($rowFilter != '')
			{
				$sqlFilters['whereSQL'] = $this->GetFilterSQL($rowFilter);
			}
*/
			// Get list of sales (one row per sale)
			$userId = current_user_can(WPCARDZNET_CAPABILITY_ADMINUSER) ? 0 : get_current_user_id();
			$results = $this->myDBaseObj->GetGroups($userId, $sqlFilters);
		}

		function GetDetailsRowsDefinition()
		{
			$ourOptions = array(
				array(WPCardzNetLibTableClass::TABLEPARAM_TYPE => WPCardzNetLibTableClass::TABLEENTRY_FUNCTION, WPCardzNetLibTableClass::TABLEPARAM_FUNC => 'ShowGroupDetails'),
			);

			$rowDefs = self::MergeSettings(parent::GetDetailsRowsDefinition(), $ourOptions);

			return $rowDefs;
		}
		
		function AddGroupButtons($result)
		{
			$buttonHTML = '';		
			
			if (current_user_can('manage_options'))
				$buttonHTML .=  $this->myDBaseObj->ActionButtonHTML('Add Existing User', $this->caller, 'wpcardznet', '', $this->GetRecordID($result), 'adduser'); 
			$buttonHTML .=  $this->myDBaseObj->ActionButtonHTML('Invite New Member', $this->caller, 'wpcardznet', '', $this->GetRecordID($result), 'addmember'); 
				
			return $buttonHTML;
		}
		
		function CreateGroupAdminDetailsListObject($env, $editMode, $groupResults)
		{
			return new WPCardzNetGroupsAdminDetailsListClass($env, $editMode, $groupResults);	
		}
		
	}
}


if (!class_exists('WPCardzNetGroupsAdminDetailsListClass')) 
{
	class WPCardzNetGroupsAdminDetailsListClass extends WPCardzNetLibAdminDetailsListClass // Define class
	{		
		function __construct($env, $editMode, $groupResults) //constructor
		{
			// Call base constructor
			parent::__construct($env, $editMode);
			
			$this->SetRowsPerPage(self::WPCARDZNETLIB_EVENTS_UNPAGED);
			
			$this->HeadersPosn = WPCardzNetLibTableClass::HEADERPOSN_TOP;
		}
		
		function GetTableID($result)
		{
			return "wpcardznet-groups-list-tab";
		}
		
		function GetRecordID($result)
		{
			if (!isset($result->groupId))
			{
				$filename = basename(__FILE__);
				$fileline = __LINE__;
				WPCardzNetLibEscapingClass::Safe_EchoHTML("groupId not defined at line $fileline in $filename <br>\n");
				//debug_print_backtrace();
				WPCardzNetLibUtilsClass::print_r($result, '$result');
				die;
			}		
				
			return $result->groupId;
		}
		
		function GetDetailID($result)
		{
			return '_'.$result->memberUserId;
		}
		
		function AddDeleteMemberButton($result)
		{
			if ($result->memberUserId == 0) return '';
			
			$gidParam  = 'gid='.$result->groupId;
			$html = $this->myDBaseObj->ActionButtonHTML(__('Remove', 'wpcardznet'), $this->caller, 'wpcardznet', '', $result->memberId, 'removemember', $gidParam);    				
			return $html;
		}
		
		function GetMainRowsDefinition()
		{
			$rtnVal = array(
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Name',    WPCardzNetLibTableClass::TABLEPARAM_ID => 'memberName',   WPCardzNetLibTableClass::TABLEPARAM_TYPE => WPCardzNetLibTableClass::TABLEENTRY_VIEW, ),
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'EMail',   WPCardzNetLibTableClass::TABLEPARAM_ID => 'memberEMail',  WPCardzNetLibTableClass::TABLEPARAM_TYPE => WPCardzNetLibTableClass::TABLEENTRY_VIEW, ),
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Status',  WPCardzNetLibTableClass::TABLEPARAM_ID => 'memberStatus', WPCardzNetLibTableClass::TABLEPARAM_TYPE => WPCardzNetLibTableClass::TABLEENTRY_VIEW, ),

				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Action',  WPCardzNetLibTableClass::TABLEPARAM_ID => 'memberId',     self::TABLEPARAM_TYPE => self::TABLEENTRY_FUNCTION, self::TABLEPARAM_FUNC => 'AddDeleteMemberButton'),
			);
			
			return $rtnVal;
		}
		
		function IsRowInView($result, $rowFilter)
		{
			return true;
		}		
				
	}
}
	
if (!class_exists('WPCardzNetGroupsAdminClass')) 
{
	// --- Define Class: WPCardzNetGroupsAdminClass
	class WPCardzNetGroupsAdminClass extends WPCardzNetLibAdminClass // Define class
	{		
		var $results;
		var $showOptionsID = 0;
		
		function __construct($env)
		{
			$this->pageTitle = __('Groups', 'wpcardznet');
			
			parent::__construct($env, true);
		}
		
		function ProcessActionButtons()
		{
			$myPluginObj = $this->myPluginObj;
			$myDBaseObj = $this->myDBaseObj;				
				
			if (!current_user_can(WPCARDZNET_CAPABILITY_MANAGER)) 
			{
				die("User must have ".WPCARDZNET_CAPABILITY_MANAGER." Permission");
				return;
			}
			
			if (WPCardzNetLibUtilsClass::IsElementSet('post', 'addGroupRequest'))
			{
				// Check that the referer is OK
				$this->CheckAdminReferer();		

				// Add Group to Database - Show group setup page
				$html = $this->GetGroupDetailsForm();
				WPCardzNetLibEscapingClass::Safe_EchoHTML($html);
				
				$this->donePage = true;
			}
			else if (WPCardzNetLibUtilsClass::IsElementSet('post', 'addGroupDetails'))
			{
				$groupName = WPCardzNetLibUtilsClass::GetHTTPAlphaNumericElem('post', 'groupName');
				
				$groupId = 0;
				if (current_user_can(WPCARDZNET_CAPABILITY_ADMINUSER))
					$groupId = WPCardzNetLibUtilsClass::GetHTTPInteger('post', 'userId');
				
				// Check if this group already exists				
				if ($myDBaseObj->GroupExists($groupName))
				{
					WPCardzNetLibEscapingClass::Safe_EchoHTML($this->myDBaseObj->BannerMsg(__("Group Name Already Exists", 'wpcardznet'), 'error'));
					return;
				}

				$this->myDBaseObj->AddGroup($groupName, $groupId);

				WPCardzNetLibEscapingClass::Safe_EchoHTML($this->myDBaseObj->BannerMsg(__("Group Added", 'wpcardznet'), 'updated'));
			}
			else if (WPCardzNetLibUtilsClass::IsElementSet('get', 'action'))
			{
				// Check that the referer is OK
				$this->CheckAdminReferer();		

				switch (WPCardzNetLibUtilsClass::GetHTTPTextElem('get', 'action'))
				{
					case 'addmember':
						$sentInvite = false;
						if (WPCardzNetLibUtilsClass::IsElementSet('post', 'sendInvitationDetails'))
						{
							// Check that user is admin or group owner
							if (!current_user_can('manage_options')) 
							{
								$groupId = WPCardzNetLibUtilsClass::GetHTTPInteger('post', 'id');
								$groupDef = $myDBaseObj->GetGroupById($groupId);
								$user = wp_get_current_user();
								if ((count($groupDef) == 0) || ($groupDef[0]->groupUserId != $user->ID))
								{
									WPCardzNetLibEscapingClass::Safe_EchoHTML($myDBaseObj->BannerMsg(__("You are not the group administrator", 'wpcardznet'), 'error'));
									return;
								}
							}
							
							// NOTE: Could Check that new memeber is not already a memeber of this group
							
							// Callback from submitted member details
							$sentInvite = $this->SendMemberInvitation();
						}
						
						if (!$sentInvite)
						{
							// Add Group to Database - Show group setup page
							$html = $this->GetMemberDetailsForm();
							WPCardzNetLibEscapingClass::Safe_EchoHTML($html);
							$this->donePage = true;
						}
						break;
					
					case 'adduser':
						if (!WPCardzNetLibUtilsClass::IsElementSet('post', 'adduserId'))
						{
							// Add Group to Database - Show group setup page
							$html = $this->GetAddUserForm();
							WPCardzNetLibEscapingClass::Safe_EchoHTML($html);
							$this->donePage = true;
						}
						else
						{
							// Callback from submitted member details
							$userId = WPCardzNetLibUtilsClass::GetHTTPInteger('post', 'user');
							$groupId = WPCardzNetLibUtilsClass::GetHTTPInteger('get', 'id');
							if (($userId == 0) || ($groupId == 0)) die;
							
							// Add the user to the Group as an unverified member
							$myDBaseObj->AddMemberToGroup($groupId, $userId);
							
							WPCardzNetLibEscapingClass::Safe_EchoHTML($myDBaseObj->BannerMsg(__("User added to group", 'wpcardznet'), 'updated'));
						}						
						break;
					
					case 'removemember':
						if (!WPCardzNetLibUtilsClass::IsElementSet('get', 'gid')) die("gid missing");
						if (!WPCardzNetLibUtilsClass::IsElementSet('get', 'id')) die("id missing");
						
						$groupId = WPCardzNetLibUtilsClass::GetHTTPInteger('get', 'gid');
						$memberId = WPCardzNetLibUtilsClass::GetHTTPInteger('get', 'id');
						
						$myDBaseObj->RemoveMemberFromGroup($groupId, $memberId);
						
						WPCardzNetLibEscapingClass::Safe_EchoHTML($this->myDBaseObj->BannerMsg(__("Member Removed", 'wpcardznet'), 'updated'));
						
						break;
					
					default:
						die("Invalid Action");
				}
			}
			else if (WPCardzNetLibUtilsClass::IsElementSet('post', 'savechanges'))
			{
			}			
			else if (WPCardzNetLibUtilsClass::IsElementSet('get', 'action'))
			{
				$this->CheckAdminReferer();
				$this->DoActions();
			}

		}
		
		function GetGroupDetailsForm($addHtml = '')
		{
			$addGroupText = __('Add Group', 'wpcardznet');
			$groupNameText = __('Group Name', 'wpcardznet');
			$groupManagerText = __('Group Manager', 'wpcardznet');
			
			$name = $this->myDBaseObj->GetDefaultGroupName();
			
			$html  = "<form method=post>\n";
			$html .= "<table>\n";
			
			$html .= "<tr class='addgroup_row_group'>\n";
			$html .= "<td class='groupcell'>$groupNameText</td>";
			$html .= "<td class='groupcell'><input id=groupName name=groupName value='$name'></td>";
			$html .= "</tr>\n";
						
			$html .= "<tr class='addgroup_row_login'>\n";
			$html .= "<td class='groupcell'>$groupManagerText</td>";
			$html .= "<td class='groupcell'>";
			
			$userName = WPCardzNetAdminDBaseClass::GetCurrentUserName();			
			$user = wp_get_current_user();
			if (current_user_can(WPCARDZNET_CAPABILITY_ADMINUSER))
			{
				$html .= WPCardzNetAdminDBaseClass::GetUserSelector('', $userName, WPCARDZNET_CAPABILITY_MANAGER);
			}
			else
			{
				// Just Show the current user name
				$html .= "<span>$userName</span>";
			}
			$html .= "</td></tr>\n";
			
			if (current_user_can(WPCARDZNET_CAPABILITY_ADMINUSER))
				$html .= "<tr class='addgroup_row_submit'><td colspan=3><input class='button-secondary' type='submit' name=addGroupDetails value='$addGroupText'></td></tr>\n";

			$html .= "</table>\n";
			$html .= "</form>\n";
			
			WPCardzNetLibEscapingClass::Safe_EchoHTML($html);
		}
		
		function GetAddUserForm()
		{
			if (!current_user_can('manage_options'))
			{
				return __("Permission to add User Denied", 'wpcardznet');
			}
			
			$myDBaseObj = $this->myDBaseObj;
			
			$groupId = WPCardzNetLibUtilsClass::GetHTTPInteger('get', 'id');
						
			$groupDef = $myDBaseObj->GetGroupById($groupId);
			if (count($groupDef) == 0)
			{
				WPCardzNetLibEscapingClass::Safe_EchoHTML($myDBaseObj->BannerMsg(__("Invalid Group", 'wpcardznet'), 'error'));
				return;
			}
			$excludesList = array($groupDef[0]->groupUserId);
			
			$addUserText = __('Add User', 'wpcardznet');
			$memberEMailAddressText = __('User Login', 'wpcardznet');
			
			// Exclude existing group members
			$membersDefs = $myDBaseObj->GetMembersById($groupId);
			foreach ($membersDefs as $memberDef)
			{
				$excludesList[] = $memberDef->memberUserId;
			}
			
			$all_users = get_users();
			$includesList = array();

			foreach($all_users as $user)
			{
			    if (!$user->has_cap(WPCARDZNET_CAPABILITY_PLAYER))
			    	continue;
			    	
			    if (in_array($user->ID, $excludesList))
			    	continue;
			    	
		        $includesList[] = $user->ID;
			}

			if (count($includesList) == 0)
			{
				$text = __("No users available to add", 'wpcardznet');
				$linkText = __("Go back", 'wpcardznet');
				WPCardzNetAdminDBaseClass::GoToPageLink($text, $linkText, WPCARDZNET_MENUPAGE_GROUPS);
				return;
			}
			
			$html  = "<form method=post>\n";
			$html .= "<table>\n";
			
			$html .= "<tr class='addmember_row_member'>\n";
			$html .= "<td class='membercell'>$memberEMailAddressText</td>";
			$html .= "<td class='membercell'>";
			$html .= wp_dropdown_users(array(
			    'echo' => false,
			    'show' => '',
			    'include' => $includesList,
				));
				
			$html .= "</td>";
			$html .= "</tr>\n";
						
			$html .= "<tr class='addmember_row_submit'><td colspan=3><input class='button-secondary' type='submit' name=adduserId value='$addUserText'></td></tr>\n";

			$html .= "</table>\n";
			$html .= "</form>\n";
			
			WPCardzNetLibEscapingClass::Safe_EchoHTML($html);
		}

		function GetMemberDetailsForm()
		{
			if (!current_user_can(WPCARDZNET_CAPABILITY_MANAGER))
			{
				return __("Permission to add Member Denied", 'wpcardznet');
			}
			
			$sendInvitationText = __('Send Invitation', 'wpcardznet');
			$memberFirstNameText = __('First Name', 'wpcardznet');
			$memberLastNameText = __('Last Name', 'wpcardznet');
			$memberEMailAddressText = __('EMail Address', 'wpcardznet');
			
			$memberFirstName = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'memberFirstName');
			$memberLastName = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'memberLastName');
			$memberEMail = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'memberEMail');

			$html  = "<form method=post>\n";
			$html .= "<table>\n";
			
			$html .= "<tr class='addmember_row_member'>\n";
			$html .= "<td class='membercell'>$memberFirstNameText</td>";
			$html .= "<td class='membercell'><input type=text autocomplete=off size=20 id=memberFirstName name=memberFirstName value='$memberFirstName'></td>";
			$html .= "</tr>\n";
						
			$html .= "<tr class='addmember_row_member'>\n";
			$html .= "<td class='membercell'>$memberLastNameText</td>";
			$html .= "<td class='membercell'><input type=text autocomplete=off size=20 id=memberLastName name=memberLastName value='$memberLastName'></td>";
			$html .= "</tr>\n";
						
			$html .= "<tr class='addmember_row_member'>\n";
			$html .= "<td class='membercell'>$memberEMailAddressText</td>";
			$html .= "<td class='membercell'><input type=text autocomplete=off size=80 id=memberEMail name=memberEMail value='$memberEMail'></td>";
			$html .= "</tr>\n";
						
			$html .= "<tr class='addmember_row_submit'><td colspan=3><input <input class='button-secondary' type='submit' name=sendInvitationDetails value='$sendInvitationText'></td></tr>\n";

			$html .= "</table>\n";
			$html .= "</form>\n";
			
			WPCardzNetLibEscapingClass::Safe_EchoHTML($html);
		}
		
		function SendMemberInvitation()
		{
			$myDBaseObj = $this->myDBaseObj;
			
			$groupId = WPCardzNetLibUtilsClass::GetHTTPInteger('get', 'id');
			$memberFirstName = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'memberFirstName');
			$memberLastName = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'memberLastName');
			$memberEMail = WPCardzNetLibUtilsClass::GetHTTPEMail('post', 'memberEMail');
			
			$groupDef = $myDBaseObj->GetGroupById($groupId);
			if (count($groupDef) == 0)
			{
				WPCardzNetLibEscapingClass::Safe_EchoHTML($myDBaseObj->BannerMsg(__("Invalid Group", 'wpcardznet'), 'error'));
				return false;
			}
			
			// Validate User ....
			if ((WPCardzNetLibMigratePHPClass::Safe_strlen($memberFirstName) < 1) || (WPCardzNetLibMigratePHPClass::Safe_strlen($memberLastName) < 1))
			{
				WPCardzNetLibEscapingClass::Safe_EchoHTML($myDBaseObj->BannerMsg(__("Invalid Name", 'wpcardznet'), 'error'));
				return false;
			}
			
			if (!is_email($memberEMail))
			{
				WPCardzNetLibEscapingClass::Safe_EchoHTML($myDBaseObj->BannerMsg(__("Invalid EMail", 'wpcardznet'), 'error'));
				return false;
			}

    		$userdata = get_user_by('email', $memberEMail);
			if ($userdata)
			{
				$membersList = $myDBaseObj->GetMembersById($groupId, $userdata->ID);
				if (count($membersList) > 0)
				{
					WPCardzNetLibEscapingClass::Safe_EchoHTML($myDBaseObj->BannerMsg(__("Member is already in this group", 'wpcardznet'), 'error'));
					return false;
				}
			}

			// Add the user details to the Invitations DB Table
			$inviteId = $myDBaseObj->AddInvitation($memberFirstName, $memberLastName, $memberEMail, $groupId);
			
			// Send and email to the user (and optionally to the admin)
			$inviteRecords = $myDBaseObj->GetInvitationById($inviteId);

			$cbURL  = get_option('siteurl');
			$cbURL .= '?'.WPCARDZNET_CALLBACK_ID."=$groupId";
			$cbURL .= '&action=accept';
			$cbURL .= '&auth='.$inviteRecords[0]->inviteHash;
			
			//$inviteRecords[0]->url = 'TBD_'.WPCARDZNET_CALLBACK_ID;
			$inviteRecords[0]->inviteURL = $cbURL;

			$myDBaseObj->SendEMailByTemplateID($inviteRecords, 'inviteEMail', 'emails', $memberEMail);
			
			WPCardzNetLibEscapingClass::Safe_EchoHTML($myDBaseObj->BannerMsg(__("Invitation Sent", 'wpcardznet'), 'updated'));
			return true;
		}
		
		function Output_MainPage($updateFailed)
		{
			if (isset($this->pageHTML))	
			{
				WPCardzNetLibEscapingClass::Safe_EchoHTML($this->pageHTML);
				return;
			}

			$myPluginObj = $this->myPluginObj;
			$myDBaseObj = $this->myDBaseObj;				
			
			if (!$myDBaseObj->SettingsOK())
				return;
				
			$actionURL = remove_query_arg('action');
			$actionURL = remove_query_arg('id', $actionURL);
			
			// HTML Output - Start 
			$formClass = 'wpcardznet'.'-admin-form '.'wpcardznet'.'-groups-editor';
			WPCardzNetLibEscapingClass::Safe_EchoHTML('
				<div class="'.$formClass.'">
				<form method="post" action="'.$actionURL.'">
				');

			if (isset($this->saleId))
				WPCardzNetLibEscapingClass::Safe_EchoHTML("\n".'<input type="hidden" name="saleID" value="'.$this->saleId.'"/>'."\n");
				
			$this->WPNonceField();
				 
			$noOfGroups = $this->OutputGroupsList($this->env);
			if ($noOfGroups == 0)
			{
				WPCardzNetLibEscapingClass::Safe_EchoHTML("<div class='noconfig'>".__('No Groups', 'wpcardznet')."</div>\n");
			}
			else 
			{
			}

			if (current_user_can(WPCARDZNET_CAPABILITY_ADMINUSER))
			{
				$this->OutputButton("addGroupRequest", __("Add Group", 'wpcardznet'));
			}
			
			if ($noOfGroups > 0)
			{
				//$this->OutputButton("savechanges", __("Save Changes", 'wpcardznet'), "button-primary");
			}

?>
	<br></br>
	</form>
	</div>
<?php
		} // End of function Output_MainPage()


		function OutputGroupsList($env)
		{
			$myPluginObj = $this->myPluginObj;

			$classId = $myPluginObj->adminClassPrefix.'GroupsAdminListClass';
			$groupsListObj = new $classId($env);
			$groupsListObj->showOptionsID = $this->showOptionsID;
			return $groupsListObj->OutputList($this->results);		
		}
				
		function DoActions()
		{
			$rtnVal = false;
			$myDBaseObj = $this->myDBaseObj;

			switch (WPCardzNetLibUtilsClass::GetHTTPTextElem('get', 'action'))
			{
				default:
					$rtnVal = false;
					break;
					
			}
				
			return $rtnVal;
		}

		function DoBulkPreAction($bulkAction, $recordId)
		{
			$myDBaseObj = $this->myDBaseObj;
			
			// Reset error count etc. on first pass
			if (!isset($this->errorCount)) $this->errorCount = 0;
			
			$results = $myDBaseObj->GetGroupById($recordId);
			
			switch ($bulkAction)
			{
				case WPCardzNetLibAdminListClass::BULKACTION_DELETE:
					// FUNCTIONALITY: Groups - Bulk Action Delete			
					if (count($results) == 0)
						$this->errorCount++;
					return ($this->errorCount > 0);
			}
			
			return false;
		}
		
		function DoBulkAction($bulkAction, $recordId)
		{
			$myDBaseObj = $this->myDBaseObj;
			
			$listClassId = $this->myPluginObj->adminClassPrefix.'GroupsAdminListClass';
			
			switch ($bulkAction)
			{
				case WPCardzNetLibAdminListClass::BULKACTION_DELETE:		
					$myDBaseObj->DeleteGroup($recordId);
					return true;
			}
				
			return parent::DoBulkAction($bulkAction, $recordId);
		}
		
		function GetBulkActionMsg($bulkAction, $actionCount)
		{
			$actionMsg = '';
			
			$listClassId = $this->myPluginObj->adminClassPrefix.'GroupsAdminListClass';
			
			switch ($bulkAction)
			{
				case WPCardzNetLibAdminListClass::BULKACTION_DELETE:	
					if ($this->errorCount > 0)
						$actionMsg = $this->errorCount . ' ' . _n("Group does not exist in Database", "Groups do not exist in Database", $this->errorCount, 'wpcardznet');
					else if ($actionCount > 0)
						$actionMsg = $actionCount . ' ' . _n("Group has been deleted", "Groups have been deleted", $actionCount, 'wpcardznet');
					else
						$actionMsg =  __("Nothing to Delete", 'wpcardznet');
					break;
					
				default:
					$actionMsg = parent::GetBulkActionMsg($bulkAction, $actionCount);

			}
			
			return $actionMsg;
		}
		
	}

}

?>