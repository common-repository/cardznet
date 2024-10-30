<?php
/* 
Description: Core Library Capabilities functions
 
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

if (!class_exists('WPCardzNetLibCapabilitiesClass')) 
{
	class WPCardzNetLibCapabilitiesClass // Define class
  	{
		static function GetCapabilities($role = '')
		{
			global $wp_roles;

			$roles = $wp_roles->roles;
			foreach ($wp_roles->roles as $nextRole)
			{
				if (($role != '') && ($role != $nextRole)) continue;
				
				foreach ($nextRole['capabilities'] as $capability => $unused)
				{	
					$capabilities[$capability] = true;
				}
			}
			ksort($capabilities);
			
			return $capabilities;
		}
		
		static function GetCapabilitiesSelector($role = '')
		{
			$capabilitySelects = array();
			$capabilitiesList = WPCardzNetLibCapabilitiesClass::GetCapabilities($role);

			foreach($capabilitiesList as $capability => $unused)
			{
				$capabilitySelects[] = "$capability|$capability";
			}
			
			return $capabilitySelects;
		}
  	}
}

?>