<?php
/* 
Description: Code for Table Management Class
 
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

include WPCARDZNETLIB_INCLUDE_PATH.'wpcardznetlib_table.php';

if (!class_exists('WPCardzNetLibAdminListClass')) 
{
	class WPCardzNetLibAdminListClass extends WPCardzNetLibTableClass // Define class
	{		
		const VIEWMODE = false;
		const EDITMODE = true;
		
		const BULKACTION_TOGGLE = 'toggleactive';
		const BULKACTION_DELETE = 'delete';
		
		const TABLEENTRY_DATETIME = 'datetime';
		
		var $env;
		var $caller;
		var $results;
		var $myPluginObj;
		var $myDBaseObj;
		var $rowNo;
		var $rowCount;
		var $filterRowDefs = array();
		var $defaultFilterId = '';
		var $showDBIds = false;
		var $lastCBId;
		var $currResult;
		var $enableFilter;
		
		var $editMode;
		var $usesAjax = false;
		
		var $updateFailed;
		
		var $hasDateTimeEntry = false;
		
		var	$inputElemClass = '';
	
		function __construct($env, $editMode /* = false */, $newTableType = self::TABLETYPE_HTML) //constructor
		{
			$this->editMode = $editMode;
		
			$this->env = $env;
			
			$this->caller = $env['Caller'];
			$this->myPluginObj = $env['PluginObj'];
			$this->myDBaseObj = $env['DBaseObj'];
				
			// Call base constructor
			parent::__construct($newTableType);
			
			$this->ignoreEmptyCells = false;
			
			$this->enableFilter = true;
			
			$this->pluginName = basename(dirname($this->caller));

			$tableClass = 'wpcardznet'.'-widefat';
			$this->tableTags = 'class="'.$tableClass.' widefat" cellspacing="0"';
		
			if (isset($this->myDBaseObj->adminOptions['PageLength']))
				$this->SetRowsPerPage($this->myDBaseObj->adminOptions['PageLength']);
			else
				$this->SetRowsPerPage(WPCARDZNETLIB_EVENTS_PER_PAGE);
				
			$this->useTHTags = true;
			$this->showDBIds = false;					
			$this->lastCBId = '';
		
			$this->updateFailed = false;
			
			$this->columnDefs = $this->GetMainRowsDefinition();			
			
			if (!isset($this->HeadersPosn)) $this->HeadersPosn = self::HEADERPOSN_BOTH;
			if (!isset($this->hiddenRowsButtonId)) 
			{
				if (!$this->editMode)
					$this->hiddenRowsButtonId = __('Details', 'wpcardznet');
				else
				{
					$this->hiddenRowClass = '';
					$this->hiddenRowsButtonId = '';
					$this->moreText = '';
				}
			}
		}
		
		static function Output_AjaxGlobals()
		{
			static $isFirst = true;
			
			if (!$isFirst) return;
			
			$pageId = WPCardzNetLibUtilsClass::GetHTTPTextElem('get', 'page');
			$js_ajaxvars = "<script>\n";
				
			$js_ajaxvars .= 'var WPCardzNetLib_Ajax_NOnce = "'.wp_create_nonce(WPCARDZNETLIB_AJAXNONCEKEY).'"'.";\n";
			$js_ajaxvars .= 'var WPCardzNetLib_Ajax_PluginName = "'.WPCARDZNETLIB_PLUGINNAME.'"'.";\n";
			$js_ajaxvars .= 'var WPCardzNetLib_Ajax_DebugElemId = "'.WPCARDZNETLIB_PLUGINNAME.'-ajax-debug"'.";\n";
			$js_ajaxvars .= 'var WPCardzNetLib_Ajax_PageId = "'.$pageId.'"'.";\n";

			$js_ajaxvars .= "</script>\n";

			WPCardzNetLibEscapingClass::Safe_EchoScript($js_ajaxvars);
				
			$isFirst = false;
		}
		
		static function AddFilterRow(&$filterRowDefs, $id, $title = '', $name = '')
		{
			if ($title == '') $title = $id;
			if ($name == '') $name = $id;
			
			$filterDef = new stdClass();
			$filterDef->name = $name;
			$filterDef->title = $title;
			$filterDef->count = 0;
			
			$filterRowDefs[$id] = $filterDef;
		}
		
		function NeedsConfirmation($bulkAction)
		{
			switch ($bulkAction)
			{
				case self::BULKACTION_DELETE:
					return true;
					
				default:
					return false;
			}
		}
		
		function NewRow($result, $rowAttr = '')
		{
			WPCardzNetLibTableClass::NewRow($result, $rowAttr);
			
			$col=1;
			
			$recordID = $this->GetRecordID($result);
			$isFirstLine = ($this->lastCBId !== $recordID);
			$this->lastCBId = $recordID;
			
			if (isset($this->bulkActions))
			{
				if ($isFirstLine)
					$this->AddCheckBoxToTable($result, 'rowSelect[]', false, $col++, $recordID);
				else	
					$this->AddToTable($result, ' ', $col++);
			}
			
			if ($this->showDBIds)
			{
				if ($isFirstLine)
					$this->AddToTable($result, $recordID, $col++);
				else	
					$this->AddToTable($result, ' ', $col++);
			}
		}
		
		function GetTableID($result)
		{
			WPCardzNetLibUtilsClass::UndefinedFuncCallError($this, 'GetTableID');
		}
		
		function GetRecordID($result)
		{
			WPCardzNetLibUtilsClass::UndefinedFuncCallError($this, 'GetRecordID');
		}
		
		function GetDetailID($result)
		{
			return '';
		}
		
		function GetRowClass($result)
		{
			return '';
		}
		
		function IsRowInView($result, $rowFilter)
		{
			return true;
		}
		
		function GetFilterSQL($rowFilter)
		{	
			return 'TRUE';
		}

		function GetDBFilterCounts($sqlSelect)
		{
			WPCardzNetLibUtilsClass::UndefinedFuncCallError($this, 'GetDBFilterCounts');
		}
		
		function GetFilterCounts()
		{
			// Loop through all entries to get SQL for row counts
			$sqlSelect = '';
			foreach ($this->filterRowDefs as $filterId => $filterRowDef)
			{
				$filterSQL = $this->GetFilterSQL($filterId);
				if ($sqlSelect != '') $sqlSelect .= ', ';
				$sqlSelect .= "COUNT(IF($filterSQL, 1, NULL)) AS count$filterId";
			}

			if ($sqlSelect == '') return null;

			$filtersCounts = $this->GetDBFilterCounts($sqlSelect);
							
			// Loop through all entries to get row counts for each filter
			foreach ($this->filterRowDefs as $filterId => $filterRowDef)
			{			
				$filterIndex = "count$filterId";				
				$this->filterRowDefs[$filterId]->count = $filtersCounts[0]->$filterIndex;
			}			
			
		}

		static function OutputFilterLinks($filterRowDefs, $defaultFilterId = '')
		{
			$rtnVal = new stdClass();
			
			// Get the filter requested in the HTTP request 
			$rowFilter = WPCardzNetLibUtilsClass::GetHTTPTextElem('get', 'filter', $defaultFilterId); 
				
			// Check that the selected filter is defined ... or use first filter as default
			if (!isset($filterRowDefs[$rowFilter]) || ($filterRowDefs[$rowFilter]->count == 0))
			{
				if (count($filterRowDefs) > 0)
				{
					$filterKeys = array_keys($filterRowDefs);
					$rowFilter = $filterKeys[0];
				}
				else
				{
					$rowFilter = '';
				}
			}
			
			$current_url = WPCardzNetLibUtilsClass::GetPageURL();
			$current_url = remove_query_arg( 'filter', $current_url);
			$current_url = remove_query_arg( 'paged', $current_url);
			$current_url = remove_query_arg( 'searchsalestext', $current_url);
				
			$searchText = WPCardzNetLibUtilsClass::GetHTTPTextElem('request', 'searchsalestext');
			if ($searchText != '')
				$current_url = add_query_arg('searchsalestext', $searchText, $current_url);
				
			$html_filterLinks = '';

			foreach ($filterRowDefs as $filterId => $filterRowDef)
			{
				if ($html_filterLinks != '')
					$html_filterLinks .= ' | ';

				$filterClass = WPCardzNetLibMigratePHPClass::Safe_str_replace(' ', '', $filterId);
				$hrefClass = ($rowFilter == $filterId) ? 'current' : '';
				$filterClass = 'filter-'.WPCardzNetLibMigratePHPClass::Safe_strtolower($filterClass);
				
				$filterURL = esc_url( add_query_arg( 'filter', $filterId, $current_url ) );
				$filterTitle = isset($filterRowDefs[$filterId]) ? $filterRowDefs[$filterId]->title : '';
				
				$rowCount = $filterRowDefs[$filterId]->count;
				$filterName = $filterRowDefs[$filterId]->name;
				
				$html_filterLinks .= sprintf( "<li class='%s'><a class='%s' title='%s' %s>%s</a></li>",
					$filterClass,
					$hrefClass,
					$filterTitle,
					$rowCount > 0 ? 'href='.$filterURL : '',
					"$filterName ($rowCount)"
				);
				
				$filter_sep = ' | ';
			}
/*				
			WPCardzNetLibEscapingClass::Safe_EchoHTML("<div class=filter-links>\n");
			WPCardzNetLibEscapingClass::Safe_EchoHTML($html_filterLinks);
			WPCardzNetLibEscapingClass::Safe_EchoHTML("</div>\n");
*/
			WPCardzNetLibEscapingClass::Safe_EchoHTML("
<style>
.subsubsub
{
	float:none;
}
</style>
<ul class=subsubsub>{$html_filterLinks}</ul>
				");
			
			$rtnVal->totalRows = (isset($filterRowDefs[$rowFilter]) ? $filterRowDefs[$rowFilter]->count : 0);
			$rtnVal->rowFilter = $rowFilter;
			
			return $rtnVal;
		}
		
		function GetSelectOptsArray($settingOption, $result=null)
		{
			if (isset($settingOption[WPCardzNetLibTableClass::TABLEPARAM_ITEMS]))
			{
				$selectOpts = $settingOption[WPCardzNetLibTableClass::TABLEPARAM_ITEMS];
			}
			else if (isset($settingOption[WPCardzNetLibTableClass::TABLEPARAM_DIR]))
			{
				if (isset($settingOption[WPCardzNetLibTableClass::TABLEPARAM_EXTN]))
					$fileExtns = $settingOption[WPCardzNetLibTableClass::TABLEPARAM_EXTN];
				else
					$fileExtns = '*';
				$shortenBy = isset($settingOption[WPCardzNetLibTableClass::TABLEPARAM_HIDEEXTNS]) ? $shortenBy = WPCardzNetLibMigratePHPClass::Safe_strlen($fileExtns)+1 : 0;

				$selectOpts = array();
				
				$fileExtnsArray = explode(',', $fileExtns);
				foreach($fileExtnsArray as $fileExtn)
				{
					// Folder is defined ... create the search path
					$dir = $settingOption[WPCardzNetLibTableClass::TABLEPARAM_DIR];
					if (WPCardzNetLibMigratePHPClass::Safe_substr($dir, WPCardzNetLibMigratePHPClass::Safe_strlen($dir)-1, 1) != '/')
						$dir .= '/';
						
					$dir .= '*.'.$fileExtn;					

					// Now get the files list and convert paths to file names
					$filesList = glob($dir);
					foreach ($filesList as $key => $path)
					{
						$selectValue = basename($path);
						$selectText = ($shortenBy > 0) ? WPCardzNetLibMigratePHPClass::Safe_substr($selectValue, 0, WPCardzNetLibMigratePHPClass::Safe_strlen($selectValue) - $shortenBy) : $selectValue;
						$selectOpts[] = "$selectValue|$selectText";
					}
				}
			}
			else if (isset($settingOption[WPCardzNetLibTableClass::TABLEPARAM_FUNC]))
			{
				$functionId = $settingOption[WPCardzNetLibTableClass::TABLEPARAM_FUNC];
				$selectOpts = $this->$functionId($result);
			}
			else
				return array();
									
			$selectOptsArray = array();
			
			if (isset($settingOption[WPCardzNetLibTableClass::TABLEPARAM_ADDEMPTY]))
			{
				$selectOptsArray[''] = '';
			}
						
			foreach ($selectOpts as $selectOpt)
			{
				$selectAttrs = explode('|', $selectOpt);
				if (count($selectAttrs) == 1)
				{
					$selectOptValue = $selectOptText = $selectAttrs[0];
				}
				else
				{
					$selectOptValue = $selectAttrs[0];
					$selectOptText = __($selectAttrs[1], 'wpcardznet');
				}
				
				$selectOptsArray[$selectOptValue] = $selectOptText;
			}
			
			return $selectOptsArray;
		}
		
		function GetSelectOptsText($settingOption, $controlValue)
		{
			$selectOptsArray = self::GetSelectOptsArray($settingOption);
			foreach ($selectOptsArray as $selectOptValue => $selectOptText)
			{
				if ($controlValue == $selectOptValue)
				{
					$controlValue = $selectOptText;
					break;
				}
			}
			
			return $controlValue;
		}
		
		function OutputButton($buttonId, $buttonText, $buttonClass = "button-secondary", $clickEvent = '')
		{
			$buttonText = __($buttonText, 'wpcardznet');
			
			if ($clickEvent != '')
			{
				$clickEvent = ' onclick="'.$clickEvent.'" ';
			}
			
			return "<input class=\"$buttonClass\" type=\"submit\" $clickEvent name=\"$buttonId\" value=\"$buttonText\" />\n";
		}
		
		function GetHTMLTag($settingOption, $controlValue, $editMode = true, $class = '')
		{
			$autocompleteTag = ' autocomplete="off"';
			$controlIdDef = 'id="'.$settingOption[self::TABLEPARAM_ID].'" name="'.$settingOption[self::TABLEPARAM_ID].'" ';
			
			$editControl = '';
			$inputClass = '';
			
			if (isset($settingOption[self::TABLEPARAM_ALWAYSEDIT])) $editMode = true;
			
			$settingType = $settingOption[self::TABLEPARAM_TYPE];
			if ($this->tableUsesSerializedPost)
			{
				switch($settingType)
				{
					case self::TABLEENTRY_VALUE:
					case self::TABLEENTRY_SELECT:
					case self::TABLEENTRY_INTEGER:
					case self::TABLEENTRY_FLOAT:
					case self::TABLEENTRY_COOKIE:
					case self::TABLEENTRY_TEXT:
					case self::TABLEENTRY_TEXTBOX:
					case self::TABLEENTRY_CHECKBOX:
					case self::TABLEENTRY_DATETIME:
						$controlIdDef = 'id="'.$settingOption[self::TABLEPARAM_ID].'" ';
						$class .= ' wpcardznetlib_PostVal '.$class;
						break;
				}
			}
			
			$onChange = isset($settingOption[self::TABLEPARAM_ONCHANGE]) ? ' onchange="'.$settingOption[self::TABLEPARAM_ONCHANGE].'(this)" ' : '';

			if (isset($settingOption[self::TABLEPARAM_READONLY]))
				$editMode = false;
				
			if ($editMode && isset($settingOption[self::TABLEPARAM_CANEDIT]))
			{
				$funcName = $columnDef[WPCardzNetLibTableClass::TABLEPARAM_CANEDIT];
				$editMode = $this->$funcName($result);
			}
			
			if (!$editMode)
			{
				if ($this->allowHiddenTags) WPCardzNetLibEscapingClass::Safe_EchoHTML('<input type="hidden" '.$controlIdDef.' value="'.$controlValue.'"/>'."\n");

				switch ($settingType)
				{
					case self::TABLEENTRY_SELECT:
						$controlValue = $this->GetSelectOptsText($settingOption, $controlValue);
						$settingType = self::TABLEENTRY_VIEW;
						break;						
					
					case self::TABLEENTRY_CHECKBOX:
						$controlValue = $controlValue ? __('Yes', 'wpcardznet') : __('No', 'wpcardznet');
						$settingType = self::TABLEENTRY_VIEW;
						break;		
										
					case self::TABLEENTRY_TEXT:
					case self::TABLEENTRY_INTEGER:
					case self::TABLEENTRY_FLOAT:
					case self::TABLEENTRY_DATETIME:
					case self::TABLEENTRY_TEXTBOX:
					case self::TABLEENTRY_COOKIE:
						$settingType = self::TABLEENTRY_VIEW;
						break;						
				}				
			}
				
			$eventHandler = '';				
			switch ($settingType)
			{
				case self::TABLEENTRY_DATETIME:
					$editSize = 28;
					$class .= 'wpcardznet'.'-dateinput';
					if ($this->inputElemClass != '') $class .= ' '.$this->inputElemClass;
					$controlIdDef .= ' class="'.$class.'"';
					$dateMode = isset($settingOption[self::TABLEPARAM_DATEMODE]) ? $settingOption[self::TABLEPARAM_DATEMODE] : 'future';
					$eventHandler = " readonly=true onclick=\"javascript:WPCardzNetLib_DateModeCalendarSelector(this, '".$this->dateTimeMode."', '".$dateMode."')\" ";
					$editControl  = '<input type="text"'.$eventHandler.' size="'.$editSize.'" '.$controlIdDef.' value="'.$controlValue.'" />'."\n";
					if ($this->allowHiddenTags) $editControl .= '<input type="hidden" '.WPCardzNetLibMigratePHPClass::Safe_str_replace('="', '="curr', $controlIdDef).' value="'.$controlValue.'" />'."\n";
					$this->hasDateTimeEntry = true;
					break;

				case self::TABLEENTRY_INTEGER:
					$inputType = 'number';
					// Drop into next case ...
				
				case self::TABLEENTRY_FLOAT:
					if (isset($settingOption[self::TABLEPARAM_LIMITS]))
						$limits = $settingOption[self::TABLEPARAM_LIMITS];
					else
						$limits = "'U', 0";
					
					if ($settingType == self::TABLEENTRY_INTEGER)
						$limits .= ", false";
					else
						$limits .= ", true";
					$eventHandler = ' onkeypress="WPCardzNetLib_OnKeypressNumericOnly(this, event, '.$limits.');" ';
					$eventHandler .= ' onchange="WPCardzNetLib_OnChangeNumericOnly(this, event, '.$limits.');" ';
					// Drop into next case ...
				
				case self::TABLEENTRY_TEXT:
					$controlValue = WPCardzNetLibMigratePHPClass::Safe_htmlspecialchars($controlValue);
					// Drop into next case ...
					
				case self::TABLEENTRY_COOKIE:
					if (!isset($inputType)) $inputType = 'text';
					$controlIdDef .= ' class="'.$class.'" ';
					if ($eventHandler == '')
					{
						//$eventHandler = ' onkeypress="WPCardzNetLib_OnKeypressNumericOnly(this, event, '.$limits.');" ';
						$eventHandler .= ' onchange="WPCardzNetLib_OnChangeText(this, event);" ';
					}
					if ($this->inputElemClass != '') $eventHandler .= ' class="'.$this->inputElemClass.'" ';
					$editLen = $settingOption[self::TABLEPARAM_LEN];
					$editSize = isset($settingOption[self::TABLEPARAM_SIZE]) ? $settingOption[self::TABLEPARAM_SIZE] : $editLen+1;
					$editControl  = "<input type=$inputType ".$eventHandler.$autocompleteTag.' maxlength="'.$editLen.'" size="'.$editSize.'" '.$controlIdDef.' value="'.$controlValue.'" />'."\n";
					if ($this->allowHiddenTags) $editControl .= '<input type="hidden" '.WPCardzNetLibMigratePHPClass::Safe_str_replace('="', '="curr', $controlIdDef).' value="'.$controlValue.'" />'."\n";
					if (isset($settingOption[WPCardzNetLibTableClass::TABLEPARAM_TEXT])) 
						$editControl .=  __($settingOption[WPCardzNetLibTableClass::TABLEPARAM_TEXT], 'wpcardznet');
					break;

				case self::TABLEENTRY_TEXTBOX:
					$controlIdDef .= ' class="'.$class.'" ';
					$eventHandler = ($onChange == '') ? 'onchange="WPCardzNetLib_OnChangeTextBox(this, event);" ' : $onChange;
					if ($this->inputElemClass != '') $eventHandler .= ' class="'.$this->inputElemClass.'" ';
					$editRows = isset($settingOption[self::TABLEPARAM_ROWS]) ? $settingOption[self::TABLEPARAM_ROWS] : 1;
					$editCols = isset($settingOption[self::TABLEPARAM_COLS]) ? $settingOption[self::TABLEPARAM_COLS] : 60;
					if (isset($settingOption[self::TABLEPARAM_ALLOWHTML]))
						$controlValue = WPCardzNetLibMigratePHPClass::Safe_htmlspecialchars($controlValue);
					$editControl = '<textarea rows="'.$editRows.'" cols="'.$editCols.'" '.$controlIdDef.$eventHandler.' >'.$controlValue."</textarea>\n";
					break;

				case self::TABLEENTRY_SELECT:
					if (($controlValue == '') && isset($settingOption[self::TABLEPARAM_DEFAULT]))
						$controlValue = $settingOption[self::TABLEPARAM_DEFAULT];
					$controlIdDef .= ' class="'.$class.'" ';
					$eventHandler = ($onChange == '') ? ' onchange="WPCardzNetLib_OnChangeSelect(this, event);" ' : $onChange;
					if ($this->inputElemClass != '') $eventHandler .= ' class="'.$this->inputElemClass.'" ';
					$selectOptsArray = self::GetSelectOptsArray($settingOption);
					if (count($selectOptsArray)>1)
					{
						$editControl  = '<select '.$controlIdDef.$eventHandler.'>'."\n";
						foreach ($selectOptsArray as $selectOptValue => $selectOptText)
						{
							$selected = ($controlValue == $selectOptValue) ? ' selected=""' : '';
							$editControl .= '<option value="'.$selectOptValue.'"'.$selected.' >'.$selectOptText."&nbsp;</option>\n";
						}
						$editControl .= '</select>'."\n";	
					}
					else
					{
						if (count($selectOptsArray)==1) $controlValue = key($selectOptsArray);
						$editControl  = $this->GetSelectOptsText($settingOption, $controlValue);
						if ($this->allowHiddenTags) $editControl .=  '<input type="hidden" '.$controlIdDef.' value="'.$controlValue.'"/>';					
					}
					break;

				case self::TABLEENTRY_CHECKBOX:
					$controlIdDef .= ' class="'.$class.'"';
					$eventHandler = ($onChange == '') ? 'onchange="WPCardzNetLib_OnChangeCheckbox(this, event);" ' : $onChange;
					if ($this->inputElemClass != '') $eventHandler .= ' class="'.$this->inputElemClass.'" ';
					$checked = ($controlValue == true) ? ' checked="yes"' : '';
					$cbText = __($settingOption[WPCardzNetLibTableClass::TABLEPARAM_TEXT], 'wpcardznet');
					$editControl = '<input type="checkbox" '.$controlIdDef.' value="1" '.$eventHandler.$checked.' />&nbsp;'.$cbText."\n";
					break;

				case self::TABLEENTRY_READONLY:
					$editControl = $controlValue;
					if (isset($settingOption[self::TABLEPARAM_ITEMS]))
					{
						// This was a drop down edit - Get User Prompt for this value
						$editControl = $this->GetSelectOptsText($settingOption, $controlValue);
					}
					if ($this->allowHiddenTags) $editControl .= '<input type="hidden" '.$controlIdDef.' value="'.$controlValue.'">'."\n";
					break;
					
				case self::TABLEENTRY_VIEW:
					$editControl = $controlValue.'&nbsp;';
					break;

				case self::TABLEENTRY_VALUE:
					$editControl = $settingOption[self::TABLEPARAM_VALUE];
					break;

				default:
					break;
			}

			$enableButton = current_user_can(WPCARDZNETLIB_CAPABILITY_SETUPUSER);
			if (isset($settingOption[self::TABLEPARAM_BUTTON]))
			{
				if (isset($settingOption[self::TABLEPARAM_EXTN]))
				{
					$fileExtn = $settingOption[self::TABLEPARAM_EXTN];
					$editControl .= '<input id="'.$settingOption[self::TABLEPARAM_ID].'-Extn" type="hidden" value="'.$fileExtn.'" />'."\n";
				}
				
				$buttonId = $settingOption[self::TABLEPARAM_ID].'-Button';
				$buttonIdDef = '';
				if ($enableButton)
				{
					$buttonIdDef = 'id="'.$buttonId.'" name="'.$buttonId.'" ';
					if ($this->tableUsesSerializedPost)
					{
						$clickEvent="return wpcardznetlib_JSONEncodePost(this, 'wpcardznetlib_PostVal')";
						$buttonIdDef .= ' onclick="'.$clickEvent.'"';
					}
				}
				else
				{
					$buttonIdDef .= ' disabled="yes"';
				}
				$buttonClass = 'button-secondary';
				if ($this->inputElemClass != '') $buttonClass .= ' '.$this->inputElemClass;
				$buttonText = __($settingOption[self::TABLEPARAM_BUTTON], 'wpcardznet');
				$editControl .= '<input '.$buttonIdDef.' class="'.$buttonClass.'" type="submit" value="'.$buttonText.'" />'."\n";
			}
		
			return $editControl;
		}
		
		function AddResultFromTable($result)
		{		
			$canDisplayTable = true;
			
			// Check if this row CAN be output using data from the columnDefs table
			foreach ($this->columnDefs as $key => $columnDef)
			{
				if (!isset($columnDef[self::TABLEPARAM_TYPE]))
					return true;
				
				switch ($columnDef[self::TABLEPARAM_TYPE])
				{
					case self::TABLEENTRY_CHECKBOX:
					case self::TABLEENTRY_TEXT:
					case self::TABLEENTRY_INTEGER:
					case self::TABLEENTRY_FLOAT:
					case self::TABLEENTRY_DATETIME:
					case self::TABLEENTRY_TEXTBOX:
					case self::TABLEENTRY_SELECT:
					case self::TABLEENTRY_VALUE:
					case self::TABLEENTRY_VIEW:
					case self::TABLEENTRY_READONLY:
					case self::TABLEENTRY_COOKIE:
					case self::TABLEENTRY_FUNCTION:
						break;
												
					default:
						$canDisplayTable = false;
						WPCardzNetLibEscapingClass::Safe_EchoHTML("Can't display this table - Label:".$columnDef[self::TABLEPARAM_LABEL]." Id:".$columnDef[self::TABLEPARAM_ID]." Column Type:".$columnDef[self::TABLEPARAM_TYPE]."<br>\n");
						break 2;
				}
			}
			
			if ($canDisplayTable)
			{
				$rowClass = $this->GetRowClass($result);
				$rowAttr = ($rowClass != '') ? 'class="'.$rowClass.'"' : '';
				$this->NewRow($result, $rowAttr);
				
				foreach ($this->columnDefs as $columnDef)
				{
					if (isset($columnDef[self::TABLEPARAM_POSITION]))
					{
						switch($columnDef[self::TABLEPARAM_POSITION])
						{
							case self::TABLEENTRY_BELOWLAST:
								// Increment the current row and decrement the column
								$this->currRow++;
								$this->currCol--;
								break;
						}
					}
				
					if (isset($columnDef[self::TABLEPARAM_ID]))
					{
						$columnId = $columnDef[self::TABLEPARAM_ID];
						$recId = $this->GetRecordID($result).$this->GetDetailID($result);
						
						if ($this->updateFailed && WPCardzNetLibUtilsClass::IsElementSet('post', $columnId.$recId))
						{
							if ( (isset($columnDef[self::TABLEPARAM_TYPE])) 
							  && ($columnDef[self::TABLEPARAM_TYPE] == self::TABLEENTRY_TEXTBOX) )
							{
								// Error updating values - Get value(s) from form controls
								$currVal = WPCardzNetLibUtilsClass::GetHTTPTextareaElem('post', $columnId.$recId);							
							}
							else
							{
								// Error updating values - Get value(s) from form controls
								$currVal = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', $columnId.$recId);									
							}
						}
						else
						{
							// Get value(s) from database
							if (property_exists($result, $columnId))
								$currVal = $result->$columnId;
							else
							{
								$currVal = '';	
							}
							
						}						
					}
					else
						$currVal = '';
				
					$hiddenVal = $currVal;						
					if (isset($columnDef[self::TABLEPARAM_DECODE]))
					{
						$optionId = $columnDef[self::TABLEPARAM_ID];
						$funcName = $columnDef[self::TABLEPARAM_DECODE];
						if (!property_exists($result, $optionId))
						{
							$value =  $optionId;							
						}
						else if (!is_null($result->$optionId))
						{
							$value =  $result->$optionId;
						}
						else
						{
							$value =  '';
						}
						$hiddenVal = $currVal = $this->$funcName($value, $result);
						if (WPCardzNetLibMigratePHPClass::Safe_strpos($currVal, '>'))
						{
							$hiddenVal = $this->$funcName($result->$optionId, $result, true);
						}
					}
					
					$columnType = $columnDef[self::TABLEPARAM_TYPE];
					if ((!$this->editMode) && ($columnType != self::TABLEENTRY_FUNCTION))
					{
						switch ($columnType)
						{
							case self::TABLEENTRY_CHECKBOX:
								$currVal = ($currVal == 1) ? __('Yes', 'wpcardznet') : __('No', 'wpcardznet');
								break;

							case self::TABLEENTRY_SELECT:
								$currVal = $this->GetSelectOptsText($columnDef, $currVal);
								break;
						}
						
						$columnType = self::TABLEENTRY_VIEW;
					}

					if ($this->editMode)	
					{
						if (isset($columnDef[self::TABLEPARAM_CANEDIT]))
						{
							$funcName = $columnDef[self::TABLEPARAM_CANEDIT];
							$editMode = $this->$funcName($result);
							if (!$editMode)
							{
								if ($columnType == self::TABLEENTRY_SELECT)
								{
									// Get Value from Items List
									$srchText = $currVal.'|';
									$srchLen = WPCardzNetLibMigratePHPClass::Safe_strlen($srchText);
									foreach ($columnDef[WPCardzNetLibTableClass::TABLEPARAM_ITEMS] as $item)
									{
										if (WPCardzNetLibMigratePHPClass::Safe_substr($item, 0, $srchLen) === $srchText)
										{
											$currVal = WPCardzNetLibMigratePHPClass::Safe_substr($item, $srchLen);
											break;
										}
									}
								}
								else if ($columnType == self::TABLEENTRY_CHECKBOX)
								{
									$currVal = ($currVal == 1) ? __('Yes', 'wpcardznet') : __('No', 'wpcardznet');
								}
								$columnType = self::TABLEENTRY_VIEW;
							}
						}						
					}
						
					switch ($columnType)
					{
						case self::TABLEENTRY_CHECKBOX:
							$checked = ($currVal==1);
							$this->AddCheckBoxToTable($result, $columnDef, $checked, 0, "1");
							break;
							
						case self::TABLEENTRY_SELECT:
							$options = self::GetSelectOptsArray($columnDef, $result);							
							$this->AddSelectToTable($result, $columnDef, $options, $currVal);
							break;
						
						case self::TABLEENTRY_COOKIE:
							$cookieID = $columnDef[self::TABLEPARAM_ID];
							if (WPCardzNetLibUtilsClass::IsElementSet('cookie', $cookieID))
								$currVal = WPCardzNetLibUtilsClass::GetHTTPTextElem('cookie', $cookieID);
							else
								$currVal = '';
							// Fall into next case ...
							
						case self::TABLEENTRY_TEXTBOX:
							$currVal = WPCardzNetLibMigratePHPClass::Safe_htmlspecialchars($currVal);
							$this->AddTextBoxToTable($result, $columnDef, $currVal, 0);
							break;
						
						case self::TABLEENTRY_TEXT:
							$currVal = WPCardzNetLibMigratePHPClass::Safe_htmlspecialchars($currVal);
							// Fall into next case ...

						case self::TABLEENTRY_INTEGER:
						case self::TABLEENTRY_FLOAT:
							if (!isset($columnDef[self::TABLEPARAM_LEN]))
							{
								WPCardzNetLibEscapingClass::Safe_EchoHTML("No Len entry in Column Definition<br>\n");
							}
							
							$size = isset($columnDef[self::TABLEPARAM_SIZE]) ? $columnDef[self::TABLEPARAM_SIZE] : $columnDef[self::TABLEPARAM_LEN]+1;
							$extraParams = 'size="'.$size.'"';
							$this->AddInputToTable($result, $columnId, $columnDef[self::TABLEPARAM_LEN], $currVal, 0, false, $extraParams);
							break;

						case self::TABLEENTRY_DATETIME:
							$size = 28;
							$inputClass = 'wpcardznet'.'-dateinput';
							$dateMode = isset($columnDef[self::TABLEPARAM_DATEMODE]) ? $columnDef[self::TABLEPARAM_DATEMODE] : 'future';
							$extraParams = "class=\"".$inputClass."\" readonly=true onclick=\"javascript:WPCardzNetLib_DateModeCalendarSelector(this, '".$this->dateTimeMode."', '".$dateMode."')\" ";
							$this->AddInputToTable($result, $columnId, $size, $currVal, 0, false, $extraParams);
							$this->hasDateTimeEntry = true;
							break;

						case self::TABLEENTRY_VALUE:
						case self::TABLEENTRY_VIEW:
						case self::TABLEENTRY_READONLY:
							$recId = $this->GetRecordID($result).$this->GetDetailID($result);
							if (!$this->allowHiddenTags) 
							{
								$hiddenTag = '';								
							}
							else if ($this->tableUsesSerializedPost)
							{
								$hiddenTag = '<input type="hidden" class="wpcardznetlib_PostVal" id="'.$columnId.$recId.'" value="'.$hiddenVal.'">';
							}
							else
							{
								$hiddenTag = '<input type="hidden" name="'.$columnId.$recId.'" id="'.$columnId.$recId.'" value="'.$hiddenVal.'">';
							}
							if (isset($columnDef[WPCardzNetLibTableClass::TABLEPARAM_LINK]))
							{
								$currValLink = $columnDef[WPCardzNetLibTableClass::TABLEPARAM_LINK];
								if (isset($columnDef[WPCardzNetLibTableClass::TABLEPARAM_LINKURL]))
								{
									$optionId = $columnDef[self::TABLEPARAM_ID];
									$funcName = $columnDef[WPCardzNetLibTableClass::TABLEPARAM_LINKURL];
									$currValLink = $this->$funcName($result->$optionId, $result);
									$target = 'target="_blank"';
								}
								else if (isset($columnDef[WPCardzNetLibTableClass::TABLEPARAM_LINKTO]))
								{
									$currValLink .= "http://";		// Make link absolute
									$currValLink .= $result->$columnDef[WPCardzNetLibTableClass::TABLEPARAM_LINKTO];
									$target = 'target="_blank"';
								}
								else
								{
									$currValLink .= $recId;
									$currValLink = $this->myDBaseObj->AddParamAdminReferer($this->caller, $currValLink);
									$target = '';
								}
								$currVal = '<a href="'.$currValLink.'" '.$target.'>'.$currVal.'</a>';
							}
							$this->AddToTable($result, $currVal.$hiddenTag.'&nbsp;');
							break;
							
						case self::TABLEENTRY_FUNCTION:
							$functionId = $columnDef[self::TABLEPARAM_FUNC];
							$content = $this->$functionId($result);
							$this->AddToTable($result, $content);
							break;
							
						default:
							break;
					}

				}
			}
						
			return $canDisplayTable;
		}
		
		function GetCurrentOptionValue($option, $optionId, $result)
		{
			if (isset($option[self::TABLEPARAM_TYPE]) && ($option[self::TABLEPARAM_TYPE] != self::TABLEENTRY_COOKIE))
			{
				if (property_exists($result, $optionId))
					$currVal = $result->$optionId;
				else 
				{
					if (isset($option[self::TABLEPARAM_DEFAULT]))
						$currVal = $option[self::TABLEPARAM_DEFAULT];
					else
						$currVal = '';
				
				}
						
				if (isset($option[WPCardzNetLibTableClass::TABLEPARAM_DECODE]))
				{
					$funcName = $option[WPCardzNetLibTableClass::TABLEPARAM_DECODE];
					$currVal = $this->$funcName($currVal, $result);
				}
			}
			else if (WPCardzNetLibUtilsClass::IsElementSet('cookie', $optionId))
				$currVal = WPCardzNetLibUtilsClass::GetHTTPAlphaNumericElem('cookie', $optionId); 
			else
				$currVal = '';
				
			return $currVal;
		}
		
		function AddOptions($result, $optionDetails = array())
		{
			$optionsRecordID = $this->GetRecordID($result);
			$showOptions = ($this->showOptionsID == $optionsRecordID);
			
			$hiddenRowsID = 'record'.$optionsRecordID.'options';
			
			if (count($this->detailsRowsDef) > 0)
			{
				$colClassList = '';
				for ($c=1; $c<$this->maxCol; $c++)
					$colClassList .= ',';

				if ($this->moreText != '')
				{
					$buttonText = $showOptions ? $this->lessText : $this->moreText;				
					$this->AddShowOrHideButtonToTable($result, $this->tableName, $hiddenRowsID, $buttonText);
					$colClassList .= 'optionsCol';					
				}
				else if ($this->maxCol > 0)
				{
					$this->AddToTable($result, '');
				}
				
				$this->SetColClass($colClassList);
												
				$tableId = $this->GetTableID($result);
				$hiddenRowsColId = $tableId.'-hiddenCol';
		
				$tabbedRowCounts = array();
		
				$nextInline = false;
				$hiddenRows = isset($this->optionsRowFormURL) ? '<form method="post" action="'.$this->optionsRowFormURL.'">' : '';
				$hiddenRows .= "<table class=$tableId-table width=\"100%\">\n";
				
				foreach ($this->detailsRowsDef as $option)
				{
					if (isset($option[self::TABLEPARAM_ID]) && !$this->CanShowDetailsRow($result, $option[self::TABLEPARAM_ID]))
						continue;
						
					if (isset($option[self::TABLEPARAM_LABEL]))
						$optionLabel = __($option[self::TABLEPARAM_LABEL], 'wpcardznet');
						
					$tabRowId = '';
					if (isset($option[self::TABLEPARAM_BLOCKBLANK]))
					{
						$optionId = $option[self::TABLEPARAM_ID];
						$currVal = $this->GetCurrentOptionValue($option, $optionId, $result);
						if (($currVal == '') || ($currVal === 0))
						{
							// Remove Row if the value is blank or zero
							continue;
						}
					}
					
					if (!$nextInline && isset($option[self::TABLEPARAM_TAB]))
					{
						$tabId = $option[self::TABLEPARAM_TAB];
						$rowNumber = isset($tabbedRowCounts[$tabId]) ? $tabbedRowCounts[$tabId] + 1 : 1;
						$tabbedRowCounts[$tabId] = $rowNumber;
						
						$tabRowId = 'id='.$tabId.'-row'.$rowNumber;
					}					
					 					
					$tableRowTag = '<tr '.$tabRowId.' >';
					switch ($option[self::TABLEPARAM_TYPE])
					{
						case self::TABLEENTRY_FUNCTION:
							$functionId = $option[self::TABLEPARAM_FUNC];
							$content = $this->$functionId($result, $optionDetails);
							$hiddenRows .= $tableRowTag."\n";
							$colSpan = ' class='.$hiddenRowsColId.'2';
							if (isset($option[self::TABLEPARAM_LABEL]))
								$hiddenRows .= '<td class='.$hiddenRowsColId.'1>'.$optionLabel."</td>\n";
							else
								$colSpan = " colspan=2";
								
							$hiddenRows .= '<td'.$colSpan.'>'.$content."</td>\n";
							$hiddenRows .= "</tr>\n";
							break;
							
						case self::TABLEENTRY_ARRAY:
							if (isset($option[self::TABLEPARAM_LABEL]))
							{
								$hiddenRows .= $tableRowTag."\n";
								$hiddenRows .= '<td colspan=2>'.$optionLabel."</td>\n";
								$hiddenRows .= "</tr>\n";
							}
							$arrayId = $option[self::TABLEPARAM_ID];
							foreach ($result->$arrayId as $elemId => $elemValue)
							{
								$hiddenRows .= $tableRowTag."\n";
								$hiddenRows .= '<td class='.$hiddenRowsColId.'1>'.$elemId."</td>\n";
								$hiddenRows .= '<td class='.$hiddenRowsColId.'2>'.$elemValue."</td>\n";
								$hiddenRows .= "</tr>\n";
							}
							break;
							
						default:
							$recId = $this->GetRecordID($result);
							$optionId = $option[self::TABLEPARAM_ID];
							$option[self::TABLEPARAM_ID] = $option[self::TABLEPARAM_ID].$recId;
											
							if (!$nextInline)
								$hiddenRows .= $tableRowTag."\n";
							if (WPCardzNetLibMigratePHPClass::Safe_strlen($option[self::TABLEPARAM_LABEL]) > 0)
							{
								if (!$nextInline)
									$hiddenRows .= '<td class='.$hiddenRowsColId.'1>';
								$hiddenRows .= $optionLabel."</td>\n";
								$nextInline = false;
							}
							if (!$nextInline)
								$hiddenRows .= '<td class='.$hiddenRowsColId.'2>';
							
							$currVal = $this->GetCurrentOptionValue($option, $optionId, $result);
								
							$class = 'wpcardznetlib_PostVal';
							if ($recId != '') $class .= '-'.$recId;
							$hiddenRows .= self::GetHTMLTag($option, $currVal, $this->editMode, $class);
							
							$nextInline = isset($option[self::TABLEPARAM_NEXTINLINE]);
							if (!$nextInline) 
								$hiddenRows .= "</td>\n</tr>\n";
							break;
					}
				}
				$hiddenRows .= "</table>\n";
				$hiddenRows .= isset($this->optionsRowFormURL) ? '</form>'."\n" : '';
				
				$class = $showOptions ? $this->visibleRowClass : $this->hiddenRowClass;
				$this->spanEmptyCells = true;
				$this->AddHiddenRows($result, $hiddenRowsID, $hiddenRows, $class);
			}			
		}
				
		static function GetSettingsRowIndex($arr1, $id)
		{			
			foreach ($arr1 as $index => $elem)
			{
				if (isset($elem[self::TABLEPARAM_ID]) && ($elem[self::TABLEPARAM_ID] === $id))
					return $index;
			}
			
			return -1;
		}
		
		
		static function MergeSettings($arr1, $arr2)
		{
			// Merge Arrays ... keeping all duplicate entries
			$vals1 = $arr1;
			foreach ($arr2 as $val2)
			{
				$index = -1;
				if (isset($val2[self::TABLEPARAM_BEFORE]))
				{
					// This entry must be positioned within earlier entries
					$index = self::GetSettingsRowIndex($vals1, $val2[self::TABLEPARAM_BEFORE], self::TABLEPARAM_BEFORE);
				}
				if (isset($val2[self::TABLEPARAM_AFTER]))
				{
					// This entry must be positioned within earlier entries
					$index = self::GetSettingsRowIndex($vals1, $val2[self::TABLEPARAM_AFTER], self::TABLEPARAM_AFTER);
					if ($index >= 0) $index++;
				}
				
				if ($index >= 0)
					array_splice($vals1, $index, 0, array($val2));
				else
					$vals1 = array_merge($vals1, array($val2));
			}
			return $vals1;
		}
		
		function GetListDetails($result)
		{
			return array();
		}
		
		function OutputJSDateConstants()
		{
			if (!$this->hasDateTimeEntry)
				return;
			
			WPCardzNetLibEscapingClass::Safe_EchoScript("<script type='text/javascript'>\n");	
		
			// Use a date for a Sunday (22/12/2013)			
			$scriptOutput = "var wpcardznetlib_WeekDayName1 = [";
			for ($dayNo = 22, $i=1; $i <= 7; $dayNo++, $i++)
			{
				$day = date_i18n("l",WPCardzNetLibMigratePHPClass::Safe_strtotime('2013-12-'.$dayNo));
				if ($i > 1) $scriptOutput .= ', ';
				$scriptOutput .= '"'.ucwords($day).'"';
			}
			$scriptOutput .= "];\n";
			
			// Use a date for a Monday (23/12/2013)			
			$scriptOutput .= "var wpcardznetlib_WeekDayName2 = [";
			for ($dayNo = 23, $i=1; $i <= 7; $dayNo++, $i++)
			{
				$day = date_i18n("l",WPCardzNetLibMigratePHPClass::Safe_strtotime('2013-12-'.$dayNo));
				if ($i > 1) $scriptOutput .= ', ';
				$scriptOutput .= '"'.ucwords($day).'"';
			}
			$scriptOutput .= "];\n";
			
			$scriptOutput .= "var wpcardznetlib_MonthName = [";
			for ($monthNo = 1; $monthNo <= 12; $monthNo++)
			{
				$month = date_i18n("F",WPCardzNetLibMigratePHPClass::Safe_strtotime('2000-'.$monthNo.'-20'));
				if ($monthNo > 1) $scriptOutput .= ', ';
				$scriptOutput .= '"'.ucwords($month).'"';
			}
			$scriptOutput .= "];\n";
								
			$scriptOutput .= "var wpcardznetlib_textOK = \"".__("OK", 'wpcardznet')."\";\n";
			$scriptOutput .= "var wpcardznetlib_textCancel = \"".__("Cancel", 'wpcardznet')."\";\n";
	
			WPCardzNetLibEscapingClass::Safe_EchoScript($scriptOutput);
			
			WPCardzNetLibEscapingClass::Safe_EchoScript("</script>\n");
			
		}
		
		function OutputJavascript($selectedTabIndex = 0)
		{
			if (!$this->isTabbedOutput)
				return;
					
			if (count($this->columnDefs) <= 1)
				return;

			$lastTabId = WPCardzNetLibUtilsClass::GetHTTPAlphaNumericElem('post', 'lastTabId', ""); 
			WPCardzNetLibEscapingClass::Safe_EchoHTML('<input type="hidden" name="lastTabId" id="lastTabId" value="'.$lastTabId.'"/>'."\n");
			
			$js = ("<script type='text/javascript'>");
			$js .= "
				";

			$js .= $this->JS_Top();
			foreach ($this->columnDefs as $column)
			{
				$setingsPageID = $column[self::TABLEPARAM_ID];
				$js .= $this->JS_Tab($setingsPageID);
			}
				
			$js .=  $this->JS_Bottom($selectedTabIndex);

			$js .= "</script>\n";

			WPCardzNetLibEscapingClass::Safe_EchoScript($js);
		}

		function GetLimitSQL()
		{
			$rowsPerPage = $this->myDBaseObj->GetRowsPerPage();
			$currentPage = WPCardzNetLibTableUtilsClass::GetCurrentPage();	
			$offset = ($currentPage - 1) * $rowsPerPage;			
			return " LIMIT $offset, ".$rowsPerPage.' ';
		}
		
		function GetTableData(&$results, $rowFilter)
		{
			// This function can be overloaded to get the data from the DB
		}

		function OutputList($results, $updateFailed = false)
		{
			if ($this->usesAjax)
				$this->Output_AjaxGlobals();
			
			$rowFilter = '';
			if (count($this->filterRowDefs) > 0)
			{
				$this->GetFilterCounts();

				// Calculate and output filter links - Returns the row count for the selected filter
				$filterObj = $this->OutputFilterLinks($this->filterRowDefs, $this->defaultFilterId);
				
				$this->totalRows = $filterObj->totalRows;
				
				$this->GetTableData($results, $filterObj->rowFilter);
			}
			else if ($this->rowsPerPage > 0)
			{
				// Get number of rows
				$this->totalRows = $this->GetTableRowCount();
				
				// Now get table data for this page 
				$this->GetTableData($results, $rowFilter);
			}										
			else
			{
				$this->GetTableData($results, $rowFilter);
				$this->totalRows = count($results);		
			}										
			
			if ($this->totalRows == 0) 
			{
				if (!isset($this->blankTableClass))
					return 0;
				$tableId = $this->GetTableID(null);
				$this->tableTags = WPCardzNetLibMigratePHPClass::Safe_str_replace('class="', 'class="'.$this->blankTableClass.' ', $this->tableTags);
			}
			else
			{
				$tableId = $this->GetTableID(reset($results));
				
				$this->OutputJavascript();		
			}
			
			$headerColumns = array();
			foreach ($this->columnDefs as $column)
			{
				if (isset($column[self::TABLEPARAM_POSITION])) continue;
				
				$columnID = $column[self::TABLEPARAM_ID];
				$columnLabel = __($column[self::TABLEPARAM_LABEL], 'wpcardznet');
				$headerColumns[$columnID] = $columnLabel;
			}
			$this->SetListHeaders($tableId, $headerColumns, $this->HeadersPosn);

			$this->results = $results;
	
			$this->EnableListHeaders();
			
			$this->rowNo = 0;
			$this->rowCount = 0;

			if ($this->totalRows > 0)
			{
				$this->tableName = $this->GetTableID(reset($results));			
	
				foreach($results as $result)
				{
					$this->rowNo++;
					
					if (!$this->AddResultFromTable($result))
					{
						if (!isset($this->usedAddResult))
						{
							$this->usedAddResult = true;
							WPCardzNetLibEscapingClass::Safe_EchoHTML("<br>Error returned by AddResultFromTable function in ".get_class($this)." class<br>\n");
							WPCardzNetLibUtilsClass::ShowCallStack();
						}
					}
					$resultDetails = $this->GetListDetails($result);
					$this->AddOptions($result, $resultDetails);
					$this->rowCount++;
				}				
			}
			
			$this->Display();
			
			$this->OutputJSDateConstants();	
			
			return $this->totalRows;		
		}
		
		function JS_Top()
		{
			$js_top = "var tabIdsList  = [";
			return $js_top;
		}
		
		function JS_Tab($tabID)
		{
			$js_tab = "'".$tabID."',";
			return $js_tab;
		}
		
		function JS_Bottom($defaultTab)
		{
			$js_bottom  = "''];\n";
			$js_bottom .= "
var defaultTabIndex = ".$defaultTab.";
			";
			return $js_bottom;
		}
		
		function GetTableRowCount()
		{
			return 1;
		}
	}

	class WPCardzNetLibAdminDetailsListClass extends WPCardzNetLibAdminListClass
	{
	}
}



