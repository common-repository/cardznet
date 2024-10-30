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

if (!class_exists('WPCardzNetCardsClass'))
{
	class WPCardzNetCardsClass // Define class
	{
		var	$card_defs = array();
		var $groupBySuits = true;
		
		function __construct()
		{
			$cardNo = 1;
			$suits = $this->GetCardSuits();			
			$cards = $this->GetCardNames();
			if ($this->groupBySuits)
			{
				foreach ($suits as $suitIndex => $suit)
				{
					foreach ($cards as $cardIndex => $card)
					{
						$cardScore = $this->GetCardScore($suit, $card);
						$name = "{$card}-of-{$suit}";
						$classes = "{$name} {$card}";
						$this->card_defs[] = $this->CreateCardDef($name, $classes, $cardNo++, $suitIndex, $cardIndex, $cardScore);
					}
				}
			}
			else
			{
				foreach ($cards as $cardIndex => $card)
				{
					foreach ($suits as $suitIndex => $suit) {
						$cardScore = $this->GetCardScore($suit, $card);
						$name = "{$card}-of-{$suit}";
						$classes = "{$name} {$card}";
						$this->card_defs[] = $this->CreateCardDef($name, $classes, $cardNo++, $suitIndex, $cardIndex, $cardScore);
					}
				}
			}
			
			$cards = array('joker-of-black', 'joker-of-red');
			foreach ($cards as $card)
			{
				$cardScore = $this->GetCardScore('', $card);
				$classes = "{$card} joker";
				$this->card_defs[] = $this->CreateCardDef($card, $classes, $cardNo++, 5, $cardIndex++, $cardScore);
			}

			$cards = array('card-back', 'card-hidden', 'card-blank', 'card-empty');
			foreach ($cards as $card)
			{
				$this->card_defs[] = $this->CreateCardDef($card, $card, $cardNo++, 5, $cardIndex++, 0);
			}
		}	

		function GetCardSuits()
		{
			return array('clubs', 'diamonds', 'spades', 'hearts');
		}	

		function GetCardNames()
		{
			return array('two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'jack', 'queen', 'king', 'ace');
		}	

		function GetCardScore($suit, $card)
		{
			$cardScore = 0;
			
			return $cardScore;
		}
			
		function CreateCardDef($name, $classes, $cardNo, $suitNo = 0, $rank=0, $score=0)
		{
			//WPCardzNetLibEscapingClass::Safe_EchoHTML("CardNo: $cardNo = $name <br>\n");			
			$cardDef = new stdclass();
			$cardDef->cardno = $cardNo;
			$cardDef->name = $name;
			$cardDef->classes = $classes;
			$cardDef->suitNo = $suitNo;
			$cardDef->rank = $rank;
			$cardDef->score = $score;
			return $cardDef;			
		}
/*
		function GetCardDefByName($cardName)
		{
			$cardNo = $this->GetCardNo($cardName);
			return $this->GetCardDef($cardNo);
		}
*/		
		function GetCardDef($cardNo)
		{
			// Keys start at 0 - card numbers start at 1
			$key = $cardNo-1;
			if (($key >= count($this->card_defs)) || ($key < 0))
				return $this->CreateCardDef('card-back', 'tba', $cardNo);
				
			return $this->card_defs[$key];
		}
/*		
		function GetCardName($cardNo)
		{
			$cardDef = $this->GetCardDef($cardNo);
			return $cardDef->classes;
		}
		
		function GetPrettyCardName($cardNo)
		{
			$cardName = $this->GetCardName($cardNo);
			$cardName = WPCardzNetLibMigratePHPClass::Safe_str_replace("-of-", " of ", $cardName);
			$cardName = ucwords($cardName);
			return $cardName;
		}
*/		
		function GetCardNo($cardName)
		{
			foreach ($this->card_defs as $cardDef)
			{
				if ($cardName == $cardDef->name)
				{
					return $cardDef->cardno;
				}
			}
			
			return null;
		}
	}
}

?>