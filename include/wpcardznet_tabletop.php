<?php
/* 
Description: Code for Managing CardzNet Debug Settings
 
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
include WPCARDZNETLIB_INCLUDE_PATH.'wpcardznet_cards.php';

if (!class_exists('WPCardzNetGamesBaseClass')) {
	define('WPCARDZNET_GAMEOPTS_STATE_NO', 'no');
	define('WPCARDZNET_GAMEOPTS_STATE_YES', 'yes');

	class WPCardzNetGamesBaseClass // Define class
	{
		var $atts;
		var $cardDefObj = null;
		var $stripFormElems = false;
		var $cardGUID = 0;

		var $cardDefClass = 'WPCardzNetCardsClass';

		var $promptMsg = '';

		var $MIN_NO_OF_PLAYERS = 0;
		var $MAX_NO_OF_PLAYERS = 0;
		
		static function GetGameName()
		{
			return 'TBD';
		}

		static function GetGameIncludeDefs($gameName)
		{
			$rslt = new stdClass();

			$gameRootName = "wpcardznet_".WPCardzNetLibMigratePHPClass::Safe_str_replace(' ', '_', WPCardzNetLibMigratePHPClass::Safe_strtolower($gameName));

			$rslt->cssFile = 'css/'.$gameRootName.".css";
			$rslt->cssId = $gameRootName."-css";
			$rslt->jsFile = 'js/'.$gameRootName.".js";
			$rslt->jsId = $gameRootName."-js";

			return $rslt;
		}

		function LoadCSSandJS($cardsSet, $plugin_version)
		{
			$cssId = WPCARDZNET_CODE_PREFIX.'-cards';
			$cardsSetCssURL = WPCARDZNET_URL."cards/$cardsSet/css/wpcardznet_cards.css";

			// Add Style Sheet for Cards
			$this->myDBaseObj->enqueue_style($cssId, $cardsSetCssURL);

			// Include size defs for PHP
			$cardsSetCardDefs = WPCARDZNET_CARDS_PATH."$cardsSet/wpcardznet_cards.php";
			include $cardsSetCardDefs;
		}

		function AddGameIncludes($gameName, $plugin_version)
		{
			$myDBaseObj = $this->myDBaseObj;

			$gameFileDefs = $this->GetGameIncludeDefs($gameName);

			$gamesPath = WPCARDZNET_GAMES_PATH;
			$gamesURL = WPCARDZNET_GAMES_URL;

			// It is possible to override includes in uploads folder
			$gamesUploadsPath = WPCardzNetLibMigratePHPClass::Safe_str_replace('plugins', 'uploads', $gamesPath);
			$gamesUploadsURL = WPCardzNetLibMigratePHPClass::Safe_str_replace('plugins', 'uploads', $gamesURL);

			// Add game Javascript file
			$jsFilePath = $gamesUploadsPath.$gameFileDefs->jsFile;
			if (file_exists($jsFilePath)) {
				$jsFileURL = $gamesUploadsURL.$gameFileDefs->jsFile;
			} else {
				$jsFilePath = $gamesPath.$gameFileDefs->jsFile;
				$jsFileURL = $gamesURL.$gameFileDefs->jsFile;
			}
			if (file_exists($jsFilePath)) {
				$jsId = $gameFileDefs->jsId;
				$myDBaseObj->enqueue_script($jsId, $jsFileURL);
			}

			// Add game CSS file
			$cssFilePath = $gamesUploadsPath.$gameFileDefs->cssFile;
			if (file_exists($cssFilePath)) {
				$cssFileURL = $gamesUploadsURL.$gameFileDefs->cssFile;
			} else {
				$cssFilePath = $gamesPath.$gameFileDefs->cssFile;
				$cssFileURL = $gamesURL.$gameFileDefs->cssFile;
			}
			if (file_exists($cssFilePath)) {
				$cssId = $gameFileDefs->cssId;
				$myDBaseObj->enqueue_style($cssId, $cssFileURL);
			}
		}

		function CSS_JS_and_Includes($gameDef)
		{
			$myDBaseObj = $this->myDBaseObj;

			$gameName = $gameDef->gameName;
			$plugin_version = $myDBaseObj->get_JSandCSSver();
			$this->AddGameIncludes($gameName, $plugin_version);

			$cardsSet = $gameDef->gameCardFace;
			$this->LoadCSSandJS($cardsSet, $plugin_version);

			self::OutputCardsURLs($cardsSet, $plugin_version);
		}

		function __construct($myDBaseObj, $atts = array())
		{
			$this->myDBaseObj = $myDBaseObj;

			$this->atts = $atts;
		}

		function GetCardDef($cardSpec)
		{
			if ($this->cardDefObj == null)
				$this->cardDefObj = new $this->cardDefClass();

			if (!is_integer($cardSpec))
				$cardSpec = $this->cardDefObj->GetCardNo($cardSpec);

			return $this->cardDefObj->GetCardDef($cardSpec);
		}

		function GetCardNo($cardName)
		{
			if ($this->cardDefObj == null)
				$this->cardDefObj = new $this->cardDefClass();

			return $this->cardDefObj->GetCardNo($cardName);
		}

		function GetGameAndPlayer($gameId = 0)
		{
			$userId = $this->myDBaseObj->GetOurUserId($this->atts);

			$results = $this->Initialise($userId, $this->atts);
			if ($results == null)
				return false;

			return true;
		}

		function ReInitialise()
		{
			$this->myDBaseObj->LoadActiveGame();
			$this->Initialise($this->userId, $this->atts);
		}

		function Initialise($userId, $atts)
		{
			$this->userId = $userId;
			$this->atts = $atts;

			$myDBaseObj = $this->myDBaseObj;

			if ($myDBaseObj->GetGameByUser($userId, $atts) === null)
				return null;

			if ($myDBaseObj->GetPlayersList() == null)
				return null;

			$this->GetCurrentPlayer();

			return true;
		}

		function GetGameDetailsForm($addHtml = '')
		{
			$myDBaseObj = $this->myDBaseObj;

			$gameName = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'gameName');

			// Look for a "Pre-Config" file in the site root
			$preConfigFilePath = WP_CONTENT_DIR."/../wpcardznet_cfg.php";

			$loginNames = array();
			$names = array();
			$visibilities = array();

			$lastGameDetails = $myDBaseObj->GetLastGame($gameName);

			if (WPCardzNetLibUtilsClass::IsElementSet('post', 'name0')) {
				$noOfPlayers = 0;
				for ($no=0; $no<8; $no++) {
					$loginId = WPCardzNetLibUtilsClass::GetHTTPInteger('post', "userId{no}", 0);
					if ($loginId == 0)
						break;
					$loginNames[] = WPCardzNetDBaseClass::GetUserName(GetUserName($loginId));
					$names[] = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', "name{$no}");
					$visibilities[] = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', "visibility{$no}");

					$noOfPlayers++;
				}
			} else if (count($lastGameDetails) > 0) {
				foreach ($lastGameDetails as $lastPlayer) {
					$loginName = WPCardzNetDBaseClass::GetUserName($lastPlayer->userId);
					$loginNames[] = $loginName;
					$name = '';
					if ($loginName !== $lastPlayer->playerName)
						$name = $lastPlayer->playerName;
					$names[] = $name;
					$visibilities[] = $lastPlayer->hideCardsOption;
				}
				$noOfPlayers = count($lastGameDetails);
			} else if (file_exists($preConfigFilePath)) {
				$preConfigData = file_get_contents($preConfigFilePath);
				$preConfigData = WPCardzNetLibMigratePHPClass::Safe_str_replace(' ', '', $preConfigData);
				$preConfigLines = explode("\n", $preConfigData);
				$loginNames = explode(',', $preConfigLines[1]);
				$names = explode(',', $preConfigLines[2]);
				$noOfPlayers = count($loginNames);
			} else {
				$noOfPlayers = 4;
			}

			$firstEntryIsManager = false;
			for ($no = 0; $no < $this->MAX_NO_OF_PLAYERS; $no++) {
				if (($no==0) && $this->myDBaseObj->isOptionSet('FirstPlayerIsManager')) {
					$userId = get_current_user_id();
					$userName = WPCardzNetDBaseClass::GetUserName($userId);
					$selectors[] = "<input type=hidden id=userId0 name=userId0 value=$userId>$userName";
					$firstEntryIsManager = true;
					continue;
				}
				$loginName = isset($loginNames[$no]) ? $loginNames[$no] : '';
				$selectors[] = $myDBaseObj->GetMemberSelector($no, $loginName);
			}

			$loginText = __('Login', 'wpcardznet');
			$nameText = __('Player Name', 'wpcardznet');
			$playerText = __('Player', 'wpcardznet');
			$noOfPlayersText = __('No of Players', 'wpcardznet');

			
			$html  = "<form method=post>\n";

			$html .= $myDBaseObj->WPNonceField('', '_wpnonce', false);

			$html .= "<input type=hidden id=gameName name=gameName value=\"$gameName\" >\n";
			$html .= "<table>\n";
			$html .= "<tr><td><h3>".__("Players", 'wpcardznet').":</td></tr></h3>\n";

			$html .= "<tr><td>";
			$html .= __("Number of Players", 'wpcardznet')." - ";
			$html .= __("Minimum", 'wpcardznet').": {$this->MIN_NO_OF_PLAYERS} ";
			$html .= __("Maximum", 'wpcardznet').": {$this->MAX_NO_OF_PLAYERS} ";
			$html .= "</td></tr>\n";

			$html .= "<tr><td class='gamecell'>&nbsp;</td><td class='gamecell'>$loginText</td><td class='gamecell'>$nameText</td>";
						$html .= "</tr>\n";
			for ($no = 0; $no < $this->MAX_NO_OF_PLAYERS; $no++) {
				$name = isset($names[$no]) ? $names[$no] : '';
				$playerNo = $no + 1;
				$selector = $selectors[$no];
				$style = ($no >= $noOfPlayers) ? ' style="display:none"' : '';
				$html .= "<tr class='addgame_row_login'><td class='gamecell'>$playerText $playerNo</td>";
				$html .= "<td class='gamecell'>".$selector."</td>";
				$html .= "<td class='gamecell'>";
				if (($no == 0) && $firstEntryIsManager)
					$html .= "&nbsp;<input type=hidden id='name$no' name='name$no' value=''>";
				else
					$html .= "<input id='name$no' name='name$no' value='$name'>";
				$html .= "</td>";
								$html .= "</tr>\n";
			}

			$html .= "<tr><td><h3>".__("Other Details", 'wpcardznet').":</td></tr></h3>\n";
			$html .= $addHtml;

			$lastCardFace = (count($lastGameDetails) > 0) ? $lastGameDetails[0]->gameCardFace : '';
			$html .= $this->CardFaceSelectRow($lastCardFace);

			if ($myDBaseObj->isDbgOptionSet('Dev_RerunGame')) {
				$html .= $this->CopyGameSelectRow();
			}

			$currOpts = array();
			if ((count($lastGameDetails) > 0)
			&& isset($lastGameDetails[0]->gameMeta)
			&& ($lastGameDetails[0]->gameMeta != '')) {
				$currOpts =  unserialize($lastGameDetails[0]->gameMeta);
			}

			$optionsHtml = $this->GetGameOptionsHTML($currOpts);
			if ($optionsHtml != '') {
				$html .= "<tr><td><h3>".__("Options for Selected Game", 'wpcardznet').":</td></tr></h3>\n";
				$html .= $optionsHtml;
			}

			$html .= "<tr class='addgame_row_submit'><td colspan=3><input class='button-secondary' type='submit' name=addGameDetails value='Add Game'></td></tr>\n";

			$html .= "</table>\n";

			$html .= $myDBaseObj->GetHiddenFromRequest('gameGroupId');

			$html .= "</form>\n";

			return $html;
		}

		function GetGameOptionsHTML($currOpts)
		{
			return '';
		}

		function CardFaceSelectRow($defaultCardFace = '')
		{
			$cardFaceText = __("Card Face", 'wpcardznet');

			$html  = "<tr class='addgame_cardface'><td>$cardFaceText</td>\n";
			$html .= "<td class='gamecell'>";
			$html .= $this->myDBaseObj->GetCardFaceSelector($defaultCardFace);
			$html .= "</td></tr>";

						return $html;
		}

		function GetCardFaceSelector($lastCardFace = '')
		{
			if ($lastCardFace == '')
				$lastCardFace = 'Standard';

			$cardFaces = $this->GetCardFacesList();

			$html  = "<select id=gameCardFace name=gameCardFace>\n";
			foreach ($cardFaces as $cardFace) {
				$selected = ($cardFace == $lastCardFace) ? ' selected=""' : '';
				$html .= '<option value="'.$cardFace.'"'.$selected.'>'.$cardFace.'&nbsp;&nbsp;</option>'."\n";
			}
			$html .= "</select>\n";
			return $html;
		}

		function CopyGameSelectRow()
		{
			$gameName = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'gameName');

			$copyGameText = __("Copy Game", 'wpcardznet');
			$nocopyGameText = __("No Copy", 'wpcardznet');

			$selector = $this->myDBaseObj->GetGameSelector($gameName, $nocopyGameText);
			if ($selector == '')
				return '';

			$html  = "<tr class='addgame_copygame'><td>$copyGameText</td>\n";
			$html .= "<td colspan=2 class='gamecell'>";
			$html .= $selector;
			$html .= "</td></tr>";

			return $html;
		}

		function ProcessGameOptions($gameOpts = array())
		{
			return array();
		}

		function ProcessGameDetailsForm($gameName)
		{
			$gameUserIDs = array();
			$tmpList = array();

			$firstPlayerUserIdAndName = '';
			if ($this->myDBaseObj->isDbgOptionSet('Dev_RerunGame')) {
				$prevroundId = WPCardzNetLibUtilsClass::GetHTTPInteger('post', 'prevroundId');
				if ($prevroundId != 0) {
					// Get the deck from an earlier round
					$lastRounds = $this->myDBaseObj->GetRounds($prevroundId);
					$lastFirstPlayerId = $lastRounds[0]->firstPlayerId;

					$playerDetails = $this->myDBaseObj->GetNextPlayer($lastFirstPlayerId);
					$firstPlayerUserIdAndName = "{$playerDetails->userId}.{$playerDetails->playerName}";
				}
			}

			$hasVisibility = WPCardzNetLibUtilsClass::IsElementSet('post', 'cardsVisibleOption_0');
			$idAndNameList = array();
			for ($no=0; $no < $this->MAX_NO_OF_PLAYERS; $no++) {
				$userId = WPCardzNetLibUtilsClass::GetHTTPInteger('post', "userId$no");
				if ($userId == 0)
					continue;

				$name = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', "name$no");
				if ($name == '') {
					$name = WPCardzNetDBaseClass::GetUserName($userId);
				}
				if ($userId == 0)
					continue;

				$idAndName = $userId.$name;
				if (isset($idAndNameList[$idAndName])) {
					WPCardzNetLibEscapingClass::Safe_EchoHTML($this->myDBaseObj->BannerMsg(__("Login and Player Name must be unique", 'wpcardznet'), 'error'));
					return false;
				}
				$idAndNameList[$idAndName] = true;

				$gameUserIDs[] = array('id' => $userId, 'name' => $name);

				if ($hasVisibility) {
					$vis = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', "visibility$no");
					$gameUserIDs[$no]['visibility'] = $vis;
				}

				$thisPlayerIdAndName = "{$userId}.{$name}";
				
				if ($firstPlayerUserIdAndName != '') {
					if ($thisPlayerIdAndName == $firstPlayerUserIdAndName)
						$gameUserIDs[$no]['first'] = true;
				}
				$tmpList[] = $no;
			}

			if (count($gameUserIDs) < $this->MIN_NO_OF_PLAYERS) {
				WPCardzNetLibEscapingClass::Safe_EchoHTML($this->myDBaseObj->BannerMsg(__("Not enough players", 'wpcardznet'), 'error'));
				return false;
			}

			// First player is random .... but players are always in the same order
			if ($firstPlayerUserIdAndName == '') {
				shuffle($tmpList);
				foreach ($tmpList as $tmp) {
					$playerIndex = $tmp;
					break;
				}
				$gameUserIDs[$playerIndex]['first'] = true;
			}

			$gameNoOfPlayers = count($gameUserIDs);
			$gameDealDetails = $this->GetDealDetails($gameNoOfPlayers);
			$gameCardsPerPlayer = $gameDealDetails->cardsPerPlayer;
			$gameCardFace = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'gameCardFace', '');

						if (!$this->myDBaseObj->AddGame($gameName, $gameUserIDs, $gameCardsPerPlayer, $gameCardFace)) {
				WPCardzNetLibEscapingClass::Safe_EchoHTML($this->myDBaseObj->BannerMsg(__("One or more Players Are Active", 'wpcardznet'), 'error'));
				return false;
			}

						
			for ($no=0; $no < $this->MAX_NO_OF_PLAYERS; $no++) {
				$userId = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', "userId$no");
			}

			$this->DealCards($gameDealDetails);

			$gameOpts = $this->ProcessGameOptions();
			if (count($gameOpts) > 0)
				$this->myDBaseObj->UpdateGameOptions($gameOpts);

			WPCardzNetLibEscapingClass::Safe_EchoHTML($this->myDBaseObj->BannerMsg(__("Game Added", 'wpcardznet'), 'updated'));
			return true;
		}

		function GetDealDetails($noOfPlayers = 0)
		{
			$this->NotImplemented('GetDealDetails');
		}

		function PlayerColour($details, $playerNo)
		{
			$ourColours = array('teamA', 'teamB');
			$noOfTeams = $details->noOfTeams;
			$colourIndex = $playerNo % $noOfTeams;
			return $ourColours[$colourIndex];
		}

		function DealCards($details = null, $roundState = '')
		{
			$myDBaseObj = $this->myDBaseObj;

			if ($details == null)
				$details = $this->GetDealDetails();

			if ($roundState == '')
				$roundState = WPCardzNetDBaseClass::ROUND_READY;
			$roundId = $myDBaseObj->AddRound($roundState);

			$cards = $myDBaseObj->GetDeck($details);

			$cardIndex = 0;

			$noOfPlayers = $myDBaseObj->GetNoOfPlayers();
			$cardsPerPlayer = $myDBaseObj->cardsPerPlayer;
			for ($playerNo = 0; $playerNo<$noOfPlayers; $playerNo++) {
				$playerId = $myDBaseObj->AddDeckToHand($playerNo, $cards, $cardIndex, $cardsPerPlayer);

				// Add player "colours"
				if (isset($details->noOfTeams)) {
					$playerColour = $this->PlayerColour($details, $playerNo);
					$myDBaseObj->SetPlayerColour($playerId, $playerColour);
				}
			}

			$myDBaseObj->SetNextCardInDeck($cardIndex);
		}

		function GetCurrentPlayer()
		{
			$this->myDBaseObj->FindCurrentPlayer();
		}
		
		function SetNextPlayer($nextPlayerId)
		{
			$this->myDBaseObj->UpdateNextPlayer($nextPlayerId);
		}

		function OutputNoGame()
		{
			WPCardzNetLibEscapingClass::Safe_EchoHTML('<form id=nogame name=nogame method=post>'."\n");
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div>\n");

			WPCardzNetLibEscapingClass::Safe_EchoHTML($this->myDBaseObj->BannerMsg(__('Not currently in a game!', 'wpcardznet'), 'error'));

			WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");
			WPCardzNetLibEscapingClass::Safe_EchoHTML("</form>\n");
		}

		function OutputCardTable($gameId = 0)
		{
			$stripFormElems = isset($this->atts['stripFormElems']);
			if (!$stripFormElems)
				WPCardzNetLibEscapingClass::Safe_EchoHTML('<form id=wpcardznet_form name=wpcardznet_form method=post>'."\n");

			if (!$this->GetGameAndPlayer($gameId)) {
				// Error initialising ... cannot play!
				$this->OutputNoGame();
			} else {
				$this->OutputTabletop();
			}

			$this->myDBaseObj->AddTickerTag();

			WPCardzNetLibEscapingClass::Safe_EchoHTML($this->myDBaseObj->AJAXVarsTags());

			if ($stripFormElems)
				return;

			WPCardzNetLibEscapingClass::Safe_EchoHTML("</form>\n");

			WPCardzNetLibEscapingClass::Safe_EchoScript($this->myDBaseObj->JSGlobals());
					}

		function OutputCard($cardOptions, $cardDef, $cardZIndex=0)
		{
			if (!isset($cardOptions['id']))
				$this->cardGUID++;
			return self::OutputCardEx($cardOptions, $cardDef, $this->cardGUID, $cardZIndex);
		}

		static function OutputCardEx($cardOptions, $cardDef, $cardGUID, $cardZIndex=0)
		{
			$active = false;
			$visible = true;
			$hasClickEvent = false;

			if (isset($cardOptions['active']))
				$active = $cardOptions['active'];

			if (isset($cardOptions['hasClick'])) {
				$hasClickEvent = $cardOptions['hasClick'];
				if (is_string($cardOptions['hasClick']))
					$clickEvent = $cardOptions['hasClick'];
				else
					$clickEvent = 'wpcardznet_clickCard';
			}

			if (isset($cardOptions['visible']))
				$visible = $cardOptions['visible'];

			foreach ($cardOptions as $layoutId => $layoutVal)
				$cardDef->$layoutId = $layoutVal;

			$cardNo = $cardDef->cardno;

			$hspace = (isset($cardOptions['hspace'])) ? $cardOptions['hspace'] : WPCardzNetCardDefClass::CardXSpace;
			$hoffset = (isset($cardOptions['hoffset'])) ? $cardOptions['hoffset'] : 20;
			$vspace = (isset($cardOptions['vspace'])) ? $cardOptions['vspace'] : 0;
			$voffset = (isset($cardOptions['voffset'])) ? $cardOptions['voffset'] : 0;
			$suffix  = isset($cardDef->cardDirn) && ($cardDef->cardDirn != '') ? $cardDef->cardDirn : '-p';
			$suffix .= isset($cardDef->cardSize) && ($cardDef->cardSize != '') ? '-'.$cardDef->cardSize : '';
			$cardClass  = "cardNo_$cardNo card$suffix {$cardDef->classes} ";
			$cardClass .= $active ? ' activecard' : '';
			$cardClass .= $visible ? '' : ' card-back';
			if (isset($cardOptions['class']))
				$cardClass .= ' '.$cardOptions['class'];

			$frameClass = '';
			if (isset($cardOptions['frameclass']))
				$frameClass .= ' '.$cardOptions['frameclass'];
			$frameClass .= $active ? ' activeframe' : '';

			$leftOrRight = isset($cardOptions['right']) ? 'right' : 'left';
			$horizPosn = $hspace * $cardZIndex;
			$horizPosn += $hoffset;
			$style = "$leftOrRight: ".$horizPosn."px;";

			$topOrBottom = isset($cardOptions['bottom']) ? 'bottom' : 'top';
			$vertPosn = $vspace * $cardZIndex;
			$vertPosn += $voffset;
			$style .= "$topOrBottom: ".$vertPosn."px;";

			if (isset($cardOptions['id']))
				$cardId = $cardOptions['id'];
			else
				$cardId = "cardGUID_".$cardGUID;
			$frameId = 'fr_'.$cardId;

			$onClick = $hasClickEvent ? "onclick=$clickEvent(this)" : "";

			$counterhtml = '';
			if (isset($cardOptions['counterclass']) && ($cardOptions['counterclass'] != '')) {
				$counterClass = $cardOptions['counterclass'];
				$counterhtml = "<div class=\"counter {$counterClass}\"></div>";
			}

			$html  = "<div class='card-frame card-div $frameClass' id=$frameId style='$style'>\n";
			$html .= "<div class='card-face  card-div $cardClass' id=$cardId name=$cardId $onClick >$counterhtml</div>\n";
			$html .= "</div>\n";

			return $html;
		}

		function PlayCard($cardsList)
		{
			$myDBaseObj = $this->myDBaseObj;

			$calledWith = $cardsList;
			if (!is_array($cardsList)) {
				// Just a single card - convert to an array
				$cardsList = array($cardsList);
			}

			$playersHand = $myDBaseObj->GetHand();
			foreach ($cardsList as $cardNo) {
				$cardIndex = array_search($cardNo, $playersHand->cards);

				if ($cardIndex === false) {
										die("$cardNo is not in hand");
				} else {
					// Remove this card from the hand and add to played list
					unset($playersHand->cards[$cardIndex]);
				}
				$playersHand->played[] = $cardNo;
			}

			$myDBaseObj->UpdateCurrentHand($playersHand->cards, $playersHand->played);
			$this->playerNoOfCards = count($playersHand->cards);

			return true;
		}

		function CheckCanPlay(&$playersHand, $state = true)
		{
			foreach ($playersHand->cards as $key => $card) {
				$playersHand->canPlay[$key] = $state;
			}

			return count($playersHand->cards);
		}

		function OutputCards($playersHand)
		{
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<!-- OutputCards function called -->\n");
			
			$this->showingCards = true;

			$myDBaseObj = $this->myDBaseObj;

			$this->CheckCanPlay($playersHand);

			$visible = $myDBaseObj->CanShowCards();

			$this->OutputTableView($visible);

			$noOfCards = $this->OutputPlayersHand($playersHand, $visible);

			$this->OutputCardsOnTable($playersHand);

			//$this->OutputTableView($visible);

			if (!$visible && ($noOfCards > 0)) {
				$unhideButton = __('Click Here or Press Space Bar to Turn Cards Over', 'wpcardznet');
				WPCardzNetLibEscapingClass::Safe_EchoHTML('<div class="tablediv controls">');
				WPCardzNetLibEscapingClass::Safe_EchoHTML('<input type=button class="clickbutton" id=unhidecardsbutton name=unhidecardsbutton class=secondary value="'.$unhideButton.'" onclick="wpcardznet_unhidecardsClick();" >');
				WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");
			}
		}

		static function OutputCardsURLs($cardsSet, $ver = '')
		{
			self::OutputCardsImageCSS($cardsSet, 'cards-p', $ver);
			self::OutputCardsImageCSS($cardsSet, 'cards-l', $ver);
			self::OutputCardsImageCSS($cardsSet, 'cards-l-75', $ver);
		}

		static function OutputCardsImageCSS($cardsSet, $cardsId, $verNo)
		{
			$url = WPCARDZNET_URL."cards/$cardsSet/images/$cardsId.png";
			if ($verNo != '')
				$url .= "?ver=$verNo";

			$selector = "div#wpcardznet .$cardsId .card-face";
			$css = "background-image: url($url)";
			$html = "<style>$selector { $css } </style>\n";

			WPCardzNetLibEscapingClass::Safe_EchoHTML($html);
		}

		function OutputTableView($visible = true)
		{
			$myDBaseObj = $this->myDBaseObj;

			switch ($myDBaseObj->getOption('NextPlayerMimicDisplay')) {
				case WPCARDZNET_MIMICVISIBILITY_ALWAYS:
					$visible = true;
					break;

				case WPCARDZNET_MIMICVISIBILITY_NEVER:
					return;
			}

			$classes = 'tableview ';
			if (!$visible)
				$classes .= ' hidden-div';
			$html = "<div class=\"$classes\" >\n";
			$players = $myDBaseObj->currPlayersRec;

			$noOfPlayers = count($players);

			//$tableurl = WPCARDZNET_IMAGES_URL.'table.png';
			$html .= '<div class="tabletopdiv" >';
			//$html .= "<img src=\"$tableurl\" class=\"tabletop\" ></img>\n";
			$html .= "</div>\n";

			$playerurl = WPCARDZNET_IMAGES_URL.'player.png';

			$firstPlayerIndex = $myDBaseObj->thisPlayer->index;
			switch ($myDBaseObj->getOption('NextPlayerMimicRotation')) {
				case WPCARDZNET_MIMICMODE_ADMIN:
					$firstPlayerIndex = 0;
					break;

				case WPCARDZNET_MIMICMODE_DEALER:
					for ($index = 0; $index<$noOfPlayers; $index++) {
						if ($players[$index]->isFirstPlayer) {
							$firstPlayerIndex = $index;
							break;
						}
					}
					break;

				case WPCARDZNET_MIMICMODE_PLAYER:
				default:
					// Use default ....
					break;
			}

			for ($index = $firstPlayerIndex, $playerNo=1; $playerNo<=$noOfPlayers; $playerNo++, $index++) {
				if ($index >= $noOfPlayers)
					$index = 0;
				$iconId = "playericon-{$playerNo}of{$noOfPlayers}";
				$html .= "<div class=playericon id=$iconId name=$iconId>";
				$html .= "<img src=\"$playerurl\" ></img>\n";

				$player = $players[$index];
				$divName = 'player_'.$playerNo.'_of_'.$noOfPlayers;
				$playerName = $player->playerName;
				$divClass = 'player_view player_name';
				$divClass .= ($player->isActive) ? ' player_view_active' : ' player_view_inactive';

				$html .= "<div class=\"player_view_frame\" >";
				$html .= "<div class=\"$divClass\" >$playerName</div>\n";
				$html .= "</div></div>\n";
			}

			$html .= "</div>\n";

			WPCardzNetLibEscapingClass::Safe_EchoHTML($html);
		}

		function SortScores($object1, $object2)
		{
			if ($object1->roundScore == $object2->roundScore) {
				return $object1->score > $object2->score;
			}
			return $object1->roundScore - $object2->roundScore;
		}

		function GetLastScores()
		{
			return $this->myDBaseObj->GetLastRoundScores();
		}

		function GetGameScores()
		{
			$roundResults = $this->GetLastScores();
			usort($roundResults, array($this, 'SortScores'));

			return $roundResults;
		}

		function IsGameComplete()
		{
			return false;
		}

		function GetRoundScore($totalResult)
		{
			return $totalResult->tricksCount;
		}

		function OutputScores()
		{
			$this->showingScores = true;

			$myDBaseObj = $this->myDBaseObj;

			$noOfDeals = $myDBaseObj->GetNumberOfRounds();
			$scoresHeader = sprintf(_n("Score after %s Deal", "Score after %s Deals", $noOfDeals, 'wpcardznet'), $noOfDeals);
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div id='scores_header' name='scores_header' >$scoresHeader</div><br>\n");

			$totalResults = $this->GetGameScores();

			$onClick = 'onclick="wpcardznet_sortScoresClick(event)"';

			$html = "<table id=scores_table>\n";
			$html .= "<tr class='scores_table_header'>";
			$html .= "<td id='playerName' class='playerName' $onClick >Name</td>";
			$html .= "<td id='playerScore' class='playerScore' $onClick >Last Round</td>";
			$html .= "<td id='playerTotal' class='playerTotal' $onClick >Total</td></tr>\n";
			foreach ($totalResults as $index => $totalResult) {
				$playerName = $totalResult->playerName;
				$roundScore = $this->GetRoundScore($totalResult);
				$totalScore = $totalResult->score;

				$html .= "<tr class='scores_table_row' >";
				$html .= "<td class='playerName'>$playerName</td>";
				$html .= "<td class='playerScore'>$roundScore</td>";
				$html .= "<td class='playerTotal'>$totalScore</td>";
				$html .= "</tr>\n";
			}
			$html .= "</table><br>\n";

			WPCardzNetLibEscapingClass::Safe_EchoHTML($html);

			// Check if game is finished
			if ($this->IsGameComplete()) {
				WPCardzNetLibEscapingClass::Safe_EchoHTML(__("Game Complete", 'wpcardznet')."<br>\n");
			} else if ($myDBaseObj->IsNextPlayer()) {
				WPCardzNetLibEscapingClass::Safe_EchoHTML(__("You are the dealer", 'wpcardznet')."<br><br>\n");
				$dealButton = __("Click Here or Press Enter to Deal the Cards", 'wpcardznet');
				$buttonId = ($this->myDBaseObj->isDbgOptionSet('Dev_DisableAJAX')) ? 'dealcards' : 'ajaxdealcards';
				WPCardzNetLibEscapingClass::Safe_EchoHTML("<input type=submit class=\"clickbutton\" id=\"$buttonId\" name=\"$buttonId\" class=\"dealcards secondary\" value=\"$dealButton\" onclick=\"return wpcardznet_dealcardsClick();\" ><br>");
			} else {
				$dealerName = $myDBaseObj->GetNextPlayerName();
				printf( __( 'Waiting for %s to deal', 'wpcardznet' ), $dealerName );
			}
		}

		function IsMyTurn()
		{
			return $this->myDBaseObj->IsNextPlayer();
		}

		function GetUserPrompt()
		{
			return '';
		}

		function OutputPlayerId()
		{
			WPCardzNetLibEscapingClass::Safe_EchoHTML($this->myDBaseObj->GetPlayerIdHiddenTag());
		}

		function OutputTabletop()
		{
			$myDBaseObj = $this->myDBaseObj;

			// Show the tabletop
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div id=wpcardznet>\n");

			// Get filename of audio file from Settings
			$optionIds = array('mp3_Ready', 'mp3_RevealCards', 'mp3_SelectCard', 'mp3_PlayCard', 'mp3_Success');
			foreach ($optionIds as $optionId) {
				$mp3Path = $myDBaseObj->getOption($optionId);
				if ($mp3Path != '') {
					// i.e. /wp-content/plugins/wpcardznet/mp3/beep-07.wav
					if (WPCardzNetLibMigratePHPClass::Safe_strpos($mp3Path, "/") === false)
						$mp3Path = WPCARDZNET_UPLOADS_URL.'/mp3/'.$mp3Path;

					$id = "wpcardznet_".$optionId;
					WPCardzNetLibEscapingClass::Safe_EchoHTML('<audio id="'.$id.'" src="'.$mp3Path.'"></audio>'."\n");
				}
			}

			// Get the players cards
			$playersHand = $myDBaseObj->GetHand();

			// NOTE: Could Output the player info
			$playerInfo = $this->GetPlayerInfo();
			$colourClass = $this->IsMyTurn() ? " readyToPlay " : " waitingToPlay ";

			$classes = 'class="infobar-button reload" ';
			$events  = "onclick='wpcardznet_ReloadPage()' ";
			$headerButtonDivs = "<div id=reload-button {$classes} {$events}></div>";

			$fullscreenState = WPCardzNetLibUtilsClass::GetHTTPInteger('post', 'wpcardznetlib_fsState', 0);
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<input type='hidden' id='wpcardznetlib_fsState' name='wpcardznetlib_fsState' value='{$fullscreenState}'/>\n");

			$pageScale = WPCardzNetLibUtilsClass::GetHTTPNumber('post', 'wpcardznetlib_pageScale', '1.0');
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<input type='hidden' id='wpcardznetlib_pageScale' name='wpcardznetlib_pageScale' value='{$pageScale}'/>\n");

			if ($this->CanGoFullscreen())
			{
				$fsButtonClass = ($fullscreenState == 0) ? 'fullscreen' : 'normalscreen';
				$classes = "class='infobar-button {$fsButtonClass}' ";
				$events  = "onclick='wpcardznetlib_ToggleFullScreen(\"wpcardznet_body\")' ";
				$headerButtonDivs .= "<div id=fullscreen-button {$classes} {$events}></div>";
			}

			$headerButtonDivs .= $this->GetGameButtons($playersHand);

			$rulesLinkText = __('Click for Rules', 'wpcardznet');
			$gameName = $this->GetGameName();
			$rulesFile = 'rules_'.WPCardzNetLibMigratePHPClass::Safe_strtolower(str_replace(' ', '_', $gameName)).'.pdf';
			$rulesURL = plugins_url("games/rules/{$rulesFile}", dirname(__FILE__));
			$rulesLinkDiv = "<div class=ruleslink><a target=\"_blank\" href=\"{$rulesURL}\">{$rulesLinkText}</a></div>\n";

			$tickerTellDiv = "<div id=tickertell></div>";

			// Add a background div to fill in right side of info_placeholder on wide screens
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div class='info_placeholder info_row pageWidth $colourClass'></div>");

			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div class='info_items info_row pageWidth $colourClass'>");

			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div class='info_block info_left'>");
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div class=info>{$headerButtonDivs}</div>");
			WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>");
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div class='info_block info_middle'>");
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div>{$playerInfo}</div>");
			WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>");
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div class='info_block info_right'>");
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div class='info_rightItems'>");
			WPCardzNetLibEscapingClass::Safe_EchoHTML("{$rulesLinkDiv}{$tickerTellDiv}\n");
			WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>");
			WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>");

			WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<!-- End of info_placeholder -->\n");

			WPCardzNetLibEscapingClass::Safe_EchoHTML("<style>#wpcardznet_page { transform: scale({$pageScale}); }</style> \n");
			
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div id=wpcardznet_page class='page pageWidth'>\n");
			
			if ($this->IsRoundComplete())
				$this->OutputScores();
			else
				$this->OutputCards($playersHand);
			
			$buttonText = __("Timed Out: Click to Restart", 'wpcardznet');
			$divId = 'restartdiv';
			$buttonId = 'restartbutton';
			$onClick = ' onclick="wpcardznet_restartTickerTimer();" ';
			WPCardzNetLibEscapingClass::Safe_EchoHTML('<div id='.$divId.' id='.$divId.' class="refresh wpcardznet_hide" >');
			WPCardzNetLibEscapingClass::Safe_EchoHTML('<input type=button id='.$buttonId.' name='.$buttonId.$onClick.' class="secondary tablediv" value="'.$buttonText.'" >');
			WPCardzNetLibEscapingClass::Safe_EchoHTML('</div>');
			
			$tickerURL = WPCARDZNET_UPLOADS_URL.$myDBaseObj->GetUserTickFilename($myDBaseObj->userId);
			WPCardzNetLibEscapingClass::Safe_EchoScript("<script>\n");
			WPCardzNetLibEscapingClass::Safe_EchoScript('var tickerURL = "'.$tickerURL.'"'.";\n");
			
			if (defined('WPCARDZNET_JSDEBUG_ENABLED'))
				WPCardzNetLibEscapingClass::Safe_EchoScript("isDebugMode = true;\n");
			
			WPCardzNetLibEscapingClass::Safe_EchoScript("</script>\n");
			
			$this->OutputPlayerId();
			
			WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");	// End of wpcardznet_page div
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<!-- End of wpcardznet_page div -->\n");
			
			$promptMsg = $this->GetUserPrompt();
			if ($promptMsg != '')
				WPCardzNetLibEscapingClass::Safe_EchoHTML("<div class=\"prompt\"><div id=prompt_left></div><div id=prompt_right>{$promptMsg}</div></div>\n");

			WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");		
		}
		
		function CanGoFullscreen()
		{
			return true;
		}

		function GetGameButtons($playersHand)
		{
			return '';
		}
		
		function GetPlayerInfo()
		{
			$playerText = __('Player', 'wpcardznet');
			$playerInfo = "$playerText: ".$this->myDBaseObj->GetPlayerName();
			return "<div class=info_player>$playerInfo</div>";
		}
		
		function NotImplemented($funcName = 'function')
		{
			$className = get_class();
			WPCardzNetLibEscapingClass::Safe_EchoHTML("No implementation of $funcName in $className <br>\n");
			//WPCardzNetLibUtilsClass::ShowCallStack();
			//die;
		}
		
		
		function AddSoundOnRefresh($soundId)
		{
			WPCardzNetLibEscapingClass::Safe_EchoScript("<script>\n");
			WPCardzNetLibEscapingClass::Safe_EchoScript("var wpcardznet_SoundOnRefresh = '$soundId';\n");
			WPCardzNetLibEscapingClass::Safe_EchoScript("</script>\n");
		}
	}
}

?>