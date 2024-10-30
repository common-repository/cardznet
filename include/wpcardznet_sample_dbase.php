<?php
/* 
Description: CardzNet Plugin Sample Database functions
 
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

include WPCARDZNETLIB_INCLUDE_PATH.'wpcardznet_dbase_api.php';
include WPCARDZNETLIB_INCLUDE_PATH.'wpcardznet_cards.php';
include WPCARDZNET_GAMES_PATH.'wpcardznet_hearts.php';

if (!class_exists('WPCardzNetSampleDBaseClass')) 
{
	define('WPCARDZNET_PRICE_S1_P1_ALL', '12.50');
	
	class WPCardzNetSampleDBaseClass extends WPCardzNetDBaseClass
  	{
  		const CARDS_PER_PLAYER = 13;

		function CreateSample()
		{
			$this->AddSampleGame();
			$this->AddSampleRound(WPCardzNetHeartsClass::ROUND_PASSCARD);
			$this->AddSampleHands();
			$this->AddSampleTricks();
		}
		
		function GetDeck($details)
		{
			// "Fix" the deck for sample database
			$this->deck = array(3,21,51,9,50,48,12,17,42,33,10,32,31, 11,47,36,45,43,28,41,49,27,40,2,19,13, 25,16,38,5,6,15,39,1,26,30,7,14,29, 24,37,18,52,20,8,46,34,4,23,44,35,22);
			
			// Play out sequence ....
			$this->playedCards = array(13,39,52,51,13,8,12,11,26,24,21,19,25,23,17,51,7,4,10,2,33,36,30,35,43,38,44,42,18,52,49,16,20,50,47,15,34,32,28,29,22,48,45,14,46,9,41,6,37,31,27,5,39,3,40,1);
			
			return $this->deck;
		}
		
		function AddSampleGame()
		{			
			$gameUserIDs = array();
			$gameUserIDs[] = array('login' => 'Sue');
			$gameUserIDs[] = array('login' => 'Iris');
			$gameUserIDs[] = array('login' => 'Malcolm', 'first' => true);
			$gameUserIDs[] = array('login' => 'Iris', 'name' => 'Terry');
			
			$this->userId = 4;
			
			$this->AddGame('Hearts', $gameUserIDs, self::CARDS_PER_PLAYER);
		}		
	
		function AddSampleRound($roundState = self::ROUND_READY)
		{		
			return $this->AddRound($roundState);
		}		
	
		function AddSampleHands()
		{			
			$cards = $this->GetDeck();
			
			$cardIndex = 0;
			$playerNo = 0;
			$this->AddDeckToHand($playerNo++, $cards, $cardIndex, self::CARDS_PER_PLAYER);
			$this->AddDeckToHand($playerNo++, $cards, $cardIndex, self::CARDS_PER_PLAYER);
			$this->AddDeckToHand($playerNo++, $cards, $cardIndex, self::CARDS_PER_PLAYER);
			$this->AddDeckToHand($playerNo++, $cards, $cardIndex, self::CARDS_PER_PLAYER);
		}
		
		function AddSampleTricks()
		{
			$tricksToPlay = WPCardzNetLibUtilsClass::GetHTTPInteger('post', 'noOfTricks');
			if ($tricksToPlay > 0)
			{
				$this->isSeqMode = true;
				
				include WPCARDZNET_GAMES_PATH.'wpcardznet_hearts.php';
				$gameObj = new WPCardzNetHeartsClass($this);
				
				$user = wp_get_current_user();
				$gameObj->atts['login'] = $user->data->user_login;
				
				$cardIndex = 0;
				for ($trickNo = 1; $trickNo<=$tricksToPlay; $trickNo++)
				{
					$this->GetTrickCards(true);	// Initialise DBase object
					
					$cardsText = '';
					for ($i=0; $i<=3; $i++)
					{
						$cardNo = $this->playedCards[$cardIndex++];
						$gameObj->GetGameAndPlayer();
						WPCardzNetLibUtilsClass::SetElement('post', 'playerId', $this->nextPlayerId);
						$status = $gameObj->UpdateRound($cardNo);
						if ($status != 'OK')
						{
							WPCardzNetLibEscapingClass::Safe_EchoHTML("UpdateRound (CardNo: $cardNo) $status <br>\n");
							die;
						}
						
						if ($cardsText != '') $cardsText .= ',';
						$cardsText .= $cardNo;
					}
					WPCardzNetLibEscapingClass::Safe_EchoHTML("Added Cards ($cardsText) to Trick $trickNo <br>\n");
				}
			}
	   	}

		
	}
	
}

?>