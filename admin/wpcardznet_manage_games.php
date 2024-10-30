<?php
/* 
Description: Code for Managing Games
 
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

if (!class_exists('WPCardzNetGamesAdminListClass')) 
{
	// --- Define Class: WPCardzNetGamesAdminListClass
	class WPCardzNetGamesAdminListClass extends WPCardzNetLibAdminListClass // Define class
	{	
		const BULKACTION_ENDGAME = 'endgame';
	
		function __construct($env) //constructor
		{
			$this->hiddenRowsButtonId = 'TBD';		

			// Call base constructor
			parent::__construct($env, true);
			
			$this->allowHiddenTags = false;
			
			$this->hiddenRowsButtonId = __('Details', 'wpcardznet');		
			
			$this->bulkActions = array(
				self::BULKACTION_ENDGAME => __('End Game', 'wpcardznet'),
				);
					
			if (current_user_can(WPCARDZNET_CAPABILITY_ADMINUSER))
			{
				$this->bulkActions[self::BULKACTION_DELETE] = __('Delete', 'wpcardznet');
			}
					
			$this->HeadersPosn = WPCardzNetLibTableClass::HEADERPOSN_BOTH;
			
		}
		
		function GetRecordID($result)
		{
			return $result->gameId;
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
			return "wpcardznet-games-tab";
		}
		
		function ShowGameDetails($result)
		{
			$myDBaseObj = $this->myDBaseObj;
			
			$gameDetails = '';
			$gameResults = $myDBaseObj->GetScoresByGame($result->gameId);
			if (count($gameResults) > 0)
			{
				$gameDetails = $this->BuildGameDetails($gameResults);
			}

			return $gameDetails;
		}
				
		function GetListDetails($result)
		{
			return $this->myDBaseObj->GetGameById($result->gameId);
		}
		
		function BuildGameDetails($gameResults)
		{
			$env = $this->env;

			$gameDetailsList = $this->CreateGameAdminDetailsListObject($env, $this->editMode, $gameResults);	

			// Set Rows per page to disable paging used on main page
			$gameDetailsList->enableFilter = false;
			
			ob_start();	
			$gameDetailsList->OutputList($gameResults);	
			$zoneDetailsOutput = ob_get_contents();
			ob_end_clean();

			return $zoneDetailsOutput;
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
			return WPCardzNetAdminDBaseClass::GetUserName($userId);
		}
		
		function FormatDateForAdminDisplay($dateInDB)
		{
			return WPCardzNetAdminDBaseClass::FormatAdminDateTime($dateInDB);
		}
		
		function FormatStatus($status)
		{
			switch ($status)
			{
		   		case WPCardzNetDBaseClass::GAME_INPROGRESS: $status = 'In Progress'; break;
		   		case WPCardzNetDBaseClass::GAME_COMPLETE: $status = 'Complete'; break;
		   		case WPCardzNetDBaseClass::GAME_ENDED: $status = 'Ended'; break;
			}
	   		
			return $status;
		}
		
		function GetMainRowsDefinition()
		{
			$columnDefs = array(
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Game',          WPCardzNetLibTableClass::TABLEPARAM_ID => 'gameName',          WPCardzNetLibTableClass::TABLEPARAM_TYPE => WPCardzNetLibTableClass::TABLEENTRY_VIEW, ),
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Start Date',    WPCardzNetLibTableClass::TABLEPARAM_ID => 'gameStartDateTime', WPCardzNetLibTableClass::TABLEPARAM_TYPE => WPCardzNetLibTableClass::TABLEENTRY_VIEW,  WPCardzNetLibTableClass::TABLEPARAM_DECODE => 'FormatDateForAdminDisplay', ),
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'No of Players', WPCardzNetLibTableClass::TABLEPARAM_ID => 'gameNoOfPlayers',   WPCardzNetLibTableClass::TABLEPARAM_TYPE => WPCardzNetLibTableClass::TABLEENTRY_VIEW, ),
				//array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'End Score',     WPCardzNetLibTableClass::TABLEPARAM_ID => 'gameEndScore',      WPCardzNetLibTableClass::TABLEPARAM_TYPE => WPCardzNetLibTableClass::TABLEENTRY_VIEW, ),
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'State',         WPCardzNetLibTableClass::TABLEPARAM_ID => 'gameStatus',        WPCardzNetLibTableClass::TABLEPARAM_TYPE => WPCardzNetLibTableClass::TABLEENTRY_VIEW,  WPCardzNetLibTableClass::TABLEPARAM_DECODE => 'FormatStatus', ),
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'End Date',      WPCardzNetLibTableClass::TABLEPARAM_ID => 'gameEndDateTime',   WPCardzNetLibTableClass::TABLEPARAM_TYPE => WPCardzNetLibTableClass::TABLEENTRY_VIEW,  WPCardzNetLibTableClass::TABLEPARAM_DECODE => 'FormatDateForAdminDisplay', ),
			);
			
			if (current_user_can(WPCARDZNET_CAPABILITY_ADMINUSER))
			{
				$adminDefs = array(
					array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Manager',   WPCardzNetLibTableClass::TABLEPARAM_ID => 'gameLoginId',      WPCardzNetLibTableClass::TABLEPARAM_TYPE => WPCardzNetLibTableClass::TABLEENTRY_VIEW, self::TABLEPARAM_DECODE => 'FormatUserName', WPCardzNetLibTableClass::TABLEPARAM_AFTER => 'gameName', ),
				);
				
				$columnDefs = self::MergeSettings($columnDefs, $adminDefs);
			}
			
			return $columnDefs;
		}		

		function GetTableRowCount()
		{
			$userId = current_user_can(WPCARDZNET_CAPABILITY_ADMINUSER) ? 0 : get_current_user_id();
			return $this->myDBaseObj->GetGamesCount($userId);		
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
			$results = $this->myDBaseObj->GetGames($userId, $sqlFilters);
		}

		function GetDetailsRowsDefinition()
		{
			$ourOptions = array(
				array(WPCardzNetLibTableClass::TABLEPARAM_TYPE => WPCardzNetLibTableClass::TABLEENTRY_FUNCTION, WPCardzNetLibTableClass::TABLEPARAM_FUNC => 'ShowGameDetails'),
			);
			
			$rowDefs = self::MergeSettings(parent::GetDetailsRowsDefinition(), $ourOptions);

			return $rowDefs;
		}
		
		function CreateGameAdminDetailsListObject($env, $editMode, $gameResults)
		{
			return new WPCardzNetGamesAdminDetailsListClass($env, $editMode, $gameResults);	
		}
		
	}
}


if (!class_exists('WPCardzNetGamesAdminDetailsListClass')) 
{
	class WPCardzNetGamesAdminDetailsListClass extends WPCardzNetLibAdminDetailsListClass // Define class
	{		
		function __construct($env, $editMode, $gameResults) //constructor
		{
			// Call base constructor
			parent::__construct($env, $editMode);
			
			$this->allowHiddenTags = false;
			
			$this->SetRowsPerPage(self::WPCARDZNETLIB_EVENTS_UNPAGED);
			
			$this->HeadersPosn = WPCardzNetLibTableClass::HEADERPOSN_TOP;
		}
		
		function GetTableID($result)
		{
			return "wpcardznet-games-list-tab";
		}
		
		function GetRecordID($result)
		{
			return $result->gameId;
		}
		
		function GetDetailID($result)
		{
			return '_'.$result->playerId;
		}
		
		function GetMainRowsDefinition()
		{
			$rtnVal = array(
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Player',  WPCardzNetLibTableClass::TABLEPARAM_ID => 'playerName',   WPCardzNetLibTableClass::TABLEPARAM_TYPE => WPCardzNetLibTableClass::TABLEENTRY_VIEW, ),
				array(WPCardzNetLibTableClass::TABLEPARAM_LABEL => 'Score',   WPCardzNetLibTableClass::TABLEPARAM_ID => 'playerScore',  WPCardzNetLibTableClass::TABLEPARAM_TYPE => WPCardzNetLibTableClass::TABLEENTRY_VIEW, ),
			);
			
			return $rtnVal;
		}
		
		function IsRowInView($result, $rowFilter)
		{
			return true;
		}		
				
	}
}
	
if (!class_exists('WPCardzNetGamesAdminClass')) 
{
	// --- Define Class: WPCardzNetGamesAdminClass
	class WPCardzNetGamesAdminClass extends WPCardzNetLibAdminClass // Define class
	{		
		var $results;
		var $showOptionsID = 0;
		
		function __construct($env)
		{
			$this->pageTitle = __('Games', 'wpcardznet');
			
			parent::__construct($env, true);
		}
		
		function ProcessActionButtons()
		{
			$myPluginObj = $this->myPluginObj;
			$myDBaseObj = $this->myDBaseObj;				
				
			if (WPCardzNetLibUtilsClass::IsElementSet('post', 'addGameDetails') && WPCardzNetLibUtilsClass::IsElementSet('post', 'gameName'))
			{
				// Check that the referer is OK
				$this->CheckAdminReferer();		

				// NOTE: Could Check if any users are already in a game
				// For now .... just complete any pending games
				
				// If (already in a game)
				// Get confirmation
				// Otherwise continue with adding game
				
				$gamesList = $myDBaseObj->GetGamesList();
				$gameName = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'gameName');

				if (!isset($gamesList[$gameName])) return;
				$gameEntry = $gamesList[$gameName];
				
				$gameClass = $gameEntry->className;
				$gameDefFile = $gameEntry->filename;
			
				include WPCARDZNET_GAMES_PATH.$gameDefFile;
				
				// NOTE: Could Move create "Game" object to generic class 
				$gameObj = new $gameClass($myDBaseObj);
				$rtnStatus = $gameObj->ProcessGameDetailsForm($gameName);
				if ($rtnStatus) return;
				
				// An error has occurred reload the same page
				WPCardzNetLibUtilsClass::SetElement('post', 'addGameRequest', 1);
			}
			
			if (WPCardzNetLibUtilsClass::IsElementSet('post', 'addGameRequest'))
			{
				// Check that the referer is OK
				$this->CheckAdminReferer();		

				// Add Game to Database	- Show game setup page
				$gameName = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'gameName');
				$gameDef = $myDBaseObj->GameNameToFileAndClass($gameName);
				$gameClass = $gameDef->className;
				$gameDefFile = $gameDef->srcfile;
				
				include WPCARDZNET_GAMES_PATH.$gameDefFile;
				
				// Make setup game screen
				$gameObj = new $gameClass($myDBaseObj);
				$titleText = __('Enter Settings for', 'wpcardznet');
				WPCardzNetLibEscapingClass::Safe_EchoHTML("<h2> $titleText $gameName</h2>\n");
				WPCardzNetLibEscapingClass::Safe_EchoHTML($gameObj->GetGameDetailsForm());
				
				$this->donePage = true;
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
			
			$groupSelectorHTML = $myDBaseObj->GetGroupSelector();
			if ($groupSelectorHTML == '')
			{
				$text = __("Setup a group first", 'wpcardznet');
				$linkText = __("here", 'wpcardznet');
				WPCardzNetAdminDBaseClass::GoToPageLink($text, $linkText, WPCARDZNET_MENUPAGE_GROUPS);
				return;
			}
			
			$actionURL = remove_query_arg('action');
			$actionURL = remove_query_arg('id', $actionURL);
			
			// HTML Output - Start 
			$formClass = 'wpcardznet'.'-admin-form '.'wpcardznet'.'-games-editor';
			WPCardzNetLibEscapingClass::Safe_EchoHTML('
				<div class="'.$formClass.'">
				<form method="post" action="'.$actionURL.'">
				');

			if (isset($this->saleId))
				WPCardzNetLibEscapingClass::Safe_EchoHTML("\n".'<input type="hidden" name="saleID" value="'.$this->saleId.'"/>'."\n");
				
			$this->WPNonceField();
				 
			$noOfGames = $this->OutputGamesList($this->env);;
			if($noOfGames == 0)
			{
				WPCardzNetLibEscapingClass::Safe_EchoHTML("<div class='noconfig'>".__('No Games', 'wpcardznet')."</div>\n");
				$lastGameName = '';
			}
			else 
			{				
				$userId = current_user_can(WPCARDZNET_CAPABILITY_ADMINUSER) ? 0 : get_current_user_id();
				$lastGameName = $this->myDBaseObj->GetLastGameName($userId);
			}

			// Output a selector to choose a new game to play
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div id=addgame>\n");
			$gamesList = $myDBaseObj->GetGamesList();
			
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<select name=gameName id=gameName>\n");
			foreach ($gamesList as $gameName => $gameEntry)
			{
				$selected = ($gameName == $lastGameName) ? ' selected=""' : '';
				WPCardzNetLibEscapingClass::Safe_EchoHTML("<option $selected>$gameName</option>\n");
			}
			WPCardzNetLibEscapingClass::Safe_EchoHTML("</select>\n");			
			
			WPCardzNetLibEscapingClass::Safe_EchoHTML($groupSelectorHTML);
			$this->OutputButton("addGameRequest", __("Add Game", 'wpcardznet'));
			WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");
			
/*			
			if (count($this->results) > 0)
			{
				$this->OutputButton("savechanges", __("Save Changes", 'wpcardznet'), "button-primary");
			}
*/
?>
	<br></br>
	</form>
	</div>
<?php
		} // End of function Output_MainPage()


		function OutputGamesList($env)
		{
			$myPluginObj = $this->myPluginObj;
			
			$classId = $myPluginObj->adminClassPrefix.'GamesAdminListClass';
			$gamesListObj = new $classId($env);
			$gamesListObj->showOptionsID = $this->showOptionsID;
			return $gamesListObj->OutputList($this->results);		
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
			if (!isset($this->endedCount)) $this->endedCount = 0;
			
			$results = $myDBaseObj->GetGameById($recordId);

			switch ($bulkAction)
			{
				case WPCardzNetGamesAdminListClass::BULKACTION_ENDGAME:
					if (count($results) == 0)
						$this->errorCount++;
					else if ($results[0]->gameStatus != WPCardzNetAdminDBaseClass::GAME_INPROGRESS)
						$this->endedCount++;					
					return ($this->errorCount > 0);
					
				case WPCardzNetGamesAdminListClass::BULKACTION_DELETE:
					// FUNCTIONALITY: Games - Bulk Action Delete			
					if (count($results) == 0)
						$this->errorCount++;
					return ($this->errorCount > 0);
			}
			
			return false;
		}
		
		function DoBulkAction($bulkAction, $recordId)
		{
			$myDBaseObj = $this->myDBaseObj;
			
			$listClassId = $this->myPluginObj->adminClassPrefix.'GamesAdminListClass';
			
			switch ($bulkAction)
			{
				case WPCardzNetGamesAdminListClass::BULKACTION_DELETE:		
					$myDBaseObj->DeleteGame($recordId);
					return true;
					
				case WPCardzNetGamesAdminListClass::BULKACTION_ENDGAME:		
					$myDBaseObj->EndGame($recordId);
					return true;
			}
				
			return parent::DoBulkAction($bulkAction, $recordId);
		}
		
		function GetBulkActionMsg($bulkAction, $actionCount)
		{
			$actionMsg = '';
			
			$listClassId = $this->myPluginObj->adminClassPrefix.'GamesAdminListClass';
			
			switch ($bulkAction)
			{
				case WPCardzNetGamesAdminListClass::BULKACTION_DELETE:	
					if ($this->errorCount > 0)
						$actionMsg = $this->errorCount . ' ' . _n("Game does not exist in Database", "Games do not exist in Database", $this->errorCount, 'wpcardznet');
					else if ($actionCount > 0)
						$actionMsg = $actionCount . ' ' . _n("Game has been deleted", "Games have been deleted", $actionCount, 'wpcardznet');
					else
						$actionMsg =  __("Nothing to Delete", 'wpcardznet');
					break;
					
				case WPCardzNetGamesAdminListClass::BULKACTION_ENDGAME:	
					if ($this->errorCount > 0)
						$actionMsg = $this->errorCount . ' ' . _n("Game does not exist in Database", "Games do not exist in Database", $this->errorCount, 'wpcardznet');
					else if ($actionCount > 0)
						$actionMsg = $actionCount . ' ' . _n("Game has been ended", "Games have been ended", $actionCount, 'wpcardznet');
					else
						$actionMsg =  __("Nothing to End", 'wpcardznet');
					break;
					
				default:
					$actionMsg = parent::GetBulkActionMsg($bulkAction, $actionCount);

			}
			
			return $actionMsg;
		}
		
	}

}

?>