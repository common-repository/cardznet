/* 
Description: CardzNet Javascript
 
Copyright 2014 Malcolm Shergold

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
Classes involved with choosing a card with the keyboard

selectedcard - Added to a single card selected in players hand
playedcard
card-highlight
activecard - When included allows a card to be played 

*/

var selectingCardInHand = true;
var selectTargetIndex = 0;
var numberOfTargets = 0;
var oneEyedJacks;
var twoEyedJacks;

var unhideButtonUpdate = 50;
var unhideButtonMoving = false;

function wpcardznet_OnRefreshGameBoard()
{
	var unhideContainer = jQuery("#unhidecardsbutton").parent();
	if (typeof unhideContainer === 'undefined') return;
	
	if (!unhideButtonMoving)
	{
		unhideContainer.css('left', '400px');
		unhideButtonMoving = true;
		setTimeout(wpcardznet_moveClickHere, unhideButtonUpdate);
	}
}

function wpcardznet_moveClickHere()
{
	unhideButtonMoving = false;
	
	var unhideContainer = jQuery("#unhidecardsbutton").parent();
	if (typeof unhideContainer === 'undefined') return;
	
	var unhideTop = unhideContainer.css('top');
	if (typeof unhideTop === 'undefined') return;
	
	var top = parseInt(unhideTop.replace('px', ''));
	if (top < 600) top++;
	else top = 60;
	unhideContainer.css('top', top + "px");
	unhideButtonMoving = true;
	setTimeout(wpcardznet_moveClickHere, unhideButtonUpdate);
}

function wpcardznet_playCard(cardGUI)
{
	var noOfActiveCards = jQuery('.playercards').find(".activecard").length;
	if (noOfActiveCards == 0) return;
	
	var selectedElems = jQuery("#"+cardGUI);
	
	var cardClass = selectedElems[0].className;
	
	if (cardGUI.indexOf("spaceontable_") != -1)
	{
		// Click active card on table
		if (selectedElems.parent().hasClass('card-highlight'))
		{
			wpcardznet_addCardToBoard(cardGUI);
		}
		
		return;
	}
	
	if (selectedElems.parent().hasClass('card-highlight'))
	{
		// Pass dead card back to server 
		wpcardznet_addCardToBoard(cardGUI);
	}
	else if (selectedElems.parent().hasClass('playedcard'))
	{
		wpcardznet_revertToSelectingCard();		
	}	
	else if (selectedElems.parent().hasClass('selectedcard'))	// Card is already selected - play it
	{
		var selectedcardElems = jQuery('.card-highlight');
		
		selectingCardInHand = false;
		selectedElems.parent('div').addClass('playedcard');
		selectedElems.parent('div').removeClass('selectedcard');
		
		wpcardznet_showAMatchingTarget(0);
	}	
	else
	{
		// Deselect all cards in players hand
		activecardElems = wpcardznet_deselectAllCards();		
		
		// Select the one we clicked
		selectedElems.parent('div').addClass('selectedcard');
		
		// Update selectTargetIndex to match selected card
		var cardId = selectedElems[0].id;
		selectedCardIndex = parseInt(cardId.replace('cardGUID_', ''))-1;
		
		wpcardznet_showMatchingTargets();							
	}

	wpcardznet_playSound('SelectCard');
}

function wpcardznet_revertToSelectingCard()
{
	selectingCardInHand = true;
	
	wpcardznet_selectACard(0);
	wpcardznet_showMatchingTargets();							
}

function wpcardznet_game_onkeydown(event)
{
	origEvt = event.originalEvent;
	if (typeof origEvt !== 'undefined')
	{
		var keyCode = origEvt.code
		
		var dealcardsElems = jQuery("#ajaxdealcards");
		if (dealcardsElems.length > 0)
		{
			switch (keyCode)
			{
				case 'Enter':
				case 'NumpadEnter':				
					wpcardznet_dealcardsClick();
					break;
			}
			return;
		}
	
		if (selectingCardInHand)
		{
			switch (keyCode)
			{
				case 'ArrowDown':
					wpcardznet_selectACard(1);
					wpcardznet_showMatchingTargets();
					break;
					
				case 'ArrowUp':
					wpcardznet_selectACard(-1);
					wpcardznet_showMatchingTargets();
					break;
					
				case 'ArrowRight':
				case 'Enter':
				case 'NumpadEnter':
					if (wpcardznet_showAMatchingTarget(0))
					{
						wpcardznet_playSelectedCard();
						selectingCardInHand = false;
					}
					break;
					
				case 'ArrowLeft':
					break;
					
				case 'Space':
					wpcardznet_unhidecardsClick();
					break;
					
				case 'Tab':	
					// "Blocked" keys		
					break;
					
				default:
					console.log('KeyDown Ignored! '+keyCode+' - default processing');
					return;	// Do nothing ... Allow default
			}
		}
		else
		{
			switch (keyCode)
			{
				case 'ArrowDown':
					if (numberOfTargets > 2)
						wpcardznet_showNextMatchingTargetRow(1);
					else if (numberOfTargets > 0)
						wpcardznet_showNextMatchingTarget(1);
					else
					{
						wpcardznet_selectACard(1);
						wpcardznet_showMatchingTargets();
						selectingCardInHand = true;
					}
					break;
					
				case 'ArrowUp':
					if (numberOfTargets > 2)
						wpcardznet_showNextMatchingTargetRow(-1);
					else if (numberOfTargets > 0)
						wpcardznet_showNextMatchingTarget(-1);
					else
					{
						wpcardznet_selectACard(-1);
						wpcardznet_showMatchingTargets();
						selectingCardInHand = true;
					}
					break;
					
				case 'Enter':
				case 'NumpadEnter':
					// Select card
					var targetId = wpcardznet_getSelectedTarget();
					wpcardznet_playCard(targetId);
					break;
				
				case 'ArrowLeft':
				case 'Escape':
					wpcardznet_revertToSelectingCard();
					break;
					
				case 'ArrowRight':
					if (numberOfTargets > 0)
						wpcardznet_showNextMatchingTarget(1);
					break;
					
				default:
					console.log('KeyDown Ignored! '+keyCode+' - default processing');
					return;	// Do nothing ... Allow default
			}
		}

	}
	
	console.log('KeyDown TRAPPED! ('+keyCode+')');
	event.preventDefault();
}

function wpcardznet_clearMatchingTargets()
{
	var matchingCardElems = jQuery('.card-highlight');
	matchingCardElems.removeClass('card-highlight');
}

function wpcardznet_getTargetSelector()
{
	var cardName = wpcardznet_getSelectedCardName();
	var cardId = wpcardznet_getSelectedCardId();
	var cardSelector = "undefined";
	if (oneEyedJacks.indexOf(cardName) != -1)
	{
		// One eyed jacks remove
		cardSelector = ".not-my-colour";
	}
	else if (twoEyedJacks.indexOf(cardName) != -1)
	{
		// Two eyed jacks add
		cardSelector = ".spaceontable";
	}
	else
	{
		// Look for a specific card
		cardSelector = ".spaceontable_"+cardName;
	}
	
	return cardSelector;
}

function wpcardznet_showMatchingTargets()
{
	wpcardznet_clearMatchingTargets();

	var cardSelector = wpcardznet_getTargetSelector();

	var matchingList = jQuery(cardSelector);
	numberOfTargets = matchingList.length;
	if (numberOfTargets > 0)
	{
		matchingList.parent('div').addClass('card-highlight');
	}
	else
	{
		// Dead card - No target cards 
		var deadCardElem = jQuery('.selectedcard');
		deadCardElem.addClass('playedcard');		
		deadCardElem.removeClass('selectedcard');		
		deadCardElem.addClass('card-highlight');		
		
		selectingCardInHand = false;
	}
	
}

function wpcardznet_showAMatchingTarget(index)
{
	wpcardznet_clearMatchingTargets();
	var cardSelector = wpcardznet_getTargetSelector();
	var matchingList = jQuery(cardSelector);
	if (matchingList.length == 0) return false;
	
	if (index >= matchingList.length)
		index = 0;
	else if (index < 0)
		index = matchingList.length-1;
		
	var matchedId = matchingList[index].id;
	var matchedTarget = jQuery('#'+matchedId);
	matchedTarget.parent('div').addClass('card-highlight');
	
	selectTargetIndex = index;
	return true;
}

function wpcardznet_getRowNumber(cardElem)
{
	cardIdElems = cardElem.id.split("_");
	return cardIdElems[1];
}

function wpcardznet_getColNumber(cardElem)
{
	cardIdElems = cardElem.id.split("_");
	return cardIdElems[2];
}

function wpcardznet_showNextMatchingTargetRow(roffset)
{
	wpcardznet_clearMatchingTargets();
	var cardSelector = wpcardznet_getTargetSelector();
	var matchingList = jQuery(cardSelector);
	if (matchingList.length == 0) return false;
	
	var lastCol = wpcardznet_getColNumber(matchingList[selectTargetIndex]);
	var lastRow = wpcardznet_getRowNumber(matchingList[selectTargetIndex]);

	var srchOffset;
	if (roffset > 0) srchOffset = 1;
	else srchOffset = -1;
	
	var srchIndex = selectTargetIndex;
	for (i=0; i<matchingList.length; i++)
	{
		srchIndex += srchOffset;
		if (srchIndex >= matchingList.length) break;
		if (srchIndex < 0) break;
		
		var srchRow = wpcardznet_getRowNumber(matchingList[srchIndex]);
		var srchRowOffset = srchRow - lastRow;
		if (srchRowOffset == 0)
			continue;
				
		var srchCol = wpcardznet_getColNumber(matchingList[srchIndex]);
		if ((srchOffset > 0) && (srchRowOffset == 1))
		{
			if (srchCol < lastCol)
			continue;
		}
			
		if ((srchOffset < 0) && (srchRowOffset == -1))
		{
			if (srchCol > lastCol)
			continue;
		}
			
		wpcardznet_showAMatchingTarget(srchIndex);
		return;
	}
	
	if (srchOffset < 0)
		wpcardznet_showAMatchingTarget(matchingList.length-1);
	else
		wpcardznet_showAMatchingTarget(0);
	
}

function wpcardznet_showNextMatchingTarget(offset)
{
	index = selectTargetIndex+offset;
	wpcardznet_showAMatchingTarget(index);
}

function wpcardznet_getSelectedTarget()
{
	var selectedcardElems = jQuery('.card-highlight');
	if (selectedcardElems.length != 1) return "";
	
	return selectedcardElems.find('.card-face')[0].id;
}

function wpcardznet_addCardToBoard(targetId)
{
	if (AJAXActive) return;

	// Get the card the player has selected
	var cardName = wpcardznet_getSelectedCardName();

	// OK - Do it!
	var divElem = jQuery(".playercards");
	wpcardznet_addHiddenInput(divElem, "cardId", cardName);
	wpcardznet_addHiddenInput(divElem, "targetId", targetId);

	wpcardznet_SetBusy(true, 'card');
	wpcardznet_playSound('PlayCard');
	
	selectingCardInHand = true;

	wpcardznet_AJAXplaycard();

	return true;
}

function wpcardznet_can_update_screen()
{
	if (wpcardznet_getActiveCards() > 0)
		return false;
		
	return true;
}

function wpcardznet_oej_showlastcardClick(histNo)
{
	var matchingCardElems = jQuery('#fr_historyCard'+histNo);
	var isHidden = matchingCardElems.hasClass('wpcardznet_hide');

	// Hide them all first
	var allCards = jQuery('.historyCards').find('.card-frame');
	allCards.addClass('wpcardznet_hide');

	// Remove all hightlights
	jQuery('.historyHighlight').removeClass('historyHighlight');
	
	// Now toggle the selected card visibility
	if (isHidden)
	{
		matchingCardElems.removeClass('wpcardznet_hide');
		var targetPosn = jQuery('#histPosn'+histNo)[0].value;
		if (targetPosn.length < 2) targetPosn = "0"+targetPosn;
		var targetPosnId = 'fr_spaceontable_'+targetPosn.substr(0,1)+'_'+targetPosn.substr(1,1);
		var targetPosnElem = jQuery('#'+targetPosnId);
		targetPosnElem.addClass('historyHighlight');
		jQuery('.playercards').addClass('wpcardznet_hide');
		
		jQuery('#histNo'+histNo).addClass('historyHighlight');
	}
	else
	{
		jQuery('.playercards').removeClass('wpcardznet_hide');
	}
		
	
}

