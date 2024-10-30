<?php
/* 
Description: Code for a CardzNet Def File for Card Sizes
 
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

if (!class_exists('WPCardzNetCardDefClass'))
{
	class WPCardzNetCardDefClass // Define class
	{
		// Width and Height of Portrait Cards in px
		const CardWIDTH = 79;
		const CardHEIGHT = 123;
		const CardDIGITSIZE = 20;
		
		// Width and Height of 75% Portrait Cards in px
		const CardWIDTH_75pc = 60;
		const CardHEIGHT_75pc = 93;
		
		// Other Constants
		const CardXSpace = 50;
		const CardYSpace = 50;
	}
}
?>