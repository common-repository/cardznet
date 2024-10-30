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
var meldYSpacing = 10;

var handXSpacing = 50;
var minimumMeld = 0;

var canastaYSpacing = 7;

var meldsList = '';
var meldsValid = true;

var meldsComplete = true;

var uniqueTargetMeld = '';

var replacedRedThree = false;
var laidRedThree = false;

var pickingUpPack = false;
var pickingUpPackCard = '';
var pickingUpFrozenPack = false;
var pickingUpFrozenCount = 0;
var pickedUpPack = false;

var hasAlreadyLaid = false;

var meldToggleCounter = 0;

function wpcardznet_reset_canasta()
{
	meldsList = '';
	meldsValid = true;
	pickingUpPack = false;
	pickingUpPackCard = '';
	pickingUpFrozenPack = false;
	pickingUpFrozenCount = 0;
	laidRedThree = false;
	meldToggleCounter = 0;
	
	wpcardznet_reset_vars();
}

function wpcardznet_getGlobals()
{
	meldXSpacing = wpcardznet_getHiddenInput('meldXSpacing');
	meldYSpacing = wpcardznet_getHiddenInput('meldYSpacing');
	handXSpacing = wpcardznet_getHiddenInput('handXSpacing');
	canastaYSpacing = wpcardznet_getHiddenInput('canastaYSpacing');
	
	minimumMeld = wpcardznet_getHiddenInput('minimumMeld');	
	playerMode = wpcardznet_getHiddenInput('playerMode');
	
	if (pickingUpPack) playerMode = 'normal';

	replacedRedThree = (playerMode === 'replacedRedThree');
	pickedUpPack = (playerMode === 'pickedUpPack');
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
/*
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
*/			
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

function wpcardznet_gooutAskClick()
{
	wpcardznet_gooutClick('goOutReq');
}

function wpcardznet_gooutYesClick()
{
	wpcardznet_gooutClick('goOutAuthorised');
}

function wpcardznet_gooutNoClick()
{
	wpcardznet_gooutClick('goOutReqDenied');
}

function wpcardznet_gooutClick(goOutAction)
{
	wpcardznet_logClick('clickGoOut', '', '');
	
	wpcardznet_SetBusy(true, 'card');
	var data = wpcardznet_AJAXPrepare('playerUI');
	data['clicksList'] = clicksList;
	data['UIAction'] = goOutAction;
	
	wpcardznet_reset_canasta();	
	wpcardznet_updateUI(data, wpcardznet_AJAXcb_refresh, wpcardznet_AJAXcb_error);
}

function wpcardznet_playCard(cardGUI)
{
	if (AJAXActive) return;

	wpcardznet_getGlobals();

	var divElem = jQuery("#"+cardGUI);
	var baseDiv = divElem.closest('.tablediv');

	if (baseDiv.hasClass('playercards'))
	{
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

	if (baseDiv.hasClass('centrecards'))
	{
		wpcardznet_logClick('clickCard', 'centrecards', divElem[0]);
	
		if (divElem.hasClass('card-blank'))
		{
			wpcardznet_clickPack();
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
		replacedRedThree = false;
		pickingUpPackCard = cardName;
		pickingUpFrozenPack = wpcardznet_isPackFrozen();
		if (pickingUpFrozenPack) pickingUpFrozenCount = 0;
		
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

	if (baseDiv.hasClass('ourmelds'))
	{
		wpcardznet_logClick('clickCard', 'ourmelds', divElem[0]);
	
		// Handle adding a card to an existing/new meld
		// Find the selected card
		var selectedcardElem = wpcardznet_getSelectedElem();
		if (selectedcardElem === '') return true;
		
		var cardId = selectedcardElem.id;
		var targetMeld = divElem.closest('.meldframe');
		
		if (!wpcardznet_addToMeld(targetMeld, cardId))
			return true;
			
		if (pickingUpFrozenPack) pickingUpFrozenCount++;
		
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

	if (baseDiv.hasClass('theirmelds'))
	{
		// Handle viewing an opponents meld
	}

	return true;
}

function wpcardznet_clickDiscard()
{
	wpcardznet_logClick('clickDiscard', '', '');
	
	var isActiveCard = jQuery("#discards").hasClass('activecard');
	if (!isActiveCard) return true;
	
	if (!meldsValid) return true;
	
	wpcardznet_SetBusy(true, 'card');
	var data = wpcardznet_AJAXPrepare('playerUI');
	data['clicksList'] = clicksList;
	data['UIAction'] = 'discard';
	
	// Get cardNo of selected card
	var cardNo = wpcardznet_getSelectedCardId();
	data['cardNo'] = cardNo;
	
	if (meldsList !== '')
		data['addedtomelds'] = meldsList;
	
	wpcardznet_reset_canasta();	
	wpcardznet_updateUI(data, wpcardznet_AJAXcb_refresh, wpcardznet_AJAXcb_error);
}

function wpcardznet_clickPack()
{
	wpcardznet_logClick('clickPack', '', '');
	var isActiveCard = jQuery("#discards").hasClass('activecard');
	if (!isActiveCard) return true;
	
	wpcardznet_SetBusy(true, 'card');
	var data = wpcardznet_AJAXPrepare('playerUI');
	data['clicksList'] = clicksList;
	data['UIAction'] = 'pickuppack';

	data['addedtomelds'] = meldsList;	

	wpcardznet_reset_canasta();		
	wpcardznet_updateUI(data, wpcardznet_AJAXcb_refresh, wpcardznet_AJAXcb_error);
}

function wpcardznet_replaceThreeFromDeal()
{
	wpcardznet_logClick('replaceThreeFromDeal', '', '');
	
	wpcardznet_clickStock('replaceThreeFromDeal');
}

function wpcardznet_replaceThreeFromHand()
{
	wpcardznet_logClick('replaceThreeFromHand', '', '');
	
	wpcardznet_clickStock('replaceThreeFromHand');
}

function wpcardznet_getCardFromStock()
{
	wpcardznet_logClick('getFromStock', '', '');
	
	wpcardznet_clickStock('getcard');
}

function wpcardznet_clickStock(clickAction)
{
	var isActiveCard = jQuery("#stockcards").hasClass('activecard');
	if (!isActiveCard) return true;
	
	wpcardznet_SetBusy(true, 'card');
	var data = wpcardznet_AJAXPrepare('playerUI');
	data['clicksList'] = clicksList;
	
	data['UIAction'] = clickAction;
	if (meldsList !== '')
	{
		data['addedtomelds'] = meldsList;
	}
		
	wpcardznet_reset_canasta();	
	wpcardznet_updateUI(data, wpcardznet_AJAXcb_refresh, wpcardznet_AJAXcb_error);
}

function wpcardznet_isCardWild(cardNumber)
{
	switch (cardNumber)
	{
		case 'two':
		case 'joker':
			return true;
			
		default:
			return false;
	}
}

function wpcardznet_hasCanasta()
{
	var melds = jQuery('.ourmelds').children();
	for (var m = 0; m < melds.length; m++)
	{
		var targetMeld = jQuery(melds[m]);
		var meldCardCount = targetMeld.children().length;
		if (meldCardCount >= 7) return true;
	}

	return false;
}

function wpcardznet_isTargetValid(targetMeld, cardId)
{
	// Selected card 
	var selCardElems = jQuery('#'+cardId);
	var selCardName = wpcardznet_cardNameFromClass(selCardElems[0].className);
	switch (selCardName)
	{
		case 'three-of-spades':
		case 'three-of-clubs':
			// Can only play black threes if ....
			// There is only one other card in players hand 
			var noOfThrees = wpcardznet_searchForCards('.playercards', 'three');
			var noOfCardsInHand = jQuery('.playercards').find('.card-face').length;
			if ((noOfCardsInHand-noOfThrees) > 1) return null;
			
			// and there is at least one canasta 
			if (!wpcardznet_hasCanasta()) 
				return null;
			break;
			
		case 'three-of-diamonds':
		case 'three-of-hearts':
			switch (targetMeld[0].id)
			{
				case 'melddiv_new':
				case 'melddiv_0':
					targetMeld = jQuery('#melddiv_0');
					laidRedThree = true;
					return targetMeld;
				
				default:
					return null;
			}
			break;
			
		default:
			break;
	}
	
	var selCardNumber = wpcardznet_cardNumberFromName(selCardName);
	
	// Get the list of cards in the meld
	var cardsList = targetMeld.find('.card-face');
	var noOfCards = cardsList.length;
	
	var wildsCount = 0;

	if (targetMeld[0].id == 'melddiv_0')
	{
		// The first meld position is reserved for the red threes
		return null;
	}
	else if (targetMeld[0].id == 'melddiv_new')
	{
		// Can't create a new meld with a wild card'
		if (wpcardznet_isCardWild(selCardNumber))
			return null;
	}
	else
	{
		// Search the cards list for a non-wild cardHTML
		var destCardNumber = '';
		for (var i=0; i<noOfCards; i++)
		{
			var thisCardNumber = wpcardznet_cardNumberFromClass(cardsList[i].className);
			if (wpcardznet_isCardWild(thisCardNumber))
				wildsCount++;
			else
				destCardNumber = thisCardNumber;
		}
		
		if (wildsCount > 0)
		{
			if (noOfCards >= 7) return null;			
		}
		else
		{
			if (noOfCards >= 8) return null;	
		}
		
		if (wpcardznet_isCardWild(selCardNumber))
		{
			// Must have two natural cards before adding a wild
			if (noOfCards < 2) return null;
				
			// Limit number of wilds in a meld
			if (wildsCount >= 3) return null;
				
			if (noOfCards >= 7) return null;	
			
			if (selCardNumber == "three") return null;
		}
		else
		{
			// Can only add the same number
			if ((noOfCards > 0) && (selCardNumber != destCardNumber))
				return null;
		}
	}
	
	return targetMeld;
}

function wpcardznet_addToMeld(targetMeld, cardId)
{
	if ((targetMeld[0].id == 'melddiv_new') && (uniqueTargetMeld != ''))
	{
		targetMeld = jQuery('#'+uniqueTargetMeld).closest('.meldframe');
	}
	
	targetMeld = wpcardznet_isTargetValid(targetMeld, cardId);
	if (targetMeld === null) return false;
	
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
	
	wpcardznet_copycard(cardId, targetMeld, window.meldYSpacing);
	
	return true;
}

function wpcardznet_copycard(cardId, targetDiv, spacing, targetId)
{
	var noOfCards = targetDiv.find('.card-face').length;
	
	var posnStyle;
	if (targetDiv.hasClass('meldframe'))
		posnStyle = 'bottom';
	else
		posnStyle = 'left';
	
	// Get the HTML for the card and its' frame
	var cardHTML = jQuery('#'+cardId).parent()[0].outerHTML;
		
	// Remove redundant class Ids
	var cardPosn = (noOfCards * spacing);
	cardHTML = cardHTML.replace("selectedcard", "");
	cardHTML = wpcardznet_removeStyle(cardHTML, 'top');
	cardHTML = wpcardznet_removeStyle(cardHTML, 'left');
	cardHTML = wpcardznet_addStyle(cardHTML, posnStyle, cardPosn + "px");
	
	// Change the name of the card (if specified)
	if (typeof targetId !== 'undefined')
	{
		cardHTML = cardHTML.split(cardId).join(targetId);
		targetDiv.append(cardHTML);	
	}
	else
	{
		targetDiv.prepend(cardHTML);
		
		var rollupCount = 6;
		if (targetDiv[0].id == 'melddiv_0') rollupCount = 3;
		
		if (noOfCards >= rollupCount)
		{
			// noOfCards is now one less than the number of cards 
			
			// Made a canasta ... roll it up!
			var cardDivs = targetDiv.find('.card-frame');
			var canastaType = wpcardznet_getCanastaType(targetDiv);
			for (var cardIndex = 0; cardIndex < cardDivs.length; cardIndex++)
			{
				var bottomPosn = (noOfCards - cardIndex) * canastaYSpacing;
				jQuery(cardDivs[cardIndex]).css("bottom", bottomPosn);
				if (cardIndex < cardDivs.length-1)
					jQuery(cardDivs[cardIndex]).addClass(canastaType);
			}
		}
	}
	
}

function wpcardznet_getCanastaType(targetDiv)
{
	var cardDivs = targetDiv.find('.card-frame');
	if (cardDivs.length < 7) return 'none';
	
	for (var cardIndex = 0; cardIndex < cardDivs.length; cardIndex++)
	{
		var cardFrame = cardDivs[cardIndex];
		var cardDiv = jQuery(cardFrame).find('.card-face');
		var cardNo = wpcardznet_cardNumberFromClass(cardDiv[0].className);
		if (wpcardznet_isCardWild(cardNo))
			return 'mixed-canasta';
	}
	
	return 'pure-canasta';
}

function wpcardznet_getValue(cardElem)
{
	var cardName = wpcardznet_cardNameFromClass(cardElem.className);
	var cardNo = wpcardznet_cardIdFromClass(cardElem.className);
	var cardIndex = parseInt(cardNo.replace("card_", ""), 10);
	if (cardIndex <= 2)
		return 100;	// Red 3
	if (cardIndex <= 20)
		return 5;	// Black 3 to 7
	if (cardIndex <= 44)
		return 10;	// 8 to King
	if (cardIndex <= 48)
		return 25;	// Aces 
	if (cardIndex <= 52)
		return 20;	// 2's 
	return 50;
}

function wpcardznet_checkMelds()
{
	var errMeldsCount = 0;
	var meldsValue = 0;
	
	// Find all the meld groups
	var meldElems = jQuery('.ourmelds').find('.meldframe');
	for (var m = 0; m < meldElems.length; m++)
	{
		var targetMeld = meldElems[m];
		if (targetMeld.id == 'melddiv_new') continue;
		if (targetMeld.id == 'melddiv_0') continue;
		
		var cardsList = jQuery(targetMeld).find('.card-face');
		var noOfCards = cardsList.length;
		
		if (noOfCards < 3)
			errMeldsCount++;
			
		if (!hasAlreadyLaid)
		{
			for (var c = 0; c < cardsList.length; c++)
			{
				meldsValue += wpcardznet_getValue(cardsList[c]);
			}
		}
	}
	
	if (errMeldsCount !== 0)
		return false;
	
	if (!hasAlreadyLaid)
	{
		// Check that the melds total exceeds the minimum 
		if ((meldsValue < minimumMeld) && (meldsValue > 0))
			return false;
		
	}
	
	return true;
}

function wpcardznet_OnRefreshGameBoard()
{
	var meldElems = jQuery('.ourmelds').find('.meldframe');	
	hasAlreadyLaid = (meldElems.length > 2);	// We have already laid if there are melds on refresh 

	// Call wpcardznet_setActiveAndInactive to refresh card active states 
	wpcardznet_setActiveAndInactive();
}

function wpcardznet_isRedThree(cardElem)
{
	if (cardElem.className.indexOf('card-frame') != -1)
		cardElem = cardElem.children[0];
		
	var cardName = 	wpcardznet_cardNameFromClass(cardElem.className);
	switch (cardName)
	{
		case 'three-of-diamonds':
		case 'three-of-hearts':
			return true;
	}
	return false;
}

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

function wpcardznet_isPackFrozen()
{
	// If the pack is frozen ... can't pick up pack 
	if (jQuery('#stock-frozen').length > 0) return true;
	
	// Pack is frozen until a meld has been made (Note: meldiv_0 always exists)
	var noOfMelds = jQuery('.ourmelds').find('.meldframe').length;
	if (noOfMelds <= 2) return true;
	
	return false;
}

function wpcardznet_canPickupPack(topDiscard, selectedCards)
{
	// If pack is frozen ... must have a pair in hand
	cardsInHand = wpcardznet_searchForCards('.playercards', topDiscard);
	if (cardsInHand >= 2) return true;
	
	// If the pack is frozen ... can't pick up pack 
	if (wpcardznet_isPackFrozen()) return false;
	
	// TODO: Find a meldframe that contains topDiscard
	
	// If there is a match with existing melds ... we can pick up unless it is a canasta
	var matchingMelds = wpcardznet_containsClass('.meldframe', topDiscard);
	for (var m = 0; m < matchingMelds.length; m++)
	{
		var matchingMeldId = matchingMelds[m].id;		
		var matchingCards = wpcardznet_searchForCards('#'+matchingMeldId, 'card-face');
		if (matchingCards != 7)
			return true;
	}
	
	// Then ... Must have at least one in hand
	if (cardsInHand == 0) return false;
	
	// and we need a wild
	if (wpcardznet_searchForCards('.playercards', 'two') > 0) return true;
	if (wpcardznet_searchForCards('.playercards', 'joker') > 0) return true;
	
	return false;
}

function wpcardznet_game_unhidecardsClick()
{
	wpcardznet_setActiveAndInactive();
}

function wpcardznet_setActiveAndInactive()
{
	wpcardznet_getGlobals();

	var selectedCards = jQuery('.playercards').find('.selectedcard');
	var noOfCardsSelected = selectedCards.length;

	var stockIsActive = jQuery('#stockcards').hasClass('activecard') || jQuery('#stockcards').hasClass('was_activecard');;
	
	wpcardznet_deactivateCards('.card-face');
	
	uniqueTargetMeld = '';
	
	if (jQuery('#showscores').length) return;
	if (jQuery('#goOutYesButton').length) return;
	
	if (jQuery('#unhidecardsbutton').length) 
	{
		if (stockIsActive) jQuery('#stockcards').addClass('was_activecard');
		return;
	}
	
	meldsComplete = wpcardznet_checkMelds();
	
	if (laidRedThree && !pickedUpPack)
	{
		// Just allow click on stock 
		wpcardznet_activateCards('#fr_stockcards');
		return;
	}
	
	// Find the first card in the hand
	var allCardsInHand = jQuery('.playercards ').find('.card-face');
	var noOfCardsInHand = allCardsInHand.length;
	if (noOfCardsInHand > 0)
	{
		var firstCardInHand = allCardsInHand[0];
		if (wpcardznet_isRedThree(allCardsInHand[0]))
		{
			if (noOfCardsSelected > 1)
			{
				// Just activate any red threes so we can select just one
				wpcardznet_activateCards('.playercards', '.three-of-diamonds');
				wpcardznet_activateCards('.playercards', '.three-of-hearts');
				return;
			}
			
			var redThreeSelected = true;
			if (noOfCardsSelected === 0)
			{
				// Select the first card (it's a red three)
				jQuery(allCardsInHand[0]).parent('div').addClass('selectedcard');
				var firstCardId = allCardsInHand[0].id;
				wpcardznet_activateCards('#'+firstCardId);
			}
			else
			{
				if (!wpcardznet_isRedThree(selectedCards[0]))
				{
					wpcardznet_activateCards('.playercards', '.three-of-diamonds');
					wpcardznet_activateCards('.playercards', '.three-of-hearts');
					redThreeSelected = false;
				}
			}

			// If a red three is selected .... just activate melds 
			if (redThreeSelected)
			{
				wpcardznet_activateCards('#melddiv_new');
				wpcardznet_activateCards('#melddiv_0');
			}
			
			return;
		}
						
	}
	
	if (!replacedRedThree)
	{
		if ((noOfCardsSelected == 1) && !stockIsActive)
		{
			var selCardId = selectedCards[0].children[0].id;
			var allMelds = jQuery('.ourmelds').children();
			var activeTargetsCount = 0;
			for (var m = 0; m < allMelds.length; m++)
			{
				var targetMeld = allMelds[m];
				if (wpcardznet_isTargetValid(jQuery(targetMeld), selCardId) != null)
				{
					if (targetMeld.id != 'melddiv_new')
					{
						// save the last meld that this card can be added to
						uniqueTargetMeld = targetMeld.id;
						activeTargetsCount++;
					}
					wpcardznet_activateCards('#'+targetMeld.id);
				}
			}
			if (activeTargetsCount > 1) uniqueTargetMeld = '';

			if (selectedCards[0].id == 'fr_fromPack')
				return;
				
			if (meldsComplete && !pickingUpPack)
			{
				var canDiscard = true;
				if (noOfCardsInHand == 1)
				{
					var goOutResponseElems = jQuery('#goOutResponse');
					canDiscard = ((goOutResponseElems.length > 0) && goOutResponseElems.hasClass('tick'));
				}
				if (canDiscard)
				{					
					// Only alter the activation state when not already picking up pack 
					wpcardznet_activateCards('#fr_discards'); // Activate discard pile so player can end turn		
					wpcardznet_activateCards('#stock-frozen'); // Include the "freeze" card as well ...
				}
			}
		}

		if (meldsComplete && pickingUpPack)
		{
			wpcardznet_activateCards('#discards');
			wpcardznet_activateCards('#stock-frozen');					
		}
	}

	if (stockIsActive || replacedRedThree)
	{
		wpcardznet_activateCards('#stockcards');
		// Cannot pick up pack if top card is 3 or wild
 
		var discardElems = jQuery('#discards');
		var topDiscard = wpcardznet_cardNumberFromClass(discardElems[0].className);
		switch (topDiscard)	
		{
			case 'two':
			case 'three':
			case 'joker':
				break;
				
			default:
				if (wpcardznet_canPickupPack(topDiscard, selectedCards))
				{
					wpcardznet_activateCards('#discards'); // Activate pack so player can pick it up ...
				}
				break;
		}

		return;
	}

	if (pickingUpFrozenPack)
	{
		if (pickingUpFrozenCount < 3)
		{
			wpcardznet_activateCardsByNumber('.playercards', pickingUpPackCard); 
			return;
		}
		
		pickingUpFrozenPack = false;
	}
	
	wpcardznet_activateCards('.playercards');
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

function wpcardznet_activateCardsByNumber(cardFrameSpec, cardName)
{
	var cardNumber = wpcardznet_cardNumberFromName(cardName);
	wpcardznet_activateCards(cardFrameSpec, '.'+cardNumber+'-of-clubs');
	wpcardznet_activateCards(cardFrameSpec, '.'+cardNumber+'-of-diamonds');
	wpcardznet_activateCards(cardFrameSpec, '.'+cardNumber+'-of-hearts');
	wpcardznet_activateCards(cardFrameSpec, '.'+cardNumber+'-of-spades');
}

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
/*
		case 2:
			theirMeldsElems.show();
			playerCardsElems.show();
			break;
*/
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
