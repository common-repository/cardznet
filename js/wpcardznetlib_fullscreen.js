/* 
Description: Browser Full Screen Javascript Functions
 
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

// Functions implementing Full Screen View
var	ourFSButtonID;

var enterFSClassName = "";
var exitFSClassName = "";

var ourTargetElemId = '';
var ourPageElemId = '';

var screenHeight;
var screenWidth;

var rescaleTimerTimeout = 100;	// 100ms between attempts
var rescaleTimerMaxTime = 2000;	// Max two seconds wait
var rescaleTimerCount = 0;

function wpcardznetlib_AddFullScreenEvent(reqFSButtonId, reqTargetElemId, reqPageElemId) 
{
	ourFSButtonID = "#"+reqFSButtonId;

	ourTargetElemId = reqTargetElemId;
	ourPageElemId = reqPageElemId;
	
	if (document.exitFullscreen) {
		fullScreenChangeEventId = "fullscreenchange";
	} else if (document.mozCancelFullScreen) { // Firefox 
		fullScreenChangeEventId = "mozfullscreenchange";
	} else if (document.webkitExitFullscreen) { // Chrome, Safari and Opera 
		fullScreenChangeEventId = "webkitfullscreenchange";
	} else if (document.msExitFullscreen) { // IE/Edge 
		fullScreenChangeEventId = "msfullscreenchange";
	} else {
		jQuery().hide(ourFSButtonID);
		return;
	}
	
	document.addEventListener(fullScreenChangeEventId, wpcardznetlib_FullScreenStatusChanged);
}

function wpcardznetlib_RequestFullscreen(elem) 
{
	screenHeight = window.screen.height;
	screenWidth  = window.screen.width;

	if (elem.requestFullscreen) {
		elem.requestFullscreen();
	} else if (elem.mozRequestFullScreen) { // Firefox 
		elem.mozRequestFullScreen();
	} else if (elem.webkitRequestFullscreen) { // Chrome, Safari and Opera 
		elem.webkitRequestFullscreen();
	} else if (elem.msRequestFullscreen) { // IE/Edge 
		elem.msRequestFullscreen();
	}
}

function wpcardznetlib_CloseFullscreen() 
{
	if (document.exitFullscreen) {
		document.exitFullscreen();
	} else if (document.mozCancelFullScreen) { // Firefox 
		document.mozCancelFullScreen();
	} else if (document.webkitExitFullscreen) { // Chrome, Safari and Opera 
		document.webkitExitFullscreen();
	} else if (document.msExitFullscreen) { // IE/Edge 
		document.msExitFullscreen();
	}
}

function wpcardznetlib_ToggleFullScreen(elemId) 
{
    var mapContainer = document.getElementById(elemId);
    if (wpcardznetlib_IsFullScreen()) 
    {
    	//console.log("wpcardznetlib_ToggleFullScreen: Exit Full Screen ");
    	wpcardznetlib_CloseFullscreen();
    } 
    else 
    {
     	wpcardznetlib_RequestFullscreen(mapContainer);
	}
}

function wpcardznetlib_IsFullScreen() 
{
    rtnval = !(!document.fullscreenElement &&
        !document.msFullscreenElement &&
        !document.mozFullScreenElement &&
        !document.webkitFullscreenElement);
        
    return rtnval;
}

function wpcardznetlib_FullScreenStatusChanged(e)
{
	toggleButtonElem = jQuery(ourFSButtonID)[0];

	var className = "";
    if ((enterFSClassName != "") && (exitFSClassName != ""))
    	className = toggleButtonElem.className;
	
    if (wpcardznetlib_IsFullScreen()) 
    {
    	if (className != "")
    	{
    		className = className.replace(enterFSClassName, exitFSClassName);
    		toggleButtonElem.className = className;
    	}
    	else
   			toggleButtonElem.value = 'Exit Full Screen';
    	
    	rescaleTimerCount = rescaleTimerMaxTime/rescaleTimerTimeout;
		setTimeout(wpcardznetlib_ScalePage, rescaleTimerTimeout);    	
    } 
    else 
    {
    	if (className != "")
    	{
    		className = className.replace(exitFSClassName, enterFSClassName);
    		toggleButtonElem.className = className;
    	}
    	else
	   		toggleButtonElem.value = 'Full Screen';
    	wpcardznetlib_ResetPageScale('wpcardznet_page');
    }

}

function wpcardznetlib_ScalePage()
{
	if ((ourPageElemId == '') || (ourTargetElemId == ''))
		return;
	
	var pageElems = jQuery('#'+ourPageElemId);
	var pageElem = pageElems[0];
	
	var targetElems = jQuery('#'+ourTargetElemId);
	var targetElem = targetElems[0];

	var pageHeight = pageElem.clientHeight;
	var pageWidth = pageElem.clientWidth;

	if ((pageHeight != screenHeight) || (pageWidth != screenWidth))
	{
		rescaleTimerCount--;
		if (rescaleTimerCount > 0) 
		{
			setTimeout(wpcardznetlib_ScalePage, 100);
			return;
		}
	}

//alert (' screenHeight:'+screenHeight+"\n screenWidth:"+screenWidth+"\n pageHeight:"+pageHeight+"\n pageWidth:"+pageWidth);

	var targetHeight = targetElem.clientHeight;
	var targetWidth = targetElem.clientWidth;

	var scaleWidth  = pageWidth / targetWidth;
	var scaleHeight = pageHeight / targetHeight;

	var scale;
    
	scale = Math.min(scaleWidth, scaleHeight);

	
	targetElems.css("transform", "scale(" + scale + ")");
	wpcardznetlib_UpdatePostScale(true, scale);
}

function wpcardznetlib_ResetPageScale(ourTargetElemId)
{
	var targetElems = jQuery('#'+ourTargetElemId);
	targetElems.css("transform", "scale(1)");
	wpcardznetlib_UpdatePostScale(false, 1);
}

function wpcardznetlib_UpdatePostScale(isFS, scale)
{
	var stateElems = jQuery('#wpcardznetlib_fsState');	
	if (stateElems.length == 0)
		return;

	var scaleElems = jQuery('#wpcardznetlib_pageScale');
	if (scaleElems.length == 0)
		return;

	fsMode = (isFS) ? 1 : 0;
	stateElems[0].value = fsMode;
	
	scaleElems[0].value = scale;
}



