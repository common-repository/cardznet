<?php
/* 
Description: CardzNet Plugin Admin Database Access functions
 
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

if (!class_exists('WPCardzNetAdminDBaseClass')) 
{
	class WPCardzNetAdminDBaseClass extends WPCardzNetDBaseClass
	{
		function __construct($caller, $sessionCookieID = 0) 
		{
			// Use existing value for sessionCookieID to prevent session_start error
			if ($sessionCookieID != 0)
				$this->sessionCookieID = $sessionCookieID;
			
			parent::__construct($caller);
		}
		
		function AddGroup($groupName, $groupUserId = 0)
		{
			if ($groupUserId == 0)
				$groupUserId = get_current_user_id();
					
			$sql  = 'INSERT INTO '.WPCARDZNET_GROUPS_TABLE.'(groupName, groupUserId) ';
			$sql .= "VALUES(\"{$groupName}\", {$groupUserId})";
			$this->query($sql);	
					
     		$groupId = $this->GetInsertId();
      		return $groupId;
		}

		function AddInvitation($inviteFirstName, $inviteLastName, $inviteEMail, $inviteGroupId)
		{
			$this->PurgeInvitations();
			
			$inviteDateTime = date(WPCardzNetLibDBaseClass::MYSQL_DATETIME_FORMAT);
			$inviteHash = md5(date('r', time())); // "HashTBD-$inviteDateTime";
			
			$sql  = 'INSERT INTO '.WPCARDZNET_INVITES_TABLE.'(inviteGroupId, inviteDateTime, inviteFirstName, inviteLastName, inviteEMail, inviteHash) ';
			$sql .= 'VALUES(%d, %s, %s, %s, %s, %s)';
			$this->queryWithPrepare($sql, array($inviteGroupId, $inviteDateTime, $inviteFirstName, $inviteLastName, $inviteEMail, $inviteHash));	
			
			$inviteId = $this->GetInsertId();
			
     		return $inviteId;
		}
		
		function DeleteGame($gameId)
		{			
			// Delete tick page when active game complete
			$players = $this->GetGameById($gameId);
			$usersList = array();
			foreach ($players as $player)
				$usersList[$player->userId] = true;

			foreach ($usersList as $userId => $unused)
			{
				$userGameId = $this->GetLatestGame($userId);
				if ($userGameId == $gameId)
					$this->UpdateUserTickPage($userId, 0, 0);
			}
			
			$sql  = 'DELETE FROM '.WPCARDZNET_GAMES_TABLE.' ';
			$sql .= 'WHERE '.WPCARDZNET_GAMES_TABLE.'.gameId='.$gameId.' ';
			$this->query($sql);	
												
			$this->PurgeDB(true);
		}
		
		function DeleteGroup($groupId)
		{
			$sql  = 'DELETE FROM '.WPCARDZNET_GROUPS_TABLE.' ';
			$sql .= 'WHERE '.WPCARDZNET_GROUPS_TABLE.'.groupId='.$groupId.' ';
			$this->query($sql);	
			
			$this->PurgeDB(true);
		}

		static function FormatAdminDateTime($dateInDB)
		{
			// Convert time string to UNIX timestamp
			$timestamp = WPCardzNetLibMigratePHPClass::Safe_strtotime($dateInDB);
			if ($timestamp == 0) return '';
			
			return self::LocaliseAndFormatTimestamp($timestamp);
		}
		
		static function GetCurrentUserName()
		{
			$user = wp_get_current_user();
			return self::GetUserNameFromObj($user);
		}
		
		function GetDatabaseSQL($tablePrefix)
		{
			$tables = $this->GetPluginDBTablesList($tablePrefix);
			$backupData = $this->ExportDatabaseTables($tables);						
			return $backupData;
		}
		
		function GetDefaultGroupName()
		{
			$groupName = '';
			for ($groupNumber = 1;; $groupNumber++)
			{
				$groupName = "Group $groupNumber";
				if (!$this->GroupExists($groupName))
					break;
			}
			
			return $groupName;
		}
		
		function GetGamesCount($userId = 0)
		{			
			$sql  = 'SELECT COUNT(gameLoginId) as gamesCount FROM '.WPCARDZNET_GAMES_TABLE.' ';
			if ($userId != 0)
				$sql .= 'WHERE '.WPCARDZNET_GAMES_TABLE.'.gameLoginId='.$userId.' ';
			$results = $this->get_results($sql);
			
			return (count($results) == 0) ? 0 : $results[0]->gamesCount;
		}
		
		function GetGamesList()
		{
			if (isset($this->gamesList))
				return $this->gamesList;
				
			$this->gamesList = array();
			
			$gameFilePath = WPCARDZNET_GAMES_PATH.'wpcardznet_*.php';
			$gamePaths = glob( $gameFilePath );

			foreach ($gamePaths as $gamePath)			
			{
				$gameEntry = new stdClass();
				
				$gameEntry->filepath = $gamePath;
				$gameEntry->filename = basename($gamePath);
				$gameBasename = WPCardzNetLibMigratePHPClass::Safe_str_replace('.php', '', $gameEntry->filename);
				$gameBasename = WPCardzNetLibMigratePHPClass::Safe_str_replace('wpcardznet_', '', $gameBasename);
				$gameName = ucwords(WPCardzNetLibMigratePHPClass::Safe_str_replace('_', ' ', $gameBasename));			
				$gameEntry->gameName = $gameName;
				$gameClassBase = WPCardzNetLibMigratePHPClass::Safe_str_replace(' ', '', $gameName);
				$gameEntry->className = 'WPCardzNet'.$gameClassBase.'Class';
				
				$this->gamesList[$gameName] = $gameEntry;
			}
			
			return $this->gamesList;
		}
		
		function GetGroupSelector()
		{
			$groupUserId = 0;
			if (!current_user_can(WPCARDZNET_CAPABILITY_ADMINUSER))
				$groupUserId = get_current_user_id();

			$groupsList = $this->GetGroups($groupUserId);
			if (count($groupsList) == 0) return '';
			
			$html = "<select id=gameGroupId name=gameGroupId>";
			foreach ($groupsList as $group)
			{
				$html .= "<option value={$group->groupId}>{$group->groupName}</option> \n";
			}
			if (current_user_can(WPCARDZNET_CAPABILITY_ADMINUSER))
			{
				$allGroupsText = __("All Groups", 'wpcardznet');
				$html .= "<option value=0>$allGroupsText</option> \n";
			}
			$html .= "</select>";
			
			return $html;
		}
		
		function GetGroupsCount($userId = 0)
		{
			$sql  = 'SELECT COUNT(groupUserId) as groupsCount FROM '.WPCARDZNET_GROUPS_TABLE.' ';
			if ($userId != 0)
				$sql .= 'WHERE '.WPCARDZNET_GROUPS_TABLE.'.groupUserId='.$userId.' ';
			$results = $this->get_results($sql);
			
			return (count($results) == 0) ? 0 : $results[0]->groupsCount;
		}
		
		function GetInvitationByGroupId($groupId)
		{
			$this->PurgeInvitations();
			
			$sql  = 'SELECT * FROM '.WPCARDZNET_GROUPS_TABLE.' ';
			$sql .= 'JOIN '.WPCARDZNET_INVITES_TABLE.' ON '.WPCARDZNET_GROUPS_TABLE.'.groupId='.WPCARDZNET_INVITES_TABLE.'.inviteGroupId ';
			$sql .= 'WHERE '.WPCARDZNET_GROUPS_TABLE.'.groupId=%d ';
			
			$results = $this->getresultsWithPrepare($sql, array($groupId));
			
			return $results;
		}
		
		function GetInvitationById($inviteId)
		{
			$this->PurgeInvitations();
			
			$sql  = 'SELECT * FROM '.WPCARDZNET_INVITES_TABLE.' ';
			$sql .= 'LEFT JOIN '.WPCARDZNET_GROUPS_TABLE.' ON '.WPCARDZNET_GROUPS_TABLE.'.groupId='.WPCARDZNET_INVITES_TABLE.'.inviteGroupId ';
			$sql .= 'WHERE '.WPCARDZNET_INVITES_TABLE.'.inviteId=%d ';
			
			$results = $this->getresultsWithPrepare($sql, array($inviteId));
			
			return $results;
		}
		
		function GetLastGameName($userId)
		{
			$sqlFilters['sqlLimit'] = 'LIMIT 0,1';
			$results = $this->GetGames($userId, $sqlFilters);
			
			return (count($results) > 0) ? $results[0]->gameName : '';
		}
		
		function GetScoresByGame($gameId)
		{
			$this->gameId = $gameId;
			return $this->GetScores();
		}
		
		static function GetUserSelector($no, $name, $cap = '')
		{
			$showNone = false;
			if ($cap == '')
			{
				$cap = WPCARDZNET_CAPABILITY_PLAYER;
				$showNone = true;
			}
			
			$dropDownAtts = array('echo' => 0);

			$excludeList = '';
			$usersList = get_users();
			
			foreach ($usersList as $user)
			{
				if (!isset($user->allcaps[$cap]))
				{
					$excludeList .= $user->ID.',';
				}
				
				if (isset($user->data->display_name) && ($user->data->display_name != ''))
					$userName = $user->data->display_name;
				else
					$userName = $user->data->user_login;
					
				if ($userName = $name)
				{
					$defaultUserId = $user->ID;
					$dropDownAtts['selected'] = $defaultUserId;
				}
			}

			$dropDownAtts['name'] = "userId$no";			
			$dropDownAtts['exclude'] = $excludeList;	
					
			$html = wp_dropdown_users($dropDownAtts);
			
			if ($showNone)
			{
				$noneText = __("None", 'wpcardznet');
				$noneSelected = isset($dropDownAtts['selected']) ? '' : ' selected="selected" ';
				$noneSelect = "<option value=0 $noneSelected>($noneText)</option>";
				$html = WPCardzNetLibMigratePHPClass::Safe_str_replace('</select>', $noneSelect.'</select>', $html);
			}
			
			return $html;
		}
		
		function GetUsersCount()
		{
			$members = get_users();
			return count($members);
		}
				
		function RemoveMemberFromGroup($groupId, $memberId)
		{		
			$sql  = 'DELETE FROM '.WPCARDZNET_MEMBERS_TABLE.' ';
			$sql .= 'WHERE groupId='.$groupId.' ';
			$sql .= 'AND memberId='.$memberId.' ';
			return $this->query($sql);				
		}
		
		function SettingsOK()
		{
			if (isset($this->adminOptions['RefreshTime'])) return true;
			
			$text = __("Review and Save settings first", 'wpcardznet');
			$linkText = __("here", 'wpcardznet');
			self::GoToPageLink($text, $linkText, WPCARDZNET_MENUPAGE_SETTINGS);
			
			return false;
		}
		
	}
}

?>