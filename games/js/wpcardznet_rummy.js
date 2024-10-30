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

var meldXSpacing = 100;
var meldYSpacing = 20;	// 10;

var handXSpacing = 50;

var meldNoOfSets = 0;
var meldNoOfRuns = 0;

/*
var minimumMeld = 0;
*/
var meldsList = '';
var meldsValid = true;
/*
var meldsComplete = true;
*/
var uniqueTargetMeld = '';
/*

var pickingUpPack = false;
var pickingUpPackCard = '';
var pickedUpPack = false;

var hasAlreadyLaid = false;

var meldToggleCounter = 0;
*/
const MeldIsNotValid = 'Invalid';
const MeldIsIncomplete = 'Incomplete';
const MeldIsValid = 'Valid';

const AddToTop = 'top';
const AddToBottom = 'bottom';

function wpcardznet_reset_game()
{
	meldsList = '';
/*
	meldsValid = true;
	pickingUpPack = false;
	pickingUpPackCard = '';
	meldToggleCounter = 0;
*/

	wpcardznet_reset_vars();
}

function wpcardznet_getGlobals()
{
	meldXSpacing = wpcardznet_getHiddenInput('meldXSpacing');
	meldYSpacing = wpcardznet_getHiddenInput('meldYSpacing');
	handXSpacing = wpcardznet_getHiddenInput('handXSpacing');

	meldNoOfSets = wpcardznet_getHiddenInput('meldNoOfSets');
	meldNoOfRuns = wpcardznet_getHiddenInput('meldNoOfRuns');
/*
	minimumMeld = wpcardznet_getHiddenInput('minimumMeld');
	playerMode = wpcardznet_getHiddenInput('playerMode');

	if (pickingUpPack)
		playerMode = 'normal';

	pickedUpPack = (playerMode === 'pickedUpPack');
*/
}

function wpcardznet_game_onkeydown(event)
{
	origEvt = event.originalEvent;
	if (typeof origEvt !== 'undefined') 
	{
		var keyCode = origEvt.code;

		var dealcardsElems = jQuery("#ajaxdealcards");
		if (dealcardsElems.length > 0) 
		{
			switch (keyCode) 
			{
				case 'Enter':
				case 'NumpadEnter':
					wpcardznet_dealcardsClick();
					break;

				default:
					console.log('KeyDown Ignored! '+keyCode+' - default processing');
					return true;	// Keys are disabled ... do default action
			}
		}

		switch (keyCode) 
		{
			case 'ArrowLeft':
			case 'ArrowDown':
				wpcardznet_selectACard(-1);
				break;

			case 'ArrowRight':
			case 'ArrowUp':
				wpcardznet_selectACard(1);
				break;

			case 'Enter':
			case 'NumpadEnter':
				// Select card
				wpcardznet_playSelectedCard();
				break;
/*
			case 'Tab':
				// "Blocked" keys
				break;
*/
			case 'Space':
				wpcardznet_unhidecardsClick();
				break;

			default:
				console.log('KeyDown Ignored! '+keyCode+' - default processing');
				return true;	// Keys are disabled ... do default action
		}
	}

	console.log('KeyDown TRAPPED! ('+keyCode+')');
	event.preventDefault();
}

// TODO - Invalid card displayed if first player takes card from the discard pile 
// TODO - Limit new melds to one per turn
// TODO - Disable laying off cards until player has laid
// TODO - Greyed out should be removed once player has laid

function wpcardznet_playCard(cardGUI)
{
	if (AJAXActive)
		return;

	wpcardznet_getGlobals();

	var divElem = jQuery("#"+cardGUI);
	var baseDiv = divElem.closest('.tablediv');

	if (baseDiv.hasClass('playercards')) 
	{
		// Click will select a players card
		wpcardznet_logClick('clickCard', 'playercards', divElem[0]);

		// Check if this card is active
		var isActiveCard = divElem.hasClass('activecard');
		if (!isActiveCard)
			return true;

		var isSelected = false;

		// Check if multiple cards are selectedcardElem
		var selectedCount = baseDiv.find('.selectedcard').length;

		if (selectedCount <= 1) 
		{
			// Check if this card is already selected?
			isSelected = divElem.parent().hasClass('selectedcard');
		}

		activecardElems = wpcardznet_deselectAllCards();
		if (!isSelected)
			divElem.parent('div').addClass('selectedcard');

		wpcardznet_setActiveAndInactive();

		wpcardznet_playSound('SelectCard');
	}
/*
	if (baseDiv.hasClass('centrecards')) 
	{
		wpcardznet_logClick('clickCard', 'centrecards', divElem[0]);

		if (divElem.hasClass('card-blank')) 
		{
//			wpcardznet_clickDiscards();
			return true;
		}

		// Move top card from the stock/discards to players hand
		var cardId = divElem[0].id;
		var handDiv = jQuery('.playercards');
		wpcardznet_copycard(cardId, handDiv, window.handXSpacing, 'fromPack');

		// Find what card we are picking up
		var cardName = wpcardznet_cardNameFromClass(divElem[0].className);

		// Mark that we are picking up the pack
		pickingUpPack = true;
		pickingUpPackCard = cardName;

		// Now change the original card to a blank
		divElem.removeClass(cardName);
		divElem.addClass('card-blank');

		// Deactivate Stock
		wpcardznet_deactivateCards('#stockcards');

		wpcardznet_deselectAllCards();

		// Now select the card passed
		jQuery('#fromPack').parent().addClass('selectedcard');

		wpcardznet_setActiveAndInactive();
	}
*/
	if (baseDiv.hasClass('ourmelds')) 
	{
		wpcardznet_logClick('clickCard', 'ourmelds', divElem[0]);

		// Handle adding a card to an existing/new meld
		// Find the selected card
		var selectedcardElem = wpcardznet_getSelectedElem();
		if (selectedcardElem === '')
			return true;

		var cardId = selectedcardElem.id;
		var targetMeld = divElem.closest('.meldframe');

		if (!wpcardznet_addToMeld(targetMeld, cardId))
			return true;

		wpcardznet_removecardinhand(cardId);

		// Add it to the melds list
		var cardId = wpcardznet_cardIdFromClass(selectedcardElem.className);

		var movedCardGID = selectedcardElem.id;
		targetMeld = jQuery('.ourmelds').find('#'+movedCardGID).closest('.meldframe');

		var meldIndex = wpcardznet_indexFromId(targetMeld[0].id);
		var cardIndex = wpcardznet_indexFromId(cardId);
		var cardLocn =  meldIndex + '-' + cardIndex;

		meldsList += cardLocn + ' ';

		// Update the active status
		wpcardznet_setActiveAndInactive();
	}
/*
	if (baseDiv.hasClass('theirmelds')) 
	{
		// Handle viewing an opponents meld
	}
*/
	return true;
}

function wpcardznet_clickDiscard()
{
	wpcardznet_logClick('clickDiscard', '', '');

	var isActiveCard = jQuery("#discards").hasClass('activecard');
	if (!isActiveCard)
		return true;

	if (!meldsValid)
		return true;

	wpcardznet_SetBusy(true, 'card');
	var data = wpcardznet_AJAXPrepare('playerUI');
	data['clicksList'] = clicksList;
	data['UIAction'] = 'discard';

	// Get cardNo of selected card
	var cardNo = wpcardznet_getSelectedCardId();
	data['cardNo'] = cardNo;

	if (meldsList !== '')
		data['addedtomelds'] = meldsList;

	wpcardznet_reset_game();
	wpcardznet_updateUI(data, wpcardznet_AJAXcb_refresh, wpcardznet_AJAXcb_error);
}

function wpcardznet_clickDiscards()
{
	wpcardznet_logClick('clickDiscards', '', '');
	var isActiveCard = jQuery("#discards").hasClass('activecard');
	if (!isActiveCard)
		return true;

	wpcardznet_SetBusy(true, 'card');
	var data = wpcardznet_AJAXPrepare('playerUI');
	data['clicksList'] = clicksList;
	data['UIAction'] = 'cardfrompack';

	data['addedtomelds'] = meldsList;

	wpcardznet_reset_game();
	wpcardznet_updateUI(data, wpcardznet_AJAXcb_refresh, wpcardznet_AJAXcb_error);
}

function wpcardznet_getCardFromStock()
{
	wpcardznet_logClick('getCardFromStock', '', '');

	wpcardznet_clickStock('getcard');
}

function wpcardznet_clickStock(clickAction)
{
	var isActiveCard = jQuery("#stockcards").hasClass('activecard');
	if (!isActiveCard)
		return true;

	wpcardznet_SetBusy(true, 'card');
	var data = wpcardznet_AJAXPrepare('playerUI');
	data['clicksList'] = clicksList;

	data['UIAction'] = clickAction;
	if (meldsList !== '') 
	{
		data['addedtomelds'] = meldsList;
	}

	wpcardznet_reset_game();
	wpcardznet_updateUI(data, wpcardznet_AJAXcb_refresh, wpcardznet_AJAXcb_error);
}

function wpcardznet_isTargetValid(targetMeld, cardId)
{
	// Selected card
	var selCardElems = jQuery('#'+cardId);
	var selCardName = wpcardznet_cardNameFromClass(selCardElems[0].className);

	var selCardNumber = wpcardznet_cardRankFromClass(selCardName);
	var selCardSuit = wpcardznet_cardSuitFromClass(selCardName);

	// Get the list of cards in the meld
	var cardsList = targetMeld.find('.card-face');
	var noOfCards = cardsList.length;

	var wildsCount = 0;

	if (targetMeld[0].id == 'melddiv_new') 
	{
		return AddToTop;
	} 
	else 
	{
		// Search the cards list for a non-wild cardHTML
		var destCardNumber = '';
		var minCardNo = 100;
		var maxCardNo = 0;
		var runSuit = "";
		for (var i=0; i<noOfCards; i++) 
		{
			// Check for greyed out cards 
			if (i == 0)
			{
				var frameObj = jQuery(cardsList[i]).parent();
				if (frameObj.hasClass('greyedOut'))
				{
					// Meld is disabled
					return null;
				}
			}
			
			// Find the maximum and minimum card numbers			
			var thisCardNumber = wpcardznet_cardRankFromClass(cardsList[i].className);
			var thisCardSuit = wpcardznet_cardSuitFromClass(cardsList[i].className);
			
			if (i == 0)
				runSuit = thisCardSuit;
			else if (runSuit != thisCardSuit)
				runSuit = "";
					
			if (minCardNo > thisCardNumber)
				minCardNo = thisCardNumber;
			if (maxCardNo < thisCardNumber)
				maxCardNo = thisCardNumber;			
		}

		if ((minCardNo == maxCardNo) && (selCardNumber == minCardNo))
		{
			// Adding a card to a set
			return AddToTop;			
		}
		
		if (selCardSuit == runSuit)
		{
			// Check if we are adding a card below the run
			if (selCardNumber == minCardNo-1)
				return AddToTop;			

			// Check if we are adding a card above the run
			if (selCardNumber == maxCardNo+1)
				return AddToBottom;			
		}
		
	}

	return null;
}

function wpcardznet_addToMeld(targetMeld, cardId)
{
	if ((targetMeld[0].id == 'melddiv_new') && (uniqueTargetMeld != '')) 
	{
		targetMeld = jQuery('#'+uniqueTargetMeld).closest('.meldframe');
	}

	var targetPosn = wpcardznet_isTargetValid(targetMeld, cardId);
	if (targetPosn === null)
		return false;

	if (targetMeld[0].id == 'melddiv_new') 
	{
		// Make a copy of the blank meld (before anything is changed)
		var meldHTML = targetMeld[0].outerHTML;

		// Get the number of existing melds
		var existingMelds = jQuery('.ourmelds').find('.meldframe');
		var noOfMelds = existingMelds.length;

		// Rename the copy of the blank meld with a unique id
		var newMeldId = 'melddiv_' + noOfMelds;
		meldHTML = meldHTML.replace('melddiv_new', newMeldId);

		// Change the id and position of the blank meld
		meldPosn = noOfMelds * window.meldXSpacing;
		meldHTML = wpcardznet_removeStyle(meldHTML, 'left');
		meldHTML = wpcardznet_addStyle(meldHTML, 'left', meldPosn + "px");

		// Add the new meld to the table
		targetMeld.parent().append(meldHTML);

		// Remove the blank card from the new meld
		targetMeld = jQuery('#'+newMeldId);
		var addedMeldFrame = targetMeld.find('.card-frame');
		addedMeldFrame.remove();

		noOfCards = 0;
	}

	wpcardznet_copycard(targetMeld, cardId, targetPosn);

	return true;
}

function wpcardznet_copycard(targetDiv, cardId, targetPosn)
{
	var noOfCards = targetDiv.find('.card-face').length;

	var cardPosn = 0;
 	if (targetPosn == AddToBottom) 
 	{
 		// Move up existing cards in the meld
		cardPosn = window.meldYSpacing * noOfCards;
		
		var cardsList = targetDiv.find('.card-frame');
		for (var m = 0; m < cardsList.length; m++) 
		{
			var posnStyle = cardPosn + "px";
			jQuery(cardsList[m]).css('bottom', posnStyle);
			cardPosn -= window.meldYSpacing;
		}
		cardPosn = 0;
	}
	else
	{
		cardPosn = noOfCards * window.meldYSpacing;
	}	
	
	// Get the HTML for the card and its' frame
	var cardHTML = jQuery('#'+cardId).parent()[0].outerHTML;

	// Remove redundant class Ids
	cardHTML = cardHTML.replace("activeframe", "");
	cardHTML = cardHTML.replace("activecard", "");
	cardHTML = cardHTML.replace("selectedcard", "");
	cardHTML = wpcardznet_removeStyle(cardHTML, 'top');
	cardHTML = wpcardznet_removeStyle(cardHTML, 'left');
	cardHTML = wpcardznet_removeStyle(cardHTML, 'bottom');
	cardHTML = wpcardznet_addStyle(cardHTML, 'bottom', cardPosn + 'px');

	// Add the card to the meld
 	if (targetPosn == AddToBottom) 
	{
		targetDiv.append(cardHTML);
	}
	else
	{
		targetDiv.prepend(cardHTML);
	}
	
}

function wpcardznet_checkMelds()
{
	var meldsValid = true;
	
	// Find all the meld groups
	var meldElems = jQuery('.ourmelds').find('.meldframe');
	for (var m = 0; m < meldElems.length; m++) 
	{
		var targetMeld = meldElems[m];
		if (targetMeld.id == 'melddiv_new')
			continue;

		var cardsList = jQuery(targetMeld).find('.card-face');
		if (cardsList.length < 3) 
		{
			meldsValid = false;
			break;
		}
	}

	return meldsValid;
}

function wpcardznet_checkMeld(cardsList)
{
    var rtnData = 
    {
        'valid': false,
        'type': '',
        'status': MeldIsNotValid,
    };
    
	var meldState = wpcardznet_isValidSet(cardsList);
	if (meldState != MeldIsNotValid)
	{
		rtnData['type'] = 'set';
	}
	else 
	{
		meldState = wpcardznet_isValidRun(cardsList);
		if (meldState == MeldIsNotValid)
			return rtnData;	
	
		rtnData['type'] = 'run';
	}
	
	rtnData['valid'] = true;
	rtnData['status'] = meldState;
	return rtnData;	
}

function wpcardznet_isValidSet(cardsList)
{
	var setCardNumber = -1;
	var noOfNaturals = 0;
	
	// Loop through cards
	for (var c = 0; c < cardsList.length; c++) 
	{
		var thisCardNo = wpcardznet_cardNumberFromClass(cardsList[c].className);

		if (setCardNumber == -1)
		{
			setCardNumber = thisCardNo;
		}
		else if (setCardNumber != thisCardNo)
			return MeldIsNotValid;		
		
		noOfNaturals++;
	}
	
	if (noOfNaturals < 3)
		return MeldIsIncomplete;
	
	return MeldIsValid;
}

function wpcardznet_isValidRun(cardsList)
{
	var firstCardSuit = -1;
	var firstCardRank = -1;
	var cardOfst = 0;
	var noOfNaturals = 0;
	
	// Loop through cards
	for (var c = 0; c < cardsList.length; c++) 
	{
		var thisCardSuit = wpcardznet_cardSuitFromClass(cardsList[c].className);
		var thisCardRank = wpcardznet_cardRankFromClass(cardsList[c].className);

		if (c == 0)
		{
			firstCardSuit = thisCardSuit;
			firstCardRank = thisCardRank;
		}
		else if (firstCardSuit != thisCardSuit)
			return MeldIsNotValid;
		
		if (c == 1)
		{
			cardOfst = (thisCardRank - firstCardRank);
			if ((cardOfst != 1) && (cardOfst != -1))
				return MeldIsNotValid;			
		}
		else if (c > 1)
		{
			cardOfst = cardOfst + cardOfst;
			if ((thisCardRank - firstCardRank) != cardOfst)
				return MeldIsNotValid;
		}
		
		noOfNaturals++;
	}
	
	if (noOfNaturals < 3)
		return MeldIsIncomplete;
	
	return MeldIsValid;
}

function wpcardznet_OnRefreshGameBoard()
{
	var meldElems = jQuery('.ourmelds').find('.meldframe');
	hasAlreadyLaid = (meldElems.length > 2);	// We have already laid if there are melds on refresh

	// Call wpcardznet_setActiveAndInactive to refresh card active states
	wpcardznet_setActiveAndInactive();
}

/*
function wpcardznet_containsClass(cardFrameSpec, srchClass)
{
	var meldsList = jQuery(cardFrameSpec).has('.'+srchClass);
	return meldsList;
}

function wpcardznet_searchForCards(cardFrameSpec, srchFor)
{
	var noOfCards = 0;
	var cardsList = jQuery(cardFrameSpec).find('.'+srchFor);
	noOfCards = cardsList.length;
	return noOfCards;
}

function wpcardznet_game_unhidecardsClick()
{
	wpcardznet_setActiveAndInactive();
}
*/
function wpcardznet_setActiveAndInactive()
{
	wpcardznet_getGlobals();

	var selectedCards = jQuery('.playercards').find('.selectedcard');
	var noOfCardsSelected = selectedCards.length;

	var stockElem = jQuery('#stockcards');
	var stockIsActive = stockElem.hasClass('activecard') || stockElem.hasClass('was_activecard');;

	wpcardznet_deactivateCards('.card-face');

	uniqueTargetMeld = '';

	if (jQuery('#showscores').length)
		return;

	if (jQuery('#unhidecardsbutton').length) 
	{
		if (stockIsActive)
			stockElem.addClass('was_activecard');
		return;
	}

	// Check if all melds have minimum number of cards
	meldsComplete = wpcardznet_checkMelds();

	if (stockIsActive)
	{
		wpcardznet_activateCards('#stockcards');
		wpcardznet_activateCards('#discards');
	}
	else 
	{
		wpcardznet_activateCards('.playercards');
		
		if (noOfCardsSelected > 0) 
		{
			// Get the selected card in players hand
			var selCardId = selectedCards[0].children[0].id;
			
			// Find all melds it can be added to and activate them
			var allMelds = jQuery('.ourmelds').children();
			var activeTargetsCount = 0;
			for (var m = 0; m < allMelds.length; m++) 
			{
				var targetMeld = allMelds[m];
				if (wpcardznet_isTargetValid(jQuery(targetMeld), selCardId) != null) 
				{
					if (targetMeld.id != 'melddiv_new') 
					{
						// Save the meld that the selected card can be added to
						uniqueTargetMeld = targetMeld.id;
						activeTargetsCount++;
					}
					wpcardznet_activateCards('#'+targetMeld.id);
				}
			}
			
			// Player must select target if there are multiple melds that match
			if (activeTargetsCount > 1)
				uniqueTargetMeld = '';

			if (meldsComplete) 
			{
				wpcardznet_activateCards('#discards');
			}
		}
	}
}

function wpcardznet_updateCardsState(cardFrameSpec, cardSpec, isActive)
{
	var cardsInFrame;
	var frameSelector = jQuery(cardFrameSpec);
	if (typeof cardSpec !== 'undefined')
		frameSelector = frameSelector.find(cardSpec);

	if (frameSelector.hasClass('card-face'))
		cardsInFrame = frameSelector;
	else
		cardsInFrame = frameSelector.find('.card-face');

	frameSelector = cardsInFrame.parent();
	if (isActive) 
	{
		cardsInFrame.addClass('activecard');
		frameSelector.addClass('activeframe');
	} 
	else 
	{
		cardsInFrame.removeClass('activecard');
		frameSelector.removeClass('activeframe');
	}
}

function wpcardznet_deactivateCards(cardFrameSpec, cardSpec)
{
	wpcardznet_updateCardsState(cardFrameSpec, cardSpec, false);
}

function wpcardznet_activateCards(cardFrameSpec, cardSpec)
{
	wpcardznet_updateCardsState(cardFrameSpec, cardSpec, true);
}
/*
function wpcardznet_activateCardsByNumber(cardFrameSpec, cardName)
{
	var cardNumber = wpcardznet_cardNumberFromName(cardName);
	wpcardznet_activateCards(cardFrameSpec, '.'+cardNumber+'-of-clubs');
	wpcardznet_activateCards(cardFrameSpec, '.'+cardNumber+'-of-diamonds');
	wpcardznet_activateCards(cardFrameSpec, '.'+cardNumber+'-of-hearts');
	wpcardznet_activateCards(cardFrameSpec, '.'+cardNumber+'-of-spades');
}
*/
function wpcardznet_removecardinhand(cardToRemoveId)
{
	var cardSelector = jQuery('.playercards').find("#fr_"+cardToRemoveId);
	var cardsFrame = cardSelector.parent();

	// Get the position of the card to be removed
	var cardPosn = cardSelector[0].offsetLeft;

	// Remove the card from the hand
	cardSelector.remove();

	// Shuffle up remaining cards
	var handCardFrames = cardsFrame.children();
	for (var i = 0; i < handCardFrames.length; i++) 
	{
		rowElem = handCardFrames[i];
		id = rowElem.id;

		var thisCardPosn = handCardFrames[i].offsetLeft;
		if (thisCardPosn > cardPosn) 
		{
			var newPosn = (thisCardPosn - 50) + "px" ;
			handCardFrames[i].style.left = newPosn;
		}
	}
}
/*
function wpcardznet_can_update_screen()
{
	return true;
}

function wpcardznet_enable_getActiveCards()
{
	// Disable auto selection of a card in players hand
	return false;
}

function wpcardznet_toggleOpponentsCards()
{
	meldToggleCounter++;

	var theirMeldsElems = jQuery('.theirmelds');
	var playerCardsElems = jQuery('.playercards');
	var ourMeldsElems = jQuery('.ourmelds');
	var centreCardsElems = jQuery('.centrecards');
	var prevdiscardsardsElems = jQuery('#prev-discards');
	var newMeldsElems = jQuery('#melddiv_new');

	switch (meldToggleCounter) 
	{
		case 1:
			theirMeldsElems.show();
			playerCardsElems.hide();
			centreCardsElems.hide();
			prevdiscardsardsElems.show();
			newMeldsElems.hide();
			break;
*/
/*
case 2:
theirMeldsElems.show();
playerCardsElems.show();
break;
*/
/*
		default:
			theirMeldsElems.hide();
			playerCardsElems.show();
			centreCardsElems.show();
			prevdiscardsardsElems.hide();
			newMeldsElems.show();

			meldToggleCounter = 0;
			break;
	}
}
*/