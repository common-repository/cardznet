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

function wpcardznet_game_onkeydown(event)
{
	origEvt = event.originalEvent;
	if (typeof origEvt !== 'undefined')
	{
		var keyCode = origEvt.code
		
		var dealcardsElems = jQuery("#showscores");
		if (dealcardsElems.length > 0)
		{
			switch (keyCode)
			{
				case 'Enter':
				case 'NumpadEnter':				
				case 'Space':
					wpcardznet_AJAXshowscores();
					break;
			}
			return;
		}
	
		var dealcardsElems = jQuery("#ajaxdealcards");
		if (dealcardsElems.length > 0)
		{
			switch (keyCode)
			{
				case 'Enter':
				case 'NumpadEnter':				
				case 'Space':
					wpcardznet_dealcardsClick();
					break;
			}
			return;
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
	
	console.log('KeyDown TRAPPED! ('+keyCode+')');
	event.preventDefault();
}

function wpcardznet_playCard(cardGUI)
{
	if (AJAXActive) return;

	var divElem = jQuery("#"+cardGUI);
	if (divElem.hasClass('card-back')) return;
	
	var cardClass = divElem[0].className;
	var cardId = wpcardznet_cardIdFromClass(cardClass);
	
	wpcardznet_addHiddenInput(divElem, "cardNo", cardId);
	wpcardznet_addHiddenInput(divElem, "cardClass", cardClass);

	wpcardznet_SetBusy(true, 'card');
	wpcardznet_playSound('PlayCard');
	if (enableAJAX)
		wpcardznet_AJAXplaycard();
	else
		wpcardznet_playcardSubmit();

	return true;
}

function wpcardznet_can_update_screen()
{
	return true;
}

