<?php
/* 
Description: Code for Data Export functionality
 
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

include WPCARDZNET_INCLUDE_PATH.'wpcardznetlib_export.php';

if (!class_exists('WPCardzNetDBExportAdminClass')) 
{
	if (!defined('WPCARDZNETLIB_MIMETYPE_SQL'))
		define('WPCARDZNETLIB_MIMETYPE_SQL', 'application/x-sql');
		
	class WPCardzNetDBExportAdminClass extends WPCardzNetLibExportAdminClass  // Define class
	{
		function __construct($myDBaseObj) //constructor	
		{
			parent::__construct($myDBaseObj);
			
			$mimeType = WPCARDZNETLIB_MIMETYPE_SQL;

			$this->fileName = 'wpcardznet_db.sql';
			$this->output_downloadHeader($mimeType);
			$this->export_wpcardznet_db();
		}

		function export_wpcardznet_db()
		{			
			$sqlExport = $this->myDBaseObj->GetDatabaseSQL(WPCARDZNET_TABLE_PREFIX);
			WPCardzNetLibEscapingClass::Safe_EchoHTML($sqlExport);
		}
	}
}

if ( WPCardzNetLibUtilsClass::IsElementSet('post', 'download' ) )
{
	$dbaseClass = WPCARDZNETLIB_DBASE_CLASS;
	new WPCardzNetDBExportAdminClass(new $dbaseClass(__FILE__));
} 
