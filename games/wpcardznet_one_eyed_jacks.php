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

/*
	Rules for "One Eyed Jacks" can be found at the following URLs:
	
	https://www.pagat.com/misc/jack.html
	https://www.denexa.com/blog/one-eyed-jack/
*/

include WPCARDZNETLIB_INCLUDE_PATH.'wpcardznet_tabletop.php';

if (!class_exists('WPCardzNetOneEyedJacksClass'))
{
	define('WPCARDZNET_CARDS_ON_TABLE', 100);

	define('WPCARDZNET_CARDSTATUS_NORMAL', 'normal');
	define('WPCARDZNET_CARDSTATUS_PENDING', 'pending');
	define('WPCARDZNET_CARDSTATUS_REMOVED', 'removed');

	define('WPCARDZNET_MARKER_LOCKED',				0x01000000000000);
	define('WPCARDZNET_MARKER_TWOEYEDJACK_ADD',		0x02000000000000);	// Two Eyed Jacks Add
	define('WPCARDZNET_MARKER_ONEEYEDJACK_REMOVE',	0x04000000000000);	// One eyed jacks Remove 
	define('WPCARDZNET_MARKER_REMOVED',				0x08000000000000);
	
	define('WPCARDZNET_MARKER_OPTIONSMASK',			0x0F000000000000);
	
	define('WPCARDZNET_MARKER_TOPLEFT',				0x10000000000000);
	define('WPCARDZNET_MARKER_TOPRIGHT',			0x20000000000000);
	define('WPCARDZNET_MARKER_BOTTOMLEFT',			0x40000000000000);
	define('WPCARDZNET_MARKER_BOTTOMRIGHT',			0x80000000000000);
	
	define('WPCARDZNET_CARDNO_TOPLEFT',				0);
	define('WPCARDZNET_CARDNO_TOPRIGHT',			9);
	define('WPCARDZNET_CARDNO_BOTTOMLEFT',			90);
	define('WPCARDZNET_CARDNO_BOTTOMRIGHT',			99);
	
	define('WPCARDZNET_MARKER_CORNERMASK',			0xF0000000000000);
	
	define('WPCARDZNET_MARKER_MASK',				0xFF000000000000);
	
	define('WPCARDZNET_GAMEOPTS_NOOFLINES_ID', 'noOfLines');
	define('WPCARDZNET_GAMEOPTS_NOOFLINES_DEF', 2);
			
	define('WPCARDZNET_ROUNDOPTS_ADDED_LINE', 'added-line');

	class WPCardzNetOneEyedJacksClass extends WPCardzNetGamesBaseClass // Define class
	{
		var $currTrick = null;		
		var $oneEyedJacks;
		var $twoEyedJacks;
		
		const NoOfCountersForLine = 5;
		const NoOfLinesForGame = 2;
		
		static function GetGameName()
		{
			return 'One Eyed Jacks';			
		}
		
		function __construct($myDBaseObj, $atts = array())
		{			
			parent::__construct($myDBaseObj, $atts);

			$this->MIN_NO_OF_PLAYERS = 2;
			$this->MAX_NO_OF_PLAYERS = 4;
			
			$this->DEFAULT_NOOFLINES = WPCARDZNET_GAMEOPTS_NOOFLINES_DEF;
			
			$this->NoOfLinesForGame = $myDBaseObj->getOption('NextPlayerMimicDisplay');
		}
	
		function GetJacksJS()
		{
			$oneEyedJacks = array('jack-of-hearts', 'jack-of-spades');
			$twoEyedJacks = array('jack-of-diamonds', 'jack-of-clubs');
			
			$this->oneEyedJacks = apply_filters('wpcardznet_filter_oneeyedjack', $oneEyedJacks);
			$this->twoEyedJacks = apply_filters('wpcardznet_filter_twoeyedjack', $twoEyedJacks);
			
			$jsCode = "<script>\n";
			$jsCode .= "oneEyedJacks = [";
			foreach ($this->oneEyedJacks as $index => $oneEyedJack)
			{
				if ($index > 0) $jsCode .= ', ';
				$jsCode .= "'$oneEyedJack'";
			}
			$jsCode .= "];\n";
			$jsCode .= "twoEyedJacks = [";
			foreach ($this->twoEyedJacks as $index => $twoEyedJack)
			{
				if ($index > 0) $jsCode .= ', ';
				$jsCode .= "'$twoEyedJack'";
			}
			$jsCode .= "];\n";
			$jsCode .= "</script>\n";
			
			return $jsCode;
		}
		
		function OutputPlayersHand($playersHand, $visible)
		{
			$noOfCards = count($playersHand->cards);
			if ($noOfCards == 0) return 0;
			
			$cardOptions = array('cardSize' => '', 'cardDirn' => '', 'vspace' => WPCardzNetCardDefClass::CardYSpace, 'hspace' => 0, 'hoffset' => 0);
		 	
			$cardOptions['visible'] = $visible;
			$cardOptions['class'] = '';
					
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div class=\"tablediv playercards cards-p\">\n");
			$cardZIndex = 0;
			foreach ($playersHand->cards as $index => $cardNo)
			{
				$cardDef = $this->GetCardDef($cardNo);
				$active = $this->IsMyTurn();
				$cardOptions['active'] = $cardOptions['hasClick'] = $active;
				WPCardzNetLibEscapingClass::Safe_EchoHTML($this->OutputCard($cardOptions, $cardDef, $cardZIndex++));
			}
			WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");
			
			return $noOfCards;
		}
	
		function AddCountersToCorners($myColour)
		{
			$cards = array(0,9,90,99);
			
			foreach ($cards as $cardIndex)
			{
				if ( isset($this->playedCards[$cardIndex])
				  && isset($this->playedCards[$cardIndex]->locked) )
				  	continue;
				
				$cornerCounter = new stdClass();
				$cornerCounter->colour = $myColour;
				$cornerCounter->state = WPCARDZNET_CARDSTATUS_NORMAL;
							
				$this->playedCards[$cardIndex] = $cornerCounter;
			}
				
		}
	
		function GetPlayedCards()
		{
			// Add counters to the corners
			$myColour = $this->myDBaseObj->GetPlayerColour();
			$myPlayerId = $this->myDBaseObj->GetPlayerId();

			if (isset($this->playedCards)) 
			{

				// Refresh the corner colours (playerId had probably changed!)
				$this->AddCountersToCorners($myColour);
				return;
			}
			
			$this->playedCards = array();
			
			$this->onTableList = $this->GetTilesOnTable();
			
			$noOfPlayers = count($this->onTableList);
			if ($noOfPlayers > 0)
			{
				$maxCardsPerPlayer = count($this->onTableList[0]->posns)+1;
				
				// Loop through the cards list
				$cardsListIndex = 0;
				
				// Scan through the list of cards in the order they were played
				for ($cardsListIndex = 0; $cardsListIndex <= $maxCardsPerPlayer; $cardsListIndex++)
				{
					for ($playerIndex = 0; $playerIndex<$noOfPlayers; $playerIndex++)
					{
						if (!isset($this->onTableList[$playerIndex]->posns[$cardsListIndex])) continue;
						
						$cardIndex = $this->onTableList[$playerIndex]->posns[$cardsListIndex];
						$isLocked = false;
						$cardState = WPCARDZNET_CARDSTATUS_NORMAL;
						$playerColour = $this->onTableList[$playerIndex]->playerColour;

						// Counters that have been removed are marked as such and ignored
						if (($cardIndex & WPCARDZNET_MARKER_REMOVED) != 0)
							continue;
						
						// One-Eyed Jacks are not included in list 
						if (($cardIndex & WPCARDZNET_MARKER_ONEEYEDJACK_REMOVE) != 0)
							continue;
						
						if (($cardIndex & WPCARDZNET_MARKER_LOCKED) != 0)
							$isLocked = true;
						
						if (($cardIndex & WPCARDZNET_MARKER_CORNERMASK) != 0)
						{
							switch ($cardIndex & WPCARDZNET_MARKER_CORNERMASK)
							{
								case WPCARDZNET_MARKER_TOPLEFT:	
									$cornerCardIndex = WPCARDZNET_CARDNO_TOPLEFT; break;
								case WPCARDZNET_MARKER_TOPRIGHT:
									$cornerCardIndex = WPCARDZNET_CARDNO_TOPRIGHT; break;
								case WPCARDZNET_MARKER_BOTTOMLEFT:
									$cornerCardIndex = WPCARDZNET_CARDNO_BOTTOMLEFT; break;
								case WPCARDZNET_MARKER_BOTTOMRIGHT:
									$cornerCardIndex = WPCARDZNET_CARDNO_BOTTOMRIGHT; break;
								
								default:
									die;
							}
							
							$this->playedCards[$cornerCardIndex] = new stdClass();
							$this->playedCards[$cornerCardIndex]->state = WPCARDZNET_CARDSTATUS_NORMAL;
							$this->playedCards[$cornerCardIndex]->colour = $playerColour;
							$this->playedCards[$cornerCardIndex]->locked = true;
						}
						
						$cardIndex &= ~WPCARDZNET_MARKER_MASK;
						
						$this->playedCards[$cardIndex] = new stdClass();
						$this->playedCards[$cardIndex]->state = $cardState;
						$this->playedCards[$cardIndex]->colour = $playerColour;

						if ($isLocked)
						{
							$this->playedCards[$cardIndex]->locked = true;
						}
						
					}

				}	
			}
			
			$this->AddCountersToCorners($myColour);
		}
	
		function ShowHistory()
		{
			$noOfPlayers = $this->myDBaseObj->GetNoOfPlayers(); // count($this->onTableList);
			//$playerId = $this->myDBaseObj->GetPlayerId();	
			$playerId = $this->myDBaseObj->nextPlayerId;	

			$noOfPlayersPlayed = count($this->onTableList);
			if ($noOfPlayersPlayed == 0) return;

			$playerIndex=0;
			if ($noOfPlayersPlayed >= $noOfPlayers)
			{
				for (; $playerIndex<$noOfPlayers; $playerIndex++)
				{
					if (!isset($this->onTableList[$playerIndex])) continue;
					
					if ($this->onTableList[$playerIndex]->playerId == $playerId)
					{
						$playerIndex++;
						break;
					} 
				}
			}
			
			if ($playerIndex>$noOfPlayers) exit(sprintf("Invalid playerIndex (%d) in ShowHistory", $playerIndex));

			$lastCardsList = array();
			for ($i=1; $i<$noOfPlayers; $i++, $playerIndex++)
			{
				if ($playerIndex >= $noOfPlayers) $playerIndex = 0;
				if (!isset($this->onTableList[$playerIndex])) continue;

				$posn = end($this->onTableList[$playerIndex]->posns) & ~WPCARDZNET_MARKER_MASK;
				if (isset($this->playedCards[$posn]))
					$this->playedCards[$posn]->recent = true;	
				
				$lastCardEntry = new stdClass();
				$lastCardEntry->playerIndex = $playerIndex;
				$lastCardEntry->posn = $posn;
				$lastCardEntry->cardNo = end($this->onTableList[$playerIndex]->played);			
				$lastCardEntry->playerId = $this->onTableList[$playerIndex]->playerId;			
				
				$lastCardsList[] = $lastCardEntry;						
			}
?>
<style>

</style>					
<?php
			// Output the last played cards 
			$cardOptions = array('vspace' => 60, 'hspace' => 0, 'hoffset' => 0, 'voffset' => 10);
			$cardOptions['frameclass'] = 'wpcardznet_hide';
			$cardOptions['hasClick'] = false;
			$cardNo = 27; // TODO
			$cardZIndex = 0;
			$html = '<div class="historyCards cards-p">';
			$histNo=0;
			foreach ($lastCardsList as $lastCardEntry)
			{
				$histNo++;
				$cardNo = $lastCardEntry->cardNo;
				$cardDef = $this->GetCardDef($cardNo);
				$cardOptions['id'] = "historyCard{$histNo}";
				$html .= $this->OutputCard($cardOptions, $cardDef, $cardZIndex++);
			}
			$html .= "</div>";

			// Output the history buttons
			$html .= '<div class="historyButtons">';
			$histNo=0;
			foreach ($lastCardsList as $lastCardEntry)
			{
				$histNo++;
				$onCLick = "wpcardznet_oej_showlastcardClick('$histNo');";
				$html .= "<div id=histNo".$histNo." name=histNo".$histNo." class=\"histNoBlock\">";
				$html .= "<div id=histText".$histNo." name=histText".$histNo." class=\"histTextBlock\" onclick=\"".$onCLick."\">";
				$html .=$histNo;
				$html .= $this->myDBaseObj->GetHiddenInputTag("histPosn{$histNo}", $lastCardEntry->posn);
				$html .= "</div>";
				$html .= "</div>\n";
			}
			$html .= "</div>";
			WPCardzNetLibEscapingClass::Safe_EchoHTML($html);

		}
	
		function GetBoardLayout()
		{
			$b = $this->GetCardNo('card-blank');
			
			$boardLayout = array(
				$b, 22, 21, 20, 19, 32, 33, 34, 35, $b,  
				 9, 12, 18, 17, 16, 29, 30, 31, 51, 48,  
				 8,  5, 11, 15, 14, 27, 28, 50, 44, 47,  
				 7,  4,  2, 24, 26, 39, 37, 41, 43, 46,  
				 6,  3,  1, 13, 25, 38, 52, 40, 42, 45,  
				45, 42, 40, 52, 38, 25, 13,  1,  3,  6,  
				46, 43, 41, 37, 39, 26, 24,  2,  4,  7,  
				47, 44, 50, 28, 27, 14, 15, 11,  5,  8,  
				48, 51, 31, 30, 29, 16, 17, 18, 12,  9,  
				$b, 35, 34, 33, 32, 19, 20, 21, 22, $b,  
			);
			
			return $boardLayout;
		}
	
		function OutputCardsOnTable($playersHand)
		{
			// info row is 60px and add 10px for border = 70px
			$pageHeight = (WPCardzNetCardDefClass::CardWIDTH_75pc * 10) + 50;
			$html  = "<style>div#wpcardznet .page { height: {$pageHeight}px; } </style>\n";

			$pageWidth = (WPCardzNetCardDefClass::CardHEIGHT_75pc * 10) + 350;
			$html .= "<style>div#wpcardznet .pageWidth { min-width: {$pageWidth}px; } </style>\n";
			$html .= "<style>div#wpcardznet .page { width: {$pageWidth}px; } </style>\n";

			$html .= "<div class=\"centrecards cards-l-75 \">\n";
			
			$boardLayout = array();
			
			$this->GetPlayedCards();
			$this->ShowHistory();
			
			// Add the board rows
			$boardLayout = $this->GetBoardLayout();

			$rowSpacing = WPCardzNetCardDefClass::CardWIDTH_75pc;
			$colSpacing = WPCardzNetCardDefClass::CardHEIGHT_75pc;
			
			$cardOptions = array(
				'hasClick' => true,
				'cardDirn' => '-l', 
				'cardSize' => '75', 
				'hspace' => $colSpacing, 
				'hoffset' => 0
				);
				
			$myColour = $this->myDBaseObj->GetPlayerColour();
			
			$cardOnTableNo = 0;
			for ($rowNo = 0; $rowNo < 10; $rowNo++)
			{
				$cardOptions['voffset'] = $rowNo * $rowSpacing; 
				
				for ($colNo = 0; $colNo < 10; $colNo++, $cardOnTableNo++)
				{

					$cardNo = $boardLayout[$cardOnTableNo];

					// Output a card
					$cardDef = $this->GetCardDef($cardNo);
					unset($cardOptions['counterclass']);
					unset($cardOptions['frameclass']);

					switch ($cardDef->classes)
					{
						case 'card-back':
						case 'card-blank':
							//unset($cardOptions['id']);
							unset($cardOptions['class']);
							$cardOptions['hasClick'] = false;
							break;
						
						default:
							$cardOptions['id'] = 'spaceontable_'.$rowNo.'_'.$colNo;
							$cardOptions['class'] = 'spaceontable spaceontable_'.$cardDef->classes;
							$cardOptions['hasClick'] = true;
							break;
					}
					if (isset($this->playedCards[$cardOnTableNo]))
					{
						$ourColour = $this->playedCards[$cardOnTableNo]->colour;
						
						if (isset($this->playedCards[$cardOnTableNo]->recent))
							$cardOptions['frameclass'] = 'recent-'.$ourColour;
						
						if ($this->playedCards[$cardOnTableNo]->state != WPCARDZNET_CARDSTATUS_REMOVED)
						{
							$locked = isset($this->playedCards[$cardOnTableNo]->locked);
							$counterMatch = ($ourColour == $myColour) || $locked ? '' : 'not-my-colour';
							$cardOptions['class'] = "$counterMatch hascounter cardontable_".$cardDef->classes;
							$cardOptions['counterclass'] = 'counterdiv-'.$ourColour;
							$cardState = !isset($this->playedCards[$cardOnTableNo]->locked) ? 'normal' : 'locked';
							$cardOptions['counterclass'] .= ' card-'.$cardState;
						}	
					}
					
					$cardHtml = $this->OutputCard($cardOptions, $cardDef, $colNo);

					$html .= $cardHtml;
				}
			}
			$html .= "</div>\n";
			
			WPCardzNetLibEscapingClass::Safe_EchoHTML($html);
		}
		
		function GetCurrentPlayer()
		{
			parent::GetCurrentPlayer();
		}

		function GetGameOptionsHTML($currOpts)
		{
			$html = parent::GetGameOptionsHTML($currOpts);

			$noOfLines = $this->DEFAULT_NOOFLINES;
			if (isset($currOpts[WPCARDZNET_GAMEOPTS_NOOFLINES_ID]))
				$noOfLines = $currOpts[WPCARDZNET_GAMEOPTS_NOOFLINES_ID];

			$noOfLinesText = __('No of lines for game', 'wpcardznet');
			
			$html .= "<tr class='addgame_row_noOfLines'><td class='gamecell'>$noOfLinesText</td>\n";
			$html .= "<td class='gamecell' colspan=2><input type=number id=gameMeta_NoOfLines name=gameMeta_NoOfLines value='$noOfLines'></td></tr>\n";

			return $html;
		}
		
		function ProcessGameOptions($gameOpts = array())
		{
			$gameOpts = parent::ProcessGameOptions($gameOpts);

			$gameOpts[WPCARDZNET_GAMEOPTS_NOOFLINES_ID] = WPCardzNetLibUtilsClass::GetHTTPInteger('post', 'gameMeta_NoOfLines', WPCARDZNET_GAMEOPTS_NOOFLINES_DEF);

			return $gameOpts;
		}
		
		function GetDealDetails($noOfPlayers = 0)
		{
			if ($noOfPlayers == 0)
			{
				$noOfPlayers = $this->myDBaseObj->GetNoOfPlayers();
			}
			
			$dealDetails = new stdClass();
			$dealDetails->noOfPacks = 2;
			$dealDetails->excludedCardNos = array();
			
			$noOfCardsList = array (
				2 => 7, 
				3 => 6, 
				4 => 6, 
				6 => 5, 
				8 => 4, 
				9 => 4, 
				10 => 3,
				12 => 3
				);
			
			$noOfTeamsList = array (
				2 => 2, 
				3 => 3, 
				4 => 2, 
				6 => 3, 
				8 => 2, 
				9 => 3, 
				10 => 2,
				12 => 3
				);
				
			if (isset($noOfCardsList[$noOfPlayers]))
			{
				$dealDetails->cardsPerPlayer = $noOfCardsList[$noOfPlayers];	
				$dealDetails->noOfTeams = $noOfTeamsList[$noOfPlayers];	
			}
			else
			{
				$dealDetails->cardsPerPlayer = 0;
				$dealDetails->noOfTeams = 0;	
				$dealDetails->errMsg = __('Invalid number of players', 'wpcardznet');
				return $dealDetails;
			}
					
			return $dealDetails;
		}
		
		function PlayerColour($details, $playerNo)
		{
			$ourColours = array('colourA', 'colourB', 'colourC');
			$noOfTeams = $details->noOfTeams;
			$colourIndex = $playerNo % $noOfTeams;
			return $ourColours[$colourIndex];
		}
/*		
		function DealCards($details = null, $roundState = '')
		{
			parent::DealCards($details, $roundState);		
		}
				
		function SetNextPlayer($nextPlayerId)
		{
			parent::SetNextPlayer($nextPlayerId);
		}
*/
		function GetWinner($trickCards)
		{
			$this->NotImplemented('GetWinner');
		}
		
		function GetTargetNo($targetId)
		{	
			$matches = array();
			preg_match("#^spaceontable_([0-9]+)_([0-9]+)#", $targetId, $matches);
			if (count($matches) < 2) return null;

			$rowNo = $matches[1];
			$colNo = $matches[2];
			
			if (($rowNo >= 10) || ($colNo >= 10)) return null;
			
			$targetNo = ($rowNo * 10) + $colNo;
			return $targetNo;
		}
		
		function AddMarkersToCards($targetNos, $marker = WPCARDZNET_MARKER_REMOVED)
		{
			if (!is_array($targetNos))
			{
				$targetNos = array($targetNos);		
			}

			$playersUpdated = array();
			
			$this->onTableList = $this->GetTilesOnTable();

			$cornerFlags = 0;
			foreach ($targetNos as $targetNo)
			{
				switch ($targetNo)
				{
					case WPCARDZNET_CARDNO_TOPLEFT:	
						$cornerFlags |= WPCARDZNET_MARKER_TOPLEFT; break;
					case WPCARDZNET_CARDNO_TOPRIGHT:
						$cornerFlags |= WPCARDZNET_MARKER_TOPRIGHT; break;
					case WPCARDZNET_CARDNO_BOTTOMLEFT:
						$cornerFlags |= WPCARDZNET_MARKER_BOTTOMLEFT; break;
					case WPCARDZNET_CARDNO_BOTTOMRIGHT:
						$cornerFlags |= WPCARDZNET_MARKER_BOTTOMRIGHT; break;
					default:
						break;
				}
			}
			
			foreach ($targetNos as $targetNo)
			{
				// Find the tricks that include the $targetNos
				foreach ($this->onTableList as $listIndex => $onTable)
				{
					$cards = $onTable->posns;
					$targetKey = array_search($targetNo, $cards);
					if ($targetKey === false)
						$targetKey = array_search($targetNo | WPCARDZNET_MARKER_TWOEYEDJACK_ADD, $cards);
					if ($targetKey !== false)
					{
						// Mark it as locked
						$this->onTableList[$listIndex]->posns[$targetKey] |= ($marker | $cornerFlags);
						$cornerFlags = 0;
						
						$playersUpdated[$listIndex] = true;
						break;
					}
				}
			}

			foreach (array_keys($playersUpdated) as $listIndex)
			{
				$onTable = $this->onTableList[$listIndex];
				
				// Found record with matching location					
				$playerId = $onTable->playerId;
					
				// Get the updated cards list
				$cards = $this->onTableList[$listIndex]->posns;

				// Write it back to the tricks table
				$this->myDBaseObj->UpdateTrick($cards, $playerId);										
			}
		}
		
		function AddCardToTable($cardNo, $playerId)
		{
			$myDBaseObj = $this->myDBaseObj;
			$trickCards = $myDBaseObj->GetTrickCards(false, $playerId);
			if ($trickCards == null)
			{
				$noOfTricks = $myDBaseObj->GetTricksCount() + 1;
				$trickId = $myDBaseObj->NewTrick($cardNo, $noOfTricks);
			}
			else
			{
				$myDBaseObj->AddToTrick($cardNo);
			}
		}
		
		function IsOneEyedJack($cardNo)
		{
			$cardDef = $this->GetCardDef($cardNo);
			$cardName = $cardDef->name; 

			$isOneEyed = in_array($cardName, $this->oneEyedJacks);

			return $isOneEyed;
		}
		
		function CheckForNewLines($targetNo)
		{
			// Check for a row of 5 counters - Array of offsets to next counter
			$matchOffsets = array(1, 11, -10, -9);
// WPCardzNetLibEscapingClass::Safe_EchoHTML('<CheckForNewLines - $targetNo:'."$targetNo <br>\n");
			$maxLength = 0;
			$this->GetPlayedCards();
			$ourColour = $this->playedCards[$targetNo]->colour;
			
			$linesCount = 0;
			
			$rows = array();
			
			$startColumn = $targetNo % 10;
				
			foreach ($matchOffsets as $scanNo => $matchOffset)
			{
				$srchIndexes = array($targetNo, $targetNo);				
				$scanDone = array(false, false);
					
				$lockedCount = 0;
				$countersInRow = array($targetNo);
				
				for ($loopNo = 1; $loopNo <= 4; $loopNo++)
				{
					if ($scanDone[0] && $scanDone[1])
						break;
						
					if (count($countersInRow) >= self::NoOfCountersForLine)
						break;
						
					$scanOffset = $matchOffset;
					for ($scanIndex = 0; $scanIndex < 2; $scanIndex++)
					{
						// Scan to next target card
						if (!$scanDone[$scanIndex])
						{
							$srchIndexes[$scanIndex] += $scanOffset;
							$srchIndex = $srchIndexes[$scanIndex];
							
							$column = $srchIndex % 10;
							
							if (($srchIndex >= WPCARDZNET_CARDS_ON_TABLE) || ($srchIndex < 0))
								$scanDone[$scanIndex] = true;	// Row has rolled around
							else if (($scanIndex == 0) && ($column < $startColumn))
								$scanDone[$scanIndex] = true;	// Column has rolled around
							else if (($scanIndex == 1) && ($column > $startColumn))
								$scanDone[$scanIndex] = true;	// Column has rolled around
							else if (!isset($this->playedCards[$srchIndex]))
								$scanDone[$scanIndex] = true;	// No tile on this card
							else 
							{
								$counterColour = $this->playedCards[$srchIndex]->colour;
								if ($counterColour != $ourColour)
								{
									$scanDone[$scanIndex] = true;	// Colour not matched
								}
								else
								{
									if (isset($this->playedCards[$srchIndex]->locked))
									{
										if ($lockedCount == 0)
											$lockedCount++;
										else
										{
											$scanDone[$scanIndex] = true;	// Locked count exceeded											
											continue;							
										}
									}
									$countersInRow[] = $srchIndex;
								}							
							}

						}

						$scanOffset = 0 - $scanOffset;
					}
					
				}

				if (count($countersInRow) >= self::NoOfCountersForLine)
				{
					$linesCount++;

					foreach ($countersInRow as $lockedPosn)
						$this->playedCards[$lockedPosn]->locked = true;

					$this->AddMarkersToCards($countersInRow, WPCARDZNET_MARKER_LOCKED);
				}
			}
			
			$roundMeta = $this->myDBaseObj->GetRoundMeta();
			if ($linesCount > 0)
			{
				$roundMeta[WPCARDZNET_ROUNDOPTS_ADDED_LINE] = true;	
				$this->myDBaseObj->UpdateRoundOptions($roundMeta);
			}
			else if (isset($roundMeta[WPCARDZNET_ROUNDOPTS_ADDED_LINE]))
			{
				unset($roundMeta[WPCARDZNET_ROUNDOPTS_ADDED_LINE]);
				$this->myDBaseObj->UpdateRoundOptions($roundMeta);
			}
			
			$gameEnded = false;
			
			if ($linesCount > 0)
			{
				$lockedCount = 0;
				
				// Get the number of locked counters
				foreach ($this->playedCards as $playedCard)
				{
					if ($playedCard->colour != $ourColour) continue;
					if (isset($playedCard->locked)) $lockedCount++;
				}
				
				$gameOpts = $this->myDBaseObj->GetGameOptions();
				if (isset($gameOpts[WPCARDZNET_GAMEOPTS_NOOFLINES_ID]))
					$noOfLinesForGame = $gameOpts[WPCARDZNET_GAMEOPTS_NOOFLINES_ID];
				else
					$noOfLinesForGame = self::NoOfLinesForGame;

				$noOfCountersReqd = $noOfLinesForGame * (self::NoOfCountersForLine-1);
				$gameEnded = ($lockedCount >= $noOfCountersReqd);
			}
 			
 			return $gameEnded;
		}
		
		function UpdateRound($cardNo, $targetId)
		{
			$myDBaseObj = $this->myDBaseObj;

			// Check that this is the correct player
			$playerId = WPCardzNetLibUtilsClass::GetHTTPInteger('post', 'playerId', 0);
			if ($myDBaseObj->nextPlayerId != $playerId) return 'Wrong Player';
			
			// NOTE: Could Check that the card laid is valid 
			
			// Mark card as played
			$cardStatus = $this->PlayCard($cardNo);
			if (!$cardStatus)
			{
				return 'PlayCard returned false ';
			}
						
			// Get another card from the deck
			$nextCardNo = $myDBaseObj->GetNextCardFromDeck();
			if ($nextCardNo != null)
				$myDBaseObj->AddCardToHand($nextCardNo);
						
			// Add card to the trick
			$targetNo = $this->GetTargetNo($targetId);
			if ($targetNo == null) return "Invalid Target Id";
			
			if ($this->IsOneEyedJack($cardNo))
			{
				// Add this card to list of played cards
				$this->AddCardToTable($targetNo | WPCARDZNET_MARKER_ONEEYEDJACK_REMOVE, $playerId);				
				// Mark the original card played as removed
				$this->AddMarkersToCards($targetNo);				
			}
			else
			{
				$this->AddCardToTable($targetNo, $playerId);				
				$endOfRound = $this->CheckForNewLines($targetNo);
				if ($endOfRound)
				{
					// Mark this round as complete
					$myDBaseObj->UpdateRoundState(WPCardzNetDBaseClass::ROUND_COMPLETE);
					$myDBaseObj->SetGameStatus(WPCardzNetDBaseClass::GAME_COMPLETE);
					
					// Bump the ticker value to notify other players
					$myDBaseObj->IncrementTicker();
					return 'OK';
				}
			}
			
			// Get next player			
			$nextPlayerId = $myDBaseObj->AdvancePlayer(1);
			$this->SetNextPlayer($nextPlayerId);			
			$this->GetCurrentPlayer();
							
			return 'OK';
		}
		
		function IsRoundComplete()
		{
			return ($this->myDBaseObj->GetRoundState() == WPCardzNetDBaseClass::ROUND_COMPLETE);
		}
		
		function IsGameComplete()
		{
			return $this->IsRoundComplete();
		}
				
		function OutputScores()
		{
			// Create Empty Players Hand
			$playersHand = new stdClass();
			$playersHand->cards = array();
			
			$this->OutputCards($playersHand);
		}

		function OutputTabletop()
		{
			WPCardzNetLibEscapingClass::Safe_EchoScript($this->GetJacksJS());
			
			$myDBaseObj = $this->myDBaseObj;
			if (WPCardzNetLibUtilsClass::IsElementSet('post', 'cardId') && WPCardzNetLibUtilsClass::IsElementSet('post', 'targetId'))
			{
				$cardId = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'cardId');
				$cardNo = $this->GetCardNo($cardId);
				$targetId = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'targetId');
				$rtnStatus = $this->UpdateRound($cardNo, $targetId);
			}
			else if (WPCardzNetLibUtilsClass::IsElementSet('post', 'dealcards'))
			{
/*
				$gameId = WPCardzNetLibUtilsClass::GetHTTPInteger('post', 'gameId');

				// NOTE: Check this user is the dealer ...
				if ($myDBaseObj->IsNextPlayer() && $this->IsRoundComplete())
				{
					$this->DealCards();
					$nextPlayerId = $myDBaseObj->AdvancePlayer(1, 0);
					$this->SetNextPlayer($nextPlayerId);			
					$this->GetCurrentPlayer();
				}
*/
			}
			
			parent::OutputTabletop();
			
			$roundMeta = $this->myDBaseObj->GetRoundMeta();
			if (isset($roundMeta[WPCARDZNET_ROUNDOPTS_ADDED_LINE]))
			{
				// Add object to trigger success sound
				WPCardzNetLibEscapingClass::Safe_EchoHTML($this->myDBaseObj->GetHiddenInputTag('isSuccess', 1)."\n");
			}
			
		}
		
		function GetPlayerInfo()
		{
			$myColour = $this->myDBaseObj->GetPlayerColour();
			$counterColour = 'counterdiv-'.$myColour;
			
			$playerInfo = "<div class=\"counter {$counterColour}\"></div>";
			
			$playerInfo .= parent::GetPlayerInfo();
		
			if ($this->IsRoundComplete())
			{
				$gameComplete = '&nbsp;-&nbsp;'.__('Game COMPLETE', 'wpcardznet');
				$playerInfo .= "<div class=info_player>$gameComplete</div>";
			}
			
			return $playerInfo;
		}
		
		function GetTilesOnTable($playerId = 0)
		{
			$results = $this->myDBaseObj->GetAllTricks($playerId, array('Hands' => true));
			
			$tricks = array();
			foreach ($results as $result)
			{
				$trick = new stdclass();
				$trick->playerId = $result->playerId;
				$trick->playerColour = $result->playerColour;
				$trick->posns = unserialize($result->cardsList);
				$trick->played = unserialize($result->playedList);
				$tricks[] = $trick;
			}
			

			return $tricks;			
		}
		
	}
}

?>