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
	
include 'wpcardznetlib_testbase.php';  

if (!class_exists('WPCardzNetLibDevCallerClass')) 
{
	define('WPCARDZNETLIB_DEVTESTCALLER', true);
	
	class WPCardzNetLibDevCallerClass extends WPCardzNetLibAdminClass // Define class
	{
		static function DevTestFilesList($testDir)
		{
			$filePath = $testDir.'*_test_*.php';
			$testFiles = glob( $filePath );
			return $testFiles;
		}
		
		function __construct($env) //constructor	
		{
			$this->ourClassPrefix = 'wpcardznet'.'_Test_';			
			$this->libFilePrefix = 'wpcardznetlib_test_';
			$this->libClassPrefix = 'WPCardzNetLib_Test_';
			$this->pageTitle = 'Dev TESTING';
					
			// Call base constructor
			parent::__construct($env);			
		}

		function ProcessActionButtons()
		{
		}
		
		function Output_MainPage($updateFailed)
		{
			$localServerRoot = 'U:\Internet';
			$onLocalServer = defined('WPCARDZNETLIB_ONLOCAL_SERVER');

			$myPluginObj = $this->myPluginObj;
			$myDBaseObj = $this->myDBaseObj;
			
			// Stage Show TEST HTML Output - Start 				
			$ourTestFilePrefix = strtolower($this->ourClassPrefix);
			$ourTestFilePrefixLen = WPCardzNetLibMigratePHPClass::Safe_strlen($ourTestFilePrefix);
			
			$libTestFilePrefix = $this->libFilePrefix;
			$libTestFilePrefixLen = WPCardzNetLibMigratePHPClass::Safe_strlen($libTestFilePrefix);
			
			$testClasses = array();
			$maxIndex = 0;
			$testDir = dirname(__FILE__).'/';			
			//$testFiles = scandir( $testDir );			
			$testFiles = $this->DevTestFilesList($testDir);

			foreach ($testFiles as $filePath)
			{
				$testFile = basename($filePath);
				$testName = str_replace('.php','', $testFile);
				if (substr($testName, 0, $libTestFilePrefixLen) == $libTestFilePrefix)
				{
					$testName = substr($testName, $libTestFilePrefixLen);
					$testClass = $this->libClassPrefix.$testName;
				}
				else
				{
					$parts = explode('_test_', $testName);
					$testClass = str_replace('_test_', '_Test_', $testName);
					$testClass = str_replace('wp_', 'WP', $testClass);
					$testClass = str_replace('wpcardznet', WPCARDZNETLIB_PLUGIN_ID, $testClass);
					$testName = $parts[1];
				}
					
				// echo "Test File: $testFile - Class: $testClass <br><br>\n";
								
				include $filePath;
		
				if (!class_exists($testClass)) continue;
				
				$testObj = new $testClass($this->env);
				$orderIndex = $testObj->GetOrder();
				if ($orderIndex <= 0) continue;

				if (!$onLocalServer && ($testObj->isDevTest)) continue;
				
				if (WPCardzNetLibUtilsClass::GetHTTPTextElem('post', 'lastdevtestclass') == $testName)
				{
					$orderIndex = 0;
				}
				$index = $orderIndex * 10;
				
				if (isset($testClasses[$index]))
				{
					echo "<br><strong>Duplicate Index($orderIndex) - $testClass</strong> - Moved to next available location</br>\n";
					while (isset($testClasses[$index])) 
					{
						$index++;
					}
				}				
				$testClassInfo = new stdClass;
				$testClassInfo->Name = $testName;
				$testClassInfo->Path = $filePath;
				$testClassInfo = $testObj->AddPostArgs($testClassInfo);
				$testClassInfo->Obj = $testObj;
				
				$testClasses[$index] = $testClassInfo;
				
				$maxIndex = ($index > $maxIndex) ? $index : $maxIndex;
			}
			
			//WPCardzNetLibUtilsClass::print_r($testClasses, 'testObjs');
			
			for ($index = 0; $index<=$maxIndex; $index++)
			{
				if (!isset($testClasses[$index]))
				{
					continue;
				}
				$testClassInfo = $testClasses[$index];
				$testObj = $testClassInfo->Obj;

				$postArgs  = 'method="post"';
				if ($testClassInfo->Target != "") $postArgs .= ' action="'.$testClassInfo->Target.'"';
				
				echo "<form $postArgs>\n";
				$this->WPNonceField($testClassInfo->Referer);
				echo '<input type="hidden" name="lastdevtestclass" id="lastdevtestclass" value="'.$testClassInfo->Name.'"/>'."\n";
				$testObj->Show();
				echo '</form>'."\n";
				
				if ($testObj->isRunning) break;
			}
			
		}				 


	}
}

?>