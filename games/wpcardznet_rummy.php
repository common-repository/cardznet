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

/*
Database Format (which may be specific to Canasta):

wp_cardznet_games

1 record per game 

	gameName 			- Always = Rummy
	gameStartDateTime	- Set when game added to DB
	gameEndDateTime		- Set when game ends or a new one is started
	gameStatus			- in-progress or complete
	gameLoginId			- The userID of the player adding the game
	gameNoOfPlayers		- Always 4 (for now)
	gameCardsPerPlayer	- Always 7 for 4 players
	gameCardFace		- Set when game added to DB
	firstPlayerId		- Initialised when cards dealt 
	nextPlayerId		- Updated after each player has played
	gameMeta			- Always blank
	gameTicker			- Updated each time the ticker updates
	gameReset			- Initialised to 0
	
wp_cardznet_rounds

1 record for each deal 

	gameId				- Link to the games table
	roundStartDateTime	- Set when round added to DB
	roundEndDateTime	- Set when round ends
	roundDeck			- The random list of the deck in order [serialised]
	roundNextCard		- The index number of the next card to be dealt from the deck
	roundState			- Always "ready" or "complete"
	roundMeta			- 
	
wp_cardznet_hands

4 records for each round (1 per player) 

	roundID				- Link to the games table
	playerId			- Link to the players table
	noOfCards			- Number of cards in the players hand
	cardsList			- List of cards in the players hand [serialised]
	playedList			- List of cards the player has played [serialised]
	handMeta

wp_cardznet_tricks

1 record for each time a meld is updated
Latest Record with matching "team colour" gives current melds

	roundID				- Link to the games table
	playerId			- Link to the players table
	playerOrder			- Unused
	cardsList			- List of melds (for one team) [serialised]
	winnerId
	complete
	score

*/

if (!class_exists('WPCardzNetRummyClass'))
{
	define('WPCARDZNET_HANDOPTS_NEWCARDS_ID', 'newCards');

	define('WPCARDZNET_ROUNDOPTS_NOOFSETS', 'noOfSets');
	define('WPCARDZNET_ROUNDOPTS_NOOFRUNS', 'noOfRuns');

	define('WPCARDZNET_RUMMY_DISCARDS_ID', '-1');
/*
	define('CARDNO_JOKER', 53);
*/	
	class WPCardzNetRummyCardsClass extends WPCardzNetCardsClass
	{
		function __construct()
		{
/*
			$this->groupBySuits = false;
*/			
			parent::__construct();
		}
/*		
		function GetCardSuits()
		{
			return array('diamonds', 'hearts', 'clubs', 'spades');
		}	

		function GetCardNames()
		{
			return array('three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'jack', 'queen', 'king', 'ace', 'two');
		}	
*/
		function GetCardScore($suit, $card)
		{
			switch ($card)
			{
				case 'jack':
				case 'queen':
				case 'king':
					return  10;
									
				case 'ace':
				case 'joker':
					return  15;
			}
			
			return parent::GetCardScore($suit, $card) + 1;
		}
	}
	
	class WPCardzNetRummyClass extends WPCardzNetGamesBaseClass // Define class
	{
/*
		var $currTrick = null;		
		var $minimumMeld = 50;
*/		
		// TODO - Spacing should depend on cards
		var $meldYSpacing = 20;
		var $meldXSpacing = 100;
		
		var $handXSpacing = 50;
		
		var $deckXSpacing = 120;

		static function GetGameName()
		{
			return 'Rummy';			
		}
		
		function __construct($myDBaseObj, $atts = array())
		{			
			parent::__construct($myDBaseObj, $atts);

			$this->cardDefClass = 'WPCardzNetRummyCardsClass';

			$this->MIN_NO_OF_PLAYERS = 4;	// TODO - No of players initialled fixed at 4
			$this->MAX_NO_OF_PLAYERS = 4;
			
			$this->NO_OF_CARDS_IN_PACK = 52;
		}
	
		function GetCardDef($cardSpec)
		{
			$rtnVal = parent::GetCardDef($cardSpec);
			
			$cardsGap = 10;
			$this->meldYSpacing = WPCardzNetCardDefClass::CardDIGITSIZE;
			$this->meldXSpacing = WPCardzNetCardDefClass::CardWIDTH + $cardsGap;
			$this->deckXSpacing = WPCardzNetCardDefClass::CardHEIGHT + $cardsGap;
/*
			$this->freezeXSpacing = WPCardzNetCardDefClass::CardWIDTH + $cardsGap;
*/			
			return $rtnVal;
		}
/*		
// function GetCurrentPlayer()
// function GetNoOfCardsToPassOn()
// function GetPassedCardsTargetOffset()
// function GetPassedCardsTargetName()
// function GetGameOptionsHTML($currOpts)
// function ProcessGameOptions($gameOpts = array())
*/
		function GetDealDetails($noOfPlayers = 0) 
		{
			if ($noOfPlayers == 0)
			{
				$noOfPlayers = $this->myDBaseObj->GetNoOfPlayers();
			}
			
			$dealDetails = new stdClass();
			$dealDetails->noOfPacks = 1;
			$dealDetails->noOfJokers = 0;
			$dealDetails->cardsPerPlayer = 7;		// TODO: No of cards varies with deal 
			
			// Get the number of rounds played
			$roundNumber = $this->myDBaseObj->GetNumberOfRounds();
			
			$noOfSetsList = array(2, 1, 0, 3, 2, 1, 0);
			$noOfRunsList = array(0, 1, 2, 0, 1, 2, 3);
			
			$dealDetails->noOfSets = $noOfSetsList[$roundNumber];
			$dealDetails->noOfRuns = $noOfRunsList[$roundNumber];

			return $dealDetails;
		}

		function DealCards($details = null, $roundState = '')
		{
			$myDBaseObj = $this->myDBaseObj;

			$roundId = parent::DealCards($details, $roundState);
			
			$roundMeta = array();
			$roundMeta[WPCARDZNET_ROUNDOPTS_NOOFSETS] = $details->noOfSets;
			$roundMeta[WPCARDZNET_ROUNDOPTS_NOOFRUNS] = $details->noOfRuns;
			$myDBaseObj->UpdateRoundOptions($roundMeta);

			$discardCards = array();			

			// Get the first card from the stock
			$nextCardNo = $myDBaseObj->GetNextCardFromDeck();
			$discardCards[] = $nextCardNo;
			
			// Add this to a trick with playerId=WPCARDZNET_RUMMY_DISCARDS_ID
			$this->myDBaseObj->NewTrick($discardCards, 0, WPCARDZNET_RUMMY_DISCARDS_ID);
		}
		
/*		
		function SetNextPlayer($nextPlayerId)
		{
			parent::SetNextPlayer($nextPlayerId);
		}
*/
// function AddRoundScores($scores)
// function GetWinner($trickCards)

		function OutputPlayersHand($playersHand, $visible)
		{
			$hasPickedUp = $this->HasPickedUp($playersHand);
			$pickedUpCards = array();
			
			if (isset($playersHand->handMetaDecode[WPCARDZNET_HANDOPTS_NEWCARDS_ID]))
				$pickedUpCards = $playersHand->handMetaDecode[WPCARDZNET_HANDOPTS_NEWCARDS_ID];

			$noOfCards = count($playersHand->cards);
			if ($noOfCards == 0) return 0;
			
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<!- Start OutputPlayersHand >\n");			
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div class=\"tablerow playercards-row \">\n");
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div class=\"tablediv playercards cards-p\">\n");
			$cardZIndex = 0;

			$cardOptions = array();
			$cardOptions['visible'] = $visible;
			foreach ($playersHand->cards as $index => $cardNo)
			{
				$cardDef = $this->GetCardDef($cardNo);
				$active = $this->IsMyTurn();
				$cardOptions['active'] = $hasPickedUp;	// Set players cards "active" 
				$cardOptions['hasClick'] = $active;	
				$cardOptions['frameclass'] = '';
				$cardOptions['hoffset'] = 0;
				$matchedIndex = array_search($cardNo, $pickedUpCards);
				if ($matchedIndex !== false)
				{
					// Remove the matched card from the list (so we don't duplicate it) 
					unset($pickedUpCards[$matchedIndex]);
					$cardOptions['frameclass'] = 'selectedcard';
				}
					
				WPCardzNetLibEscapingClass::Safe_EchoHTML($this->OutputCard($cardOptions, $cardDef, $cardZIndex++));
			}
			WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");	// End of tablediv
			WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");	// End of tablerow
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<!- End OutputPlayersHand >\n");

			return $noOfCards;
		}
	
		function GetCurrentMelds($teamColour)
		{
		
			$lastTrick = $this->myDBaseObj->GetCurrentTrick($teamColour);
			if ($lastTrick == null) return array();

			return $lastTrick->cardsListArr;
		}
	
		function GetMelds($playerId = 0)
		{
			$allMelds = $this->myDBaseObj->GetLastTricks($playerId);

			if ($allMelds == null)
				return array();
			
			return $allMelds;
		}

		function OutputMelds($allMelds, $hasLaid)
		{
$this->myDBaseObj->OutputDebugMessage(WPCardzNetLibUtilsClass::print_r($allMelds, '$allMelds', true));
			$cardOptions = array();
			$cardOptions['bottom'] = true;
			
			$meldDivId = 'ourmelds';
			$cardOptions['hasClick'] = true;
			$cardOptions['bottom'] = true;
			$showOurTricks = true;

			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div class=\"tablerow {$meldDivId}-row \">\n");
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div class=\"tablediv $meldDivId cards-p\">");
			$cardZIndex = 0;
			
			$cardOptions['vspace'] = $this->meldYSpacing;
			$cardOptions['hspace'] = 0;
			$cardOptions['hoffset'] = 0;
			
			if ($showOurTricks)
			{
				$cardZIndex = 0;
				$left = 0;
				$style = "left: ".$left."px;";
				$cardOptions['frameclass'] = 'frame-empty';
				$cardDef = $this->GetCardDef('card-empty');
				WPCardzNetLibEscapingClass::Safe_EchoHTML("<div id=\"melddiv_new\" class=\"meldframe\" style=\"$style\" >\n");
				WPCardzNetLibEscapingClass::Safe_EchoHTML($this->OutputCard($cardOptions, $cardDef, $cardZIndex++));
				WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");	// End of melddiv_new
			}
	
			// Now output the melds to the screen 
			foreach ($allMelds as $meld)
			{
$this->myDBaseObj->OutputDebugMessage(WPCardzNetLibUtilsClass::print_r($meld, '$meld', true));

				// This meld could be for any player ...
				if ($meld->playerId == WPCARDZNET_RUMMY_DISCARDS_ID)
					continue;
		
				// Get the cards list for each meld
				foreach ($meld->cardsListArr as $meldNo => $cards)
				{
//$this->myDBaseObj->OutputDebugMessage(WPCardzNetLibUtilsClass::print_r($cards, '$cards', true));

					$cardOptions['vspace'] = $this->meldYSpacing;					
					
					//if ($meldNo > 0) $meldOffset = 2 + $meldsOffsets[$meldNo];
					$left = ($meldNo * $this->meldXSpacing);
					$style = "left: ".$left."px;";
					
					$meldId = "melddiv_".$meldNo;
					$cardZIndex = 0;

					$cardOptions['frameclass'] = "";
					if (!$hasLaid)
						$cardOptions['frameclass'] .= " greyedOut";

					WPCardzNetLibEscapingClass::Safe_EchoHTML("<div id=\"$meldId\" class=\"meldframe\" style=\"$style\" >\n");
					
					// Output the cards "backwards" 
					for ($cardZIndex = count($cards) - 1; $cardZIndex >= 0; $cardZIndex--)
					{
						if ($cardZIndex == 0)
						{
							unset($cardOptions['class']);
						}

						$meldCard = $cards[$cardZIndex];
						$cardDef = $this->GetCardDef($meldCard);
									
						WPCardzNetLibEscapingClass::Safe_EchoHTML($this->OutputCard($cardOptions, $cardDef, $cardZIndex));
					}
					WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");
				}
			}

			WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");	// End of tablediv
			WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");	// End of tablerow
		}
	
		function OutputCardsOnTable($playersHand)
		{
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<!- Start OutputCardsOnTable >\n");
			// TODO - Function Not Fully Implemented
			$myDBaseObj = $this->myDBaseObj;
			
			$active = $this->IsMyTurn();
			
			// Get Players Activity State - Dormant, Selecting from Stock or Playing Cards
			$hasPickedUp = $this->HasPickedUp($playersHand);
					
			// Get the discards list
			$discardsList = $this->GetDiscards();

			WPCardzNetLibEscapingClass::Safe_EchoHTML($myDBaseObj->GetHiddenInputTag('meldXSpacing', $this->meldXSpacing)."\n");
			WPCardzNetLibEscapingClass::Safe_EchoHTML($myDBaseObj->GetHiddenInputTag('meldYSpacing', $this->meldYSpacing)."\n");
			WPCardzNetLibEscapingClass::Safe_EchoHTML($myDBaseObj->GetHiddenInputTag('handXSpacing', $this->handXSpacing)."\n");

			WPCardzNetLibEscapingClass::Safe_EchoHTML($myDBaseObj->GetHiddenInputTag('meldNoOfSets', $playersHand->roundMetaDecode['noOfSets'])."\n");
			WPCardzNetLibEscapingClass::Safe_EchoHTML($myDBaseObj->GetHiddenInputTag('meldNoOfRuns', $playersHand->roundMetaDecode['noOfRuns'])."\n");
			
			$noOfDiscards = count($discardsList);
			
			WPCardzNetLibEscapingClass::Safe_EchoHTML('<div id="prev-discards" class="tablediv centrecards cards-p">');
			$discardOptions = array();
			$cardZIndex = 0;
			for ($i = $noOfDiscards-4; $i<$noOfDiscards; $i++)
			{
				if ($i<0) continue;
				$cardNo = $discardsList[$i];
				
				$cardDef = $this->GetCardDef($cardNo);
				WPCardzNetLibEscapingClass::Safe_EchoHTML($this->OutputCard($discardOptions, $cardDef, $cardZIndex++));
			}
			WPCardzNetLibEscapingClass::Safe_EchoHTML('</div>');
								
			$discardOptions = array();
			if ($active)
			{
				// Discard pile can always be selected on initialization
				$discardOptions['active'] = true;
				if (!$hasPickedUp)	// Select click handler for discard pile 
					$discardOptions['hasClick'] = 'wpcardznet_clickDiscards';
				else
					$discardOptions['hasClick'] = 'wpcardznet_clickDiscard';
			}
		
			// Get the last card from discards list
			$discardTop = ($noOfDiscards > 0) ? end($discardsList) : 0;

			WPCardzNetLibEscapingClass::Safe_EchoHTML('<div id="stock-cards" class="tablediv centrecards cards-p">');
			$cardOptions = array();
			$cardOptions['active'] = false;
			$cardOptions['hspace'] = $this->deckXSpacing;

			// Output Stock and Discard Pile 			
			if (!$hasPickedUp && $active) // Check if discard pipe can be selected 
			{
				// Top card of discard pile can always be selected
				$cardOptions['active'] = $active;
			}

			$cardOptions['hasClick'] = 'wpcardznet_getCardFromStock';

			$this->cardsInStock = ($this->NO_OF_CARDS_IN_PACK - $playersHand->roundNextCard);
			if ($this->cardsInStock > 0)
			{
				// Add a non-visible card for the deck
				$cardDef = $this->GetCardDef('card-hidden');
			}
			else
			{
				// Stock Exhausted - Output a blank space
				$cardDef = $this->GetCardDef('card-empty');
				$cardOptions['frameclass'] = 'transparent';				
			}
			$cardOptions['id'] = 'stockcards';				
			WPCardzNetLibEscapingClass::Safe_EchoHTML($this->OutputCard($cardOptions, $cardDef, 0));
			unset($cardOptions['frameclass']);
			
			$cardOptions = array_merge($cardOptions, $discardOptions);
/*			
			if ($discardTop == 0)
			{
				$cardOptions['frameclass'] = 'frame-empty';
				$cardDef = $this->GetCardDef('card-empty');
			}
			else if (($freezeCardHTML != '') && $lastCardWasWild)
			{
				$cardDef = $this->GetCardDef('card-blank');
			}
			else
*/
				$cardDef = $this->GetCardDef($discardTop);
				
			$cardOptions['id'] = 'discards';
			WPCardzNetLibEscapingClass::Safe_EchoHTML($this->OutputCard($cardOptions, $cardDef, 1));
			
			WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");
/*
			if (($freezeCardHTML != '') && $lastCardWasWild)
				WPCardzNetLibEscapingClass::Safe_EchoHTML($freezeCardHTML);	
*/								
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div id=cards_info>Stock: {$this->cardsInStock}");
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<br>Discards: $noOfDiscards</div>\n");

			$playerId = $myDBaseObj->GetPlayerId();		
			$hasLaid = ($myDBaseObj->GetLastTricks($playerId) != null);
			
			$allMelds = $this->GetMelds();	
			
			$this->OutputMelds($allMelds, $hasLaid);
			
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<!- End OutputCardsOnTable >\n");
						
			return;			
		}
/*
		function CalculateScores()
		{
			$myDBaseObj = $this->myDBaseObj;

			$totals = array();
			
			$allMelds = $this->GetMelds();
			foreach ($allMelds as $teamIndex => $melds)
			{
				$totals[$teamIndex] = 0;
				foreach ($melds as $meldIndex => $meldCards)
				{
					$noOfCards = count($meldCards);
					
					if ($meldIndex == 0)
					{
						// Count any red threes 
						$redThreeScore = ($noOfCards < 4) ? ($noOfCards * 100) : 800;
						$totals[$teamIndex] += $redThreeScore;
						continue;
					}
					
					// Add the individual card scores
					foreach ($meldCards as $meldCard)
					{
						$cardDef = $this->GetCardDef($meldCard);
						$totals[$teamIndex] += $cardDef->score;
					}
					
				}
			}

			// Remove the score of the cards in players hands 
			$hands = $myDBaseObj->GetAllHands();

			foreach ($hands as $hand)
			{
				$teamIndex = $hand->playerColour;
				$cardsList = unserialize($hand->cardsList);
				
				if (count($cardsList) == 0)
				{
					// Add the bonus for going out 
					$totals[$teamIndex] += 100;
					continue;
				}
				
				foreach ($cardsList as $handCard)
				{
					$cardDef = $this->GetCardDef($handCard);
					$totals[$teamIndex] -= $cardDef->score;					
				}
			}

			return $totals;
		}

		function UpdateScores($totals)
		{
			// Get the list of players and update their scores 
			$scores = $this->GetLastScores();
			foreach ($scores as $score)
			{
				$teamId = $score->playerColour;
				$newScore = $totals[$teamId];

				$this->myDBaseObj->AddToScore($score->playerId, $newScore);
			}
			
		}

// function AllCardsPassed($trickId)
*/
		function UpdateRound($cardNo)
		{
			$myDBaseObj = $this->myDBaseObj;
			
			$cardsPlayed = array();
			
			$addToMeldsList = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'addedtomelds');
			if ($addToMeldsList != '')
			{
				$newMeldIds = explode(' ', $addToMeldsList);
$myDBaseObj->AddToLogSQL("-- ***** meldsList **$addToMeldsList**");
				$melds = array();
				foreach ($newMeldIds as $newMeldId)
				{
					// Decode Element to meld index and cardNo
					$meldAttr = explode('-', $newMeldId);
					if (count($meldAttr) != 2) 
					{
$myDBaseObj->AddToLogSQL("-- ***** Invalid Meld Element - $newMeldId");
						return 'Invalid Meld Element - $newMeldId';
					}
					
					$meldNo = -1;
					if (($meldAttr[0] != '') && is_numeric($meldAttr[0]))
					{
						$meldNo = intval($meldAttr[0]);
						$meldCardNo = intval($meldAttr[1]);
					}
					
					if ($meldNo < 0)
					{
						WPCardzNetLibUtilsClass::print_r($newMeldIds, '$newMeldIds');
						die ("Invalid meldNo");
					}
					
					if (!isset($melds[$meldNo]))
						$melds[$meldNo] = array();
					
					$melds[$meldNo][] = $meldCardNo;
					$cardsPlayed[] = $meldCardNo;
				}
				
				if (count($melds) > 0)
				{				
					$myDBaseObj->OutputDebugMessage(WPCardzNetLibUtilsClass::print_r($melds, 'UpdateRound - $melds', true)." <br>\n");					

					$playerId = $myDBaseObj->GetPlayerId();
					$lastTrick = $this->GetMelds($playerId);
					$existingMelds = $lastTrick[0]->cardsListArr;

					$myDBaseObj->OutputDebugMessage(WPCardzNetLibUtilsClass::print_r($existingMelds, 'UpdateRound - $existingMelds', true)." <br>\n");													
					// Merge the new melds with any exisiting melds 
					foreach ($melds as $meldIndex => $newMeld)
					{
						if (!isset($existingMelds[$meldIndex]))
							$existingMelds[$meldIndex] = array();
							
						foreach ($newMeld as $meldCardNo)
						{
							$existingMelds[$meldIndex][] = $meldCardNo;
						}
						
						// Sort the melds in ascending order
						sort($existingMelds[$meldIndex]);
					}

					// Save the complete melds list (for this player) as a new trick	
					$myDBaseObj->NewTrick($existingMelds);
				}		
			}
			
			if ($cardNo != 0)
			{
				// Add the card we discarded ..
				$cardsPlayed[] = $cardNo;
			}
			
			// Now update the cards in our hand
			$cardStatus = $this->PlayCard($cardsPlayed);
			if (!$cardStatus)
			{
$myDBaseObj->AddToLogSQL("-- ***** PlayCard returning false");
				return 'PlayCard returned false ';
			}
			
			if ($cardNo != 0)
			{
$myDBaseObj->AddToLogSQL("-- ***** cardNo: $cardNo");
				// Add this card to the discards pile
				$discardsList = $this->GetDiscards();
				$discardsList[] = $cardNo;
				$myDBaseObj->UpdateTrick($discardsList, WPCARDZNET_RUMMY_DISCARDS_ID);
			}
			
			$endOfRound = false;
/*

			// TODO - Detect end of round ...
			if ($cardNo != 0) // Has played a card 
			{
				// Check for hand or stock empty
				if ( ($this->playerNoOfCards == 0) || ($this->cardsInStock == 0) )
				{					
					$totals = $this->CalculateScores();
					$this->UpdateScores($totals);
					
					// Mark this round as complete
					$this->myDBaseObj->UpdateRoundState(WPCardzNetDBaseClass::ROUND_COMPLETE);
				}
			}
*/			
			return 'OK';
		}

		function HasPickedUp($playersHand)
		{
			// TODO: Rummy - check if player has picked up
			if (!isset($playersHand->handMetaDecode[WPCARDZNET_HANDOPTS_NEWCARDS_ID]))
				return false;

			return true;
		}
/*	
		function HasAlreadyPlayed($playersHand)
		{
			if ($playersHand->played == null)
				return false;
				
			$noOfCardsPlayed = count($playersHand->played);
			if ($noOfCardsPlayed == 0)
				return false;
				
			if ($noOfCardsPlayed > 4)	// There are only 4 red threes 
				return true;
				
			foreach ($playersHand->played as $cardIndex => $cardNo)
			{
				// Anything other than a red 3 means we have already played 
				if (!$this->IsRedThree($cardNo))
					return true;
			}
			
			return false;
		}
*/		
		function IsRoundComplete()
		{
			$roundState = $this->myDBaseObj->GetRoundState();
			$roundComplete = ($roundState == WPCardzNetDBaseClass::ROUND_COMPLETE);
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
/*		
		function IsGameComplete()
		{
			// TODO - Function Not Implemented
			return false;			
		}
		
// function CheckCanLead(&$playersHand, $state = true)
// function CheckCanPlay(&$playersHand, $state = true)
// function IsMyTurn()
// function GetUserPrompt()
// function OutputPlayerId()
		function GetRoundScore($totalResult)
		{
			return $totalResult->lastScore;
		}
*/		
		function OutputTabletop()
		{
			$myDBaseObj = $this->myDBaseObj;
			$userUIAction = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'UIAction');

			if ($userUIAction != '')
			{
				$handMeta = array();
				$playersHand = $myDBaseObj->GetHand();
				$hasPickedUp = $this->HasPickedUp($playersHand);
				$this->cardsInStock = ($this->NO_OF_CARDS_IN_PACK - $playersHand->roundNextCard);
				
$myDBaseObj->AddToLogSQL("-- ***** userUIAction: $userUIAction");

$clicksList = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'clicksList');
$clicks = explode(',', $clicksList);
foreach ($clicks as $click)
{
	if (WPCardzNetLibMigratePHPClass::Safe_trim($click) == '') break;
	$myDBaseObj->AddToLogSQL("-- ***** clicksList: $click");
}
				switch ($userUIAction)
				{
					case 'cardfrompack':
					case 'getcard':
						if ($hasPickedUp)
							break;	// Don't process pick up of stock/discard 
						
						$addedCardsList = array();

						if ($userUIAction == 'getcard')
						{
							if (WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'addedtomelds'))
								$rtnStatus = $this->UpdateRound(0);						
							
							// Add card to players hand from the deck
							$nextCardNo = $myDBaseObj->GetNextCardFromDeck();
						}
						else
						{
							$discardsList = $this->GetDiscards();
							
							// Get and Remove the top card (it should be in one of the melds)
							$nextCardNo = array_pop($discardsList);

							// Update the discards 
							$myDBaseObj->UpdateTrick($discardsList, WPCARDZNET_RUMMY_DISCARDS_ID);
/*						
							// Update the round (add the melds)
							$rtnStatus = $this->UpdateRound(0);						
*/
						}

						// Add the card to the hand
						$myDBaseObj->AddCardToHand($nextCardNo);

						// Add these cards to the recent cards list 
						$handMeta[WPCARDZNET_HANDOPTS_NEWCARDS_ID] = array($nextCardNo);						
						$myDBaseObj->UpdateHandOptions($handMeta);
						
						// Update Ticker ...
						$myDBaseObj->IncrementTicker();
						break;
					
				case 'discard':	
						// Player has discarded a card and completed their turn
						$cardNoPost = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'cardNo');
						if ($cardNoPost != '')
						{
							$cardParts = explode('card_', $cardNoPost);
							$cardNo = intval($cardParts[1]);
							$rtnStatus = $this->UpdateRound($cardNo);
							if ($myDBaseObj->isDbgOptionSet('Dev_ShowMiscDebug'))
								$myDBaseObj->OutputDebugMessage("UpdateRound returned $rtnStatus <br>\n");													
							
							// Clear the recent cards list 
							$handMetaEmpty = array();
							$myDBaseObj->UpdateHandOptions($handMetaEmpty);
/*
							if (isset($playersHand->roundMeta[WPCARDZNET_ROUNDOPTS_GOOUT_STATUS]))
							{
								$roundMeta = array();
								$myDBaseObj->UpdateRoundOptions($roundMeta);
							}
*/							
							// Advance to next player
							$nextPlayerId = $myDBaseObj->AdvancePlayer();
							$this->SetNextPlayer($nextPlayerId);			
							$this->GetCurrentPlayer();
							WPCardzNetLibUtilsClass::UnsetElement('post', 'playerId');
						}
						break;
						
				} // End of switch($userUIAction)
						
			}	
/*
			else if (WPCardzNetLibUtilsClass::IsElementSet('post', 'dealcards'))
			{
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
*/		
			parent::OutputTabletop();
		}
/*		
		function CanGoFullscreen()
		{
			return false;
		}
*/
		function GetGameButtons($playersHand)
		{
			$gameButtons = parent::GetGameButtons($playersHand);
		
			$classes = 'class="infobar-button swop" ';
			$events  = "onclick='wpcardznet_toggleOpponentsCards()' ";
			$gameButtons .= "<div id=switchcards-button {$classes} {$events}></div>";
			
			$active = $this->IsMyTurn();

			return $gameButtons;
		}

		function GetDiscards()
		{
			$cardsList = array();
			$discards = $this->myDBaseObj->GetCurrentTrick(WPCARDZNET_RUMMY_DISCARDS_ID);	
			if ($discards !== null)	
				$cardsList = $discards->cardsListArr;
			return $cardsList;
		}
		
	}
}

?>