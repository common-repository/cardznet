<?php
/* 
Description: CardzNet Plugin Database Access functions
 
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

if (!class_exists('WPCardzNetLibDBaseClass')) 
	include WPCARDZNETLIB_INCLUDE_PATH.'wpcardznetlib_dbase_api.php';
	
include WPCARDZNETLIB_INCLUDE_PATH.'wpcardznetlib_logfile.php';
	
if (!class_exists('WPCardzNetDBaseClass')) 
{
	global $wpdb;
	
	$dbPrefix = $wpdb->prefix.'wpcardznet_';
	
	if (!defined('WPCARDZNET_TABLE_PREFIX'))
	{
		define('WPCARDZNET_TABLE_PREFIX', $dbPrefix);
	}
	
	if( !defined( 'WPCARDZNET_FILENAME_TEXTLEN' ) )
		define('WPCARDZNET_FILENAME_TEXTLEN', 80);
	
	define('WPCARDZNET_CAPABILITY_TEXTLEN', 32);			

	define('WPCARDZNET_MENUMODE_MAINMENU', 'MainMenu');	
	define('WPCARDZNET_MENUMODE_SUBMENU', 'SubMenu');	
	define('WPCARDZNET_MENUMODE_NONADMINMAINMENU', 'NonAdminMainMenu');	

	define('WPCARDZNET_FILENAME_COMMSLOG', 'WPCardzNet.log');
	
	define('WPCARDZNET_PLAYERACTIVE_FAIL', 'fail');
	define('WPCARDZNET_PLAYERACTIVE_ENDGAME', 'endgame');

	define('WPCARDZNET_MIMICMODE_PLAYER', 'player');
	define('WPCARDZNET_MIMICMODE_DEALER', 'dealer');
	define('WPCARDZNET_MIMICMODE_ADMIN', 'admin');

	define('WPCARDZNET_MIMICVISIBILITY_ALWAYS', 'always');
	define('WPCARDZNET_MIMICVISIBILITY_WITHHAND', 'withcards');
	define('WPCARDZNET_MIMICVISIBILITY_NEVER', 'never');

	// Set the DB tables names
	class WPCardzNetDBaseClass extends WPCardzNetLibDBaseClass
	{
		var $adminOptions;
		var $errMsg;
    
    	var $gameId = 0;
    	var $roundId = 0;
    	
		var $thisPlayer;
		
    	var $nextPlayerId = 0;
    	
    	var $playersPerUser = 0;
    	
   		// Cached Database Entries
   		var $currGameRec = null;
   		var $currRoundRec = null;
   		var $currPlayersRec = null;
   		var $currTrick = null;
   		
    	var $isSeqMode = false;
    	
    	var $jsGlobals = array();
    	var $ajaxVars = array();
   	
   		const ROUND_READY = 'ready';
   		const ROUND_COMPLETE = 'complete';
   		const ROUND_UNDEFINED = 'undefined';
   		
   		const GAME_INPROGRESS = 'in-progress';
   		const GAME_COMPLETE = 'complete';
   		const GAME_ENDED = 'ended';
   		
   		const CLEAR_CARDS = true;
   		const LEAVE_CARDS = false;
   		
		static function GameNameToFileAndClass($gameName)
		{
			$rslt = new stdClass();
			
			$gameName = ucwords($gameName);
			$classRoot = WPCardzNetLibMigratePHPClass::Safe_str_replace(' ', '', $gameName);
			$gameClass = "WPCardzNet".$classRoot."Class";
			
			$srcRoot = WPCardzNetLibMigratePHPClass::Safe_str_replace(' ', '_', $gameName);
			$gameRootName = "wpcardznet_".WPCardzNetLibMigratePHPClass::Safe_strtolower($srcRoot);
			
			$rslt->gameName = $gameName;			
			$rslt->className = $gameClass;
			$rslt->srcfile = $gameRootName.".php";

			return $rslt;
		}
		
		function __construct($caller) 
		{
			$opts = array (
				'Caller'             => $caller,

				'PluginFolder'       => WPCARDZNET_FOLDER,
				'CfgOptionsID'       => WPCARDZNET_OPTIONS_NAME,
				'DbgOptionsID'       => WPCARDZNET_DBGOPTIONS_NAME,				
			);	
					
			$this->emailObjClass = 'WPCardzNetLibHTMLEMailAPIClass';
			$this->emailClassFilePath = WPCARDZNETLIB_INCLUDE_PATH.'wpcardznetlib_htmlemail_api.php';   

			// Call base constructor
			parent::__construct($opts);
		}

		function OutputDebugStart()
		{
			if (!isset($this->debugToLog))
				$this->debugToLog = $this->isDbgOptionSet('Dev_DebugToLog');
			if ($this->debugToLog) ob_start();
		}
		
		function OutputDebugEnd()
		{
			if ($this->debugToLog)
			{
				$debugOutput = ob_get_contents();
				ob_end_clean();
				if ($debugOutput != '')
				{
					$this->AddToStampedCommsLog($debugOutput);
					if (WPCardzNetLibMigratePHPClass::Safe_strpos($debugOutput, 'id="message"') !== false) 
						WPCardzNetLibEscapingClass::Safe_EchoHTML($debugOutput);
				}
			}
		}
		
		static function GoToPageLink($promptText, $linkText, $page, $echo=true)
		{
			$pageURL = WPCardzNetLibUtilsClass::GetPageBaseURL();
			$pageURL = add_query_arg('page', $page, $pageURL);

			$msg = "$promptText - <a href=\"$pageURL\">$linkText</a>";
			
			if ($echo) WPCardzNetLibEscapingClass::Safe_EchoHTML($msg);
			return $msg;
		}
		
		function ShowSQL($sql, $values = null)
		{			
			if (!$this->isDbgOptionSet('Dev_ShowSQL'))
			{
				return;		
			}
			
			$this->OutputDebugStart();
			//if (!$this->isDbgOptionSet('Dev_ShowCaller'))
			{
				ob_start();		
				debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);		
				$callStack = ob_get_contents();
				ob_end_clean();
				
				$callStack = preg_split('/#[0-9]+[ ]+/', $callStack, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
				$caller = explode("(", $callStack[2]);
				$caller = WPCardzNetLibMigratePHPClass::Safe_str_replace("->", "::", $caller[0]);
				WPCardzNetLibEscapingClass::Safe_EchoHTML("SQL Caller: $caller() \n");
			}
			
			parent::ShowSQL($sql, $values);
			$this->OutputDebugEnd();
		}
		
		function show_results($results)
		{
			if ( !$this->isDbgOptionSet('Dev_ShowSQL') 
			  && !$this->isDbgOptionSet('Dev_ShowDBOutput') )
			{
				return;
			}
			
			$this->OutputDebugStart();
			parent::show_results($results);
			$this->OutputDebugEnd();
		}
		
		function show_cache($results, $id='')
		{
			if ( !$this->isDbgOptionSet('Dev_DebugToLog') 
			  || !$this->isDbgOptionSet('Dev_ShowDBOutput'))
			  	return;

			if (function_exists('wp_get_current_user'))
			{
				if (!$this->isSysAdmin())
					return;				
			}
				
			$this->OutputDebugStart();
			if ($this->isDbgOptionSet('Dev_ShowCallStack'))
				WPCardzNetLibUtilsClass::ShowCallStack();
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<br>Cache: $id: ".print_r($results, true)."<br>\n");
			$this->OutputDebugEnd();
		}
	    
		function query($sql)
		{
			if ( !$this->isDbgOptionSet('Dev_DebugToLog') 
			  || !$this->isDbgOptionSet('Dev_ShowDBOutput'))
			  	return parent::query($sql);
			
			$this->OutputDebugStart();
			$result = parent::query($sql);
			$this->OutputDebugEnd();
			
			return $result;
		}
    
	    function upgradeDB() 
	    {
			// Add DB Tables
			$this->createDB();
			
			// Add administrator capabilities
			$adminRole = get_role('administrator');
			if ( !empty($adminRole) ) 
			{
				$adminRole->add_cap(WPCARDZNET_CAPABILITY_PLAYER);				
				$adminRole->add_cap(WPCARDZNET_CAPABILITY_MANAGER);				
				$adminRole->add_cap(WPCARDZNET_CAPABILITY_ADMINUSER);
				$adminRole->add_cap(WPCARDZNET_CAPABILITY_SETUPUSER);
				$adminRole->add_cap(WPCARDZNET_CAPABILITY_DEVUSER);				
			}
			
			// Add subscriber capabilities
			$rolesList = array('subscriber', 'contributor', 'author', 'editor');
			foreach ($rolesList as $role)
			{
				$playerRole = get_role($role);
				if ( !empty($playerRole) ) 
				{
					$playerRole->add_cap(WPCARDZNET_CAPABILITY_PLAYER);				
				}
			}
			
			// Create directory for Ticker File
			if (!is_dir(WPCARDZNET_UPLOADS_PATH))
			{
				mkdir(WPCARDZNET_UPLOADS_PATH, WPCARDZNETLIB_PHPFOLDER_PERMS, true);
			}
			
			$defaultTemplatesPath = WP_CONTENT_DIR . '/plugins/' . WPCARDZNET_FOLDER . '/templates';
			$uploadsTemplatesPath = WP_CONTENT_DIR . '/uploads/'.WPCARDZNET_FOLDER;
			
			// FUNCTIONALITY: DBase - On upgrade ... Copy sales templates to working folder
			// Copy release templates to plugin persistent templates and images folders
			if (!WPCardzNetLibUtilsClass::recurse_copy($defaultTemplatesPath, $uploadsTemplatesPath))
			{				
			}
			
			// Check for ticker update
			if ($this->IfColumnExists(WPCARDZNET_GAMES_TABLE, 'gameTickFilename'))
			{
				// Add the new style ticker files 
				$usersList = $this->GetUsersList();
				foreach ($usersList as $userEntry)
				{
					// Get the ticker count (if any) 
					$userId = $userEntry->id;
					$gameId = $this->GetLatestGame($userId);
					if ($gameId == 0) continue;
					
					$gameTicker = $this->GetTicker($gameId);
					if ($gameTicker == null) continue;
					
					$this->UpdateUserTickPage($userId, $gameTicker, $gameId);
				}
	
				// Delete any old ones - Get the files list
				$filesList = glob(WPCARDZNET_UPLOADS_PATH.'/tick_*.txt');
				foreach ($filesList as $filePath)
					WPCardzNetLibUtilsClass::DeleteFile($filePath);

				// Remove the redundant DB fields  
				$this->deleteColumn(WPCARDZNET_GAMES_TABLE, 'gameTickFilename');
			}
		}
		
		function getTablePrefix()
		{
			$dbPrefix = parent::getTablePrefix();
			$dbPrefix .= WPCARDZNET_TABLE_ROOT;
			
			return $dbPrefix;
		}
		
		function getTableNames($dbPrefix)
		{
			$DBTables = parent::getTableNames($dbPrefix);
			
			$DBTables->Settings = WPCARDZNET_SETTINGS_TABLE;
			
			return $DBTables;
		}

		function getTableDef($tableName)
		{
			$sql = parent::getTableDef($tableName);
			switch($tableName)
			{
				case WPCARDZNET_GROUPS_TABLE:		
					$sql .= '
						groupName TEXT,
						groupUserId INT,
					';
					break;
				
				case WPCARDZNET_INVITES_TABLE:		
					$sql .= '
						inviteGroupId INT,
						inviteDateTime DATETIME NOT NULL,
						inviteFirstName TEXT,
						inviteLastName TEXT,
						inviteEMail TEXT,
						inviteHash TEXT,
					';
					break;
				
				case WPCARDZNET_MEMBERS_TABLE:		
					$sql .= '
						groupId INT,
						memberUserId INT,
					';
					break;
			
				case WPCARDZNET_GAMES_TABLE:		
					$sql .= '
						gameName VARCHAR(32),
						gameStartDateTime DATETIME NOT NULL,
						gameEndDateTime DATETIME,
						gameStatus VARCHAR(20) DEFAULT "'.self::GAME_INPROGRESS.'",
						gameLoginId INT,
						gameNoOfPlayers INT,
						gameCardsPerPlayer INT DEFAULT 0,
						gameCardFace VARCHAR(30) DEFAULT "Standard",
						firstPlayerId INT DEFAULT 0,
						nextPlayerId INT DEFAULT 0,
						gameMeta TEXT DEFAULT "",
						gameTicker INT DEFAULT 1,
						gameReset BOOL DEFAULT FALSE,
					';
					break;
					
				case WPCARDZNET_PLAYERS_TABLE:
					$sql .= '
						gameId INT UNSIGNED NOT NULL,
						userId INT,
						playerName TEXT,
						playerColour TEXT DEFAULT "",
						score INT DEFAULT 0,
						lastScore INT DEFAULT 0,
						hideCardsOption VARCHAR(20) DEFAULT "'.WPCARDZNET_VISIBLE_NORMAL.'",
					';
					break;
				
				case WPCARDZNET_ROUNDS_TABLE:		
					$sql .= '
						gameId INT UNSIGNED NOT NULL,
						roundStartDateTime DATETIME NOT NULL,
						roundEndDateTime DATETIME NOT NULL,
						roundDeck TEXT DEFAULT "",
						roundNextCard INT DEFAULT 0,
						roundState TEXT DEFAULT "",
						roundMeta TEXT DEFAULT "",
					';
					break;
					
				case WPCARDZNET_HANDS_TABLE:		
					$sql .= '
						roundID INT UNSIGNED NOT NULL,
						playerId INT,
						noOfCards INT,
						cardsList TEXT,
						playedList TEXT DEFAULT "",
						handMeta TEXT DEFAULT "",
					';
					break;
					
				case WPCARDZNET_TRICKS_TABLE:		
					$sql .= '
						roundID INT UNSIGNED NOT NULL,
						playerId INT DEFAULT 0,
						playerOrder INT DEFAULT 0,
						cardsList TEXT,
						winnerId INT DEFAULT 0,
						complete BOOL DEFAULT FALSE,
						score INT DEFAULT 0,
					';
					break;
			}
							
			return $sql;
		}
		
		function LogAutoIncrements()
		{
			$clearTable = true;
			
			$this->ArchiveSQLLog();

			$this->GetAndSetAutoIncrement(WPCARDZNET_GAMES_TABLE, $clearTable);
			$this->GetAndSetAutoIncrement(WPCARDZNET_PLAYERS_TABLE, $clearTable);			
			$this->GetAndSetAutoIncrement(WPCARDZNET_ROUNDS_TABLE, $clearTable);
			$this->GetAndSetAutoIncrement(WPCARDZNET_HANDS_TABLE, $clearTable);
			$this->GetAndSetAutoIncrement(WPCARDZNET_TRICKS_TABLE, $clearTable);	
		}
		
		function AddGameResetToLog($gameId = 0)
		{
			$tableName = WPCARDZNET_GAMES_TABLE;
			$sql = "UPDATE $tableName SET gameReset=TRUE ";
			if ($sql != 0)
				$sql .= "WHERE wp_cardznet_games.gameId=$gameId";
			$sql .= " ;";
			$this->AddToLogSQL($sql);
		}
		
		//Returns an array of admin options
		function getOptions($childOptions = array(), $saveToDB = true) 
		{
			$ourOptions = array(
				'Unused_EndOfList' => ''
			);
				
			$ourOptions = array_merge($ourOptions, $childOptions);
			
			return parent::getOptions($ourOptions);
		}
		
		function setDefault($optionID, $optionValue, $optionClass = self::ADMIN_SETTING)
		{
			$currVal = $this->getOption($optionID, $optionClass);
			if ($currVal == '')
				$this->setOption($optionID, $optionValue, $optionClass);
				
			return ($currVal == '');
		}
		
		function clearAll()
		{
		}
		
		function createDB($dropTable = false)
		{
			$this->createDBTable(WPCARDZNET_GROUPS_TABLE, 'groupId', $dropTable);			
			$this->createDBTable(WPCARDZNET_INVITES_TABLE, 'inviteId', $dropTable);
			$this->createDBTable(WPCARDZNET_MEMBERS_TABLE, 'memberId', $dropTable);
			$this->createDBTable(WPCARDZNET_GAMES_TABLE, 'gameId', $dropTable);
			$this->createDBTable(WPCARDZNET_PLAYERS_TABLE, 'playerId', $dropTable);			
			$this->createDBTable(WPCARDZNET_ROUNDS_TABLE, 'roundId',  $dropTable);
			$this->createDBTable(WPCARDZNET_HANDS_TABLE, 'handId',  $dropTable);
			$this->createDBTable(WPCARDZNET_TRICKS_TABLE, 'trickId',  $dropTable);			
		}
		
		function GetAllSettingsList()
		{			
			$settingsList = parent::GetAllSettingsList();

			return $settingsList;
		}
		
	    function uninstall()
	    {
			$this->DeleteCapability(WPCARDZNET_CAPABILITY_ADMINUSER);
			$this->DeleteCapability(WPCARDZNET_CAPABILITY_SETUPUSER);
			
			parent::uninstall();
		}
			 
		function resetDB()
		{
			$this->createDB(true);
		}
				
		function GetOurUserId($atts)
		{
			$userId = 0;
			if (current_user_can(WPCARDZNETLIB_CAPABILITY_SYSADMIN))
			{
				if (isset($atts['login']))
				{
					$userDetails = get_user_by('login', $atts['login']);
					if ($userDetails === false) exit;
					$userId = $userDetails->data->ID;
				}
				else if (isset($atts['userId']))
				{
					$userId = $atts['userId'];
				}
			}

			if ($userId == 0)
			{
				$user = wp_get_current_user();
				$userId = $user->ID;				
			}
			
			return $userId;
		}
		
		function SetSeqMode($atts, $seqMode=true)
		{
			if (!isset($atts['mode'])) 
			{
				return "SetSeqMode - No mode in atts";
			}
			
			if ($atts['mode'] != 'seq')  
			{
				return "SetSeqMode - Mode is not seq";
			}
			
			if (!current_user_can(WPCARDZNETLIB_CAPABILITY_SYSADMIN))  
			{
				return "SetSeqMode - No sysadmin perm";
			}
			
			$this->isSeqMode = $seqMode;
			return 'SetSeqMode Set: '.($seqMode ? 'TRUE' : 'FALSE');
		}
		
		function GetHiddenInputTag($tagId, $tagValue)
		{
			return "<input type=hidden name=$tagId id=$tagId value='$tagValue'/>";
		}
		
		function AddToAJAXVars($varId, $value, $addToJSVars = false)
		{
			// Add to hidden values list (can be overwritten by another call)
			$this->ajaxVars[$varId] = $value;
			
			if ($addToJSVars)
			{
				$this->AddToJSGlobals($varId, $value);			
			}
			
			return $value;
		}
		
		function AddBoolToAJAXVars($varId, $value, $addToJSVars = false)
		{
			$boolvalue = $value ? 'true' : 'false';
			return $this->AddToAJAXVars($varId, $boolvalue, $addToJSVars);
		}
		
		function AJAXVarsTags()
		{
			$html = '';
			foreach ($this->ajaxVars as $ajaxVarId => $ajaxVarVal)
			{
				if (!is_array($ajaxVarVal))
				{
					$html .= $this->AJAXVarTag($ajaxVarId, $ajaxVarVal);
					continue;
				}

				foreach ($ajaxVarVal as $ajaxArrayId => $ajaxArrayVal)
				{
					$html .= $this->AJAXVarTag($ajaxVarId.'-'.$ajaxArrayId, $ajaxArrayVal);
				}
			}
			return $html;
		}
		
		function AJAXVarTag($ajaxVarId, $ajaxVarVal)
		{
			return $this->GetHiddenInputTag('AJAX-'.$ajaxVarId, $ajaxVarVal)."\n";
		}
		
		function AddToJSGlobals($varId, $value)
		{
			// Add to globals list (can be overwritten by another call)
			$this->jsGlobals[$varId] = $value;
			
			return $value;
		}
		
		function JSGlobals()
		{			
			$jsCode = "<script>\n";
			if (count($this->jsGlobals) == 0) $jsCode .= "// No JSGlobals \n";
			foreach ($this->jsGlobals as $varId => $value)
			{
				$jsCode .= "var $varId = $value;\n";
			}
			$jsCode .= "</script>\n";
			
			return $jsCode;
		}
		
		function LockTables($tablesList)
		{
			if (!is_array($tablesList))
				$tablesList = array($tablesList);
				
			$sql = '';
			foreach ($tablesList as $tableName)				
			{
				if ($sql != '') $sql .= ", ";
				$sql .= "$tableName WRITE";
			}
			
			$sql = 'LOCK TABLES '.$sql;
			$this->query($sql);
		}
		
		function UnLockTables()
		{
			$sql  = 'UNLOCK TABLES';
			$this->query($sql);
		}
		
		function GetGroupById($groupId = 0)
		{
			$sql  = 'SELECT * FROM '.WPCARDZNET_GROUPS_TABLE.' ';
//			$sql .= 'LEFT JOIN '.WPCARDZNET_MEMBERS_TABLE.' ON '.WPCARDZNET_MEMBERS_TABLE.'.groupId='.WPCARDZNET_GROUPS_TABLE.'.groupId ';
			if ($groupId != 0)
				$sql .= 'WHERE '.WPCARDZNET_GROUPS_TABLE.'.groupId='.$groupId.' ';
			
			$results = $this->get_results($sql);
			
			return $results;
		}
		
		function GetGroups($groupUserId = 0, $sqlFilters = array())
		{
			// Get the list of groups
			$sql  = 'SELECT '.WPCARDZNET_GROUPS_TABLE.'.*, COUNT(memberUserId) as noOfMembers FROM '.WPCARDZNET_GROUPS_TABLE.' ';
			$sql .= 'LEFT JOIN '.WPCARDZNET_MEMBERS_TABLE.' ON '.WPCARDZNET_MEMBERS_TABLE.'.groupId='.WPCARDZNET_GROUPS_TABLE.'.groupId ';
			if ($groupUserId != 0)
				$sql .= 'WHERE '.WPCARDZNET_GROUPS_TABLE.'.groupUserId='.$groupUserId.' ';
			$sql .= 'GROUP BY '.WPCARDZNET_GROUPS_TABLE.'.groupId ';
			if (isset($sqlFilters['sqlLimit']))
				$sql .= $sqlFilters['sqlLimit'];
			
			$results = $this->get_results($sql);
			
			return $results;
		}

		function GroupExists($groupName)
		{
			$sql  = 'SELECT * FROM '.WPCARDZNET_GROUPS_TABLE.' ';
			$sql .= 'WHERE '.WPCARDZNET_GROUPS_TABLE.'.groupName="'.$groupName.'" ';
			
			$results = $this->get_results($sql);
			
			return (count($results) > 0);
		}

		function GetMembersById($groupId = 0, $userId = 0)
		{
			$sql  = 'SELECT * FROM '.WPCARDZNET_GROUPS_TABLE.' ';
			$sql .= ' JOIN '.WPCARDZNET_MEMBERS_TABLE.' ON '.WPCARDZNET_MEMBERS_TABLE.'.groupId='.WPCARDZNET_GROUPS_TABLE.'.groupId ';
			if ($groupId != 0)
			{
				$sql .= 'WHERE '.WPCARDZNET_GROUPS_TABLE.'.groupId='.$groupId.' ';
				if ($userId != 0)
					$sql .= 'AND '.WPCARDZNET_MEMBERS_TABLE.'.memberUserId='.$userId.' ';
			}
			
			$results = $this->get_results($sql);
			
			return $results;
		}
		
		function PurgeInvitations()
		{
			$sql  = 'DELETE FROM '.WPCARDZNET_INVITES_TABLE.' ';
			$sql .= 'WHERE '.WPCARDZNET_INVITES_TABLE.'.inviteDateTime < %s ';
					
			$inviteLimitHours = $this->getOption('inviteTimeLimit');

			$limitDateTime = date(self::MYSQL_DATETIME_FORMAT, WPCardzNetLibMigratePHPClass::Safe_strtotime("-$inviteLimitHours hours"));
			$results = $this->queryWithPrepare($sql, array($limitDateTime));
			
			return $results;
		}
		
		function GetInvitationByAuth($auth)
		{
			$this->PurgeInvitations();
			
			$sql  = 'SELECT * FROM '.WPCARDZNET_INVITES_TABLE.' ';
			$sql .= 'LEFT JOIN '.WPCARDZNET_GROUPS_TABLE.' ON '.WPCARDZNET_GROUPS_TABLE.'.groupId='.WPCARDZNET_INVITES_TABLE.'.inviteGroupId ';
			$sql .= 'WHERE '.WPCARDZNET_INVITES_TABLE.'.inviteHash=%s ';
			
			$results = $this->getresultsWithPrepare($sql, array($auth));
			
			return $results;
		}
		
		function DeleteInvitationByAuth($auth)
		{
			$this->PurgeInvitations();
			
			$sql  = 'DELETE FROM '.WPCARDZNET_INVITES_TABLE.' ';
			$sql .= 'WHERE '.WPCARDZNET_INVITES_TABLE.'.inviteHash=%s ';
			
			$results = $this->queryWithPrepare($sql, array($auth));
			
			return $results;
		}
		
		function AddMemberToGroup($groupId, $memberId)
		{		
			$sql  = 'INSERT INTO '.WPCARDZNET_MEMBERS_TABLE.'(groupId,	memberUserId) ';
			$sql .= "VALUES({$groupId}, {$memberId})";
			$this->query($sql);	
			
     		return $groupId;
		}
		
		function GetGameDef($atts)
		{			
			$this->userId = $this->GetOurUserId($atts);
			return $this->LoadActiveGame();
		}

		function LoadActiveGame()
		{						
			$results = $this->GetActiveGame($this->userId);
			if ($results === null) return null;

			$def = $this->GameNameToFileAndClass($this->currGameRec->gameName);	
					
			$def->gameCardFace = $this->currGameRec->gameCardFace;

			$this->currGameRec->className = $def->className;
			$this->currGameRec->srcfile = $def->srcfile;

			return $def;
		}
/*		
		function GetGameCardFace()
		{
			return $this->currGameRec->gameCardFace;
		}
*/		
		function GetNoOfCardsDealt()
		{
			return ($this->currGameRec->gameNoOfPlayers * $this->currGameRec->gameCardsPerPlayer);
		}
		
		function GetLatestGame($userId)
		{			
			$sql  = 'SELECT * ';
			$sql .= 'FROM '.WPCARDZNET_PLAYERS_TABLE.' ';
			$sql .= 'WHERE userId='.$userId.' ';
			$sql .= 'ORDER BY gameId DESC ';
			$sql .= "LIMIT 1 ";
			$results = $this->get_results($sql);
			if (count($results) == 0) return 0;
			
			return $results[0]->gameId;
		}
		
		function GetActiveGame($userId = 0)
		{			
			$this->currGameRec = null;
			$this->currRoundRec = null;

			$selectSql  = 'SELECT MAX(gs.gameId) FROM '.WPCARDZNET_GAMES_TABLE.' AS gs ';
			if ($userId > 0)
			{
				$selectSql .= 'LEFT JOIN '.WPCARDZNET_PLAYERS_TABLE.' AS ps ON ps.gameId=gs.gameId ';
				$selectSql .= "WHERE userId={$userId}";
			}
			
			$sql  = 'SELECT g.* ';
			$sql .= 'FROM '.WPCARDZNET_GAMES_TABLE.' AS g ';
			$sql .= 'WHERE g.gameTicker > 0 ';
			$sql .= "AND g.gameId = ( $selectSql ) ";
			
			$results = $this->get_results($sql);
			if (count($results) == 0) return null;
			
			$this->currGameRec = $results[0];
			$this->gameId = $this->currGameRec->gameId;
			$this->gameMeta = $this->currGameRec->gameMeta;
			
			$selectSql  = 'SELECT MAX(rs.roundId) FROM '.WPCARDZNET_ROUNDS_TABLE.' AS rs ';
			$selectSql .= 'WHERE rs.gameId='.$this->gameId.' ';
			
			// Get entry for the current round
			$sql  = 'SELECT * FROM '.WPCARDZNET_ROUNDS_TABLE.' AS r ';
			$sql .= "WHERE r.roundId = ( $selectSql ) ";
			
			$results = $this->get_results($sql);	
			if (count($results) == 1)
			{
				$this->currRoundRec = $results[0];
			}

			return $this->currGameRec;
		}
		
		function GetGameByUser($userId, $atts)
		{			
			$this->SetSeqMode($atts);
				
			$this->OutputDebugMessage("DB Initialise - userId: $userId <br>\n");
			
			$this->userId = $userId;
			$this->atts = $atts;
			if (isset($atts['login']))
				$this->AddToAJAXVars('attslogin', $atts['login']);

			$this->currGameRec->activePlayerId = $this->currGameRec->nextPlayerId;
			
			$this->currRoundRec = apply_filters('wpcardznet_filter_ActiveRound', $this->currRoundRec);
			$this->currGameRec  = apply_filters('wpcardznet_filter_ActiveGame',  $this->currGameRec);
						
			$this->roundId = $this->currRoundRec->roundId;
			//$this->roundState = $this->currRoundRec->roundState;
			$this->firstPlayerId = $this->currGameRec->firstPlayerId;
			$this->nextPlayerId = $this->currGameRec->nextPlayerId;
			$this->activePlayerId = $this->currGameRec->activePlayerId;
			
			$this->gameId = $this->AddToAJAXVars('gameId', $this->currGameRec->gameId);
			$this->gameTicker = $this->currGameRec->gameTicker;

			$this->AddToAJAXVars('gameName', $this->currGameRec->gameName);

			$this->cardsPerPlayer = $this->currGameRec->gameCardsPerPlayer;
			
			$gameMeta = '';
			$this->gameMeta = $this->currGameRec->gameMeta;
			if ($this->gameMeta != '')
			{
				$this->OutputDebugMessage(WPCardzNetLibUtilsClass::print_r($this->currGameRec, '$this->currGameRec', true)." <br>\n");													
				$this->gameMetaArr = unserialize($this->currGameRec->gameMeta);	
			}			

			$this->AddBoolToAJAXVars('isSeqMode', $this->isSeqMode);	// AddToAJAXVars
			
			return $this->currGameRec->gameId;
		}
			
		function AddTickerTag()
		{
			$this->AddToAJAXVars('gameTicker', $this->currGameRec->gameTicker);
		}
		
		function GetCurrPlayerIndex($playerId)
		{
			foreach ($this->currPlayersRec as $playerIndex => $playersRec)	
			{
				if ($playersRec->playerId == $playerId)
				{					
					return $playerIndex;
				}
			}	
				
			return null;
		}
		
		function UpdateCache($cacheId, $newVals, $recId = 0)
		{
			$newVals['cacheUpdated'] = date(WPCardzNetLibDBaseClass::MYSQL_DATETIME_FORMAT);
			switch ($cacheId)
			{
				case 'game':
					if ($this->currGameRec == null) return;
					foreach ($newVals as $fieldid => $fieldVal) 
						$this->currGameRec->$fieldid = $fieldVal;
					break;
					
				case 'round':
					if ($this->currRoundRec == null) return;
					foreach ($newVals as $fieldid => $fieldVal) 
						$this->currRoundRec->$fieldid = $fieldVal;						
					break;
					
				case 'hand':
					if ($this->currPlayersRec == null) return;
					$i = $this->GetCurrPlayerIndex($recId);
					if ($i !== null)
					{
						foreach ($newVals as $fieldid => $fieldVal) 
							$this->currPlayersRec[$i]->$fieldid = $fieldVal;
					}
					break;
					
				default:
					break;					
			}
		}
		
		function CardsVisibleOptions($index, $currSetting)
		{
			if (!current_user_can(WPCARDZNETLIB_CAPABILITY_DEVUSER)) return '';

			$options = array(
				WPCARDZNET_VISIBLE_NORMAL => __('Normal', 'wpcardznet'), 
				WPCARDZNET_VISIBLE_ALWAYS => __('Always Visible', 'wpcardznet'), 
				WPCARDZNET_VISIBLE_NEVER  => __('Never Visible ', 'wpcardznet'), 
				);

			$html  = "<select name=visibility$index>\n";
			foreach ($options as $optionValue => $optionText)
			{
				$selected = ($optionValue === $currSetting) ? ' selected="" ' : '';
				$html .= "<option value=\"$optionValue\" $selected>$optionText</option>\n";
			}
			$html .= "</select>\n";
			
			return $html;
		}
		
		function GetUsersList()
		{
			$sql  = 'SELECT DISTINCT(userId) as id, user_login FROM '.WPCARDZNET_PLAYERS_TABLE.' ';
			$sql .= 'JOIN wp_users ON '.WPCARDZNET_PLAYERS_TABLE.'.userId=wp_users.ID ';;
			$sql .= 'GROUP BY id ';
			$results = $this->get_results($sql);
			return $results;
		}

		function GetPlayers($gameId)
		{			
			$sql  = 'SELECT * FROM '.WPCARDZNET_PLAYERS_TABLE.' ';;			
			$sql .= "WHERE gameId = $gameId ";
			
			$results = $this->get_results($sql);
			
			return $results;
		}

		function GetPlayersList()
		{			
			$this->currPlayersRec = array();
			
			$roundId = $this->currRoundRec->roundId;
			
			// Get the players list for this round in the order of play
			$sql  = 'SELECT * FROM '.WPCARDZNET_HANDS_TABLE.' AS h ';
			$sql .= 'LEFT JOIN '.WPCARDZNET_PLAYERS_TABLE.' AS p ON p.playerId=h.playerId ';;			
			$sql .= "WHERE h.roundId = $roundId ";
			$sql .= 'ORDER BY p.playerId ';
			
/*			
			$sql  = 'SELECT * FROM '.WPCARDZNET_PLAYERS_TABLE.' ';
			$sql .= 'JOIN '.WPCARDZNET_GAMES_TABLE.' ON '.WPCARDZNET_GAMES_TABLE.'.gameId='.WPCARDZNET_PLAYERS_TABLE.'.gameId ';;
			$sql .= 'WHERE '.WPCARDZNET_GAMES_TABLE.'.gameId='.$this->gameId.' ';
			$sql .= 'ORDER BY '.WPCARDZNET_PLAYERS_TABLE.'.playerId ';
*/

			$results = $this->get_results($sql);
			$noOfPlayers = count($results);
			if ($noOfPlayers == 0) return null;
			
			$this->currPlayersRec = $results;

			$firstPlayerId = $this->currGameRec->firstPlayerId;
			
			foreach ($results as $index => $result)
			{
				$this->currPlayersRec[$index]->index = $index;
				$this->currPlayersRec[$index]->ready = true;
				$this->currPlayersRec[$index]->isActive = false;
				$this->currPlayersRec[$index]->isFirstPlayer = ($result->playerId == $firstPlayerId);
			}
			
			return $noOfPlayers;
		}
		
		function GetPlayersReadyStatus($readySql = 'true')
		{
			// NOTE: Could Allow different number of passed cards 
			$sql  = 'SELECT '.WPCARDZNET_PLAYERS_TABLE.'.*, ';
			$sql .= 'roundState, ';
			$sql  = 'SELECT *, ';
			$sql .= '(roundState = "'.self::ROUND_READY.'") AS roundReady, ';
			$sql .= '(noOfCards = gameCardsPerPlayer) AS fullDeck, ';
			$sql .= '((roundState = "'.self::ROUND_READY.'") || '.$readySql.') AS ready ';
			$sql .= 'FROM '.WPCARDZNET_PLAYERS_TABLE.' ';
			$sql .= 'JOIN '.WPCARDZNET_GAMES_TABLE.' ON '.WPCARDZNET_GAMES_TABLE.'.gameId='.WPCARDZNET_PLAYERS_TABLE.'.gameId ';;
			$sql .= 'LEFT JOIN '.WPCARDZNET_ROUNDS_TABLE.' ON '.WPCARDZNET_ROUNDS_TABLE.'.gameId='.WPCARDZNET_GAMES_TABLE.'.gameId ';;
			$sql .= 'LEFT JOIN '.WPCARDZNET_HANDS_TABLE.' ON '.WPCARDZNET_HANDS_TABLE.'.roundId='.WPCARDZNET_ROUNDS_TABLE.'.roundId ';	
			$sql .= 'AND '.WPCARDZNET_HANDS_TABLE.'.playerId='.WPCARDZNET_PLAYERS_TABLE.'.playerId ';
			$sql .= 'WHERE '.WPCARDZNET_ROUNDS_TABLE.'.roundId='.$this->roundId.' ';
			$sql .= 'ORDER BY '.WPCARDZNET_PLAYERS_TABLE.'.playerId ';
			
			$results = $this->get_results($sql);
			if (count($results) == 0) return null;
			
			return $results;
			
		}
		
		function GetNoOfPlayers()
		{
			return count($this->currPlayersRec);
		}
		
		function GetPlayerObject($playerId)
		{
			$index = $this->GetPlayerIndex($playerId);
			if ($index === null) return null;
			
			return $this->currPlayersRec[$index];
		}
		
		function GetPlayerIndex($playerId)
		{
			foreach ($this->currPlayersRec as $index => $player)
			{
				if ($player->playerId == $playerId)
				{
					return $index;
				}
			}

			$this->dbError('GetPlayerIndex', "No Match for Player $playerId", $this->currPlayersRec);
			return null;
		}

		function SetPlayerReady($playerId, $ready)
		{
			$index = $this->GetPlayerIndex($playerId);
			$player = $this->currPlayersRec[$index];
			$player->ready = $ready;
			$player->isActive = $ready;
		}

		function FindCurrentPlayer()
		{
			$userId = $this->userId;
			$matchingUsers = 0;		
			$nextPlayerIndex = $this->GetPlayerIndex($this->nextPlayerId);	
			if ($nextPlayerIndex === null) $this->dbError('FindCurrentPlayer', 'No Match for Next Player', null);
			
			$activePlayerIndex = $this->GetPlayerIndex($this->activePlayerId);	
			if ($activePlayerIndex === null) $this->dbError('FindCurrentPlayer', 'No Match for Active Player', null);

			// Find the first player that matches (in case there are none ready)
			foreach ($this->currPlayersRec as $index => $player)
			{
				if ($userId == $player->userId)
				{
					if (!$this->isSeqMode) $thisPlayerIndex = $index;
					$matchingUsers++;
				}
			}
			if ($matchingUsers == 0) $this->dbError('FindCurrentPlayer', 'No Matching Users', null);
			
			$noOfPlayers = count($this->currPlayersRec);
			
			// If the next player matches this players UserID then they are the current player	
			if ($this->isSeqMode)
			{
				$thisPlayerIndex = $nextPlayerIndex;
				for ($loop=0; $loop<$noOfPlayers; $loop++, $thisPlayerIndex++)
				{
					if ($thisPlayerIndex >= $noOfPlayers) $thisPlayerIndex = 0;
					
					$player = $this->currPlayersRec[$thisPlayerIndex];
					if ($player->ready) break;
				}
				$this->userId = $userId = $player->userId;
			}
			else
			{
				$this->playersPerUser = $matchingUsers;
				
				if ($this->playersPerUser > 1)
				{
					// Multiple players on the same screen ... find the next one to play
					$key = $nextPlayerIndex;
					
					$nextPlayer = $this->currPlayersRec[$nextPlayerIndex];
					$activePlayer = $this->currPlayersRec[$activePlayerIndex];
					if (($nextPlayer->userId == $userId) && ($nextPlayer->ready))
					{
						$thisPlayerIndex = $nextPlayerIndex;
					}
					else if (($activePlayer->userId == $userId) && ($activePlayer->ready))
					{
						$thisPlayerIndex = $activePlayerIndex;
					}
					else
					{
						for ($loop=0; $loop<$noOfPlayers; $loop++, $key++)
						{
							if ($key >= $noOfPlayers) $key = 0;
							
							$player = $this->currPlayersRec[$key];
							if (($player->userId == $userId) && ($player->ready))
							{
								$thisPlayerIndex = $key;
								break;
							}
						}
					}
					
				}
				
			}

			for ($index=0; $index<count($this->currPlayersRec); $index++)
				$this->currPlayersRec[$index]->isActive = false;
			$this->currPlayersRec[$nextPlayerIndex]->isActive = true;
			
			$this->thisPlayer = $this->currPlayersRec[$thisPlayerIndex];
			
			$this->AddToAJAXVars('thisPlayerName', $this->thisPlayer->playerName);
			
			return true;
		}
		
		function GetPlayerId()
		{	
			return $this->thisPlayer->playerId;
		}
		
		function GetPlayerScore()
		{	
			return $this->thisPlayer->score;
		}
		
		function IsNextPlayer()
		{	
			return ($this->nextPlayerId == $this->thisPlayer->playerId);		
		}
			
		function GetNextPlayerName()
		{
			$player = $this->GetPlayerObject($this->nextPlayerId);
			return $player->playerName;
		}
/*		
		function GetHandMeta($playerId = 0)
		{
			if ($playerId == 0)
				$playerId = $this->thisPlayer->playerId;
			$player = $this->GetPlayerObject($playerId);
			return $player->handMeta;
		}
*/		
		function IsPlayerReady()
		{	
			$player = $this->currPlayersRec[$this->thisPlayer->index];
			return ($player->ready);		
		}
			
		function GetFollowingPlayerName($offset = 1)
		{
			$noOfPlayers = count($this->currPlayersRec);
			while ($offset >= $noOfPlayers) $offset -= $noOfPlayers;
			
			$selIndex = $this->thisPlayer->index + $offset;
			if ($selIndex >= $noOfPlayers)
				$selIndex -= $noOfPlayers;
				
			$player = $this->currPlayersRec[$selIndex];
			return $player->playerName;
		}
		
		function CanShowCards()
		{
			switch ($this->thisPlayer->hideCardsOption)	
			{
				case WPCARDZNET_VISIBLE_ALWAYS:
					$canShow = true;
					$this->OutputDebugMessage("CanShowCards TRUE - hideCardsOption is ALWAYS <br>\n");
					break;
					
				case WPCARDZNET_VISIBLE_NEVER:
					$canShow = false;
					$this->OutputDebugMessage("CanShowCards FALSE - hideCardsOption is NEVER <br>\n");
					break;
					
				case WPCARDZNET_VISIBLE_NORMAL:
				default:
					if ($this->playersPerUser <= 1)
					{
						$canShow = true;
					}
					else if (WPCardzNetLibUtilsClass::GetHTTPInteger('post', 'playerId') != $this->thisPlayer->playerId)
					{
						$canShow = false;
					}
					else if (WPCardzNetLibUtilsClass::IsElementSet('post', 'cardsVisible'))
					{
						$canShow = true;
					}
					else
					{
						$canShow = false;
					}
					break;
			}
					
			return $canShow;
		}
			
		function GetCardFacesList()
		{
			if (isset($this->cardFacesList))
				return $this->cardFacesList;
				
			$this->cardFacesList = array();
			$cardFaceFilePath = WPCARDZNET_CARDS_PATH.'*';
			$cardFacePaths = glob( $cardFaceFilePath );
			foreach ($cardFacePaths as $cardFacePath)
			{
				$this->cardFacesList[] = basename($cardFacePath);
			}

			return $this->cardFacesList;
		}
			
		function GetCardFaceSelector($lastCardFace = '')
		{
			if ($lastCardFace == '')
				$lastCardFace = 'Standard';
				
			$cardFaces = $this->GetCardFacesList();

			$html  = "<select id=gameCardFace name=gameCardFace>\n";
			foreach ($cardFaces as $cardFace)
			{
				$selected = ($cardFace == $lastCardFace) ? ' selected=""' : '';
				$html .= '<option value="'.$cardFace.'"'.$selected.'>'.$cardFace.'&nbsp;&nbsp;</option>'."\n";
			}
			$html .= "</select>\n";
			return $html;
		}
		
		function GetGames($userId = 0, $sqlFilters = array())
		{			
			$sql  = 'SELECT * FROM '.WPCARDZNET_GAMES_TABLE.' ';
			if ($userId != 0)
				$sql .= 'WHERE '.WPCARDZNET_GAMES_TABLE.'.gameLoginId='.$userId.' ';
			$sql .= 'ORDER BY '.WPCARDZNET_GAMES_TABLE.'.gameStartDateTime DESC ';

			if (isset($sqlFilters['sqlLimit']))
				$sql .= $sqlFilters['sqlLimit'];

			$results = $this->get_results($sql);
			return $results;
		}
				
		function GetGameById($gameId)
		{			
			$sql  = 'SELECT * FROM '.WPCARDZNET_GAMES_TABLE.' ';
			$sql .= 'JOIN '.WPCARDZNET_PLAYERS_TABLE.' ON '.WPCARDZNET_GAMES_TABLE.'.gameId='.WPCARDZNET_PLAYERS_TABLE.'.gameId ';
			$sql .= 'WHERE '.WPCARDZNET_GAMES_TABLE.'.gameId='.$gameId.' ';
			$results = $this->get_results($sql);
			return $results;
		}
			
		function GetGameStatus($gameId)
		{
			$sql  = 'SELECT gameStatus FROM '.WPCARDZNET_GAMES_TABLE.' ';
			$sql .= 'WHERE '.WPCARDZNET_GAMES_TABLE.'.gameId='.$gameId.' ';
			$results = $this->get_results($sql);
			
			if (count($results) == 0) return '';
			
			return $results[0]->gameStatus;
		}
		
		function AddGame($gameName, $gamePlayersList, $gameCardsPerPlayer, $gameCardFace='', $gameMeta='')
		{			
			$this->gameId = 0;
			
			$gameFirstPlayerIndex = 0;
			
			$gameStartDateTime = date(WPCardzNetLibDBaseClass::MYSQL_DATETIME_FORMAT);
			$gameNoOfPlayers = count($gamePlayersList);

     		$userIdsList = array();
     		foreach ($gamePlayersList as $index => $playerEntry)
			{
				if (isset($playerEntry['login']))
				{
					$userDetails = get_user_by('login', $playerEntry['login']);
					$userId = $userDetails->data->ID;
					$gamePlayersList[$index]['id'] = $userId;
				}
				else
					$userId = $playerEntry['id'];
				
				if (!isset($playerEntry['name']))
					$playerEntry['name'] = self::GetUserName($userId);
					
				$userIdsList[$userId] = $userId;
			}
						
			$gamesEnded = $this->EndGameByUsers($userIdsList);
			if ($gamesEnded < 0) return false;
			
			$this->cardsPerPlayer = $gameCardsPerPlayer;
			$this->gameMetaArr = $gameMeta;
			
			if ($gameMeta != '')
			{
				$this->gameMetaArr = $gameMeta;
				$this->gameMeta = $gameMeta = serialize($gameMeta);
			}
			else
			{
				$this->gameMetaArr = array();
				$this->gameMeta = $gameMeta;
			}			
			
			$user = wp_get_current_user();
			$gameLoginId = $user->ID;
						
			$this->LockTables(array(WPCARDZNET_GAMES_TABLE, WPCARDZNET_PLAYERS_TABLE));
			
			$gameTicker = $this->gameTicker = 1;
			
			$sqlFields  = 'gameName';
			$sqlFields .= ', gameStartDateTime';
			$sqlFields .= ', gameLoginId';
			$sqlFields .= ', gameNoOfPlayers';
			$sqlFields .= ', gameCardsPerPlayer';
			$sqlFields .= ', gameTicker';
			$sqlFields .= ', gameMeta';
			
			$sqlData  = '"'.$gameName.'"';
			$sqlData .= ', "'.$gameStartDateTime.'"';
			$sqlData .= ', "'.$gameLoginId.'"';
			$sqlData .= ', "'.$gameNoOfPlayers.'"';
			$sqlData .= ', "'.$gameCardsPerPlayer.'"';
			$sqlData .= ', "'.$gameTicker.'"';
			$sqlData .= ', "'.$gameMeta.'"';
			
			if ($gameCardFace != '')
			{
				$sqlFields .= ', gameCardFace';
				$sqlData .= ', "'.$gameCardFace.'"';
			}
			
			$sql  = 'INSERT INTO '.WPCARDZNET_GAMES_TABLE."($sqlFields) ";
			$sql .= "VALUES($sqlData)";
			$this->query($sql);	
     		$gameId = $this->GetInsertId();
/*					
			$rand = WPCardzNetLibMigratePHPClass::Safe_str_pad(rand(0, pow(10, 4)-1), 4, '0', STR_PAD_LEFT);
			$gameTickFilename = "tick_{$gameId}_{$rand}.txt";
			
			$sql  = 'UPDATE '.WPCARDZNET_GAMES_TABLE.' ';
			$sql .= 'SET gameTicker="'.$gameTicker.'" ';

			$sql .= ', gameTickFilename="'.$gameTickFilename.'" ';

			if ($gameCardFace != '')
				$sql .= ', gameCardFace="'.$gameCardFace.'" ';
			$sql .= 'WHERE '.WPCARDZNET_GAMES_TABLE.'.gameId='.$gameId.' ';
			$this->query($sql);	
*/			     		
     		foreach ($userIdsList as $userId)
			{
				$this->UpdateUserTickPage($userId, $gameTicker, $gameId);
			}
			
     		$this->currPlayersRec = array();
     		
     		foreach ($gamePlayersList as $index => $playerEntry)
			{
				$userId = $playerEntry['id'];
				if (isset($playerEntry['name']))
					$name = $playerEntry['name'];
				else
					$name = self::GetUserName($userId);

				$vis = WPCARDZNET_VISIBLE_NORMAL;
				if (isset($playerEntry['visibility']))
				{
					$vis = $playerEntry['visibility'];
				}
				
				$sql  = 'INSERT INTO '.WPCARDZNET_PLAYERS_TABLE.'(gameId, userId, playerName, hideCardsOption) ';
				$sql .= "VALUES({$gameId}, {$userId}, \"{$name}\", \"{$vis}\")";
				$this->query($sql);	
				
				$playerId = $this->GetInsertId();
				
				if (isset($playerEntry['first']))
				{
					$gameFirstPlayerIndex = $playerId;							
				}
				
				$player = new stdClass();
				$player->index = count($this->currPlayersRec);
				$player->playerId = $playerId;
				$player->playerName = $name;
				$player->userId = $userId;
				$player->ready = true;
				$player->isActive = false;
				$player->hideCardsOption = WPCARDZNET_VISIBLE_NEVER;
				
				$this->currPlayersRec[] = $player;
			}
			
			if ($gameFirstPlayerIndex != 0)
			{
				$this->SetFirstPlayer($gameId, $gameFirstPlayerIndex);
			}
			
			$this->UnLockTables();
			
			$this->gameId = $gameId;
			return true;
		}
			
		function SetFirstPlayer($gameId, $playerId)
		{
			$sql  = 'UPDATE '.WPCARDZNET_GAMES_TABLE.' ';
			$sql .= 'SET firstPlayerId="'.$playerId.'"';
			$sql .= ', nextPlayerId="'.$playerId.'" ';
			$sql .= 'WHERE gameId='.$gameId.' ';
			
			$this->query($sql);	
		}
			
		function GetGameOptions()
		{
			if (!isset($this->gameMetaArr))
			{
				if ($this->gameMeta == '')
				{
					$this->gameMetaArr = array();
				}
				else
				{					
					$this->gameMetaArr = unserialize($this->gameMeta);
				}
			}
			
			$this->OutputDebugMessage(WPCardzNetLibUtilsClass::print_r($this->gameMetaArr, '$this->gameMetaArr', true)." <br>\n");													
			
			return $this->gameMetaArr;
		}
		
		function UpdateGameOptions($gameMeta)	// Calls UpdateCache 
		{
			$ser_gameMeta = serialize($gameMeta);
			
			$newVals = array('gameMeta' => $ser_gameMeta);

			$sql  = 'UPDATE '.WPCARDZNET_GAMES_TABLE.' ';
			$sql .= 'SET gameMeta=%s ';
			$sql .= 'WHERE '.WPCARDZNET_GAMES_TABLE.'.gameId=%d ';
			
			$results = $this->queryWithPrepare($sql, array($ser_gameMeta, $this->gameId));
			
			$this->UpdateCache('game', $newVals);

			return $results;
		}
			
		function GetLastGame($gameName)
		{
			$user = wp_get_current_user();
			
			$sql  = 'SELECT * FROM '.WPCARDZNET_GAMES_TABLE.' ';
			$sql .= 'WHERE gameLoginId='.$user->ID.' ';
			$sql .= 'AND gameName="'.$gameName.'" ';
			$sql .= 'ORDER BY gameId DESC ';
			$sql .= 'LIMIT 1 ';
			$results = $this->get_results($sql);
			if (count($results) == 0) return array();
			
			$gameId = $results[0]->gameId;
			
			$sql  = 'SELECT * FROM '.WPCARDZNET_PLAYERS_TABLE.' ';
			$sql .= 'JOIN '.WPCARDZNET_GAMES_TABLE.' ON '.WPCARDZNET_GAMES_TABLE.'.gameId='.WPCARDZNET_PLAYERS_TABLE.'.gameId ';;
			$sql .= 'WHERE '.WPCARDZNET_GAMES_TABLE.'.gameId='.$gameId.' ';
			$results = $this->get_results($sql);
			
			return $results;
		}
		
		function EndGameByUsers($userIds)
		{
			$activeGamesExits = ($this->getOption('ActiveUserAction') == WPCARDZNET_PLAYERACTIVE_FAIL);
			$gamesCount = 0;
			foreach ($userIds as $userId)
			{
				$gameRec = $this->GetActiveGame($userId);
				if ($gameRec !== null)
				{
					if ($activeGamesExits) return -1;
					
					$this->EndGame($gameRec->gameId);
					$gamesCount++;
				}
			}
			
			return ($gamesCount > 0);
		}
		
		function EndGame($gameId)
		{
			// End the game .... 
			$this->gameId = $gameId;
			
			$gameStatus = $this->GetGameStatus($gameId);
			if ($gameStatus != self::GAME_INPROGRESS)
				return $gameStatus;
				
			// Set the game end time
			$this->SetGameStatus(self::GAME_ENDED);
			
			// Set the game ticker to 0
			$this->SetTicker(0, $gameId);
			
			// NOTE: Could Delete any unfinished rounds ?
			// $noOfRounds = $this->GetNumberOfRounds();	
			
			return $gameStatus;	
		}
/*		
		function UpdateHideCardsOption($playerId, $hideCardsOption)
		{
			return $this->UpdateTable(WPCARDZNET_PLAYERS_TABLE, 'hideCardsOption', $hideCardsOption, 'playerId', $playerId);
		}

		function UpdateTable($tableId, $fieldId, $value, $whereId, $where)
		{
			if (true) $value = '"'.$value.'"';
			
			$sql  = "UPDATE $tableId ";
			$sql .= "SET $fieldId=$value ";
			$sql .= "WHERE $whereId=$where ";
			$this->query($sql);	
			
			return $this->roundId;
		}
*/
		function PurgeDB($alwaysRun = false)
		{
			if (!$alwaysRun && isset($this->PurgeDBDone)) return;
			
			$this->PurgeOrphans(array(WPCARDZNET_MEMBERS_TABLE.'.memberId', WPCARDZNET_GROUPS_TABLE.'.groupId'));
			$this->PurgeOrphans(array(WPCARDZNET_PLAYERS_TABLE.'.playerId', WPCARDZNET_GAMES_TABLE.'.gameId'));
			$this->PurgeOrphans(array(WPCARDZNET_ROUNDS_TABLE.'.roundId', WPCARDZNET_GAMES_TABLE.'.gameId'));
			$this->PurgeOrphans(array(WPCARDZNET_HANDS_TABLE.'.handId', WPCARDZNET_ROUNDS_TABLE.'.roundId'));
			$this->PurgeOrphans(array(WPCARDZNET_TRICKS_TABLE.'.trickId', WPCARDZNET_ROUNDS_TABLE.'.roundId'));
			
			$this->PurgeDBDone = true;
		}
		
		function PurgeOrphans($dbFields, $condition = '')
		{
			// Removes DB rows that have lost their parent in DB
			$masterCol = $dbFields[0];
			
			$dbFieldParts = explode('.', $masterCol);
			$masterTable = $dbFieldParts[0];
			$masterIndex = $dbFieldParts[1];
			
			$subCol = $dbFields[1];
			
			$dbFieldParts = explode('.', $subCol);
			$subTable = $dbFieldParts[0];
			$subIndex = $dbFieldParts[1];
			
			$sqlSelect  = 'SELECT '.$masterCol.' AS id ';
			$sql  = 'FROM '.$masterTable.' ';
			$sql .= 'LEFT JOIN '.$subTable.' ON '.$masterTable.'.'.$subIndex.'='.$subTable.'.'.$subIndex.' ';
			$sql .= 'WHERE '.$subTable.'.'.$subIndex.' IS NULL ';
			
			if ($condition != '')
			{
				$sql .= 'AND '.$condition.' ';
			}
	
			if ($this->isDbgOptionSet('Dev_ShowDBOutput'))
			{
				$this->OutputDebugStart();
				WPCardzNetLibEscapingClass::Safe_EchoHTML("<br>Run SELECT * just to see result of next query.\n");
				$this->get_results('SELECT * '.$sql);
				$this->OutputDebugEnd();
			}
			
			$sql = $sqlSelect.$sql;
			$idsList = $this->get_results($sql);
			if (count($idsList) == 0) return;
			
			$ids = '';
			foreach ($idsList AS $idEntry)
			{
				if ($ids != '') $ids .= ',';
				$ids .= $idEntry->id;
			}
			
			$sql  = 'DELETE FROM '.$masterTable.' ';
			$sql .= 'WHERE '.$masterIndex.' IN ( ';
			$sql .= $ids;
			$sql .= ') ';
			
			$this->query($sql);
		}
		
		function GetTicker($gameId)
		{
			// NOTE: Could Detect that the game has finished ....
			$sql  = 'SELECT gameTicker FROM '.WPCARDZNET_GAMES_TABLE.' ';
			$sql .= 'WHERE '.WPCARDZNET_GAMES_TABLE.'.gameId='.$gameId.' ';
			$results = $this->get_results($sql);
			if ($results == null) return null;
			
			return $results[0]->gameTicker;
		}
		
		function AddRound($roundState = self::ROUND_READY)	// Calls UpdateCache
		{
			$this->currTrick = null;
						
			$roundStartDateTime = date(WPCardzNetLibDBaseClass::MYSQL_DATETIME_FORMAT);

			$newVals = array('roundStartDateTime' => $roundStartDateTime, 'roundState' => $roundState);

			$sql  = 'INSERT INTO '.WPCARDZNET_ROUNDS_TABLE.'(gameId, roundStartDateTime, roundState) ';
			$sql .= 'VALUES("'.$this->gameId.'", "'.$roundStartDateTime.'", "'.$roundState.'")';
			$this->query($sql);	
					
			//$this->roundState = $roundState;

			$this->roundId = $this->GetInsertId();
			$newVals['roundId'] = $this->roundId;

			$this->UpdateCache('round', $newVals);

			return $this->roundId;
		}		
		
		function GetNumberOfRounds($completeRounds = false)
		{
			if (!isset($this->gameId))
				return 0;

			if ($this->gameId<=0)
				return 0;

			$gameId = $this->gameId;
			
			$sql  = 'SELECT COUNT(roundStartDateTime) AS noOfRounds FROM '.WPCARDZNET_GAMES_TABLE.' ';
			$sql .= 'JOIN '.WPCARDZNET_ROUNDS_TABLE.' ON '.WPCARDZNET_ROUNDS_TABLE.'.gameId='.WPCARDZNET_GAMES_TABLE.'.gameId ';
			$sql .= 'WHERE '.WPCARDZNET_GAMES_TABLE.'.gameId='.$gameId.' ';
			if ($completeRounds)
				$sql .= 'AND '.WPCARDZNET_ROUNDS_TABLE.'.roundState="'.self::ROUND_COMPLETE.'" ';
				
			$results = $this->get_results($sql);
			if ($results == null) return null;
			
			return $results[0]->noOfRounds;
		}
		
		function GetRoundState()
		{
			return $this->currRoundRec->roundState;
		}
		
		function GetRoundMeta()
		{
			$roundMeta = $this->currRoundRec->roundMeta;
			return ($roundMeta != '') ? unserialize($roundMeta) : array();
		}
		
		function UpdateRoundState($roundState, $gameComplete = false)	// Calls UpdateCache 
		{
			$sql  = 'UPDATE '.WPCARDZNET_ROUNDS_TABLE.' ';
			$sql .= 'SET roundState="'.$roundState.'" ';
			if ($roundState == self::ROUND_COMPLETE)
			{
				$roundEndDateTime = date(WPCardzNetLibDBaseClass::MYSQL_DATETIME_FORMAT);
				$sql .= ', roundEndDateTime="'.$roundEndDateTime.'" ';
			}
			$sql .= 'WHERE '.WPCARDZNET_ROUNDS_TABLE.'.roundId='.$this->roundId.' ';
			$this->query($sql);	
			
			//$this->roundState = $roundState;
			
			$newVals = array('roundState' => $roundState);
			
			$this->UpdateCache('round', $newVals);
		}

		function UpdateRoundOptions($roundMeta = null)	// Calls UpdateCache 
		{
			if ($roundMeta === null) 
				$roundMeta = array();
				
			$ser_roundMeta = serialize($roundMeta);

			$sql  = 'UPDATE '.WPCARDZNET_ROUNDS_TABLE.' ';
			$sql .= 'SET roundMeta=%s ';
			$sql .= 'WHERE '.WPCARDZNET_ROUNDS_TABLE.'.roundId=%d ';
			
			$results = $this->queryWithPrepare($sql, array($ser_roundMeta, $this->roundId));		
			
			$newVals = array('roundMeta' => $ser_roundMeta);
			
			$this->UpdateCache('round', $newVals);
		}
		
		function AddDeckToRound($cards)	// Calls UpdateCache 
		{
			$ser_cards = serialize($cards);
			
			$newVals = array('roundDeck' => $ser_cards);
			
			$sql  = 'UPDATE '.WPCARDZNET_ROUNDS_TABLE.' ';
			$sql .= 'SET roundDeck="'.$ser_cards.'" ';
			$sql .= 'WHERE '.WPCARDZNET_ROUNDS_TABLE.'.roundId='.$this->roundId.' ';
			$this->query($sql);	
			
			$this->UpdateCache('round', $newVals);
		}
		
		function SetNextCardInDeck($nextCard)	// Calls UpdateCache
		{
			$newVals = array('roundNextCard' => $nextCard);
			
			$sql  = 'UPDATE '.WPCARDZNET_ROUNDS_TABLE.' '; 
			$sql .= 'SET roundNextCard="'.$nextCard.'" ';
			$sql .= 'WHERE '.WPCARDZNET_ROUNDS_TABLE.'.roundId='.$this->roundId.' ';
			$this->query($sql);	
			
			$this->UpdateCache('round', $newVals);
		}
		
		function GetRounds($roundId=0, $limit=0)
		{			
			$sql  = 'SELECT * FROM '.WPCARDZNET_ROUNDS_TABLE.' ';
			$sql .= 'JOIN '.WPCARDZNET_GAMES_TABLE.' ON '.WPCARDZNET_GAMES_TABLE.'.gameId='.WPCARDZNET_ROUNDS_TABLE.'.gameId ';
			if ($roundId > 0)
				$sql .= 'WHERE '.WPCARDZNET_ROUNDS_TABLE.'.roundId='.$roundId.' ';
			$sql .= 'ORDER BY '.WPCARDZNET_GAMES_TABLE.'.gameId DESC,roundId DESC ';
			if ($limit > 0) $sql .= "LIMIT $limit ";

			$results = $this->get_results($sql);
			return $results;
		}
		
		function GetNextCardFromDeck()
		{			
			$sql  = 'SELECT roundDeck, roundNextCard FROM '.WPCARDZNET_ROUNDS_TABLE.' ';
			$sql .= 'WHERE '.WPCARDZNET_ROUNDS_TABLE.'.roundId='.$this->roundId.' ';
				
			$results = $this->get_results($sql);
			if ($results == null) return null;
			
			$cardIndex = $results[0]->roundNextCard;
			$deck = unserialize($results[0]->roundDeck);
			
			if ($cardIndex >= count($deck)) return null;
			
			$cardNo = $deck[$cardIndex];			
			$cardIndex++;
			$this->SetNextCardInDeck($cardIndex);
			
			return $cardNo;
		}

		function GetDeck($details)
		{
			$noOfPacks = $details->noOfPacks;
			$noOfJokers = isset($details->noOfJokers) ? $details->noOfJokers : 0; 
			$excludedCardNos = isset($details->excludedCardNos) ? $details->excludedCardNos : array(); 
			
			$cards = array();
			for ($cardNo=1; $cardNo<=52; $cardNo++)
			{
				if (!empty($excludedCardNos[$cardNo]))
				{
					continue;
				}
				
				for ($cardCount = 1; $cardCount <= $noOfPacks; $cardCount++)
				{
					$cards[] = $cardNo;
				}
			}
			
			$jokerCardNo = 53; // NOTE: Could Get using GetCardNo('joker-of-black');
			for ($jCount=0; $jCount<$noOfJokers; $jCount++)
			{
				$cards[] = $jokerCardNo;
			}
			
			shuffle($cards);
	
			if ($this->isDbgOptionSet('Dev_RerunGame'))
			{
				$prevroundId = WPCardzNetLibUtilsClass::GetHTTPInteger('post', 'prevroundId');
				if ($prevroundId != 0)
				{
					// Get the deck from an earlier round
					$lastRounds = $this->GetRounds($prevroundId);
					$cards = unserialize($lastRounds[0]->roundDeck);
				}
			}

			// Create new array to get indexes in order 
			$shuffledCards = array();
			foreach ($cards as $card)
			{
				$shuffledCards[] = $card;
			}
			

			$this->AddDeckToRound($shuffledCards);
			
			return $shuffledCards;
		}
		
		function AddDeckToHand($playerNo, $cards, &$cardIndex, $noOfCards)
		{			
			for ($i=1; $i<=$noOfCards; $i++)
			{
				$cardNo = $cards[$cardIndex];
				$cardsList[] = $cardNo;
				$cardIndex++;
			}
			
			sort($cardsList);
			
			$player = $this->currPlayersRec[$playerNo];
			$playerId = $player->playerId;

			$this->AddHand($playerId, $cardsList);
			
			return $playerId;
		}
		
		function AddHand($playerId, $cardsList)	// Calls UpdateCache
		{			
			$ser_cardsList = serialize($cardsList);
			$noOfCards = count($cardsList);
			
			$sql  = 'INSERT INTO '.WPCARDZNET_HANDS_TABLE.'(roundId, playerId, noOfCards, cardsList) ';
			$sql .= 'VALUES("'.$this->roundId.'", "'.$playerId.'", "'.$noOfCards.'", "'.$ser_cardsList.'")';
			$this->query($sql);	
								
     		$handId = $this->GetInsertId();
			
			$newVals = array('handId' => $handId, 'noOfCards' => $noOfCards, 'cardsList' => $ser_cardsList, 'cardsListArr' => $cardsList, 'playedList' => "a:0:{}");
			$this->UpdateCache('hand', $newVals, $playerId);
			
     		return $handId;
		}		
		
		function UpdateCurrentHand($cardsList, $playedList = null)
		{			
			$playerId = $this->thisPlayer->playerId;
			return $this->UpdateHand($playerId, $cardsList, $playedList);
		}		
		
		function UpdateHand($playerId, $cardsList, $playedList = null)	// Calls UpdateCache 
		{			
			sort($cardsList);
			
			$ser_cardsList = serialize($cardsList);
			$noOfCards = count($cardsList);
			
			$newVals = array('noOfCards' => $noOfCards, 'cardsList' => $ser_cardsList, 'cardsListArr' => $cardsList);
			
			$sql  = 'UPDATE '.WPCARDZNET_HANDS_TABLE.' ';
			$sql .= 'SET cardsList="'.$ser_cardsList.'" ';
			$sql .= ', noOfCards="'.$noOfCards.'" ';
			if ($playedList != null)
			{
				$ser_playedList = serialize($playedList);
				$newVals['playedList'] = $ser_playedList;
				$sql .= ', playedList="'.$ser_playedList.'" ';				
			}
			$sql .= 'WHERE '.WPCARDZNET_HANDS_TABLE.'.roundId='.$this->roundId.' ';
			$sql .= 'AND '.WPCARDZNET_HANDS_TABLE.'.playerId='.$playerId.' ';
			$this->query($sql);	
			
			$this->UpdateCache('hand', $newVals, $playerId);
					
     		return $this->GetInsertId();
		}		
		
		function AddCardToHand($cardNos)
		{			
			if (!is_array($cardNos))
				$cardNos = array($cardNos);

			$playerId = $this->thisPlayer->playerId;
			
			$sql  = 'SELECT cardsList FROM '.WPCARDZNET_HANDS_TABLE.' ';
			$sql .= 'WHERE '.WPCARDZNET_HANDS_TABLE.'.roundId='.$this->roundId.' ';
			$sql .= 'AND '.WPCARDZNET_HANDS_TABLE.'.playerId='.$playerId.' ';
			$results = $this->get_results($sql);
			if (count($results) != 1) return 0;
			
			$cardsList = unserialize($results[0]->cardsList);
			$cardsList = array_merge($cardsList, $cardNos);
			
			return $this->UpdateHand($playerId, $cardsList);
		}		
		
		function UpdateHandOptions($handMeta, $playerId = 0)	// Calls UpdateCache 
		{
			if ($playerId == 0)
				$playerId = $this->thisPlayer->playerId;
			$ser_handMeta = serialize($handMeta);
			
			$newVals = array('handMeta' => $ser_handMeta);
			
			$sql  = 'UPDATE '.WPCARDZNET_HANDS_TABLE.' ';
			$sql .= 'SET handMeta=%s ';
			$sql .= 'WHERE '.WPCARDZNET_HANDS_TABLE.'.playerId=%d ';
			
			$results = $this->queryWithPrepare($sql, array($ser_handMeta, $playerId));
			
			$this->UpdateCache('hand', $newVals, $playerId);
			
			return $results;
		}
			
		function GetAllHands()
		{
			$this->show_cache($this->currPlayersRec, 'currPlayersRec');
			return $this->currPlayersRec;
		}		
		
		function GetHand($playerId = 0)
		{
			$useCurrentPlayerId = ($playerId == 0);

			if ($useCurrentPlayerId)
				$playerId = $this->thisPlayer->playerId;
			
			$playersHand = $this->GetPlayersHand($playerId);
			if ($playersHand === null) $this->dbError('GetHand', '', $playersHand);
			
			$playersHand->cards = unserialize($playersHand->cardsList);
			$playersHand->played = unserialize($playersHand->playedList);
			$playersHand->handMetaDecode = ($playersHand->handMeta != '') ? unserialize($playersHand->handMeta) : array();
			$playersHand->roundMetaDecode = ($playersHand->roundMeta != '') ? unserialize($playersHand->roundMeta) : array();
			
			if ($useCurrentPlayerId)
				$this->noOfCards = $playersHand->noOfCards;
			
			return $playersHand;
		}		
		
		function GetPlayersHand($playerId)
		{
			$rtnval = array();
			foreach ($this->currPlayersRec as $playersRec)	
			{
				if ($playersRec->playerId == $playerId)
				{				
					// Add the cached rounds data to the record	
					foreach ($this->currRoundRec as $recName => $recValue)
						$playersRec->$recName = $recValue;
						
					$this->show_cache($playersRec, 'playersRec');
					return $playersRec;
				}
			}	
				
			$this->show_cache($rtnval, 'playerId not found');
			
			return $rtnval;
		}
		
		function RevertPassedCards($destPlayerOffset = 0, $clearPlayedCards = self::CLEAR_CARDS)
		{
			// Get "Played cards list
			$hands = $this->GetAllHands();
			
			// Add played cards to next player
			$noOfPlayers = count($hands);
			for ($src=0; $src<$noOfPlayers; $src++)
			{
				$cardsList[$src] = unserialize($hands[$src]->cardsList);
				$playedList[$src] = unserialize($hands[$src]->playedList);
			}			

			$cardsMoved = 0;
			for ($src=0; $src<$noOfPlayers; $src++)
			{
				if (!isset($playedList[$src][0])) continue;
				
				$dest = $src + $destPlayerOffset;
				if ($dest >= $noOfPlayers) $dest -= $noOfPlayers;
				
				// Copy the playedList to the destination
				foreach ($playedList[$src] as $cardNo)
				{
					$cardsList[$dest][] = $cardNo;
					$cardsMoved++;		
				}
			}
						
			// Re-sort Cards Lists, (optionally) Clear played cards list & write back to database
			for ($src=0; $src<$noOfPlayers; $src++)
			{
				if ($clearPlayedCards == self::CLEAR_CARDS) $playedList[$src] = array();

				$this->UpdateHand($hands[$src]->playerId, $cardsList[$src], $playedList[$src]);
			}
			
			return $cardsMoved;
		}
/*		
		function CardsPerPlayer()
		{
			return $this->cardsPerPlayer;
		}
*/		
		function GetNoOfCards($playerId = 0)
		{
			if ($playerId == 0)
				return $this->noOfCards;
				
			$playersHand=$this->GetHand($playerId);
			return $playersHand->noOfCards;
		}
		
		function GetUnplayedCardsCount()
		{
			$sql  = 'SELECT SUM(noOfCards) AS totalCards FROM '.WPCARDZNET_ROUNDS_TABLE.' ';
			$sql .= 'LEFT JOIN '.WPCARDZNET_HANDS_TABLE.' ON '.WPCARDZNET_HANDS_TABLE.'.roundId='.WPCARDZNET_ROUNDS_TABLE.'.roundId ';
			$sql .= 'WHERE '.WPCARDZNET_ROUNDS_TABLE.'.roundId='.$this->roundId.' ';
			$results = $this->get_results($sql);
			if (count($results) != 1) return 0;
			
			return $results[0]->totalCards;
		}
		
		function GetTricksCount()
		{
			$sql  = 'SELECT COUNT(roundId) AS noOfTricks FROM '.WPCARDZNET_TRICKS_TABLE.' ';
			$sql .= 'WHERE '.WPCARDZNET_TRICKS_TABLE.'.roundId='.$this->roundId.' ';
			$results = $this->get_results($sql);
			
			return $results[0]->noOfTricks;
		}
		
		function GetAllTricks($playerId = 0, $sqlOpts = array())
		{
			// playerId can be an integer(playerId) or string (playerColour)			
			$sql  = 'SELECT '.WPCARDZNET_TRICKS_TABLE.'.*,playerName, playerColour';
			if (isset($sqlOpts['Hands']))
				$sql .= ', playedList';
			$sql .= ' FROM '.WPCARDZNET_TRICKS_TABLE.' ';
			$sql .= 'LEFT JOIN '.WPCARDZNET_PLAYERS_TABLE.' ON '.WPCARDZNET_PLAYERS_TABLE.'.playerId='.WPCARDZNET_TRICKS_TABLE.'.playerId ';;
			if (isset($sqlOpts['Hands']))
				$sql .= 'LEFT JOIN '.WPCARDZNET_HANDS_TABLE.' ON '.WPCARDZNET_HANDS_TABLE.'.playerId='.WPCARDZNET_TRICKS_TABLE.'.playerId ';;
			$sql .= 'WHERE '.WPCARDZNET_TRICKS_TABLE.'.roundId='.$this->roundId.' ';
			if (isset($sqlOpts['Complete']))
				$sql .= 'AND NOT '.WPCARDZNET_TRICKS_TABLE.'.complete ';
			if (!is_numeric($playerId))
			{
				$playerColour = $playerId;
				$sql .= 'AND '.WPCARDZNET_PLAYERS_TABLE.'.playerColour="'.$playerColour.'" ';
			}
			else if ($playerId == 0)
			{
				$sql .= 'AND '.WPCARDZNET_PLAYERS_TABLE.'.playerId>0 ';
				$sql .= 'ORDER BY '.WPCARDZNET_TRICKS_TABLE.'.playerOrder ASC ';
				$sql .= ', '.WPCARDZNET_TRICKS_TABLE.'.trickId ASC ';
			}
			else
				$sql .= 'AND '.WPCARDZNET_PLAYERS_TABLE.'.playerId='.$playerId.' ';
			$results = $this->get_results($sql);
			
			return $results;
		}
		
		function GetCurrentTrick($playerId = 0)
		{
			$sql  = 'SELECT * FROM '.WPCARDZNET_TRICKS_TABLE.' ';
			$sql .= 'WHERE '.WPCARDZNET_TRICKS_TABLE.'.roundId='.$this->roundId.' ';
			$sql .= 'AND NOT '.WPCARDZNET_TRICKS_TABLE.'.complete ';
			if ($playerId != 0)
				$sql .= 'AND '.WPCARDZNET_TRICKS_TABLE.'.playerId='.$playerId.' ';
			$sql .= 'ORDER BY '.WPCARDZNET_TRICKS_TABLE.'.trickId DESC ';
			$sql .= 'LIMIT 1 ';
			$results = $this->get_results($sql);
			if (count($results) != 1) return null;
			
			$results[0]->cardsListArr = unserialize($results[0]->cardsList);
			return $results[0];
		}
		
		function GetLastTrick()
		{
			$sql  = 'SELECT * FROM '.WPCARDZNET_TRICKS_TABLE.' ';
			$sql .= 'WHERE '.WPCARDZNET_TRICKS_TABLE.'.roundId='.$this->roundId.' ';
			$sql .= 'AND '.WPCARDZNET_TRICKS_TABLE.'.complete ';
			$sql .= 'ORDER BY '.WPCARDZNET_TRICKS_TABLE.'.trickId DESC ';
			$sql .= 'LIMIT 1 ';
			
			$results = $this->get_results($sql);
			if (count($results) != 1) return null;
			
			$results[0]->cardsListArr = unserialize($results[0]->cardsList);
			
			$winnerId = $results[0]->winnerId;
			$winnerIndex = $this->GetPlayerIndex($winnerId);
			$results[0]->winnerName = $this->currPlayersRec[$winnerIndex]->playerName;

			return $results[0];
		}
		
		function GetLastTricks($playerId = 0)
		{
			$sql  = 'SELECT * FROM '.WPCARDZNET_TRICKS_TABLE.' ';
			$sql .= 'WHERE trickId = ';
			$sql .= '( ';
			$sql .= 'SELECT MAX(trickId) As tId from '.WPCARDZNET_TRICKS_TABLE.' ';
			$sql .= 'WHERE roundId='.$this->roundId.' ';
			if ($playerId != 0)
			{
				$sql .= 'AND '.WPCARDZNET_TRICKS_TABLE.'.playerId='.$playerId.' ';
			}
			else
			{
				$sql .= 'AND playerId > 0 ';
			}
			$sql .= ') ';

			$results = $this->get_results($sql);
			if (count($results) == 0) return null;
			
			$this->DecodeCardsList($results);
			
			return $results;
		}
		
		function DecodeCardsList(&$results)
		{
			foreach ($results as $index => $result)
			{
				$results[$index]->cardsListArr = unserialize($results[$index]->cardsList);
			}
		}
		
		function GetLastTeamTrick($playerColour)
		{
			$sql  = 'SELECT * FROM '.WPCARDZNET_TRICKS_TABLE.' ';
			$sql .= 'LEFT JOIN '.WPCARDZNET_PLAYERS_TABLE.' ON '.WPCARDZNET_PLAYERS_TABLE.'.playerId='.WPCARDZNET_TRICKS_TABLE.'.playerId ';;
			$sql .= 'WHERE '.WPCARDZNET_TRICKS_TABLE.'.roundId='.$this->roundId.' ';
			$sql .= 'AND '.WPCARDZNET_PLAYERS_TABLE.'.playerColour="'.$playerColour.'" ';
			$sql .= 'ORDER BY '.WPCARDZNET_TRICKS_TABLE.'.trickId DESC ';
			$sql .= 'LIMIT 1 ';
			
			$results = $this->get_results($sql);
			if (count($results) != 1) return null;
			
			$results[0]->cardsListArr = unserialize($results[0]->cardsList);
			
			return $results[0];
		}
		
		function NewTrick($cardNo = 0, $playerOrder = 0, $playerId = 0)
		{
			if ($playerId == 0)
			{
				// $cardNo can be an integer or an array 
				$playerId = $this->thisPlayer->playerId;
			}
			
			if (is_array($cardNo))
			{
				$ser_cardsList = serialize($cardNo);
			}
			else
			{
				$cardsList = array();
				if ($cardNo != 0) $cardsList[] = $cardNo;
				$ser_cardsList = serialize($cardsList);
			}
			
			$sql  = 'INSERT INTO '.WPCARDZNET_TRICKS_TABLE.'(roundID, playerId, playerOrder, cardsList) ';
			$sql .= "VALUES({$this->roundId}, {$playerId}, {$playerOrder}, \"{$ser_cardsList}\") ";
			$this->query($sql);
					
     		return $this->GetInsertId();
		}
		
		function AddToTrick($cardNo)
		{
			if ($this->currTrick == null) $this->dbError('AddToTrick', 'trickId unknown', '');
			
			$trickId = $this->currTrick->trickId;
			
			$this->currTrick->cardsListArr[] = $cardNo;
			
			$ser_cardsList = serialize($this->currTrick->cardsListArr);
			
			$sql  = 'UPDATE '.WPCARDZNET_TRICKS_TABLE.' ';
			$sql .= 'SET cardsList="'.$ser_cardsList.'" ';
			$sql .= 'WHERE '.WPCARDZNET_TRICKS_TABLE.'.trickId='.$trickId.' ';
			$this->query($sql);	
					
     		return $trickId;
		}
		
		function UpdateTrick($cardsList, $playerId)
		{
			$ser_cardsList = serialize($cardsList);
			
			$sql  = 'UPDATE '.WPCARDZNET_TRICKS_TABLE.' ';
			$sql .= 'SET cardsList="'.$ser_cardsList.'" ';
			$sql .= 'WHERE '.WPCARDZNET_TRICKS_TABLE.'.playerId='.$playerId.' ';
			$sql .= 'AND '.WPCARDZNET_TRICKS_TABLE.'.roundId='.$this->roundId.' ';
			
			$this->query($sql);	
		}
		
		function DeleteTrick($trickId)
		{
			$sql  = 'DELETE FROM '.WPCARDZNET_TRICKS_TABLE.' ';
			$sql .= 'WHERE '.WPCARDZNET_TRICKS_TABLE.'.trickId='.$trickId.' ';
			$rtnStatus = $this->query($sql);
			
			$this->currTrick = null;
			
			return $rtnStatus;		
		}
		
		function CompleteTrick($winnerPlayerId, $winnerScore)
		{
			if ($this->currTrick == null) $this->dbError('CompleteTrick', 'trickId unknown', '');
			
			$trickId = $this->currTrick->trickId;
			
			$sql  = 'UPDATE '.WPCARDZNET_TRICKS_TABLE.' ';
			$sql .= 'SET complete=TRUE ';
			$sql .= ', winnerId="'.$winnerPlayerId.'" ';
			$sql .= ', score="'.$winnerScore.'" ';
			$sql .= 'WHERE '.WPCARDZNET_TRICKS_TABLE.'.trickId='.$trickId.' ';
			$this->query($sql);	
					
			$this->currTrick = null;
			
     		return $this->GetInsertId();
		}
		
		function GetTrickCards($refresh = false, $playerId = 0)
		{
			if (($this->currTrick == null) || $refresh)
				$this->currTrick = $this->GetCurrentTrick($playerId);

			if ($this->currTrick == null)
				return null;
				
			return $this->currTrick->cardsListArr;
		}
/*		
		function GetPlayerDetails($playerId)
		{
			$sql  = 'SELECT * FROM '.WPCARDZNET_PLAYERS_TABLE.' ';
			$sql .= 'WHERE '.WPCARDZNET_PLAYERS_TABLE.'.playerId='.$playerId.' ';
			$results = $this->get_results($sql);
			if (count($results) != 1) $this->dbError('GetPlayerDetails', $sql, $results);

			return $results[0];
		}
*/		
		function SetPlayerColour($playerId, $playerColour)
		{			
			$sql  = 'UPDATE '.WPCARDZNET_PLAYERS_TABLE.' ';
			$sql .= 'SET playerColour="'.$playerColour.'" ';
			$sql .= 'WHERE '.WPCARDZNET_PLAYERS_TABLE.'.playerId='.$playerId.' ';
			$this->query($sql);	
		}
		
		function GetNextPlayer($playerId)
		{
			$sql  = 'SELECT * FROM '.WPCARDZNET_PLAYERS_TABLE.' ';
			$sql .= 'WHERE '.WPCARDZNET_PLAYERS_TABLE.'.playerId='.$playerId.' ';
			$results = $this->get_results($sql);
			if (count($results) != 1) $this->dbError('GetNextPlayer', $sql, $results);

			return $results[0];
		}
		
		function GetNextDealer()
		{
			$noOfRounds = $this->GetNumberOfRounds();
			$dealerId = $this->AdvancePlayer($noOfRounds-1, $this->firstPlayerId);
			return $dealerId;
		}
		
		function AdvancePlayer($numPlayers = 1, $lastPlayerId = 0)
		{
			if ($lastPlayerId == 0) $lastPlayerId = $this->nextPlayerId;
			
			// Get the players list for this round
			$index = $this->GetPlayerIndex($lastPlayerId);
			$index += $numPlayers;
			$noOfPlayers = count($this->currPlayersRec);
			while ($index > $noOfPlayers-1) $index -= $noOfPlayers;
			
			$player = $this->currPlayersRec[$index];
			$playerId = $player->playerId;

			return $player->playerId;
		}
		
		function UpdateNextPlayer($playerId)	// Calls UpdateCache 
		{
			$gameTicker = $this->gameTicker+1;
			
			$newVals = array('nextPlayerId' => $playerId, 'activePlayerId' => $playerId, 'gameTicker' => $gameTicker);

			$this->LockTables(WPCARDZNET_GAMES_TABLE);
			
			$sql  = 'UPDATE '.WPCARDZNET_GAMES_TABLE.' ';
			$sql .= 'SET nextPlayerId="'.$playerId.'" ';
			$sql .= ', gameTicker="'.$gameTicker.'" ';
			$sql .= 'WHERE '.WPCARDZNET_GAMES_TABLE.'.gameId='.$this->gameId.' ';
			$this->query($sql);	
			
			$this->nextPlayerId = $this->activePlayerId = $playerId;
			
			$nextPlayerIndex = $this->GetPlayerIndex($playerId);	
			$this->thisPlayer = $this->currPlayersRec[$nextPlayerIndex];
			
			if ($this->currGameRec != null)
				$this->currGameRec->nextPlayerId = $playerId;
				
			$this->UpdateTickPage($gameTicker);
			
			$this->UpdateCache('game', $newVals);

			$this->UnLockTables();
			
			return $playerId;
		}
		
		function SelectPlayerBeforeNext()
		{
			$nextPlayerIndex = $this->GetPlayerIndex($this->nextPlayerId);
			if (--$nextPlayerIndex < 0) $nextPlayerIndex = count($this->currPlayersRec)-1;
			$player = $this->currPlayersRec[$nextPlayerIndex];
			$this->nextPlayerId = $player->playerId;
			return $this->nextPlayerId;
		}

		function GetNextPlayerInTeam()
		{
			$noOfPlayers = count($this->currPlayersRec);
			$playerId = $this->thisPlayer->playerId;
			$nextPlayerIndex = $currPlayerIndex = $this->GetPlayerIndex($playerId);
			$currPlayerColour = $this->currPlayersRec[$currPlayerIndex]->playerColour;
			for ($i=1; $i<=$noOfPlayers; $i++)
			{
				$nextPlayerIndex++;
				if ($nextPlayerIndex >= $noOfPlayers) $nextPlayerIndex = 0;
				if ($this->currPlayersRec[$nextPlayerIndex]->playerColour == $currPlayerColour)
				{
					$partnerObject = $this->currPlayersRec[$nextPlayerIndex];
					$partnerId = $partnerObject->playerId;
					return $partnerId;
				}
			}
			
			return 0;
		}
		
		function IncrementTicker()
		{
			$gameTicker = $this->gameTicker+1;
			return $this->SetTicker($gameTicker);
		}

		function IsTickerEnabled()
		{
			return !$this->isDbgOptionSet('Dev_DisableTicker');
		}

		function SetTicker($gameTicker, $reqGameId = 0)	// Calls UpdateCache 
		{
			if ($this->isDbgOptionSet('Dev_DisableTicker')) return 0;
			
			if ($reqGameId == 0) 
			{
				$gameId = $this->gameId;
				$this->LockTables(WPCARDZNET_GAMES_TABLE);
			}
			else
			{
				$gameId = $reqGameId;
				$this->LockTables(array(WPCARDZNET_GAMES_TABLE, WPCARDZNET_PLAYERS_TABLE));
			}
								
			$newVals = array('gameTicker' => $gameTicker);

			$sql  = 'UPDATE '.WPCARDZNET_GAMES_TABLE.' ';
			$sql .= 'SET gameTicker="'.$gameTicker.'" ';
			$sql .= 'WHERE '.WPCARDZNET_GAMES_TABLE.'.gameId='.$this->gameId.' ';
			$this->query($sql);	
			
			// Update ticker file 
			$this->UpdateTickPage($gameTicker, $reqGameId);
			
			$this->UpdateCache('game', $newVals);

			$this->UnLockTables();
			
			$this->gameTicker = $gameTicker;
			
			return $gameTicker;
		}

		function GetUserTickFilename($userId)
		{
			return sprintf("ticku_%04d.txt", $userId);
		}
/*		
		function GetTickFilename($gameId = 0)
		{
			if ($gameId == 0)
				$gameId = $this->gameId;

			$sql  = 'SELECT gameTickFilename FROM '.WPCARDZNET_GAMES_TABLE.' ';
			$sql .= 'WHERE gameId='.$gameId.' ';
			$results = $this->get_results($sql);
			if (count($results) == 0) return '';
			
			return $results[0]->gameTickFilename;
		}
*/
		function UpdateTickPage($gameTicker, $reqGameId = 0)
		{
			$userIDsList = array();
			
			if ($reqGameId == 0) 
			{
				// Get players list for current game 
				$gameId = $this->gameId;
				foreach($this->currPlayersRec as $player)
				{
					$userIDsList[$player->userId] = true;
				}				
			}
			else
			{
				// Get players list for game requested
				$gameId = $reqGameId;
				$playersRec = $this->GetGameById($gameId);
				foreach($playersRec as $player)
				{
					$userIDsList[$player->userId] = true;
				}				
			}
									
			//$msg = "Ticker: $gameTicker --> $tickPagePath <br>\n";
			//$this->AddToStampedCommsLog($msg);

			foreach ($userIDsList as $userId => $unused)
			{
				$this->UpdateUserTickPage($userId, $gameTicker, $gameId);
			}
		}

		function UpdateUserTickPage($userId, $gameTicker, $gameId)
		{
			$tickerEntry = "$gameTicker $gameId";
			$tickPagePath = WPCARDZNET_UPLOADS_PATH.'/'.$this->GetUserTickFilename($userId);
			if ($tickPagePath != '')
			{
				file_put_contents($tickPagePath, $tickerEntry);				
			}
		}
				
		function SetGameStatus($gameStatus = '', $gameEndDateTime = '')	// Calls UpdateCache 
		{
			if (($gameStatus == self::GAME_COMPLETE) && ($gameEndDateTime == ''))
				$gameEndDateTime = date(WPCardzNetLibDBaseClass::MYSQL_DATETIME_FORMAT);
			
			$newVals = array('gameStatus' => $gameStatus);

			$sql  = 'UPDATE '.WPCARDZNET_GAMES_TABLE.' ';
			$sql .= 'SET gameStatus="'.$gameStatus.'" ';
			if ($gameEndDateTime != '')
			{
				$sql .= ', gameEndDateTime="'.$gameEndDateTime.'" ';
				$newVals['gameEndDateTime'] = $gameEndDateTime;
			}
			
			$sql .= 'WHERE '.WPCARDZNET_GAMES_TABLE.'.gameId='.$this->gameId.' ';
			$this->query($sql);	
			
			$this->UpdateCache('game', $newVals);
		}

		function GetPlayerName()
		{
			return $this->thisPlayer->playerName;
		}
			
		function GetPlayerColour()
		{
			return $this->thisPlayer->playerColour;
		}
			
		function GetPlayerIdHiddenTag()
		{
			return "<input type=hidden id=playerId name=playerId value=".$this->thisPlayer->playerId.">\n";
		}	
				
		function GetLastRoundScores()
		{
			return $this->GetScores($this->roundId);
		}
/*		
		function GetLastRoundScore($playerId = 0)
		{
			return $this->GetScores($this->roundId, $playerId);
		}
*/		
		function GetScores($roundId = 0, $playerId = 0)
		{
			// Get the players list for this round in the order of play
			$sql  = 'SELECT '.WPCARDZNET_PLAYERS_TABLE.'.*, winnerId, gameMeta, ';
			$sql .= 'COALESCE(SUM('.WPCARDZNET_TRICKS_TABLE.'.score),0) AS roundScore, ';
			$sql .= 'COUNT(playerName) AS tricksCount ';
			$sql .= 'FROM '.WPCARDZNET_PLAYERS_TABLE.' ';
			$sql .= 'JOIN '.WPCARDZNET_GAMES_TABLE.' ON '.WPCARDZNET_GAMES_TABLE.'.gameId='.WPCARDZNET_PLAYERS_TABLE.'.gameId ';;
			$sql .= 'LEFT JOIN '.WPCARDZNET_ROUNDS_TABLE.' ON '.WPCARDZNET_ROUNDS_TABLE.'.gameId='.WPCARDZNET_GAMES_TABLE.'.gameId ';;
			$sql .= 'LEFT JOIN '.WPCARDZNET_TRICKS_TABLE.' ON '.WPCARDZNET_TRICKS_TABLE.'.winnerId='.WPCARDZNET_PLAYERS_TABLE.'.playerId ';;
			$sql .= 'AND '.WPCARDZNET_TRICKS_TABLE.'.roundId='.WPCARDZNET_ROUNDS_TABLE.'.roundId ';;
			$sql .= 'WHERE '.WPCARDZNET_GAMES_TABLE.'.gameId='.$this->gameId.' ';
			if ($roundId > 0)
			{
				$sql .= 'AND '.WPCARDZNET_ROUNDS_TABLE.'.roundId='.$roundId.' ';
			}
			if ($playerId > 0)
			{
				$sql .= 'AND '.WPCARDZNET_PLAYERS_TABLE.'.playerId='.$playerId.' ';
			}
			
			$sql .= 'GROUP BY '.WPCARDZNET_PLAYERS_TABLE.'.playerId ';
			$sql .= 'ORDER BY playerId ASC';
			$results = $this->get_results($sql);
			if (count($results) == 0) return null;
						
			for ($index=0; $index<count($results); $index++)
			{
				$results[$index]->gameOpts = unserialize($results[$index]->gameMeta);
			}
			return $results;
		}
		
		function AddToScore($playerId, $newScore)
		{
			$sql  = 'UPDATE '.WPCARDZNET_PLAYERS_TABLE.' ';
			$sql .= 'SET lastScore='.$newScore.' ';
			$sql .= ', score=score+'.$newScore.' ';
			$sql .= 'WHERE '.WPCARDZNET_PLAYERS_TABLE.'.playerId='.$playerId.' ';
			$this->query($sql);	
		}
		
		function UpdateScore($playerId, $newScore)
		{
			$sql  = 'UPDATE '.WPCARDZNET_PLAYERS_TABLE.' ';
			$sql .= 'SET score="'.$newScore.'" ';
			$sql .= 'WHERE '.WPCARDZNET_PLAYERS_TABLE.'.playerId='.$playerId.' ';
			$this->query($sql);	
		}
		
		function SendEMailByTemplateID($eventRecord, $templateID, $folder, $EMailTo = '')
		{	
			$eventRecord[0]->organisation = get_bloginfo('name');
			
			$groupUserId = $eventRecord[0]->groupUserId;
			$eventRecord[0]->groupAdminName = $this->GetUserName($groupUserId);
			$eventRecord[0]->groupAdminEMail = $this->GetUserEMail($groupUserId);
						
			$eventRecord[0]->siteName = get_bloginfo('name');
			$eventRecord[0]->url = get_bloginfo('url');
			$eventRecord[0]->loginURL = get_bloginfo('url').'/wp-admin/';
		
			return parent::SendEMailByTemplateID($eventRecord, $templateID, $folder, $EMailTo);
		}
		
		static function GetUserEMail($userId)
		{
			$user = get_user_by('id', $userId);
			if (!isset($user->user_email)) return '';
			return $user->user_email;
		}
		
		static function GetUserName($userId)
		{
			$user = get_user_by('id', $userId);
			return self::GetUserNameFromObj($user);
		}
		
		static function GetUserNameFromObj($user)
		{
			if (isset($user->display_name) && (WPCardzNetLibMigratePHPClass::Safe_strlen($user->display_name) > 0))
				$userName = $user->display_name;
			else if (isset($user->user_login))
				$userName = $user->user_login;
			else
				$userName = 'Unknown User';
				
			return $userName;
		}
		
		function GetMemberSelector($no, $name = '')
		{
			$showNone = true;
			$selectorName = "userId$no";

			if (!WPCardzNetLibUtilsClass::IsElementSet('post', 'gameGroupId')) die("No gameGroupId");
			$groupId = WPCardzNetLibUtilsClass::GetHTTPInteger('post', 'gameGroupId');
			if (!current_user_can(WPCARDZNET_CAPABILITY_ADMINUSER))
			{
				if ($groupId == 0) die("Invalid gameGroupId");
			}
			
			$groupDef = $this->GetGroupById($groupId);
			if (count($groupDef) == 0) return '';
			$groupUserId = $groupDef[0]->groupUserId;
					
			$listFromDB = $this->GetMembersById($groupId);
			
			$userId = get_current_user_id();
			if (!current_user_can(WPCARDZNET_CAPABILITY_ADMINUSER))
			{
				if ($groupUserId != $userId) die("User does not match groupUserId");
			}

			$membersList = array();
			$membersList[$groupUserId] = $this->GetUserName($groupUserId);
			
			foreach ($listFromDB as $member)
			{
				$membersList[$member->memberUserId] = $this->GetUserName($member->memberUserId);		
			}
			asort($membersList);

			$rowSelected = false;
			$html = "<select id=$selectorName name=$selectorName>";
			foreach ($membersList as $memberId => $memberName)
			{
				$selected = ($name == $memberName) ? ' selected="selected" ' : '';
				$rowSelected |= ($selected != '');
				$html .= "<option value=$memberId $selected>$memberName</option> \n";
			}
			
			if ($showNone)
			{
				$noneText = __("None", 'wpcardznet');
				$noneSelected = $rowSelected ? '' : ' selected="selected" ';
				$noneSelect = "<option value=0 $noneSelected>($noneText)</option>";
				$html .= $noneSelect;
			}
			
			$html .= "</select>";
			
			return $html;
		}
		
		function GetGameSelector($gameName = '', $nocopyGameText = '')
		{
			$rounds = $this->GetRounds(0, 10);
			$roundsCount = 0;

			$html  = "<select id=prevroundId name=prevroundId>\n";
			
			if ($nocopyGameText != '')
				$html .= '<option value="0">'.$nocopyGameText.'&nbsp;&nbsp;</option>'."\n";
			
			foreach ($rounds as $round)
			{
				if (($gameName != '') && ($round->gameName != $gameName)) continue;
				
				$roundId = $round->roundId;
				$roundDetails = "{$round->gameName} @ {$round->roundStartDateTime}";
				
				$html .= '<option value="'.$roundId.'">'.$roundDetails.'&nbsp;&nbsp;</option>'."\n";
				$roundsCount++;
			}
			$html .= "</select>\n";			
			
			if ($roundsCount == 0) return '';
			
			return $html;
		}
		
		static function LocaliseAndFormatTimestamp($timestamp = null) 
		{
			$tz_string = get_option('timezone_string');
			$tz_offset = get_option('gmt_offset', 0);

			$date_format = get_option('date_format');
			$time_format = get_option('time_format');

			$format = "$date_format $time_format";

			if (!empty($tz_string)) 
			{
				// If site timezone option string exists, use it
				$timezone = $tz_string;
			} 
			elseif ($tz_offset == 0) 
			{
				// get UTC offset, if it isn’t set then return UTC
				$timezone = 'UTC';
			} 
			else 
			{
				$timezone = $tz_offset;

				if(WPCardzNetLibMigratePHPClass::Safe_substr($tz_offset, 0, 1) != "-" && WPCardzNetLibMigratePHPClass::Safe_substr($tz_offset, 0, 1) != "+" && WPCardzNetLibMigratePHPClass::Safe_substr($tz_offset, 0, 1) != "U") 
				{
					$timezone = "+" . $tz_offset;
				}
			}

			$datetime = new DateTime();
			$datetime->setTimestamp($timestamp);
			$datetime->setTimezone(new DateTimeZone($timezone));
			
			return $datetime->format($format);
		}

		function BannerMsg($msg, $class)
		{
			return '<div id="message" class="'.$class.'"><p>'.$msg.'</p></div>';
		}
		
		function AddToStampedCommsLog($logMessage)
		{
			return $this->WriteCommsLog($logMessage, true);
		}
/*		
		function AddObjectToCommsLog($objName, $obj)
		{
			$logMsg = WPCardzNetLibUtilsClass::print_r($obj, $objName, true);
			return $this->AddToCommsLog($logMsg);
		}
		
		function AddToCommsLog($logMessage)
		{
			return $this->WriteCommsLog($logMessage, false);
		}
*/		
		function WriteCommsLog($logMessage, $addTimestamp = false, $mode = WPCardzNetLibLogFileClass::ForAppending)
		{
			$logMessage .= "\n";

			if (!isset($this->logFileObj))
			{
				// Create log file using mode passed in call
				$LogsFolder = ABSPATH.$this->getOption('LogsFolderPath');
				$this->logFileObj = new WPCardzNetLibLogFileClass($LogsFolder);
				$this->logFileObj->LogToFile(WPCARDZNET_FILENAME_COMMSLOG, '', $mode);
				$mode = WPCardzNetLibLogFileClass::ForAppending;
			}
			
			if ($addTimestamp)
				$logMessage = current_time('D, d M y H:i:s').' '.$logMessage;

			$this->logFileObj->LogToFile(WPCARDZNET_FILENAME_COMMSLOG, $logMessage, $mode);			
		}
	
		function ClearCommsLog()
		{
			return $this->WriteCommsLog('Log Cleared', true, WPCardzNetLibLogFileClass::ForWriting);
		}
		
		function GetCommsLog()
		{
			$LogsFolder = ABSPATH.$this->getOption('LogsFolderPath');
			$logsPath = $LogsFolder.'/'.WPCARDZNET_FILENAME_COMMSLOG;
			return file_get_contents($logsPath);
		}
		
		function GetCommsLogSize()
		{
			$LogsFolder = ABSPATH.$this->getOption('LogsFolderPath');
			$logsPath = $LogsFolder.'/'.WPCARDZNET_FILENAME_COMMSLOG;
			if (!file_exists($logsPath)) return "0";
			$logSize = filesize($logsPath);
			if ($logSize === false) return "0";
			
			return round($logSize/1024, 2).'k';
		}

		function OutputDebugMessage($msg, $dbgOption = 'Dev_ShowMiscDebug')
		{
			if (($dbgOption != '') && !$this->isDbgOptionSet($dbgOption))
				return;
			
			if ($this->isDbgOptionSet('Dev_DebugToLog'))
				$this->AddToStampedCommsLog($msg);
			else
				WPCardzNetLibEscapingClass::Safe_EchoHTML($msg);	
		}
		
		function print_r($obj, $name='', $return = false, $eol = "<br>")
		{
			//if (!$this->isDbgOptionSet('Dev_ShowMiscDebug')) return '';
			
			return WPCardzNetLibUtilsClass::print_r($obj, $name, $return, $eol);
		}
		
		function dbError($fname, $sql, $results)			
		{
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<br><br>********************<br>Error in <strong>$fname</strong> call<br>\n");
			WPCardzNetLibUtilsClass::print_r($sql, 'Context');
			WPCardzNetLibUtilsClass::print_r($results, 'Data');
			exit;
		}

		function DoEmbeddedImage($eMailFields, $fieldMarker, $optionID)
		{
			$fieldMarker = '['.$fieldMarker.']';
			if (!WPCardzNetLibMigratePHPClass::Safe_strpos($eMailFields, $fieldMarker))
				return $eMailFields;
				
			if (isset($this->emailObj))
			{
				$imageFile = $this->getOption($optionID);
				if ($imageFile == '')
					return $eMailFields;
				
				$imagesPath = WPCARDZNETLIB_UPLOADS_PATH.'/images/';				
				
				// Add Image to EMail Images List
				$CIDFile = $this->emailObj->AddFileImage($imagesPath.$imageFile);
				$imageSrc = "cid:".$CIDFile;
			}
			else
			{
				$imageSrc = $this->getImageURL($optionID);
			}
				
			$eMailFields = WPCardzNetLibMigratePHPClass::Safe_str_replace($fieldMarker, $imageSrc, $eMailFields);
				
			return $eMailFields;
		}
		
		// Commented out Class Def (StageShowPlusCartDBaseClass)
		function sendMail($to, $from, $subject, $content, $headers = '')
		{
			include $this->emailClassFilePath;
			$emailObj = new $this->emailObjClass($this);
			$emailObj->sendMail($to, $from, $subject, $content, $headers);
		}
		
		
		function GetHiddenFromRequest($postId)
		{
			$postVal = WPCardzNetLibUtilsClass::GetHTTPTextElem('request', $postId);
			return "<input type=hidden id=\"$postId\" name=\"$postId\" value=\"$postVal\" >\n";
		}
	}

}

?>