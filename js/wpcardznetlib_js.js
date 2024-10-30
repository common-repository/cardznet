/* 
Description: Generic Javascript
 
Copyright 2022 Malcolm Shergold

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

var currencySymbol = '';
var pageGeneratedServerTime = 0;
var pageGeneratedClientTime = 0;
var currentServerTime = 0;
var WPCardzNetLib_CachedPageTimeDelta = 60;

function WPCardzNetLib_addWindowsLoadHandler(newHandler)
{
	jQuery(document).ready(
		function() 
		{
		    newHandler();
		}
	);
}

function WPCardzNetLib_OnLoadOnlineStore(obj)
{
	if (pageGeneratedServerTime > 0)
	{
		pageGeneratedClientTime = Math.floor(Date.now()/1000);	// Get the time on the local machine
		
		var timeInCache = Math.abs(pageGeneratedClientTime - pageGeneratedServerTime);	
		if (timeInCache > WPCardzNetLib_CachedPageTimeDelta)
		{
			/* It looks like the page is Cached - Check Current Server Time to confirm */
			WPCardzNetLib_JQuery_CompareServerTimestamp();
		}
	}
	
	WPCardzNetLib_GetButtonRefs();
}

var buttonRefs = [];

function WPCardzNetLib_GetButtonRefs()
{
	buttonRefs = [];
		
	if (typeof wpcardznetlib_qtyInputsList === "undefined")
		return;
		
	for (var buttonID in wpcardznetlib_qtyInputsList) 
	{
		inputsList = wpcardznetlib_qtyInputsList[buttonID].split(",");
		for (var i=0; i<inputsList.length; i++)
		{
			var inputID = inputsList[i];
			if (inputID > 0)
				buttonRefs[inputID] = buttonID;
		}
		
		/* Disable/Enable the button */
		WPCardzNetLib_SetAddButtonState(buttonID);
	}
}

function WPCardzNetLib_UpdateAddButtonStateObj(obj)
{
	var inputCtrlParts = obj.id.split("_")
	var inputID = inputCtrlParts[1];
	
	var buttonID = buttonRefs[inputID];
	WPCardzNetLib_SetAddButtonState(buttonID);
}

function WPCardzNetLib_SetAddButtonState(buttonID)
{
	var enableButton = false;
	
	inputsList = wpcardznetlib_qtyInputsList[buttonID].split(",");
	for (var i=0; i<inputsList.length; i++)
	{
		var inputID = inputsList[i];
		obj = jQuery("#quantity_" + inputID);
		if (!obj.length || obj[0].value > 0)
		{
			enableButton = true;
			break;
		}
	}
	
	if (enableButton)
	{
		jQuery("#AddItemButton_" + buttonID).removeAttr('disabled');
		jQuery("#AddItemButton_" + buttonID).css("cursor", "pointer");		
	}
	else
	{
		jQuery("#AddItemButton_" + buttonID).attr('disabled','disabled');
		jQuery("#AddItemButton_" + buttonID).css("cursor", "default");		
	}
}

function WPCardzNetLib_ParseCurrency(currencyText)
{
	currencySymbol = '';
	while (currencyText.length >= 1) 
	{
		nextChar = currencyText[0];
		if (!isNaN(parseInt(nextChar)))
			break;
			
		if (nextChar == '.')
			break;
			
		currencySymbol = currencySymbol + nextChar;
		currencyText = currencyText.substr(1, currencyText.length);
	}
	
	return parseFloat(currencyText);
}

function WPCardzNetLib_OnChangeTrolleyTotal(obj)
{
	var pluginId = wpcardznetlib_cssDomain;
	var donationObj = document.getElementById('saleDonation');
	var saleDonation = 0;
	if (donationObj != null)
	{
		var saleDonation = WPCardzNetLib_ParseCurrency(donationObj.value);
		if (isNaN(saleDonation))
		{
			saleDonation = 0;
		}
		else
		{
			saleDonation = Math.abs(saleDonation);
		}		
	}

	var postValue = 0;
	var postTicketsObj = document.getElementById('salePostTickets');
	if (postTicketsObj != null)
	{
		var salePostageRowObj = document.getElementById(pluginId + '-trolley-postagerow');
		if (postTicketsObj.checked)
		{
			var salePostageObj = document.getElementById('salePostage');
			postValue = WPCardzNetLib_ParseCurrency(salePostageObj.value);
			
			salePostageRowObj.style.display = '';
		}
		else
		{			
			salePostageRowObj.style.display = 'none';
		}
	}

	var subTotalObj = document.getElementById("saleTrolleyTotal");
	var finalTotalObj = document.getElementById(pluginId + '-trolley-totalval');
	var subTotal = subTotalObj.value;
	
	var newTotalVal = WPCardzNetLib_ParseCurrency(subTotal);
	newTotalVal += saleDonation;
	newTotalVal += postValue;
	newTotalVal += 0.00001; /* To force rounding error ... then it is corrected below */
		 	
	var newTotal = newTotalVal.toString();

	var origDps = WPCardzNetLib_NumberOfDps(subTotal);
	var newDps = WPCardzNetLib_NumberOfDps(newTotal);
	while (newDps < origDps)
	{
		if (newDps == 0) newTotal += '.';
		newTotal += '0';
		newDps++;
	}

	if (newDps > origDps)
	{
		/* Limit the number of decimal points */
		newTotal = newTotal.substr(0, newTotal.length + origDps - newDps);
	}
	
	finalTotalObj.innerHTML = currencySymbol + newTotal;
}

function WPCardzNetLib_capitalize_Words(str)
{
	return str.replace(/\w\S*/g, function(txt){return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();});
}

function WPCardzNetLib_NumberOfDps(price)
{
	var priceFormat, dpLen;
	var dpPosn = price.indexOf('.');
	if (dpPosn < 0)
	{
		dpLen = 0;
	}
	else
	{
		dpLen = price.length-dpPosn-1;
	}
	
	return dpLen;
}

function WPCardzNetLib_HideElement(obj)
{
	// Get the header 'Tab' Element					
	tabElem = document.getElementById(obj.id);
	
	// Hide the settings row
	tabElem.style.display = 'none';
}

function WPCardzNetLib_replaceAll(find, replace, str) 
{
	return str.replace(new RegExp(find, 'g'), replace);
}

function WPCardzNetLib_BeforeSubmit(obj, cssDomain) 
{
	jQuery("body").css("cursor", "progress");
	WPCardzNetLib_EnableControls(cssDomain + "-trolley-ui", false, false);
	
	// Re-enable submit Button 
	var uiElemSpec = "#" + obj.name;
	var uiElem = jQuery(uiElemSpec);
			
	uiElem.prop("disabled", false);			
}

function WPCardzNetLib_SetBusy(isBusy, elemClassId, buttonsClassId) 
{
	if (isBusy)
	{
		jQuery("body").css("cursor", "progress");		
		WPCardzNetLib_EnableControls(elemClassId, false, true);
	}
	else
	{
		WPCardzNetLib_EnableControls(elemClassId, true, true);
		jQuery("body").css("cursor", "default");	
		if (buttonsClassId !== undefined)	
			jQuery("." + buttonsClassId).css("cursor", "pointer");		
	}
}

function WPCardzNetLib_AddHiddenValue(name, value)
{
	var hiddenTag = '<input type=hidden name="' + name + '" id="' + name + '" value="' + value + '" />';

	/* Add HTML to Trolley Header div tag */
	divElem = jQuery(".stageshow-trolley-header");

	/* Get updated trolley (which is not visible) */
	trolleyHTML = divElem[0].innerHTML;
	trolleyHTML = hiddenTag + trolleyHTML;
	divElem.html(trolleyHTML);
	
	var newTrolleyHTML = divElem[0].innerHTML;
	newTrolleyHTML = "";
}

function WPCardzNetLib_EnableControls(classId, state, disable)
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

function WPCardzNetLib_SubmitOnReturnKey(obj, event)
{
	if (event.keyCode!=13)
	{
		return true;
	}
	
	/* Find the objects parent form */
	parentForm = jQuery(obj).closest("form");
	parentForm.submit();
	return true;
}
	
function WPCardzNetLib_CheckNumberKey(obj, event)
{
	if (isFinite(event.key))
		return true;
	
	switch(event.key)
	{
		case 'Delete':
		case 'Backspace':
		case 'ArrowUp':
		case 'ArrowDown':
		case 'ArrowLeft':
		case 'ArrowRight':
		case 'Tab':
		case 'Enter':
			return true;
	}		
	event.preventDefault();
	return false;
}

function WPCardzNetLib_CheckNumericOnly(obj, event, maxval, minval, dp)
{
	var newValueText = obj.value + event.key;
	var newValue;
	
	if (dp)
	{
		newValue = parseFloat(newValueText);
	}
	else
	{
		newValue = parseInt(newValueText);
	}

	if ((maxval != 'U') && (newValue > maxval))
	{
		obj.value = maxval;
		event.preventDefault();
		return false;
	}
	if ((minval != 'U') && (newValue < minval))
	{
		obj.value = minval;
		event.preventDefault();
		return false;
	}
	
	return true;
}

function WPCardzNetLib_OnClickQty(obj, event)
{
	obj.select();
	return true;
}

function WPCardzNetLib_OnInputQuantity(obj)
{
	/* Update State of Corresponding button */
	WPCardzNetLib_UpdateAddButtonStateObj(obj);
	
	return true;
}

function WPCardzNetLib_OnSelectQuantity(obj, event, maxval, minval, dp)
{
	/* Update State of Corresponding button */
	WPCardzNetLib_UpdateAddButtonStateObj(obj);
	
	return true;
}

function WPCardzNetLib_OnChangeQuantity(obj, event, maxval, minval, dp)
{
	if (!WPCardzNetLib_CheckNumericOnly(obj, event, maxval, minval, dp))
		return false;
	
	/* Update State of Corresponding button */
	WPCardzNetLib_UpdateAddButtonStateObj(obj);
	
	return true;
}

function WPCardzNetLib_OnChangeCheckbox(obj, event)
{
	if (WPCardzNetLib_DoAJAXCall(obj, event))
	{
		return false;
	}

	return true;
}

function WPCardzNetLib_OnChangeSelect(obj, event)
{
	if (WPCardzNetLib_DoAJAXCall(obj, event))
	{
		return false;
	}

	return true;
}

function WPCardzNetLib_OnChangeText(obj, event)
{
	if (WPCardzNetLib_DoAJAXCall(obj, event))
	{
		return false;
	}

	return true;
}

function WPCardzNetLib_OnChangeTextBox(obj, event)
{
	if (WPCardzNetLib_DoAJAXCall(obj, event))
	{
		return false;
	}

	return true;
}

function WPCardzNetLib_OnChangeNumericOnly(obj, event, maxval, minval, dp)
{
	if (obj.value == '') obj.value = minval;
	
	$rtnval = WPCardzNetLib_CheckNumericOnly(obj, event, maxval, minval, dp);
	
	if (WPCardzNetLib_DoAJAXCall(obj, event))
	{
		return false;
	}

	return $rtnval;
	
}

function WPCardzNetLib_DoAJAXCall(obj, event)
{
	if (!wpcardznetlib_Plugin_UsesAjax())
		return false;
	
	/* Call Ajax Handler - TODO - Make this generic */
	var data = {
			"ajaxdebug": true
		};
	
	data['ajaxid'] = obj.id;
	if (obj.type != 'checkbox')
	{
		data['ajaxval'] = obj.value;
	}
	else
	{
		data['ajaxval'] = obj.checked;
	}	
		
	wpcardznetlib_Plugin_AjaxCall(data);
	
	return true;
}

function WPCardzNetLib_OnKeypressNumericOnly(obj, event, maxval, minval, dp)
{
	if (event.altKey || event.ctrlKey)
	{
		return true;
	}
	
	if (event.keyCode == 13)
	{
		event.preventDefault();
		return false;
	}
	
	if (event.charCode > 32)
	{
		if (dp && (event.charCode == 46))
		{
			return WPCardzNetLib_CheckNumericOnly(obj, event, maxval, minval, dp);
		}
		else if ((event.charCode < 48) || (event.charCode > 57))
		{
			event.preventDefault();
			return false;
		}
		else
		{
			/* return WPCardzNetLib_CheckNumericOnly(obj, event, maxval, minval, dp); */
			return true;
		}
	}
	
	return true;
}

function WPCardzNetLib_confirmAction(actionText, count)
{
	var confirmMsg = 'Do ' + actionText + ' on ' + count + ' entries?';
	return WPCardzNetLib_confirmRequest(confirmMsg);	
}

function WPCardzNetLib_confirmRequest(confirmMsg)
{
	var agree = confirm(confirmMsg);
	if (!agree)
	{
		return false;
	}

	return true;	
}
	
function WPCardzNetLib_JQuery_ScrollToAnchor(inst)
{
	if ( (typeof wpcardznetlib_pageAnchor == 'undefined') 
	  || (typeof wpcardznetlib_pageAnchor[inst] != 'string') )
		return;
	
	var anchorId = "#"+wpcardznetlib_pageAnchor[inst];	
	if (jQuery(anchorId).length == 0)
		return;
		
	WPCardzNetLib_JQuery_ScrollTo(anchorId, anchorOffset, anchorDuration);
}

function WPCardzNetLib_JQuery_ScrollTo(reqAnchorId, reqAnchorOffset, reqAnchorDuration)
{
    jQuery('html, body').animate({
        scrollTop: jQuery(reqAnchorId).offset().top - reqAnchorOffset
    }, reqAnchorDuration);					
	
}

function WPCardzNetLib_JQuery_OnClickTrolleyButton(obj, inst, callback)
{
	if (typeof WPCardzNetLib_JQuery_Blocked != 'undefined') 
	{ 
  		return true; 
	}	

	var postvars = {
		jquery: "true"
	};
	
	return WPCardzNetLib_JQuery_ActionTrolleyButton(obj, inst, postvars, callback);
}

function WPCardzNetLib_JQuery_ActionTrolleyButton(obj, inst, postvars, callback)
{
	var pluginId = wpcardznetlib_cssDomain;
	var buttonId = obj.id;	
	
	/* Set Cursor to Busy and Disable All UI Buttons */
	WPCardzNetLib_SetBusy(true, pluginId + "-trolley-ui");
			
	/* Clear any messages */
	messageElem = jQuery("#message");
	messageElem.hide();
			
	postvars.count = inst;
	postvars.timeout = 30000;
	postvars.cache = false;
	//postvars.path = window.location.pathname + window.location.search;
	
	postvars = WPCardzNetLib_JQuery_PostVars(postvars);

	if (pageGeneratedServerTime > 0)
	{
		postvars.pageServerTime = pageGeneratedServerTime;
		postvars.pageClientTime = pageGeneratedClientTime;
	}

	postvars.requestClientTime = Math.floor(Date.now()/1000);	// Get the time on the local machine;

	var qty = 0;
	var nameParts = buttonId.split("_");
	if (nameParts[0] == "AddItemButton")
	{
		if (wpcardznetlib_hasAddButtonPerPrice)
		{
			var qtyElemId = "quantity_" + nameParts[1];
			var qtyElem = jQuery("#" + qtyElemId);
			qty = qtyElem[0].value;
			postvars[qtyElemId] = qty;
		}
		
		var elemsList = jQuery("." + pluginId + "-trolley-ui");
		for (var i=0; i<elemsList.length; i++)
		{
			var elem = elemsList[i];
			var elemId = elem.id;
			if (elemId.substr(0,9) == "quantity_")
			{
				if (!wpcardznetlib_hasAddButtonPerPrice)
				{
					var thisqty = elem.value;
					if (thisqty > 0)
					{
						postvars[elemId] = thisqty;
					}
					qty += thisqty;
				}
			}
			elem.removeAttribute("name");
		}
	}
	
	ourAtts = wpcardznetlib_attStrings[inst-1];
	ourAtts = ourAtts.split(",");
	for (var attId=0; attId<ourAtts.length; attId++) 
	{
		var thisAtt = ourAtts[attId].split("=");
		var key = thisAtt[0];
		var value = thisAtt[1];
		
		postvars[key] = value;
	}

	postvars[buttonId] = "submit";
	
	var getURL = false;
	/* Optional debug code to create full URL */
	if (getURL)
	{
		var theURL = jQueryURL + "?";
		for(var key in postvars) 
		{
			theURL += key + "=" + postvars[key] + "&";
		}
		getURL = false;
	}
	
	/* Call Server to action trolley buttons ... and get new HTML */
    jQuery.post(jQueryURL, postvars, 
	    function(data, status)
	    {
			if ((status != 'success') || (data.length == 0))
			{
				WPCardzNetLib_SetBusy(false, pluginId + "-trolley-ui", pluginId + "-trolley-button");
				
				/* Will get here if wpnonce is out of date ... just reload page */
				if (data.length == 0)
				{
					location.reload(true);
				}
				
				return true;
			}

			var callbackFn = window[callback];
			callbackFn(data, inst, buttonId, qty);
		}    
    );
    
    return false;
}
				
function WPCardzNetLib_JQuery_Callback(data, inst, buttonId, qty)
{
	var pluginId = wpcardznetlib_cssDomain;
	
	trolleyTargetElem = jQuery("#" + pluginId + "-trolley-trolley-std");

	targetElemID = "#" + pluginId + "-trolley-container" + inst;
	divElem = jQuery(targetElemID);
	divElem.html(data);

	/* Copy New Trolley HTML */
	trolleyUpdateElem = jQuery("#" + pluginId + "-trolley-trolley-jquery");

	// 	trolleyUpdateElem will be empty is call just returns an error status
	if (trolleyUpdateElem.length > 0)
	{
		/* Get updated trolley (which is not visible) */
		trolleyHTML = trolleyUpdateElem[0].innerHTML;

		/* Copy New Trolley HTML */
		trolleyTargetElem.html(trolleyHTML);
	}
	
	/* Now delete the downloaded HTML */
	trolleyUpdateElem.remove();
	
	if (qty > 0)
	{
		WPCardzNetLib_JQuery_ScrollToAnchor(inst);				
	}
	
	/* Set Cursor to Normal and Enable All UI Buttons */
	WPCardzNetLib_SetBusy(false, pluginId + "-trolley-ui", pluginId + "-trolley-button");
					
	WPCardzNetLib_GetButtonRefs();
}

function getJsonFromUrl(url) {
  if(!url) url = location.search;
  var query = url.substr(1);
  var result = {};
  query.split("&").forEach(function(part) {
    var item = part.split("=");
    result[item[0]] = decodeURIComponent(item[1]);
  });
  return result;
}	
		
function WPCardzNetLib_Send_EMail(saleID, url, saleEMail, saleTxnId, ourNOnce, cssDomain)
{
	/* Set Cursor to Busy and Disable All UI Buttons */
	WPCardzNetLib_SetBusy(true, cssDomain+"-trolley-ui");
	
	/* Implement Manual Sale EMail */
	var postvars = {
		saleTxnId: saleTxnId,	
		saleID: saleID,
		saleEMail: saleEMail,
		nonce: ourNOnce,
		jquery: "true"
	};
	
	var paramStart = url.indexOf('?');
	if (paramStart > 0)
	{
		var params = url.substr(paramStart+1);
		url = url.substr(0, paramStart);
		
		params.split("&").forEach(function(part) 
		{
			var item = part.split("=");
			postvars[item[0]] = decodeURIComponent(item[1]);
		});
		
	}
	
	/* Call Server to action "EMail Sale" button from sales page */
    jQuery.post(url, postvars,
	    function(data, status)
	    {
	    	if (status != "success") data = "JQuery Error: " + status;
	    	
	    	alert(data);
			
			/* Set Cursor to Normal and Enable All UI Buttons */
			WPCardzNetLib_SetBusy(false, cssDomain+"-trolley-ui");
			
			/* Reload buttonRefs array */
			WPCardzNetLib_GetButtonRefs();
	    }
    );
    
    return false;
}

function WPCardzNetLib_JQuery_CompareServerTimestamp()
{
	/* Implement Manual Sale EMail */
	var postvars = {
		id: "timestamp",
		jquery: "true"
	};
	
	var baseLen = jQueryURL.lastIndexOf("/");
	
	url = jQueryURL.substring(0,baseLen) + "/wpcardznetlib_jquery_server.php";
	
	/* Call Server to check the server time  */
    jQuery.post(url, postvars,
	    function(data, status)
	    {
	    	if (status == "success") 
	    	{
	    		currentServerTime = data;
	    		if (Math.abs(pageGeneratedServerTime - currentServerTime) > WPCardzNetLib_CachedPageTimeDelta)
	    		{
	    			var errorClass = 'error';
					if (typeof WPCardzNetLib_GetClassID != 'undefined') 
					{ 
				  		errorClass = WPCardzNetLib_GetClassID() + '-error error';
					}	
					
	    			var errorLine1 = 'Error: This Box Office Page is Cached';
	    			var errorLine2 = 'Update Cache Settings for this page or disable Caching';  
	    			var errorHTML = '<div class="' + errorClass + '">' + errorLine1 + '<br>' + errorLine2 + '</div>';  
	    			
	    			/* Find the trolley form element */
					var trolleyElem = jQuery("#trolley");
					trolleyElem.find('div').hide();
					trolleyElem.prepend(errorHTML);
				}
			}
	    }
    );
    
    return 0;
}

function WPCardzNetLib_getAllUrlParams(url) 
{
	// get query string from url (optional) or window
	var queryString = url.split('?')[1];

	// we'll store the parameters here
	var urlParams = {};

	// if query string exists
	if (queryString) 
	{
		// stuff after # is not part of query string, so get rid of it
		queryString = queryString.split('#')[0];

		// split our query string into its component parts
		var arr = queryString.split('&');

		for (var i = 0; i < arr.length; i++) 
		{
			// separate the keys and the values
			var a = arr[i].split('=');

			// set parameter name and value (use 'true' if empty)
			var paramName = a[0];
			var paramValue = typeof (a[1]) === 'undefined' ? true : a[1];
/*
			// (optional) keep case consistent
			paramName = paramName.toLowerCase();
			if (typeof paramValue === 'string') paramValue = paramValue.toLowerCase();
*/
			if (typeof paramValue === 'string') paramValue = paramValue.replace("+", " ");
			
			// if the paramName ends with square brackets, e.g. colors[] or colors[2]
			if (paramName.match(/\[(\d+)?\]$/)) 
			{
				// create key if it doesn't exist
				var key = paramName.replace(/\[(\d+)?\]/, '');
				if (!urlParams[key]) urlParams[key] = [];

				// if it's an indexed array e.g. colors[2]
				if (paramName.match(/\[\d+\]$/)) 
				{
					// get the index value and add the entry at the appropriate position
					var index = /\[(\d+)\]/.exec(paramName)[1];
					urlParams[key][index] = paramValue;
				} 
				else 
				{
					// otherwise add the value to the end of the array
					urlParams[key].push(paramValue);
				}      
			} 
			else 
			{
				// we're dealing with a string
				if (!urlParams[paramName]) 
				{
					// if it doesn't exist, create property
					urlParams[paramName] = paramValue;
				} 
				else if (urlParams[paramName] && typeof urlParams[paramName] === 'string')
				{
					// if property does exist and it's a string, convert it to an array
					urlParams[paramName] = [urlParams[paramName]];
					urlParams[paramName].push(paramValue);
				} 
				else 
				{
					// otherwise add the property
					urlParams[paramName].push(paramValue);
				}
			}
		}
	}

	return urlParams;
}

function WPCardzNetLib_showSubmitMsg(msg, topLevelId) 
{
	// Find our form object
	topLevelElem = jQuery(topLevelId);
	findElems = jQuery(topLevelElem).find("form");
	
	// Create a div element for the message
	var divElem = document.createElement("div");
	divElem.id = 'formSubmitMsg';
	divElem.textContent = msg;
	
	// Add the message
	//topLevelElem[0].before(divElem);
	findElems[0].before(divElem);
	
	// Now hide the form
	findElems.hide();
}

function WPCardzNetLib_getParentNode(obj, nodeName)
{
	var pobj = obj;
	while (pobj !== null)
	{
		pobj = pobj.parentNode;
		if (pobj === null)
			break;
		pName = pobj.nodeName;
		if (pName === nodeName)
			break;
	}
	
	return pobj;
}

function WPCardzNetLib_updateCheckboxes(obj)
{
	var boxid = 'rowSelect[]';
	
	var elem = WPCardzNetLib_getParentNode(obj, 'FORM');
	elem = elem.elements;
	
	var newState = obj.checked;				
	for(var i = 0; i < elem.length; i++)
	{
		if (elem[i].name == boxid) 
			elem[i].checked = newState;
		
		if (elem[i].name == obj.name)
			elem[i].checked = newState;
	} 
		
	//var eventtype = event.type;
}

function WPCardzNetLib_HideOrShowRows(buttonId, rowId)
{
	var rowObj = document.getElementById(rowId);
	var buttonObj = document.getElementById(buttonId);

	var rowClass = rowObj.className;
	
	// Toggle display state
	if (!rowClass.includes('wpcardznetlib_hiderow'))
	{
		rowObj.className = rowClass + ' ' + 'wpcardznetlib_hiderow';
		buttonObj.innerHTML = moreText;
		rowsVisible = false;
	}
	else
	{
		rowObj.className = rowObj.className.replace(/\bwpcardznetlib_hiderow\b/,'');
		buttonObj.innerHTML = lessText;
		rowsVisible = true;	
	}
	
}

function WPCardzNetLib_confirmBulkAction(obj, ctrlId)
{
	var elem = WPCardzNetLib_getParentNode(obj, 'FORM');
	var count = WPCardzNetLib_getCheckboxesCount(elem);
	if (count == 0)
	{
		return false;
	}
	
	var actionObj = document.getElementById(ctrlId);	
	var actionIndex = actionObj.selectedIndex;
	if (actionIndex == 0)
	{
		return false;
	}
	
	var actionText = actionObj.options[actionIndex].text;
	
	var mustConfirm = false;
	for (i=0; i<confirmActionsArray.length; i++)
	{
		if (confirmActionsArray[i] == actionText)
		{
			mustConfirm = true;
			break;
		}
	}
	
	if (!mustConfirm)
	{
		return true;
	}
		
	return WPCardzNetLib_confirmAction(actionText, count);
}
	
function WPCardzNetLib_getCheckboxesCount(elem)
{
	var boxid = 'rowSelect[]';
	
	elem = elem.elements;
	
	var checkedCount = 0;				
	for(var i = 0; i < elem.length; i++)
	{
		if (elem[i].name == boxid) 
		{
			if (elem[i].checked)
			{
				checkedCount++;
			}
		}
	} 
		
	return checkedCount;
}
