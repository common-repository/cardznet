<?php
/* 
Description: Code for a CardzNet Game
 
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
include WPCARDZNETLIB_INCLUDE_PATH.'wpcardznet_tabletop.php';

if (!class_exists('WPCardzNetWhistClass'))
{
	class WPCardzNetWhistCardsClass extends WPCardzNetCardsClass
	{
		function GetCardScore($suit, $card)
		{
			return 1;
		}
	}
	
	define('WPCARDZNET_GAMEOPTS_ENDSCORE_ID', 'endScore');
	define('WPCARDZNET_GAMEOPTS_ENDSCORE_DEF', 100);
		
	define('WPCARDZNET_GAMEOPTS_DEALSLIMIT_ID', 'dealsLimit');
	define('WPCARDZNET_GAMEOPTS_DEALSLIMIT_DEF', 0);
	
	define('WPCARDZNET_GAMEOPTS_WHIST_ENDSCORE_DEF', 5);
	
	class WPCardzNetWhistClass extends WPCardzNetGamesBaseClass // Define class
	{
		var $currTrick = null;
		var $noOfCardsToPassOn = 1;
		
		static function GetGameName()
		{
			return 'Whist';			
		}
		
		function AddGameIncludes($gameName, $plugin_version)
		{
			$gameName = self::GetGameName();
			parent::AddGameIncludes($gameName, $plugin_version);
		}
		
		static function GetGameIncludeDefs($gameName)
		{
			$gameName = self::GetGameName();
			$rslt = parent::GetGameIncludeDefs($gameName);
			
			return $rslt;
		}
		
		function __construct($myDBaseObj, $atts = array())
		{
			parent::__construct($myDBaseObj, $atts);
			
			$this->cardDefClass = 'WPCardzNetWhistCardsClass';
			$this->noOfCardsToPassOn = 0;
			
			$this->MIN_NO_OF_PLAYERS = 4;
			$this->MAX_NO_OF_PLAYERS = 4;
			
			$this->DEFAULT_ENDSCORE = WPCARDZNET_GAMEOPTS_WHIST_ENDSCORE_DEF;
			$this->DEFAULT_DEALSLIMIT = WPCARDZNET_GAMEOPTS_DEALSLIMIT_DEF;
			
			$this->showPoints = false;
		}
		
		// function GetCurrentPlayer()

		function GetNoOfCardsToPassOn()
		{
			return $this->noOfCardsToPassOn;
		}
		
		function GetPassedCardsTargetOffset()
		{
			// Black Maria always passes to the next player
			return 1;
		}
		
		function GetGameOptionsHTML($currOpts)
		{
			$html = parent::GetGameOptionsHTML($currOpts);

			$endOfGameText = __('End of game score', 'wpcardznet');
			if (isset($currOpts[WPCARDZNET_GAMEOPTS_ENDSCORE_ID]))
				$gameEndScore = $currOpts[WPCARDZNET_GAMEOPTS_ENDSCORE_ID];
			else
				$gameEndScore = $this->DEFAULT_ENDSCORE;
			
			$dealslimitText = __('Maximum no of deals', 'wpcardznet');
			$dealslimit = $this->DEFAULT_DEALSLIMIT;
			if (isset($currOpts[WPCARDZNET_GAMEOPTS_DEALSLIMIT_ID]))
			{
				$ourlimit = $currOpts[WPCARDZNET_GAMEOPTS_DEALSLIMIT_ID];
				if (is_integer($ourlimit) && ($ourlimit>0))
					$dealslimit = $ourlimit;
			}
			
			$html  = "<tr class='addgame_row_endgamescore'><td class='gamecell'>$endOfGameText</td>\n";
			$html .= "<td class='gamecell' colspan=2><input type=number id=gameMeta_EndScore name=gameMeta_EndScore value='$gameEndScore'></td></tr>\n";

			$html .= "<tr class='addgame_row_dealslimit'><td class='gamecell'>$dealslimitText</td>\n";
			$html .= "<td class='gamecell' colspan=2><input type=number id=gameMeta_DealsLimit name=gameMeta_DealsLimit value='$dealslimit'></td></tr>\n";

			return $html;
		}
		
		function ProcessGameOptions($gameOpts = array())
		{
			$gameOpts = parent::ProcessGameOptions($gameOpts);

			$gameOpts[WPCARDZNET_GAMEOPTS_ENDSCORE_ID] = WPCardzNetLibUtilsClass::GetHTTPInteger('post', 'gameMeta_EndScore', WPCARDZNET_GAMEOPTS_ENDSCORE_DEF);
			$gameOpts[WPCARDZNET_GAMEOPTS_DEALSLIMIT_ID] = WPCardzNetLibUtilsClass::GetHTTPInteger('post', 'gameMeta_DealsLimit', WPCARDZNET_GAMEOPTS_DEALSLIMIT_DEF);

			return $gameOpts;
		}
		
		function GetDealDetails($noOfPlayers = 0)
		{
			if ($noOfPlayers == 0)
			{
				$noOfPlayers = $this->myDBaseObj->GetNoOfPlayers();
			}
			
			$dealDetails = new stdClass();
			$dealDetails->noOfPacks = 1;
			$dealDetails->excludedCardNos = array();
			
			switch ($noOfPlayers)
			{
				case 3:
					$dealDetails->cardsPerPlayer = 17;
					$dealDetails->excludedCards = 'two-of-diamonds';
					break;
					
				case 4:
					$dealDetails->cardsPerPlayer = 13;
					$dealDetails->excludedCards = '';
					break;
				
				case 5:
					$dealDetails->cardsPerPlayer = 10;
					$dealDetails->excludedCards = 'two-of-diamonds,two-of-clubs';
					break;
					
				default:
					$dealDetails->gameCardsPerPlayer = 0;
					$dealDetails->errMsg = __('Invalid number of players', 'wpcardznet');
					return $dealDetails;
			}
			
			$excludedCardNos = array();
			$excludedList = explode(',', $dealDetails->excludedCards);
			$dealDetails->excludedCardNos = array();
			foreach ($excludedList as $excludedCard)
			{
				$cardNo = $this->GetCardNo($excludedCard);
				$dealDetails->excludedCardNos[$cardNo] = true;
			}
			
			return $dealDetails;
		}
		
		function DealCards($details = null, $roundState = '')
		{
			$myDBaseObj = $this->myDBaseObj;
			
			if ($myDBaseObj->getDbgOption('Dev_ShowMiscDebug'))
				$myDBaseObj->AddToStampedCommsLog("******* Dealing Cards *******");
			
			parent::DealCards($details, $roundState);
		}
		
		function GetLastScores()
		{
			$scores = parent::GetLastScores();
			
			$myDBaseObj = $this->myDBaseObj;

			// Turn the number of tricks into scores
			// First and Third players are partners
			// Second and Fourth Players are partners 
			for ($i=0; $i<2; $i++)
			{
				$roundScore = $scores[$i]->tricksCount + $scores[$i+2]->tricksCount - 6;
				if ($roundScore < 0)
					$roundScore = 0;
				
				$scores[$i]->roundScore = $scores[$i+2]->roundScore = $roundScore;
			}
			
			return $scores;
		}
		
/*		
		function SetNextPlayer($nextPlayerId)
		{
			parent::SetNextPlayer($nextPlayerId);
		}
*/
		function AddRoundScores($scores)
		{
			// Update the scores for the round on the database
			foreach ($scores as $score)
			{
				$newScore = $score->score + $score->roundScore;	
				$myDBaseObj->UpdateScore($score->playerId, $newScore);
			}

		}
		
		function GetWinner($trickCards)
		{
			$winnerObj = new stdClass();
			
			$trickSuitNo = 0;
			$trickRank = 0;
			$trickScore = 0;

			$noOfCards = count($trickCards);
			
			foreach ($trickCards as $index => $cardNo)
			{
				$cardDef = $this->GetCardDef($cardNo);
				if ($index == 0)
				{
					$trickSuitNo = $cardDef->suitNo;
					$trickRank = $cardDef->rank;
					$winnerIndex = $index;
				}
				else if (($trickSuitNo == $cardDef->suitNo) && ($trickRank < $cardDef->rank))
				{
					$trickSuitNo = $cardDef->suitNo;
					$trickRank = $cardDef->rank;
					$winnerIndex = $index;
				}
				else
				{

				}
				
				$trickScore += $cardDef->score;
			}
			
			$winnerObj->index = $winnerIndex;
			$winnerObj->score = $trickScore;
			
			return $winnerObj;
		}
	
		function OutputPlayersHand($playersHand, $visible)
		{
			$noOfCards = count($playersHand->cards);
			if ($noOfCards == 0) return 0;
			
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div class=\"tablediv playercards cards-p\">\n");
			$cardZIndex = 0;

			$cardOptions = array();
			$cardOptions['visible'] = $visible;
			foreach ($playersHand->cards as $index => $cardNo)
			{
				$cardDef = $this->GetCardDef($cardNo);
				$active = $this->IsMyTurn() && $playersHand->canPlay[$index];
				$cardOptions['active'] = $cardOptions['hasClick'] = $active;				
				WPCardzNetLibEscapingClass::Safe_EchoHTML($this->OutputCard($cardOptions, $cardDef, $cardZIndex++));
			}
			WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");
			
			return $noOfCards;
		}
	
		function OutputCardsOnTable($playersHand)
		{
			$myDBaseObj = $this->myDBaseObj;
			
			$trickVisible = ($myDBaseObj->GetRoundState() == WPCardzNetDBaseClass::ROUND_READY);
			$trickCards = $myDBaseObj->GetTrickCards();

			if ($trickCards != null)
			{
				WPCardzNetLibEscapingClass::Safe_EchoHTML('<div class="tablediv centrecards cards-p">');
				$cardOptions = array();
				$cardOptions['active'] = false;
				$cardOptions['visible'] = $trickVisible;
				
				$cardZIndex = 0;
				foreach ($trickCards as $cardNo)
				{
					$cardDef = $this->GetCardDef($cardNo);
					if (!$trickVisible) 
					{
						$cardDef->cardNo = 0;
						$cardDef->classes = 'card-hidden';
					}
					WPCardzNetLibEscapingClass::Safe_EchoHTML($this->OutputCard($cardOptions, $cardDef, $cardZIndex++));
				}
				WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");
			}		
						
			$lastTrick = $myDBaseObj->GetLastTrick();
			if ($lastTrick != null)
			{
				$points = 0;
				WPCardzNetLibEscapingClass::Safe_EchoHTML("<div class=\"lasttrick-frame\"> \n");
				WPCardzNetLibEscapingClass::Safe_EchoHTML('<div class="tablediv lasttrick cards-p">');
				$cardOptions['active'] = false;
				$cardOptions['visible'] = true;
				$cardZIndex = 0;
				$cardOptions = array();
				foreach ($lastTrick->cardsListArr as $cardNo)
				{
					$cardDef = $this->GetCardDef($cardNo);
					$points += $cardDef->score;
					WPCardzNetLibEscapingClass::Safe_EchoHTML($this->OutputCard($cardOptions, $cardDef, $cardZIndex++));
				}
				
				$winner = $lastTrick->winnerName;
				$titleText  = __("Last Trick", 'wpcardznet');
				$winnerText = __("Winner", 'wpcardznet');
				$pointsText = __("Points", 'wpcardznet');
				WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");
				
				WPCardzNetLibEscapingClass::Safe_EchoHTML('<div class="tablediv lastwinner">');
				WPCardzNetLibEscapingClass::Safe_EchoHTML("$titleText<br><br>$winnerText:<br>$winner");
				if ($this->showPoints)
					WPCardzNetLibEscapingClass::Safe_EchoHTML('<span class=" lastpoints">'."<br>$points $pointsText</span>");
				WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");
				WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");
			}	
			
		}
		
		function AllCardsPassed($trickId)
		{
			$myDBaseObj = $this->myDBaseObj;
			
			$offset = $this->GetPassedCardsTargetOffset();
			$myDBaseObj->RevertPassedCards($offset, WPCardzNetDBaseClass::LEAVE_CARDS);
									
			// Delete the trick 
			$myDBaseObj->DeleteTrick($trickId);
			
			// Change round state to ready (ROUND_READY)
			$myDBaseObj->UpdateRoundState(WPCardzNetDBaseClass::ROUND_READY);
			
			// Update the ticker
			$myDBaseObj->IncrementTicker();
	
			$this->GetCurrentPlayer();
			
			// "Cancel" the user prompt ... 
			$this->promptMsg = '';
		}
		
		function UpdateRound($cardNo)
		{
			$myDBaseObj = $this->myDBaseObj;
			
			$isPlayingHand = ($myDBaseObj->GetRoundState() == WPCardzNetDBaseClass::ROUND_READY);
			
			// Check that this is the correct player
			if ($isPlayingHand)
			{
				$playerId = WPCardzNetLibUtilsClass::GetHTTPInteger('post', 'playerId', 0);
				if ($myDBaseObj->nextPlayerId != $playerId) return 'Wrong Player';
			}
			
			// Check if a trick is in progress
//$trickCards = $myDBaseObj->GetTrickCards();
			
			// NOTE: Could Check that the card laid is valid 
			
			// Mark card as played
			$cardStatus = $this->PlayCard($cardNo);
			if (!$cardStatus)
			{
				return 'PlayCard returned false ';
			}
			
			$endOfRound = false;
			$playerAdvance = 1;
			// Add card to the trick
			$trickCards = $myDBaseObj->GetTrickCards();
			if ($trickCards == null)
			{
				$trickId = $myDBaseObj->NewTrick($cardNo);
				$trickComplete = false;
			}
			else
			{
				$noOfPlayers = $this->myDBaseObj->GetNoOfPlayers();
				$trickCards[] = $cardNo;
				
				$noOfCardsInTrick = $isPlayingHand ? $noOfPlayers : ($noOfPlayers * $this->GetNoOfCardsToPassOn());				
				$myDBaseObj->OutputDebugMessage("********* noOfCardsInTrick = $noOfCardsInTrick");
				
				$trickComplete = (count($trickCards) == $noOfCardsInTrick);
			
				$trickId = $myDBaseObj->AddToTrick($cardNo);
				
				if ($trickComplete)
				{
					if ($isPlayingHand)
					{
						$winnerObj = $this->GetWinner($trickCards);
						
						// If last card in trick .... update scores																																{
						$playerAdvance = $winnerObj->index+1;	
						
						$winnerPlayerId = $myDBaseObj->AdvancePlayer($playerAdvance);
						$winnerScore = $winnerObj->score;
								
						$myDBaseObj->CompleteTrick($winnerPlayerId, $winnerScore);
						
						$endOfRound = ($this->playerNoOfCards == 0);
						
						if ($endOfRound)
						{
							// Get Players scores for this round (format will depend on the game)
							$scores = $this->GetLastScores();
							
							// Add round scores to players
							$this->AddRoundScores($scores);
							
							// Mark this round as complete
							$myDBaseObj->UpdateRoundState(WPCardzNetDBaseClass::ROUND_COMPLETE);
							
							if ($this->IsGameComplete())
								$myDBaseObj->SetGameStatus(WPCardzNetDBaseClass::GAME_COMPLETE);
						}

					}
					else
					{
						$this->AllCardsPassed($trickId);
					}
				}
				
			}
			
			// Get next player			
			if ($isPlayingHand)
			{
				if ($endOfRound)
				{
					// Next dealer 
					$nextPlayerId = $myDBaseObj->GetNextDealer();
				}
				else
				{
					$nextPlayerId = $myDBaseObj->AdvancePlayer($playerAdvance);
				}
				
				// Update next player and tick count 
				$this->SetNextPlayer($nextPlayerId);			
				$this->GetCurrentPlayer();
			}
			else
			{
				// Update Tick Count 
				$myDBaseObj->IncrementTicker();
				
				// Get next playerId for shared logins
				$this->GetGameAndPlayer();
			}
							
			return 'OK';
		}
		
		function IsRoundComplete()
		{
			$roundComplete = ($this->myDBaseObj->GetUnplayedCardsCount() == 0);
			if (!$roundComplete) return false;
			
			if ( WPCardzNetLibUtilsClass::IsElementSet('post', 'showscores')
			  || WPCardzNetLibUtilsClass::IsElementSet('post', 'dealcards') )
				return true;

			$showScoresButton = __('Click Here to Show Scores', 'wpcardznet');
			
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div class=\"tablediv controls \">\n");
			WPCardzNetLibEscapingClass::Safe_EchoHTML('<input type=button class="clickbutton" id=showscores name=showscores class=secondary value="'.$showScoresButton.'" onclick="wpcardznet_AJAXshowscores();" >');
			WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");
			
			return false;			
		}
		
		function IsGameComplete()
		{
			$totalResults = $this->myDBaseObj->GetScores();
			if (count($totalResults) == 0) return false;
			
			$gameOpts = $totalResults[0]->gameOpts;
			
			foreach ($totalResults as $index => $totalResult)				
			{
				if ($totalResult->roundScore >= $gameOpts[WPCARDZNET_GAMEOPTS_ENDSCORE_ID])
					return true;
			}
			
			if ($gameOpts[WPCARDZNET_GAMEOPTS_DEALSLIMIT_ID] > 0)
			{
				$noOfCompleteRounds = $this->myDBaseObj->GetNumberOfRounds(true);
				if ($noOfCompleteRounds >= $gameOpts[WPCARDZNET_GAMEOPTS_DEALSLIMIT_ID])
					return true;
			}
			
			return false;			
		}
		
		function CheckCanLead(&$playersHand, $state = true)
		{
			return parent::CheckCanPlay($playersHand, $state);
		}
		
		function CheckCanPlay(&$playersHand, $state = true)
		{
			$myDBaseObj = $this->myDBaseObj;
			
			$trickCards = $this->myDBaseObj->GetTrickCards();
			if ($trickCards == null)
			{
				// This is the first card in the trick - check lead
				return $this->CheckCanLead($playersHand, $state);
			}
				
			$firstSuitNo = 0;		
			foreach ($trickCards as $key => $cardNo)
			{
				$firstCardDef = $this->GetCardDef($cardNo);
				$firstSuitNo = $firstCardDef->suitNo;
				break;
			}
			
			// Not the first card in the trick - must follow suit
			$noOfCardsToPlay = 0;
			foreach ($playersHand->cards as $key => $cardNo)
			{
				$cardDef = $this->GetCardDef($cardNo);
				$canPlay = ($cardDef->suitNo == $firstSuitNo);
				$playersHand->canPlay[$key] = $canPlay;
				if ($canPlay) $noOfCardsToPlay++;
			}
			
			if ($noOfCardsToPlay == 0)
				return WPCardzNetGamesBaseClass::CheckCanPlay($playersHand, $state);
				
			return $noOfCardsToPlay;
		}
		
		function IsMyTurn()
		{	
			return parent::IsMyTurn();		
		}
			
		function GetUserPrompt()
		{			
			return $this->promptMsg;
		}
		
		function OutputPlayerId()
		{
			if (!isset($this->showingCards)) return;
			parent::OutputPlayerId();
		}
		
		function OutputTabletop()
		{
			$myDBaseObj = $this->myDBaseObj;
			if (WPCardzNetLibUtilsClass::IsElementSet('post', 'cardNo'))
			{
				$cardNoPost = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'cardNo');
				if ($myDBaseObj->isDbgOptionSet('Dev_ShowMiscDebug'))
					$myDBaseObj->OutputDebugMessage("OutputTabletop: cardNoPost=$cardNoPost <br>\n");
				$cardParts = explode('card_', $cardNoPost);
				$cardNo = intval($cardParts[1]);
				$rtnStatus = $this->UpdateRound($cardNo);
				if ($myDBaseObj->isDbgOptionSet('Dev_ShowMiscDebug'))
					$myDBaseObj->OutputDebugMessage("UpdateRound returned $rtnStatus <br>\n");
			}
			else if (WPCardzNetLibUtilsClass::IsElementSet('post', 'dealcards'))
			{
				$gameId = WPCardzNetLibUtilsClass::GetHTTPInteger('post', 'gameId');

				// Check this user is the dealer ...
				if ($myDBaseObj->IsNextPlayer() && $this->IsRoundComplete())
				{
					$this->DealCards();
					$nextPlayerId = $myDBaseObj->AdvancePlayer();
					$this->SetNextPlayer($nextPlayerId);			
					$this->GetCurrentPlayer();
					WPCardzNetLibUtilsClass::UnsetElement('post', 'playerId');
				}
			}
			
			parent::OutputTabletop();
		}
	}
}

?>