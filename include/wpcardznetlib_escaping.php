<?php
/* 
Description: General Utilities Code
 
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

if (!class_exists('WPCardzNetLibEscapingClass')) 
{

	class WPCardzNetLibEscapingClass  // Define class
	{
		public static $inJS = false;
		public static $jsBuffer = '';
			
		static function Safe_EchoAttr($attr)
		{
echo $attr; return;
			echo esc_attr($attr);
		}
		
		static function Safe_EchoJs($js)
		{
echo $js; return;
			// Output inline JS in HTML tag
			echo esc_js($js);
		}
		
		static function Safe_EchoHTML($echoData)
		{
echo $echoData; return;

			if (is_null($echoData)) return;
				
			$htmlTags = WPCardzNetLibUtilsClass::GetAllowedHTMLTags();
			$protocols = WPCardzNetLibUtilsClass::GetAllowedProtocols();
			$escData = wp_kses($echoData, $htmlTags, $protocols);
			echo $escData;
		}
		
		static function Safe_EchoJSON($response)
		{
			if (is_null($response)) return;
				
			echo json_encode($response);
		}
		
		static function Safe_EchoScript($js)
		{
echo $js; return;

			if (is_null($js)) return;
				
			// Handle buffering up Javascript output 
			if (!self::$inJS)
			{
				self::$inJS = (strpos($js, "<script") !== false);
			}
			
			if (!self::$inJS)
			{
				echo ("Safe_EchoScript called when not outputting script\n");
				exit;
			}	
			
			// Buffer and Output block of inline JS
			self::$jsBuffer .= $js;
							
			if (strpos($js, '</script') !== false)
			{
				// End of script - output buffer and clear down 
				WPCardzNetLibEscapingClass::Safe_EchoHTML(self::$jsBuffer);
				self::$jsBuffer = '';
				self::$inJS = false;
			}
		}

		static function Safe_EchoDownload($echoData)
		{
			// Not escaped as not output to browser ....
			echo $echoData;
		}
		
	}
		
}
			

?>
