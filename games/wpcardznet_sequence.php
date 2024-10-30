<?php
/* 
Description: Code for a CardzNet Game
 
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
include WPCARDZNET_GAMES_PATH.'wpcardznet_one_eyed_jacks.php';

if (!class_exists('WPCardzNetSequenceClass'))
{
	class WPCardzNetSequenceClass extends WPCardzNetOneEyedJacksClass // Define class
	{
		static function GetGameName()
		{
			return 'Sequence';			
		}
		
		function __construct($myDBaseObj, $atts = array())
		{
			parent::__construct($myDBaseObj, $atts);
		}
				
		function AddGameIncludes($gameName, $plugin_version)
		{
			parent::AddGameIncludes(parent::GetGameName(), $plugin_version);
		}
		
		function GetBoardLayout()
		{
			$b = $this->GetCardNo('card-blank');
			
			$boardLayout = array(
				$b,40,41,42,43,44,45,46,47,$b,
				18,17,16,15,14,39,38,37,35,48,
				19,52, 1, 2, 3, 4, 5, 6,34,50,
				20,51,18,17,16,15,14, 7,33,51,
				21,50,19,31,30,29,39, 8,32,52,
				22,48,20,32,27,28,38, 9,31, 1,
				24,47,21,33,34,35,37,11,30, 2,
				25,46,22,24,25,26,13,12,29, 3,
				26,45,44,43,42,41,40,27,28, 4,
				$b,13,12,11, 9, 8, 7, 6, 5,$b
			);
			
			return $boardLayout;
		}
	
		
		
	}
}

?>