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
include WPCARDZNET_GAMES_PATH.'wpcardznet_whist.php';

if (!class_exists('WPCardzNetBlackMariaClass'))
{
	class WPCardzNetBlackmariaCardsClass extends WPCardzNetCardsClass
	{
		function GetCardScore($suit, $card)
		{
			$cardScore = 0;
			switch ($suit)
			{
				case 'hearts': return 1;
				case 'spades': 
				{
					switch ($card)
					{
						case 'ace': return 7; 
						case 'king': return 10;
						case 'queen': return 13; 
					}
				}
			}
			
			return parent::GetCardScore($suit, $card);
		}
	}
	
	define('WPCARDZNET_GAMEOPTS_ENABLESLAM_ID', 'slamEnabled');
	define('WPCARDZNET_GAMEOPTS_ENABLESLAM_DEF', WPCARDZNET_GAMEOPTS_STATE_NO);
	
	define('WPCARDZNET_GAMEOPTS_BLACKMARIA_ENDSCORE_DEF', 100);
	
	class WPCardzNetBlackMariaClass extends WPCardzNetWhistClass // Define class
	{
		const ROUND_PASSCARD = 'passcard';
		
		static function GetGameName()
		{
			return 'Black Maria';			
		}
		
		function __construct($myDBaseObj, $atts = array())
		{
			parent::__construct($myDBaseObj, $atts);
			
			$this->cardDefClass = 'WPCardzNetBlackmariaCardsClass';
			$this->noOfCardsToPassOn = 1;
			
			$this->MIN_NO_OF_PLAYERS = 3;
			$this->MAX_NO_OF_PLAYERS = 5;
			
			$this->MAX_SCORE = 43;
			
			$this->DEFAULT_ENDSCORE = WPCARDZNET_GAMEOPTS_BLACKMARIA_ENDSCORE_DEF;
			
			$this->showPoints = true;
		}
		
		function GetCurrentPlayer()
		{
			$myDBaseObj = $this->myDBaseObj;
			$roundState = $myDBaseObj->GetRoundState();
			$myDBaseObj->OutputDebugMessage("GetCurrentPlayer - Round state is $roundState <br>\n");
			if ($roundState == self::ROUND_PASSCARD)
			{
				$myDBaseObj->OutputDebugMessage("Round state is ROUND_PASSCARD <br>\n");
				
				// Get players list with "ready" State
				$cardsLeftWhenready = $this->myDBaseObj->cardsPerPlayer - $this->GetNoOfCardsToPassOn();

				$results = $myDBaseObj->GetPlayersReadyStatus("(noOfCards > $cardsLeftWhenready)");
				
				// Update the ready state for each player 		
				foreach ($results as $index => $result)
				{
					$myDBaseObj->SetPlayerReady($result->playerId, $result->ready);
				}
				
				// Set next player to Dealer ...
				$nextPlayerId = $myDBaseObj->SelectPlayerBeforeNext();
			}
			
			parent::GetCurrentPlayer();
			
			if ($roundState == self::ROUND_PASSCARD)
			{
				foreach ($results as $index => $result)
				{
					$myDBaseObj->SetPlayerReady($result->playerId, $result->ready);
				}

				// Ready state shows that player can play a card
				if ($myDBaseObj->IsPlayerReady())
				{
					$followingPlayerName = $this->GetPassedCardsTargetName();
					$this->promptMsg = __("Select a card to pass on to", 'wpcardznet')." $followingPlayerName!";			
				}
				else
					$this->promptMsg = __("Waiting for other players!", 'wpcardznet');			
			}
		}

// function GetNoOfCardsToPassOn()
// function GetPassedCardsTargetOffset()

		function GetPassedCardsTargetName()
		{
			$offset = $this->GetPassedCardsTargetOffset();			
			if ($offset == 0)
				return '';

			return $this->myDBaseObj->GetFollowingPlayerName($offset);
		}

		function GetGameOptionsHTML($currOpts)
		{
			$html = parent::GetGameOptionsHTML($currOpts);

			$slamScoresMaxText = __('Slam Scores Maximum', 'wpcardznet');
			if (isset($currOpts[WPCARDZNET_GAMEOPTS_ENABLESLAM_ID]))
				$slamScoresMaxLast = $currOpts[WPCARDZNET_GAMEOPTS_ENABLESLAM_ID];
			else
				$slamScoresMaxLast = WPCARDZNET_GAMEOPTS_ENABLESLAM_DEF;
			
			$html .= "<tr class='addgame_row_slamScoresMax'><td class='gamecell'>$slamScoresMaxText</td>\n";
			$html .= "<td class='gamecell' colspan=2>";
			
			$selected = '';
			$slamScoresMaxOptions = array(
				WPCARDZNET_GAMEOPTS_STATE_NO => __('No', 'wpcardznet'),
				WPCARDZNET_GAMEOPTS_STATE_YES => __('Yes', 'wpcardznet'),
				);
				
			$html .= "<select id=gameMeta_slamScoresMax name=gameMeta_slamScoresMax>\n";
			foreach ($slamScoresMaxOptions as $slamScoresMaxOption => $slamScoresMaxText)
			{
				$selected = ($slamScoresMaxOption == $slamScoresMaxLast) ? ' selected=""' : '';
				$html .= '<option value="'.$slamScoresMaxOption.'"'.$selected.'>'.$slamScoresMaxText.'&nbsp;&nbsp;</option>'."\n";
			}
			$html .= "</select>\n";
			$html .= "</td></tr>\n";

			return $html;
		}
		
		function ProcessGameOptions($gameOpts = array())
		{
			$gameOpts = parent::ProcessGameOptions($gameOpts);

			$gameOpts[WPCARDZNET_GAMEOPTS_ENABLESLAM_ID] = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'gameMeta_slamScoresMax', WPCARDZNET_GAMEOPTS_ENABLESLAM_DEF);

			return $gameOpts;
		}
		
// function GetDealDetails($noOfPlayers = 0)

		function DealCards($details = null, $roundState = '')
		{
			$myDBaseObj = $this->myDBaseObj;
			
			if ($myDBaseObj->getDbgOption('Dev_ShowMiscDebug'))
				$myDBaseObj->AddToStampedCommsLog("******* Dealing Cards *******");
			
			if ($details == null)
			{
				$details = $this->GetDealDetails();
			}
			
			if ($this->GetPassedCardsTargetOffset() > 0)
				$roundState = self::ROUND_PASSCARD;
			
			parent::DealCards($details, $roundState);
		}
		
		function GetLastScores()
		{
			// Do not call parent function as it counts number of tricks 
			$scores = $this->myDBaseObj->GetLastRoundScores();
			
			$myDBaseObj = $this->myDBaseObj;
			$slamPlayerId = 0;
			
			$slamScoreEnabled = false;
			$gameOpts = $myDBaseObj->GetGameOptions();
			if (isset($gameOpts[WPCARDZNET_GAMEOPTS_ENABLESLAM_ID]))
			{
				$slamScoreEnabled = ($gameOpts[WPCARDZNET_GAMEOPTS_ENABLESLAM_ID] !== WPCARDZNET_GAMEOPTS_STATE_NO);
			}
			
			if ($slamScoreEnabled)
			{
				// Scan for a slam (maximum score)
				foreach ($scores as $score)
				{
					if ($score->roundScore == $this->MAX_SCORE)
						$slamPlayerId = $score->playerId;
				}
			}
			
			if ($slamPlayerId != 0)
			{
				// Add Trick Scores to totals
				foreach ($scores as $index => $score)
				{
					if ($slamPlayerId != $score->playerId)
					{
						$scores[$index]->roundScore = $this->MAX_SCORE;
					}
					else
					{
						$scores[$index]->roundScore = 0;
					}
				}
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
			$myDBaseObj = $this->myDBaseObj;
						
			// Add Trick Scores to totals
			foreach ($scores as $score)
			{
				if ($score->roundScore == 0) continue;
				$newScore = $score->score + $score->roundScore;	
				$myDBaseObj->UpdateScore($score->playerId, $newScore);
			}
			
			// Parent method is not called 
		}
		
// function GetWinner($trickCards)
// function OutputPlayersHand($playersHand, $visible)
// function OutputCardsOnTable($playersHand)
// function AllCardsPassed($trickId)
// function UpdateRound($cardNo)
// function IsRoundComplete()
// function IsGameComplete()
// function CheckCanLead(&$playersHand, $state = true)

		function CheckCanPlay(&$playersHand, $state = true)
		{
			$myDBaseObj = $this->myDBaseObj;
			
			// NOTE: Could Use ready in $players_list[]
			if ($myDBaseObj->GetRoundState() == self::ROUND_PASSCARD)
			{
				$noOfCards = $myDBaseObj->GetNoOfCards();
				$canPlay = ($noOfCards > ($myDBaseObj->cardsPerPlayer - $this->GetNoOfCardsToPassOn()));
				
				$myDBaseObj->OutputDebugMessage('CheckCanPlay() $canPlay='.$canPlay);
				
				return WPCardzNetGamesBaseClass::CheckCanPlay($playersHand, $canPlay);
			}
		
			return parent::CheckCanPlay($playersHand, $state);
		}
		
		function IsMyTurn()
		{	
			$myDBaseObj = $this->myDBaseObj;
			
			if ($myDBaseObj->GetRoundState() == self::ROUND_PASSCARD)
			{
				$isMyTurn = ($myDBaseObj->GetNoOfCards() > ($this->myDBaseObj->cardsPerPlayer - $this->GetNoOfCardsToPassOn()));
			}
			else
				$isMyTurn = parent::IsMyTurn();		

			return $isMyTurn;
		}
			
// function GetUserPrompt()
// function OutputPlayerId()

		function GetRoundScore($totalResult)
		{
			return $totalResult->roundScore;
		}
				
// function OutputTabletop()

	}
}

?>