/* 
Description: Javascript & jQuery Code for CardzNet
 
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

var gameId;
var gameName;

var tickerTimeout = 5000;	// Default - Updated by WP settings 
var tickerColour = "";
var tickerCount = 0;
var tickerTimer = 0;
var activeTimeout = 600000; // 10 minutes
var timeToTimeout = 0;
var selectedCardIndex = -1;

var lastTimeout = 0;

var hideWPElements = true;

var timerRunning = false;
var AJAXActive = false;
var gameEnded = false;


var clicksList = '';

function wpcardznet_reset_vars()
{
	clicksList = '';
}

function wpcardznet_OnLoadTabletop()
{
	wpcardznet_setupScreen();
	gameId = wpcardznet_getHiddenInput('AJAX-gameId');
	if ( gameId == 'undefined') return;

	gameName = wpcardznet_getHiddenInput('AJAX-gameName');
	
	wpcardznet_OnRefresh();
	
	timeToTimeout = activeTimeout;
	
	wpcardznet_getActiveCards();
	wpcardznet_handler('body', 'keydown', wpcardznet_onkeydown);
	wpcardznet_handler('body', 'contextmenu', wpcardznet_disableEvent);
	wpcardznet_handler('div', 'selectstart', wpcardznet_disableEvent);
	
	wpcardznet_startTickerTimer(tickerTimeout, 'onLoad');
}

function wpcardznet_OnLoadResponse()
{
	wpcardznet_setupScreen();
}

function wpcardznet_OnRefresh()
{
	if (typeof wpcardznet_OnRefreshGameBoard === 'function') 
		wpcardznet_OnRefreshGameBoard();
		
	if (typeof wpcardznet_SoundOnRefresh !== 'undefined') 
		wpcardznet_playSound(wpcardznet_SoundOnRefresh);
}

function wpcardznet_get_topelem()
{
	var topElem = jQuery('.wpcardznet_msgpage').parent();
	if (topElem.length > 0) return topElem;
		
	topElem = jQuery('#wpcardznet_form').parent();
	if (topElem.length > 0) return topElem;
	
	topElem = jQuery('#loginform').parent().parent().parent();
	if (topElem.length > 0) return topElem;
	
	topElem = jQuery('#message').parent();
	if (topElem.length > 0) return topElem;
	
	return topElem;
}

function wpcardznet_setupScreen()
{
	if (!hideWPElements) return;
	
	enterFSClassName = "fullscreen";
	exitFSClassName = "normalscreen";
	
	wpcardznetlib_AddFullScreenEvent("fullscreen-button", 'wpcardznet_page', 'wpcardznet_body');
	
	var parentElem = wpcardznet_get_topelem();

	wpcardznet_removeWPElements(parentElem, 1);

	wpcardznet_removeThemeStyles();
	
	// Change the id of the html element so we can style it 
	var htmlElems = jQuery('html');
	htmlElems.removeClass();
	htmlElems.attr('id', "wpcardznet_html");
}

function wpcardznet_removeThemeStyles()
{
	var headElem = jQuery('head');
	
	// Now get theme styles in headElem
	var themeStyles = headElem.children().filter(
		function(index)
		{
			var rtnVal = false;
			
			switch (this.tagName)
			{
				case 'LINK':
					var href = this.href;
					if (href.search("themes/") != -1)
					{
						rtnVal = true;
					}
					break;
					
				case 'STYLE':
					rtnVal = true;
					break;
					
				default:
					break;
			}
			
			return rtnVal;
		}
	);
	
	themeStyles.remove();
}

function wpcardznet_removeWPElements(anchorElem, divLevel)
{
	var tagName = anchorElem[0].tagName;
	var elemId = anchorElem.attr('id');
	var elemClass = anchorElem.attr('class');
	
	if (tagName == 'BODY')
	{
		anchorElem.removeClass();
		anchorElem.attr('id', "wpcardznet_body");
		return;
	}
	
	// Remove its siblings
	var siblings  = anchorElem.siblings();
	siblings = siblings.filter(
		function(index)
		{
			var rtnVal = true;
			
			switch (this.tagName)
			{
				case 'LINK':
				case 'SCRIPT':
					var tagId = this.id;
					if (tagId.search("wpcardznet") != -1)
					{
						rtnVal = false;
					}
					break;
					
				default:
					break;
			}
			
			return rtnVal;
		}
	);
	siblings.remove();

	// Now clear the id and classes of this element
	anchorElem.removeAttr("id role");
	anchorElem.attr('id', "wpcardznet_divno"+divLevel);
	
	//anchorElem.id="";
	anchorElem.removeClass();
	
	// Recursively call wpcardznet_removeWPElements for parent element
	var parentElem = anchorElem.parent();
	wpcardznet_removeWPElements(parentElem, divLevel+1);
}

function wpcardznet_handler(elemSelector, elemEvent, elemHandler)
{
	var elemsList = jQuery(elemSelector);
	if (elemsList.length == 0) return;
	elemsList.on(elemEvent, elemHandler);
}

function wpcardznet_disableEvent(event)
{
	if (event.ctrlKey) return true;
	
	event.preventDefault();
	return false;	// Disable default action 
}

function wpcardznet_onkeydown(event)
{
	if (typeof wpcardznet_game_onkeydown !== 'undefined')
		wpcardznet_game_onkeydown(event);
}


function wpcardznet_getSelectedElem()
{
	var selectedFrameElems = jQuery(".selectedcard");
	if (selectedFrameElems.length == 0) selectedFrameElems = jQuery(".playedcard");
	if (selectedFrameElems.length == 0) return "";
	
	selectedCardElems = selectedFrameElems.find('.card-face');
	
	return selectedCardElems[0];
}

function wpcardznet_getSelectedCardGID()
{
	var selectedcardElem = wpcardznet_getSelectedElem();
	if (selectedcardElem == "") return "";
	return selectedcardElem.id;
}
	
function wpcardznet_getSelectedCardId()
{
	var selectedcardElem = wpcardznet_getSelectedElem();
	if (selectedcardElem == "") return "";
	var cardId = wpcardznet_cardIdFromClass(selectedcardElem.className);
	return cardId;
}

function wpcardznet_getSelectedCardName()
{
	var selectedcardElem = wpcardznet_getSelectedElem();
	if (selectedcardElem == "") return "";
	var cardName = wpcardznet_cardNameFromClass(selectedcardElem.className);
	return cardName;
}

function wpcardznet_playSelectedCard()
{
	var cardGID = wpcardznet_getSelectedCardGID();
	wpcardznet_playCard(cardGID);
}

function wpcardznet_clickCard(obj)
{
	var cardGID = obj.id;
	wpcardznet_playCard(cardGID);
}

function wpcardznet_cardNumberFromName(cardName)
{
	if (cardName == '') return '';
	var nameLen = cardName.indexOf('-of-');
	var cardNumber = cardName.substr(0, nameLen);
	return cardNumber;
}

function wpcardznet_cardNumberFromClass(cardClass)
{
	var cardName = wpcardznet_cardNameFromClass(cardClass);
	return wpcardznet_cardNumberFromName(cardName);
}

function wpcardznet_cardRankFromClass(cardClass)
{
	var cardName = wpcardznet_cardNameFromClass(cardClass);
	var cardNumber = wpcardznet_cardNumberFromName(cardName);
	switch (cardNumber)
	{
		case 'ace': return 1;
		case 'two': return 2;
		case 'three': return 3;
		case 'four': return 4;
		case 'five': return 5;
		case 'six': return 6;
		case 'seven': return 7;
		case 'eight': return 8;
		case 'nine': return 9;
		case 'ten': return 10;
		case 'jack': return 11;
		case 'queen': return 12;
		case 'king': return 13;
		default: return 0;
	}
}


function wpcardznet_cardSuitFromName(cardName)
{
	if (cardName == '') return '';
	var nameLen = cardName.indexOf('-of-');
	var cardSuit = cardName.substr(nameLen+4);
	return cardSuit;
}

function wpcardznet_cardSuitFromClass(cardClass)
{
	var cardName = wpcardznet_cardNameFromClass(cardClass);
	return wpcardznet_cardSuitFromName(cardName);
}

function wpcardznet_indexFromId(id)
{
	var uscorePosn = id.indexOf('_');
	var index = id.substr(uscorePosn+1);
	return index;
}

function wpcardznet_cardNameFromClass(cardClass)
{
	var classesList = cardClass.split(" ");
	for (i = 0; i < classesList.length; i++) 
	{
		var nextClass = classesList[i];
		if (nextClass.indexOf('-of-') != -1)
			return nextClass;
	}
	
	return "";
}

function wpcardznet_cardIdFromClass(cardClass)
{
	var classesList = cardClass.split(" ");
	for (i = 0; i < classesList.length; i++) 
	{
		var nextClass = classesList[i];
		var prefix = nextClass.substr(0, 7);
 		if (prefix == "cardNo_")
		{
			return 'card_'+nextClass.substr(7);
		}
			
	}
	
	return "";
}

function wpcardznet_deselectAllCards()
{
	var noOfActiveCards = jQuery('.playercards').find(".activecard").length;
	if (noOfActiveCards == 0) return 0;
	
	var activecardElems = jQuery(".activecard").not('.card-back');
	
	jQuery(".selectedcard").removeClass('selectedcard');
	jQuery(".playedcard").removeClass('playedcard');
	
	return activecardElems;
}

function wpcardznet_selectACard(offset)
{
	var noOfActiveCards = jQuery('.playercards').find(".activecard").length;
	if (noOfActiveCards == 0) return;
	
	activecardElems = wpcardznet_deselectAllCards();
	
	selectedCardIndex = selectedCardIndex + offset;
	if (selectedCardIndex < 0) 
		selectedCardIndex = activecardElems.length-1;
	else if (selectedCardIndex >= activecardElems.length)
		selectedCardIndex = 0;
		
	jQuery(activecardElems[selectedCardIndex]).parent('div').addClass('selectedcard');
	
	wpcardznet_playSound('SelectCard');
}

function wpcardznet_removeClassFromAll(classid)
{
	var matchingElemsList = jQuery("."+classid);
	if (matchingElemsList.length > 0)
		matchingElemsList.removeClass(classid);
}

function wpcardznet_addStyle(html, styleId, styleValue)
{
	var styleMarker = ' style=';	
	var styleStart = html.indexOf(styleMarker);
	if (styleStart > 0)
	{
		var newStyle = styleId + ': ' + styleValue + ';';
		styleStart += styleMarker.length+1;
		html = html.slice(0, styleStart) + newStyle + html.slice(styleStart);
	}
	return html;
}

function wpcardznet_removeStyle(html, styleId)
{
	var styleStart = html.indexOf(styleId+':');
	if (styleStart > 0)
	{
		var styleEnd = html.indexOf(';', styleStart);
		if (styleEnd > 0)
			html = html.slice(0, styleStart) + html.slice(styleEnd);
	}
	return html;
}

function wpcardznet_unhidecardsClick()
{
	wpcardznet_removeClassFromAll('card-back');
	wpcardznet_removeClassFromAll('hidden-div');
	
	var unhidecardsDev = jQuery("#unhidecardsbutton").parent();
	unhidecardsDev.remove();
	
	wpcardznet_playSound('RevealCards');
	
	wpcardznet_getActiveCards();
	
	if (typeof wpcardznet_game_unhidecardsClick === 'function')
	{
		wpcardznet_game_unhidecardsClick();
	}

}

function wpcardznet_hashiddencards()
{
	var hiddenCardElemsList = jQuery(".playercards").find(".card-back");
	return (hiddenCardElemsList.length > 0);
}

function wpcardznet_getHiddenInput(tagId)
{
	var hiddenElems = jQuery('#'+tagId);
	if (hiddenElems.length == 0) return 'undefined';
	
	var tagVal = hiddenElems[0].value;
	var tagIntVal = parseInt(tagVal, 10);
  
	if (!isNaN(tagIntVal)) 
		return tagIntVal;
  	
	return tagVal;
}

function wpcardznet_addHiddenInput(targetElem, tagId, elemValue)
{
	var input = jQuery("<input>")
		.attr("type", "hidden")
		.attr("name", tagId).val(elemValue);
 	jQuery(targetElem).append(jQuery(input));	   
}

function wpcardznet_getActiveCards()
{
	if (typeof wpcardznet_enable_getActiveCards === 'function')
	{
		if (!wpcardznet_enable_getActiveCards()) return;
	}

	var activeElems = jQuery('.playercards').find(".activecard");
	var noOfActiveCards = activeElems.length;
	if (noOfActiveCards == 1)
	{
		var hideButtonElem = jQuery("#unhidecardsbutton");		
		if (hideButtonElem.length == 0)
		{
			// Only one possible card ... select it!
			activeElems.parent().addClass('selectedcard');
		}
	}
	
	return (noOfActiveCards > 0);
}

function wpcardznet_ReloadPage()
{
	location.reload();
}

function wpcardznet_playcardSubmit()
{
	var formElem = jQuery("#wpcardznet_form")[0];

	formElem.submit();

	return true;
}

function wpcardznet_dealcardsClick()
{
	wpcardznet_AJAXdealcards();
	return false;
}

function wpcardznet_RequestUpdate(newTicker)
{
	var data = wpcardznet_AJAXPrepare('playerUI');	
	data['newTicker'] = newTicker;
	wpcardznet_updateUI(data, wpcardznet_AJAXcb_refresh, wpcardznet_AJAXcb_error);
}

function wpcardznet_AJAXPrepare(action)
{
	wpcardznet_stopTickerTimer(action);
	
	AJAXActive = true;
	
    var data = {
        'request': action, // your action name 
    };
    
	var hiddenElems = jQuery('input[type=hidden]');
	for (i=0; i<hiddenElems.length; i++)
	{
		var elemName = hiddenElems[i].name;
		var elemValue = hiddenElems[i].value;
		data[elemName] = elemValue;
	}
	
	if (!wpcardznet_hashiddencards())
	{
		data['cardsVisible'] = true;
	}
	
	return data;
}

function wpcardznet_updateUI(data, callbackfn, errorfn)
{
	if (enableAJAX)
	{
		wpcardznet_CallAJAX(data, callbackfn, errorfn);
	}
	else
	{
		for (var objId in data)
		{
			var objVal = data[objId];
			wpcardznet_addHiddenInput(jQuery("form"), objId, objVal);			
		}
		wpcardznet_playcardSubmit();
	}
}

function wpcardznet_AJAXplaycard()
{
	var data = wpcardznet_AJAXPrepare('playerUI');	
	wpcardznet_updateUI(data, wpcardznet_AJAXcb_refresh, wpcardznet_AJAXcb_error);
}

function wpcardznet_AJAXdealcards()
{
	wpcardznet_SetBusy(true, 'card');
	var data = wpcardznet_AJAXPrepare('playerUI');
	data['dealcards'] = true;
	wpcardznet_updateUI(data, wpcardznet_AJAXcb_refresh, wpcardznet_AJAXcb_error);
}

function wpcardznet_AJAXshowscores()
{
	wpcardznet_SetBusy(true, 'card');
	var data = wpcardznet_AJAXPrepare('playerUI');
	data['showscores'] = true;
	wpcardznet_updateUI(data, wpcardznet_AJAXcb_refresh, wpcardznet_AJAXcb_error);
}

function wpcardznet_AJAXcb_refresh(response)
{
	try 
	{
		// AJAX Callback - Check for Updated frontend
		reply = JSON.parse(response);
 	
	               
		var formElem = jQuery("#wpcardznet_form")[0];
		formElem.innerHTML = reply['html'];

		// Updated AJAXVars hidden element is in the HTML
		selectedCardIndex = -1;	// No card selected
		
		var tickTime = tickerTimeout;
		if (gameEnded)
		{
			refreshGameId = wpcardznet_getHiddenInput('AJAX-gameId');
			if (gameId != refreshGameId)
			{
				gameEnded = false;
				gameId = refreshGameId;
				
				refreshGameName = wpcardznet_getHiddenInput('AJAX-gameName');
				if (refreshGameName != gameName)
				{
					// Reload the page - Loads new Javascript 
					formElem.innerHTML = '';
					location.reload();
					return;
				}
			}
			else
			{
				// Still waiting for a new game 
				timeToTimeout = 0;
			}
		}
		
		wpcardznet_refreshSounds();
		
		wpcardznet_OnRefresh();
	} 
	catch (err) 
	{
    }
	
	wpcardznet_SetBusy(false, 'card');
	wpcardznet_startTickerTimer(tickerTimeout, 'refresh');
	AJAXActive = false;
}

function wpcardznet_refreshSounds()
{
	if (wpcardznet_successful())
		wpcardznet_playSound('Success');
	else if (wpcardznet_getActiveCards())
		wpcardznet_playSound('Ready');
}

function wpcardznet_restartTickerTimer()
{
	var refreshButtonElems = jQuery("#restartdiv");
	refreshButtonElems.addClass('wpcardznet_remove');
	timeToTimeout = activeTimeout;

	wpcardznet_startTickerTimer(tickerTimeout, 'restart');
}

function wpcardznet_successful()
{
	var successElems = jQuery("#isSuccess");
	return (successElems.length > 0);
}

function wpcardznet_startTickerTimer(timeout, context)
{
	if (!enableTicker) return;
	
	isSeqMode = wpcardznet_getHiddenInput('AJAX-isSeqMode');

	if (isSeqMode == 'true') return;
	
	if (timerRunning)
	{
		// Reject request if timer is already running ...
		return;
	}

	// Start the timer
	tickerTimer = setTimeout(wpcardznet_checkTicker, timeout);
	lastTimeout = timeout;
	timerRunning = true;
}

function wpcardznet_stopTickerTimer(context)
{
	if (!timerRunning) return;
	
	clearTimeout(tickerTimer);
	tickerTimer = 0;
	timerRunning = false;
}

function wpcardznet_checkTicker()
{
	timerRunning = false;
	
	// Request ticker and wait for response .....
	wpcardznet_AJAXticker();
}

function wpcardznet_AJAXticker()
{
	tickerCount++;
	wpcardznet_GetTickerDirect();
}

function wpcardznet_handle_ticker_response(newTicker, newGameId)
{
	if ((newTicker <= 0) || (newGameId != gameId))
	{
		gameEnded = true;
		wpcardznet_RequestUpdate(newTicker);
		return;
	}
	
	gameTicker = wpcardznet_getHiddenInput('AJAX-gameTicker');

	if (!wpcardznet_can_update_screen)
		return;
		
	if (newTicker != gameTicker)
	{
		timeToTimeout = activeTimeout;	// Reset Timeout
		wpcardznet_RequestUpdate(newTicker);
		return;
	}
	
	timeToTimeout -= lastTimeout;
	if (timeToTimeout <= 0)
	{
		// Show the Restart Button and exit
		var refreshButtonElems = jQuery("#restartdiv");
		refreshButtonElems.removeClass('wpcardznet_remove');
		return;
	}
	
	wpcardznet_toggle_tickertell(newTicker);
	wpcardznet_startTickerTimer(tickerTimeout, 'timeout');
}

function wpcardznet_toggle_tickertell(newTicker)
{
	var tickertellElem = jQuery("#tickertell");
	if (tickertellElem.length != 1) return;
	
	if (tickerColour == 'white')
		tickerColour = 'yellow';
	else
		tickerColour = 'white';
	tickertellElem.css('background-color', tickerColour);
}

function wpcardznet_AJAXcb_error(status)
{
	wpcardznet_startTickerTimer(tickerTimeout, 'cb_error');
	AJAXActive = false;
}

function wpcardznet_EnableControls(classId, state, disable)
{
	var classSpec = "."+classId;
	var buttonElemsList = jQuery(classSpec);
	jQuery.each(buttonElemsList,
		function(i, listObj) 
		{
			var uiElemSpec = "#" + listObj.name;
			var uiElem = jQuery(uiElemSpec);
			
			if (state)
			{
				uiElem.prop("disabled", false);			
				uiElem.css("cursor", "default");				
			}
			else
			{
				if (disable) uiElem.prop("disabled", true);			
				uiElem.css("cursor", "progress");
			}
				
	    	return true;
		}
	);
	
	return state;		
}

function wpcardznet_playSound(soundId) 
{
	try
	{
		var soundElemId = "wpcardznet_mp3_" + soundId;
		var soundElem = document.getElementById(soundElemId);
		if (soundElem != null)
			soundElem.play();
	}
	catch (err)
	{
	}
}

function wpcardznet_SetBusy(isBusy, elemClassId, buttonsClassId) 
{
	if (isBusy)
	{
		jQuery(".page").addClass('wpcardznet_busypage');		
		jQuery("#wpcardznet_body").addClass('wpcardznet_busypage');

		jQuery("body").css("cursor", "progress");		
		jQuery(".card-frame").css("cursor", "progress");		
		wpcardznet_EnableControls(elemClassId, false, true);
	}
	else
	{
		wpcardznet_EnableControls(elemClassId, true, true);
		jQuery("body").css("cursor", "default");	
		if (buttonsClassId !== undefined)	
			jQuery("." + buttonsClassId).css("cursor", "pointer");		
		
		jQuery(".page").removeClass('wpcardznet_busypage');
		jQuery("#wpcardznet_body").removeClass('wpcardznet_busypage');
	}
}

function wpcardznet_GetTickerDirect()
{
    // Implement Manual Sale EMail
	var postvars = {
		gameId: gameId,
		action: "ticker",
		jquery: "true"
	};
	
	url = tickerURL;
	
	// Get New HTML from Server 
    jQuery.post(url, postvars,
	    function(data, status)
	    {
	    	if (status == "success") 
	    	{
	    		var tickerPart = data.split(" ");
	    		if (tickerPart.length == 2)
	    		{
					var newTicker = parseInt(tickerPart[0], 10); 
					var newGameId = parseInt(tickerPart[1], 10); 
		    		wpcardznet_handle_ticker_response(newTicker, newGameId);
				}
			}
			else
			{
			}
	    }
    ).fail(function(e)
    	{ 
    		// Handle errors here 
    		if(e.status == 404)
    		{ 
    			// Not found ... ticker file has disappeared!
	    		wpcardznet_handle_ticker_response(-1, 0);
    		}
     	}
    	);
/*    
    	function(e)
    	{ 
    		if(e.status == 404)
    		{ 
    			// ... 
    		}
    	);
*/   	
    return 0;
}

function wpcardznet_sortScoresClick(event)
{
	var action = event.target.id;
	switch (action)
	{
		case 'playerName':
		case 'playerScore':
		case 'playerTotal':
			break;
	}
}


function wpcardznet_logClick(clickAction, cardContext, cardElem)
{
	var clickCardName;

	if (cardContext === '') {
		cardContext = '***';
		clickCardName = '***';
	} else {
		var clickCardId = cardElem.id;
		var clickCardClass = cardElem.className;
		clickCardName = wpcardznet_cardNameFromClass(clickCardClass);
	}

	clicksList += clickAction + ' ' + cardContext + ' ' + clickCardName + ',';
}
