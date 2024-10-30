<?php
/* 
Description: Code for Managing Development Testing
 
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

if (!defined('WPCARDZNETLIB_INCLUDE_PATH') & WPCardzNetLibUtilsClass::IsElementSet('get', 'info')) 
{
	phpinfo();
	exit;
}
	
include dirname(dirname(__FILE__)).'/include/wpcardznetlib_admin.php';

if (!class_exists('WPCardzNetLibTestBaseClass')) 
{
	class WPCardzNetLibTestBaseClass extends WPCardzNetLibAdminBaseClass // Define class
	{
		var $isDevTest = false;
		var $isRunning = false;
		
		function __construct($env) //constructor	
		{
			$this->env = $env;
			parent::__construct($env);
		}
		
		static function GetOrder() { return 0; }

		static function AddPostArgs($postClassInfo)
		{
			$postClassInfo->Target = '';
			$postClassInfo->Referer = '';
			return $postClassInfo;
		}
		
		static function GetFileSelect($dir, $id, $fileExtns = 'png,jpg', $selectOptBase = 'ticket_template_', $hideExtn = false)
		{
			if (substr($dir, WPCardzNetLibMigratePHPClass::Safe_strlen($dir)-1, 1) != '/')
				$dir .= '/';
				
			$selectOpts = array();
			
			$fileExtnsArray = explode(',', $fileExtns);
			foreach($fileExtnsArray as $fileExtn)
			{
				// Folder is defined ... create the search path
				$thisdir = $dir.'*.'.$fileExtn;					

				// Now get the files list and convert paths to file names
				$filesList = glob($thisdir);
				foreach ($filesList as $key => $path)
					$selectOpts[] = basename($path);
			}
			
			if (count($selectOpts)==0) return "Error: count(selectOpts) is zero - Dir: $dir";
			asort($selectOpts);
			
			$controlIdDef = " id=$id name=$id ";
			$onChange = '';
			
			$controlValue = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', $id, '');
			
			$editControl = '<select '.$controlIdDef.$onChange.'>'."\n";
			$editControl .= '<option value="">(None)</option>'."\n";
			foreach ($selectOpts as $selectOptKey => $selectOptText)
			{
				$selectOptId = $selectOptBase.$selectOptKey;
				if ($hideExtn)
					$selectOptValue = substr($selectOptText, 0, WPCardzNetLibMigratePHPClass::Safe_strlen($selectOptText)-WPCardzNetLibMigratePHPClass::Safe_strlen($fileExtn)-1);
				else
					$selectOptValue = $selectOptText;
				$selected = ($controlValue == $selectOptValue) ? ' selected=""' : '';
				$editControl .= '<option id="'.$selectOptId.'" name="'.$selectOptId.'" value="'.$selectOptValue.'"'.$selected.' >'.$selectOptText."&nbsp;</option>\n";
			}
			$editControl .= '</select>'."\n";	
			
			return $editControl;
		}

		static function GetSelect($controlId, $selectOptsArray, $controlValue = '', $onChange = '')
		{
			if (count($selectOptsArray)<1) return('');

			$editControl  = "<select id=$controlId name=$controlId $onChange>\n";
			foreach ($selectOptsArray as $selectOptValue => $selectOptText)
			{
				$selected = ($controlValue == $selectOptValue) ? ' selected=""' : '';
				$editControl .= '<option value="'.$selectOptValue.'"'.$selected.' >'.$selectOptText."&nbsp;</option>\n";
			}
			$editControl .= '</select>'."\n";	
			return $editControl;
		}
	}

	class WPCardzNetLibDevTestBaseClass extends WPCardzNetLibTestBaseClass // Define class
	{
		function __construct($env) //constructor	
		{
			$this->isDevTest = true;
			parent::__construct($env);
		}
	}
}

