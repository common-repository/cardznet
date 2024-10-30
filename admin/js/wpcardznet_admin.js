/* 
Description: Generic Admin Javascript
 
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
	// Functions for Tools page 
	
	function wpcardznet_updateExportOptions(obj)
	{
		exportFormatElem = document.getElementById("export_format");
		exportTypeElem = document.getElementById("export_type");

		hideShowRow = (exportFormatElem.value == "ofx");
		if (!hideShowRow)
		{
			hideShowRow = (exportTypeElem.value == "settings");
		}
		
		selectShowRow = document.getElementById("wpcardznet-export_show-row");
		selectFilterRow = document.getElementById("wpcardznet-export_filter-row");
		if (hideShowRow)
		{
			selectShowRow.style.display = 'none';
			selectPerfRow.style.display = 'none';
			if (selectFilterRow) selectFilterRow.style.display = 'none';
		}
		else
		{
			selectShowRow.style.display = '';
			wpcardznet_onSelectShow(obj);
			if (selectFilterRow) 
			{
				selectFilterRow.style.display = '';
				for (filterIndex = 1; filterIndex<100; filterIndex++)
				{
					selectFilterElem = document.getElementById("filterSelect"+filterIndex);
					if (!selectFilterElem) break;
					selectFilterFile = selectFilterElem.value;
					if (selectFilterFile.indexOf("_"+exportTypeElem.value+"_") !== -1)
					{
						selectFilterElem.style.display = '';
					}
					else
					{
						selectFilterElem.style.display = 'none';
					}
					selectFilterElem = selectFilterElem;
				}
			}
		}		
	}
	
	function wpcardznet_onSelectShow(obj)
	{
		SelectControl = document.getElementById("export_showid");
		showID = SelectControl.value;
		hidePerfSelect = (showID == 0);
		
		selectPerfRow = document.getElementById("wpcardznet-export_performance-row");
		selectFilterRow = document.getElementById("wpcardznet-export_filter-row");
		if (hidePerfSelect)
		{
			selectPerfRow.style.display = 'none';
		}
		else
		{
			selectPerfRow.style.display = '';
			SelectControl = document.getElementById("export_perfid");

			/* Remove the current options */
			while (SelectControl.length > 0)
			{
				SelectControl.remove(0);
			}
			
			/* Reload the new options */
			for (i=0; i<perfselect_id.length; i++)
			{
				OptionIDs = perfselect_id[i].split ('.');
				if ((i==0) || (OptionIDs[0] == showID))
				{
					var option = document.createElement("option");
					option.value = perfselect_id[i];
					option.text = perfselect_text[i];
					SelectControl.add(option);					
				}
			}

		}		
	}
