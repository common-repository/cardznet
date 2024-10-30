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
include WPCARDZNET_GAMES_PATH.'wpcardznet_black_maria.php';

if (!class_exists('WPCardzNetHeartsCardsClass'))
{
	class WPCardzNetHeartsCardsClass extends WPCardzNetBlackmariaCardsClass
	{

		function GetCardScore($suit, $card)
		{
			$cardScore = 0;
			switch ($suit)
			{
				case 'spades': 
				{
					switch ($card)
					{
						case 'ace': return 0;
						case 'king': return 0;
					}
				}
			}
			
			return parent::GetCardScore($suit, $card);
		}

	}
	
	define('WPCARDZNET_GAMEOPTS_PASSPATTERN_ID', 'passPattern');
	define('WPCARDZNET_GAMEOPTS_PASSPATTERN_LEFT', 'left');
	define('WPCARDZNET_GAMEOPTS_PASSPATTERN_STD', 'std');
	define('WPCARDZNET_GAMEOPTS_PASSPATTERN_DEF', WPCARDZNET_GAMEOPTS_PASSPATTERN_STD);
	
	define('WPCARDZNET_GAMEOPTS_BREAKHEARTS_ID', 'breakHearts');
	define('WPCARDZNET_GAMEOPTS_BREAKHEARTS_DEF', WPCARDZNET_GAMEOPTS_STATE_NO);
	
	class WPCardzNetHeartsClass extends WPCardzNetBlackMariaClass // Define class
	{
		static function GetGameName()
		{
			return 'Hearts';			
		}
/*		
		function AddGameIncludes($gameName, $plugin_version)
		{
			parent::AddGameIncludes(parent::GetGameName(), $plugin_version);
		}
		
		static function GetGameIncludeDefs($gameName)
		{
			$gameName = parent::GetGameName();
			
			$rslt = parent::GetGameIncludeDefs($gameName);
			
			return $rslt;
		}
*/		
		function __construct($myDBaseObj, $atts = array())
		{			
			parent::__construct($myDBaseObj, $atts);

			$this->cardDefClass = 'WPCardzNetHeartsCardsClass';
			$this->noOfCardsToPassOn = 3;
			
			$this->MIN_NO_OF_PLAYERS = 4;
			$this->MAX_NO_OF_PLAYERS = 4;
			
			$this->MAX_SCORE = 26;
		}
		
		function AllCardsPassed($trickId)
		{
			parent::AllCardsPassed($trickId);
			
			$playerId = $this->SetFirstPlayer();
			$this->GetCurrentPlayer();
		}
		
		function SetFirstPlayer()
		{
			$myDBaseObj = $this->myDBaseObj;
			
			$playerId = 0;
			
			// Find the cardNo of the two of clubs ...
			$cardNo = $this->GetCardNo('two-of-clubs');			

			// Get the list of players hands
			$hands = $myDBaseObj->GetAllHands();

			// Check each players cards for the two of clubs
			$noOfPlayers = count($hands);
			foreach ($hands as $hand)
			{
				$cardsList = unserialize($hand->cardsList);
				if (in_array($cardNo, $cardsList))
				{
					$playerId = $hand->playerId;
					$this->SetNextPlayer($playerId);
					break;
				}
			}		
			
			return $playerId;	
		}
		
		function GetPassedCardsTargetOffset()
		{
			$myDBaseObj = $this->myDBaseObj;

			$gameOpts = $myDBaseObj->GetGameOptions();
			if (isset($gameOpts[WPCARDZNET_GAMEOPTS_PASSPATTERN_ID]))
			{
				switch($gameOpts[WPCARDZNET_GAMEOPTS_PASSPATTERN_ID])
				{
					case WPCARDZNET_GAMEOPTS_PASSPATTERN_LEFT:
						return 1;
						
					default:
						break;
				}
			}
				
			$noOfPlayers = $myDBaseObj->GetNoOfPlayers();
			$noOfRounds = $myDBaseObj->GetNumberOfRounds(true);

			$offsets = array(1, 3, 2, 0);
			$offsetIndex = ($noOfRounds % $noOfPlayers);
				
			$offset = $offsets[$offsetIndex];
			return $offset;
		}
		
		function GetGameOptionsHTML($currOpts)
		{
			$html = parent::GetGameOptionsHTML($currOpts);

			$passCardPattern = __('Pass Cards', 'wpcardznet');
			if (isset($currOpts[WPCARDZNET_GAMEOPTS_PASSPATTERN_ID]))
				$passPatternLast = $currOpts[WPCARDZNET_GAMEOPTS_PASSPATTERN_ID];
			else
				$passPatternLast = WPCARDZNET_GAMEOPTS_PASSPATTERN_DEF;
			
			$html .= "<tr class='addgame_row_passcardpattern'><td class='gamecell'>$passCardPattern</td>\n";
			$html .= "<td class='gamecell' colspan=2>";
			
			$passPatterns = array(
				WPCARDZNET_GAMEOPTS_PASSPATTERN_LEFT => __('Always Left', 'wpcardznet'),
				WPCARDZNET_GAMEOPTS_PASSPATTERN_STD => __('Left, Right, Opposite, None', 'wpcardznet'),
				);
				
			$html .= "<select id=gameMeta_passcardpattern name=gameMeta_passcardpattern>\n";
			foreach ($passPatterns as $passPattern => $passText)
			{
				$selected = ($passPattern == $passPatternLast) ? ' selected=""' : '';
				$html .= '<option value="'.$passPattern.'"'.$selected.'>'.$passText.'&nbsp;&nbsp;</option>'."\n";
			}
			$html .= "</select>\n";
			$html .= "</td></tr>\n";

			$breakHeartsText = __('Enforce Break Hearts', 'wpcardznet');
			if (isset($currOpts[WPCARDZNET_GAMEOPTS_BREAKHEARTS_ID]))
				$breakHeartsLast = $currOpts[WPCARDZNET_GAMEOPTS_BREAKHEARTS_ID];
			else
				$breakHeartsLast = WPCARDZNET_GAMEOPTS_BREAKHEARTS_DEF;
			
			$html .= "<tr class='addgame_row_breakHearts'><td class='gamecell'>$breakHeartsText</td>\n";
			$html .= "<td class='gamecell' colspan=2>";
			
			$selected = '';
			$breakHeartsOptions = array(
				WPCARDZNET_GAMEOPTS_STATE_NO => __('No', 'wpcardznet'),
				WPCARDZNET_GAMEOPTS_STATE_YES => __('Yes', 'wpcardznet'),
				);
				
			$html .= "<select id=gameMeta_breakHearts name=gameMeta_breakHearts>\n";
			foreach ($breakHeartsOptions as $breakHeartsOption => $breakHeartsText)
			{
				$selected = ($breakHeartsOption == $breakHeartsLast) ? ' selected=""' : '';
				$html .= '<option value="'.$breakHeartsOption.'"'.$selected.'>'.$breakHeartsText.'&nbsp;&nbsp;</option>'."\n";
			}
			$html .= "</select>\n";
			$html .= "</td></tr>\n";

			return $html;
		}
		
		function ProcessGameOptions($gameOpts = array())
		{
			$gameOpts = parent::ProcessGameOptions($gameOpts);

			$gameOpts[WPCARDZNET_GAMEOPTS_PASSPATTERN_ID] = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'gameMeta_passcardpattern', 'unknown');
			$gameOpts[WPCARDZNET_GAMEOPTS_BREAKHEARTS_ID] = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'gameMeta_breakHearts', 'unknown');

			return $gameOpts;
		}
		
		function DealCards($details = null, $roundState = '')
		{
			parent::DealCards($details, $roundState);
			
			$this->SetFirstPlayer();
		}
		
/*		
		function SetNextPlayer($nextPlayerId)
		{
			parent::SetNextPlayer($nextPlayerId);
		}
*/
		function CheckTricksForHearts()
		{
			// Get all tricks 
			$tricksList = $this->myDBaseObj->GetAllTricks();
			
			// Check all the tricks for a heart
			foreach ($tricksList as $trick)
			{
				$cards = unserialize($trick->cardsList);
				foreach ($cards as $cardNo)
				{
					if (($cardNo >= $this->twoOfHearts) && ($cardNo <= $this->aceOfHearts))
						return true;
				}
			}
			
			return false;
		}
		
		function CheckHandAllHearts($playersHand)
		{
			foreach ($playersHand->cards as $cardNo)
			{
				if ($cardNo < $this->twoOfHearts)
					return false;
				
				if ($cardNo > $this->aceOfHearts)
					return false;
			}
			
			return true;
		}
		
		function CheckCanLead(&$playersHand, $state = true)
		{
			$myDBaseObj = $this->myDBaseObj;
	
			// Check if this is the first lead
			if ($myDBaseObj->GetUnplayedCardsCount() == $myDBaseObj->GetNoOfCardsDealt())
			{
				// Only allow the two of clubs
				$twoOfClubs = $this->GetCardNo('two-of-clubs');
				foreach ($playersHand->cards as $key => $cardNo)
				{
					$isFirstLead = ($cardNo == $twoOfClubs);
					$playersHand->canPlay[$key] = $isFirstLead;
				}
				return true;
			}
			
			$gameOpts = $this->myDBaseObj->GetGameOptions();
			if (!isset($gameOpts[WPCARDZNET_GAMEOPTS_BREAKHEARTS_ID])
			  || ($gameOpts[WPCARDZNET_GAMEOPTS_BREAKHEARTS_ID] != WPCARDZNET_GAMEOPTS_STATE_YES))
			{
				return WPCardzNetGamesBaseClass::CheckCanPlay($playersHand, $state);
			}
			
			$this->twoOfHearts = $this->GetCardNo('two-of-hearts');
			$this->aceOfHearts = $this->GetCardNo('ace-of-hearts');
			
			// Check if a heart has been played ... if so allow all
			if ($this->CheckTricksForHearts())
			{
				return WPCardzNetGamesBaseClass::CheckCanPlay($playersHand, $state);
			}
			
			if ($this->CheckHandAllHearts($playersHand))
			{
				return WPCardzNetGamesBaseClass::CheckCanPlay($playersHand, $state);
			}
			
			// Allow any card except a heart
			foreach ($playersHand->cards as $key => $cardNo)
			{
				$isHeart = (($cardNo >= $this->twoOfHearts) && ($cardNo <= $this->aceOfHearts));
				$playersHand->canPlay[$key] = !$isHeart;
			}
			
		}
		
	}
}

?>