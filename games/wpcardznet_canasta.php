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

	gameName 			- Always = Canasta
	gameStartDateTime	- Set when game added to DB
	gameEndDateTime		- Set when game ends or a new one is started
	gameStatus			- in-progress
	gameLoginId			- The userID of the player adding the game
	gameNoOfPlayers		- Always 4
	gameCardsPerPlayer	- Always 11
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
	roundMeta			- Used for "Go Out" request handshake implementation
	
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

if (!class_exists('WPCardzNetCanastaClass'))
{
	define('WPCARDZNET_HANDOPTS_NEWCARDS_ID', 'newCards');
	define('WPCARDZNET_HANDOPTS_REDTHREE_ID', 'redthree');

	define('WPCARDZNET_ROUNDOPTS_GOOUT_REQUESTID', 'goout-reqid');
	define('WPCARDZNET_ROUNDOPTS_GOOUT_AUTHID', 'goout-authid');
	define('WPCARDZNET_ROUNDOPTS_GOOUT_STATUS', 'goout-status');
	
	define('WPCARDZNET_ROUNDOPTS_GOOUT_REQUESTED', 'requested');
	define('WPCARDZNET_ROUNDOPTS_GOOUT_AUTHORISED', 'authorised');
	define('WPCARDZNET_ROUNDOPTS_GOOUT_DENIED', 'denied');
	
	define('WPCARDZNET_CANASTA_DISCARDS_ID', '-1');

	define('CARDNO_THREE_DIAMONDS', 1);
	define('CARDNO_THREE_HEARTS', 2);
	
	define('CARDNO_TWO_DIAMONDS', 49);
	define('CARDNO_TWO_HEARTS', 50);
	define('CARDNO_TWO_CLUBS', 51);
	define('CARDNO_TWO_SPADES', 52);
	
	define('CARDNO_JOKER', 53);
	
	class WPCardzNetCanastaCardsClass extends WPCardzNetCardsClass
	{
		function __construct()
		{
			$this->groupBySuits = false;
			
			parent::__construct();
		}
		
		function GetCardSuits()
		{
			return array('diamonds', 'hearts', 'clubs', 'spades');
		}	

		function GetCardNames()
		{
			return array('three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'jack', 'queen', 'king', 'ace', 'two');
		}	

		function GetCardScore($suit, $card)
		{
			switch ($card)
			{
				case 'three':
					switch ($suit)
					{
						case 'hearts':
						case 'diamonds':
							return  100;
					}
					// Drop through for black 3's
				case 'four':
				case 'five':
				case 'six':
				case 'seven':
					return  5;
					
				case 'eight':
				case 'nine':
				case 'ten':
				case 'jack':
				case 'queen':
				case 'king':
					return  10;
									
				case 'two':
				case 'ace':
					return  20;
					
				case 'joker':
				case 'joker-of-black':
				case 'joker-of-red':
					return  50;
			}
			
			return parent::GetCardScore($suit, $card);
		}
	}
	
	class WPCardzNetCanastaClass extends WPCardzNetGamesBaseClass // Define class
	{
		var $currTrick = null;		
		var $minimumMeld = 50;
		
		// TODO - Spacing should depend on cards
		var $meldYSpacing = 20;
		var $meldXSpacing = 100;
		var $canastaYSpacing = 7;
		
		var $handXSpacing = 50;
		
		var $deckXSpacing = 120;

		static function GetGameName()
		{
			return 'Canasta';			
		}
		
		function __construct($myDBaseObj, $atts = array())
		{			
			add_filter('wpcardznet_filter_ActiveRound', array($this, 'FilterActiveRound'), 10, 1);
			add_filter('wpcardznet_filter_ActiveGame', array($this, 'FilterActiveGame'), 10, 1);

			parent::__construct($myDBaseObj, $atts);

			$this->cardDefClass = 'WPCardzNetCanastaCardsClass';

			$this->MIN_NO_OF_PLAYERS = 4;
			$this->MAX_NO_OF_PLAYERS = 4;
			
			$this->NO_OF_CARDS_IN_PACK = 108;
		}

		function FilterActiveGame($gameObj)
		{
			if ($this->roundAskGoOut)
			{
				if (isset($this->roundAuthPlayerId))
				{
					$gameObj->nextPlayerId = $this->roundAuthPlayerId;
				}
			}

			return $gameObj;
		}

		function FilterActiveRound($roundObj)
		{
			$this->roundAskGoOut = false;
			$this->roundGoOutResponse = WPCARDZNET_ROUNDOPTS_GOOUT_REQUESTED;

			if (isset($roundObj->roundMeta))
			{
				$roundMeta = unserialize($roundObj->roundMeta);
				if (isset($roundMeta[WPCARDZNET_ROUNDOPTS_GOOUT_STATUS]))
				{
					switch ($roundMeta[WPCARDZNET_ROUNDOPTS_GOOUT_STATUS])
					{
						case WPCARDZNET_ROUNDOPTS_GOOUT_REQUESTED:
							$this->roundAuthPlayerId = $roundMeta[WPCARDZNET_ROUNDOPTS_GOOUT_AUTHID];
							break;
							
						case WPCARDZNET_ROUNDOPTS_GOOUT_AUTHORISED:
						case WPCARDZNET_ROUNDOPTS_GOOUT_DENIED:
							$this->roundGoOutResponse = $roundMeta[WPCARDZNET_ROUNDOPTS_GOOUT_STATUS];
							unset($this->roundAuthPlayerId);
							break;
					}
					
					$this->roundAskGoOut = true;
					$this->roundRequestPlayerId = $roundMeta[WPCARDZNET_ROUNDOPTS_GOOUT_REQUESTID];
				}
			}

			return $roundObj;
		}
		
		function GetCardDef($cardSpec)
		{
			$rtnVal = parent::GetCardDef($cardSpec);
			
			$cardsGap = 10;
			$this->meldYSpacing = WPCardzNetCardDefClass::CardDIGITSIZE;
			$this->meldXSpacing = WPCardzNetCardDefClass::CardWIDTH + $cardsGap;
			$this->deckXSpacing = WPCardzNetCardDefClass::CardHEIGHT + $cardsGap;
			$this->freezeXSpacing = WPCardzNetCardDefClass::CardWIDTH + $cardsGap;
			
			return $rtnVal;
		}
		
// function GetCurrentPlayer()
// function GetNoOfCardsToPassOn()
// function GetPassedCardsTargetOffset()
// function GetPassedCardsTargetName()
// function GetGameOptionsHTML($currOpts)
// function ProcessGameOptions($gameOpts = array())

		function GetDealDetails($noOfPlayers = 0) 
		{
			if ($noOfPlayers == 0)
			{
				$noOfPlayers = $this->myDBaseObj->GetNoOfPlayers();
			}
			
			$dealDetails = new stdClass();
			$dealDetails->noOfPacks = 2;
			$dealDetails->noOfJokers = 4;
			$dealDetails->cardsPerPlayer = 11;	
			$dealDetails->noOfTeams = 2;	
			
			return $dealDetails;
		}

		function DealCards($details = null, $roundState = '')
		{
			$myDBaseObj = $this->myDBaseObj;

			parent::DealCards($details, $roundState);
			
			// Get the first card from the stock
			$discardCards = array();			
			$nextCardNo = $myDBaseObj->GetNextCardFromDeck();
			$discardCards[] = $nextCardNo;
			
			// Add this to a trick with playerId=WPCARDZNET_CANASTA_DISCARDS_ID
			$this->myDBaseObj->NewTrick($discardCards, 0, WPCARDZNET_CANASTA_DISCARDS_ID);
		}
		
	
		function SetNextPlayer($nextPlayerId)
		{
			parent::SetNextPlayer($nextPlayerId);
			
			// Clear any "Go Out" requests and replies 
			$this->myDBaseObj->UpdateRoundOptions();
		}

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
			$lastTrick = $this->myDBaseObj->GetLastTeamTrick($teamColour);
			if ($lastTrick == null) return array();

			return $lastTrick->cardsListArr;
		}
	
		function GetMelds()
		{
			// Gets the melds for all players
			$allMelds = array();
			$teamColour = $this->myDBaseObj->GetPlayerColour();
			for ($i=0; $i<2; $i++)
			{
				$allMelds[$teamColour] = $this->GetCurrentMelds($teamColour);
				$teamColour = $this->GetOpponentColour();
			}
			
			return $allMelds;
		}

		function GetOpponentColour()
		{
			$ourColour = $this->myDBaseObj->GetPlayerColour();
			return ($ourColour == 'teamA') ? 'teamB' : 'teamA';
		}
	
		function OutputMelds($mergedTricks, $ourMelds = true)
		{
			$cardOptions = array();
			$cardOptions['bottom'] = true;
			
			if ($ourMelds)
			{
				$tricksIndex = $this->myDBaseObj->GetPlayerColour();
				$meldDivId = 'ourmelds';
				$cardOptions['hasClick'] = true;
				$cardOptions['bottom'] = true;
				$showOurTricks = true;
			}
			else
			{
				$tricksIndex = $this->GetOpponentColour();
				$meldDivId = 'theirmelds';
				$showOurTricks = false;
			}
				
			$mergedMelds = $mergedTricks[$tricksIndex];
			
			$firstCards = array();
			
			// Get the first card in each meld
			$doneMeld0 = false;
			foreach ($mergedMelds as $meldNo => $meld)
			{
				if ($meldNo == 0) continue;	// Ignore any red 3 melds 
				$firstCards[$meldNo] = $meld[0];
			}

			// Sort into order
			$meldsOrder = $firstCards;
			sort($meldsOrder);

			// Get the position of each meld 
			$meldsOffset = array();
			foreach ($firstCards as $meldNo => $firstCard) 
			{
				$meldsOffset = array_search($firstCard, $meldsOrder);
				$meldsOffsets[$meldNo] = $meldsOffset;
			}
			
			if (!isset($mergedMelds[0]))
				$mergedMelds[0] = array();

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
			foreach ($mergedMelds as $meldNo => $meld)
			{
				$noOfCardsInMeld = count($meld);
				if ($meldNo == 0)
					$isCanasta = ($noOfCardsInMeld >= 4);
				else
					$isCanasta = ($noOfCardsInMeld >= 7);
					
				if ($isCanasta)
				{
					$canastaType = 'pure-canasta';
					foreach ($meld as $meldCard)
					{
						if ($this->IsSpecialCard($meldCard))
						{
							$canastaType = 'mixed-canasta';
							break;
						}
					}
				}
				$cardOptions['vspace'] = $isCanasta ? $this->canastaYSpacing : $this->meldYSpacing;
				$cardOptions['frameclass'] = $isCanasta ? $canastaType : '';
				
				$meldOffset = 1;
				if ($meldNo > 0) $meldOffset = 2 + $meldsOffsets[$meldNo];
				$left = ($meldOffset * $this->meldXSpacing);
				$style = "left: ".$left."px;";
				
				$meldId = "melddiv_".$meldNo;
				$cardZIndex = 0;

				WPCardzNetLibEscapingClass::Safe_EchoHTML("<div id=\"$meldId\" class=\"meldframe\" style=\"$style\" >\n");
				// Output the cards "backwards" 
				for ($cardZIndex = count($meld) - 1; $cardZIndex >= 0; $cardZIndex--)
				{
					if ($cardZIndex == 0)
					{
						unset($cardOptions['class']);
						unset($cardOptions['frameclass']);
					}
					$meldCard = $meld[$cardZIndex];
					$cardDef = $this->GetCardDef($meldCard);
					WPCardzNetLibEscapingClass::Safe_EchoHTML($this->OutputCard($cardOptions, $cardDef, $cardZIndex));
				}
				WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");
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
					
//			WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");

			// Get the discards list
			$discardsList = $this->GetDiscards();
			
			// Get current score 
			$score = $myDBaseObj->GetPlayerScore();
			if ($score >= 3000)
				$this->minimumMeld = 120;
			else if ($score >= 1500)
				$this->minimumMeld = 90;
			else
				$this->minimumMeld = 50;
			
			WPCardzNetLibEscapingClass::Safe_EchoHTML($myDBaseObj->GetHiddenInputTag('score', $score)."\n");

			WPCardzNetLibEscapingClass::Safe_EchoHTML($myDBaseObj->GetHiddenInputTag('meldXSpacing', $this->meldXSpacing)."\n");
			WPCardzNetLibEscapingClass::Safe_EchoHTML($myDBaseObj->GetHiddenInputTag('meldYSpacing', $this->meldYSpacing)."\n");
			WPCardzNetLibEscapingClass::Safe_EchoHTML($myDBaseObj->GetHiddenInputTag('handXSpacing', $this->handXSpacing)."\n");
			WPCardzNetLibEscapingClass::Safe_EchoHTML($myDBaseObj->GetHiddenInputTag('canastaYSpacing', $this->canastaYSpacing)."\n");
			WPCardzNetLibEscapingClass::Safe_EchoHTML($myDBaseObj->GetHiddenInputTag('minimumMeld', $this->minimumMeld)."\n");
			
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
						
			if ($noOfDiscards == 0)
				$playerMode = 'pickedUpPack';
			else
				$playerMode = 'normal';
			WPCardzNetLibEscapingClass::Safe_EchoHTML($myDBaseObj->GetHiddenInputTag('playerMode', $playerMode)."\n");
				
			$discardOptions = array();
			if ($active)
			{
				// Discard pile can always be selected on initialization
				$discardOptions['active'] = true;
				if (!$hasPickedUp)	// Select click handler for discard pile 
					$discardOptions['hasClick'] = 'wpcardznet_playCard';
				else
					$discardOptions['hasClick'] = 'wpcardznet_clickDiscard';
			}
			
			// Get the last card from discards list
			$discardTop = ($noOfDiscards > 0) ? end($discardsList) : 0;
			
			$freezeCardHTML = '';
			$lastCardWasWild = false;
			// Get the last wild card (if any)
			for ($i = $noOfDiscards-1; $i>=0; $i--)
			{
				$cardNo = $discardsList[$i];
				if ($this->IsSpecialCard($cardNo))
				{
					$cardDef = $this->GetCardDef($cardNo);
					$lastCardWasWild = ($i == ($noOfDiscards-1));
				
					$cardDef = $this->GetCardDef($cardDef->classes);
					$cardDef->cardDirn = '-l';
					$cardOptions['id'] = 'stock-frozen';
					$cardOptions['hspace'] = $this->freezeXSpacing;
					$cardOptions['voffset'] = 20;

					$cardOptions = array_merge($cardOptions, $discardOptions);
					
					$freezeCardHTML = '<div id="stock-freeze" class="tablediv centrecards cards-l">';
					$freezeCardHTML .= $this->OutputCard($cardOptions, $cardDef, 1);
					$freezeCardHTML .= "</div>\n";
					
					break;
				}
			}
			
			if (($freezeCardHTML != '') && !$lastCardWasWild)
				WPCardzNetLibEscapingClass::Safe_EchoHTML($freezeCardHTML);

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

			if (!isset($playersHand->cards[0]) || !$this->IsRedThree($playersHand->cards[0]))
				$cardOptions['hasClick'] = 'wpcardznet_getCardFromStock';
			else if ($this->HasAlreadyPlayed($playersHand))
				$cardOptions['hasClick'] = 'wpcardznet_replaceThreeFromHand';
			else 
				$cardOptions['hasClick'] = 'wpcardznet_replaceThreeFromDeal';

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
				$cardDef = $this->GetCardDef($discardTop);
			$cardOptions['id'] = 'discards';
			WPCardzNetLibEscapingClass::Safe_EchoHTML($this->OutputCard($cardOptions, $cardDef, 1));
			
			WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");

			if (($freezeCardHTML != '') && $lastCardWasWild)
				WPCardzNetLibEscapingClass::Safe_EchoHTML($freezeCardHTML);	
								
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div id=cards_info>Stock: {$this->cardsInStock}");
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<br>Discards: $noOfDiscards</div>\n");

			$allMelds = $this->GetMelds();	
			
			$this->OutputMelds($allMelds);
			$this->OutputMelds($allMelds, false);
			
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<!- End OutputCardsOnTable >\n");
						
			return;			
		}

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
					$hasWildCard = false;
					foreach ($meldCards as $meldCard)
					{
						$cardDef = $this->GetCardDef($meldCard);
						$totals[$teamIndex] += $cardDef->score;
						$hasWildCard |= $this->IsSpecialCard($meldCard);
					}
					
					// Find any canastas 
					if ($noOfCards >= 7)
					{
						if ($hasWildCard)
							$canastaVal = 300;	// Mixed Canasta
						else
							$canastaVal = 500;	// Natural Canasta			
						$totals[$teamIndex] += $canastaVal;					
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
		function UpdateRound($cardNo)
		{
			$myDBaseObj = $this->myDBaseObj;
			
			$cardsPlayed = array();
			
			$addToMeldsList = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'addedtomelds');
			if ($addToMeldsList != '')
			{
				$meldIds = explode(' ', $addToMeldsList);
$myDBaseObj->AddToLogSQL("-- ***** meldsList **$addToMeldsList**");
				$melds = array();
				foreach ($meldIds as $meldId)
				{
					// Decode Element to meld index and cardNo
					$meldAttr = explode('-', $meldId);
					if (count($meldAttr) != 2) 
					{
$myDBaseObj->AddToLogSQL("-- ***** Invalid Meld Element - $meldId");
						return 'Invalid Meld Element - $meldId';
					}
					
					$meldNo = -1;
					if (($meldAttr[0] != '') && is_numeric($meldAttr[0]))
					{
						$meldNo = intval($meldAttr[0]);
						$meldCardNo = intval($meldAttr[1]);
					}
					
					if ($meldNo < 0)
					{
						WPCardzNetLibUtilsClass::print_r($meldIds, '$meldIds');
						die ("Invalid meldNo");
					}
					
					if (!isset($melds[$meldNo]))
						$melds[$meldNo] = array();
					
					$melds[$meldNo][] = $meldCardNo;
					$cardsPlayed[] = $meldCardNo;
				}
				
				if (count($melds) > 0)
				{				
					$myDBaseObj->OutputDebugMessage(WPCardzNetLibUtilsClass::print_r($melds, '$melds', true)." <br>\n");													

					$teamColour = $myDBaseObj->GetPlayerColour();
					$existingMelds = $this->GetCurrentMelds($teamColour);

					// Merge the new melds with any exisiting melds 
					foreach ($melds as $meldIndex => $newMeld)
					{
						if (!isset($existingMelds[$meldIndex]))
							$existingMelds[$meldIndex] = array();
							
						foreach ($newMeld as $meldCardNo)
						{
							$existingMelds[$meldIndex][] = $meldCardNo;
						}
					}

					// Save the complete melds list as a new trick	
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
				$myDBaseObj->UpdateTrick($discardsList, WPCARDZNET_CANASTA_DISCARDS_ID);
			}
			
			$endOfRound = false;

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
			
			return 'OK';
		}

		function HasPickedUp($playersHand)
		{
			if (!isset($playersHand->handMetaDecode[WPCARDZNET_HANDOPTS_NEWCARDS_ID]))
				return false;
			
			if (!$this->HasAlreadyPlayed($playersHand))
			{
				// Has picked up if there are 12 cards in hand 
				return (count($playersHand->cards) == 12);
			}

			// Has picked up if the last card was not a single red 3 
			$pickedUp = $playersHand->handMetaDecode[WPCARDZNET_HANDOPTS_NEWCARDS_ID];

			$noOfCardsAdded = count($pickedUp);
			if ($noOfCardsAdded > 1) 
				return true;
				
			// If the last picked up card was a red three ... return TRUE 
			if ($this->IsRedThree($pickedUp[0]))
				return false;

			return true;
		}
	
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
		
		function IsRedThree($cardNo)
		{
			switch ($cardNo)
			{
				case CARDNO_THREE_DIAMONDS:
				case CARDNO_THREE_HEARTS:
					return true;
				
				default:
					return false;
			}
		}
	
		function IsSpecialCard($cardNo, $includeRedThree = false)
		{
			switch ($cardNo)
			{
				case CARDNO_THREE_DIAMONDS:
				case CARDNO_THREE_HEARTS:
					return $includeRedThree;
					
				case CARDNO_TWO_DIAMONDS:
				case CARDNO_TWO_HEARTS:
				case CARDNO_TWO_CLUBS:
				case CARDNO_TWO_SPADES:
				case CARDNO_JOKER:
					return true;
					
				default:
					return false;
			}
		}
		
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
				//unset($handMetaDecode[WPCARDZNET_HANDOPTS_REDTHREE_ID]);
				switch ($userUIAction)
				{
					case 'replaceThreeFromDeal':
						// Fall into next case 
						
					case 'replaceThreeFromHand':
						// Fall into next case 
						$hasPickedUp = false;
						$handMeta[WPCARDZNET_HANDOPTS_REDTHREE_ID] = true;						
						
					case 'getcard':
					case 'pickuppack':
						if ($hasPickedUp) break;	// Don't process pick up of stock/discard 
						
						$addedCardsList = array();
						if ($userUIAction != 'pickuppack')
						{
							if (WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'addedtomelds'))
								$rtnStatus = $this->UpdateRound(0);						
							
							// Add card to players hand from the deck
							$nextCardNo = $myDBaseObj->GetNextCardFromDeck();
							
							// Add the next card to the hand 
							if ($nextCardNo != null)
								$addedCardsList = array($nextCardNo);
							$myDBaseObj->AddCardToHand($addedCardsList);
						}
						else
						{
							$addedCardsList = $this->GetDiscards();
							
							// Add the discards list to the hand (must be before UpdateRound)
							$myDBaseObj->AddCardToHand($addedCardsList);
	
							// Remove the last card (it should be in one of the melds)
							array_pop($addedCardsList);
									
							// Create an empty trick for the discards from now
							$myDBaseObj->NewTrick(0, 0, WPCARDZNET_CANASTA_DISCARDS_ID);
						
							// Update the round (add the melds)
							$rtnStatus = $this->UpdateRound(0);						
						}
							
						// Add these cards to the recent cards list 
						$handMeta[WPCARDZNET_HANDOPTS_NEWCARDS_ID] = $addedCardsList;						
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

							if (isset($playersHand->roundMeta[WPCARDZNET_ROUNDOPTS_GOOUT_STATUS]))
							{
								$roundMeta = array();
								$myDBaseObj->UpdateRoundOptions($roundMeta);
							}
							
							// Advance to next player
							$nextPlayerId = $myDBaseObj->AdvancePlayer();
							$this->SetNextPlayer($nextPlayerId);			
							$this->GetCurrentPlayer();
							WPCardzNetLibUtilsClass::UnsetElement('post', 'playerId');
						}
						break;
						
					case 'goOutReq':
					case 'goOutAuthorised':
					case 'goOutReqDenied':
						switch ($userUIAction)
						{
							case 'goOutReq': $goOutStatus = WPCARDZNET_ROUNDOPTS_GOOUT_REQUESTED; break;						
							case 'goOutAuthorised': $goOutStatus = WPCARDZNET_ROUNDOPTS_GOOUT_AUTHORISED; break;
							case 'goOutReqDenied': $goOutStatus = WPCARDZNET_ROUNDOPTS_GOOUT_DENIED; break;
						}
						
						if ($userUIAction == 'goOutReq')
						{
							// Mark that this player has made a "Go Out Request"
							$playerId = $myDBaseObj->GetPlayerId();
							$partnerId = $myDBaseObj->GetNextPlayerInTeam();
						}
						else
						{
							// Keep the playerIds that were there before ...
							$playerId = $myDBaseObj->GetNextPlayerInTeam();
							$partnerId = $myDBaseObj->GetPlayerId();
						}

						$roundMeta = array();
						$roundMeta[WPCARDZNET_ROUNDOPTS_GOOUT_REQUESTID] = $playerId;	
						$roundMeta[WPCARDZNET_ROUNDOPTS_GOOUT_AUTHID] = $partnerId;	
						$roundMeta[WPCARDZNET_ROUNDOPTS_GOOUT_STATUS] = $goOutStatus;	
						
						$myDBaseObj->UpdateRoundOptions($roundMeta);
						
						// Signal that the state has changed	
						$myDBaseObj->IncrementTicker();
						
						// The user needs to change ... so redo initialisation ...
						$this->ReInitialise();
											
						break;
						
				} // End of switch($userUIAction)
						
			}	
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
			
			parent::OutputTabletop();
		}
		
		function CanGoFullscreen()
		{
			return false;
		}

		function GetGameButtons($playersHand)
		{
			$gameButtons = parent::GetGameButtons($playersHand);
		
			$classes = 'class="infobar-button swop" ';
			$events  = "onclick=\"wpcardznet_toggleOpponentsCards()\" ";
			$gameButtons .= "<div id=switchcards-button {$classes} {$events}></div>";
			
			$active = $this->IsMyTurn();

			// Get Players Activity State - Dormant, Selecting from Stock or Playing Cards
			$hasPickedUp = $this->HasPickedUp($playersHand);

			if ($this->roundAskGoOut) 
			{
				$currPlayerId = $this->myDBaseObj->GetPlayerId();
				$isRequester =  ($this->roundRequestPlayerId == $currPlayerId);

				switch ($this->roundGoOutResponse) 
				{
					case WPCARDZNET_ROUNDOPTS_GOOUT_REQUESTED:
						if ($this->roundAuthPlayerId == $currPlayerId) 
						{
							$active = false;

							$onClick = ' onclick="wpcardznet_gooutYesClick();" ';
							$gameButtons .= "<div id=goOutYesButton name=goOutYesButton class='infobar-button tick' $onClick></div>";

							$onClick = ' onclick="wpcardznet_gooutNoClick();" ';
							$gameButtons .= "<div id=goOutNoButton name=goOutNoButton class='infobar-button cross' $onClick></div>";
						}
						if ($isRequester) 
						{
							$active = false;
							$gameButtons .= "<div id=goOutResponse name=goOutResponse class='infobar-button clock'></div>";
						}
						break;

					case WPCARDZNET_ROUNDOPTS_GOOUT_AUTHORISED:
						if ($isRequester) {
							$gameButtons .= "<div id=goOutResponse name=goOutResponse class='infobar-button tick'></div>";
						}
						break;

					case WPCARDZNET_ROUNDOPTS_GOOUT_DENIED:
						if ($isRequester) {
							$gameButtons .= "<div id=goOutResponse name=goOutResponse class='infobar-button cross'></div>";
						}
						break;
				}
			} 
			else if ($hasPickedUp) 
			{
				$onClick = ' onclick="wpcardznet_gooutAskClick();" ';
				$gameButtons .= "<div id=goOutButton name=goOutButton class='infobar-button goout' $onClick ></div>";
			}
			
			return $gameButtons;
		}
		
		function GetDiscards()
		{
			$cardsList = array();
			$discards = $this->myDBaseObj->GetCurrentTrick(WPCARDZNET_CANASTA_DISCARDS_ID);	
			if ($discards !== null)	
				$cardsList = $discards->cardsListArr;
			return $cardsList;
		}
	
	}
}

?>