<?php
/*
Description: Settings Admin Page functions

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

include 'wpcardznetlib_admin.php';
include 'wpcardznetlib_utils.php';

if (!class_exists('WPCardzNetLibSettingsAdminClass'))
{
	class WPCardzNetLibSettingsAdminClass extends WPCardzNetLibAdminClass // Define class
	{
		function __construct($env) //constructor
		{
			$this->pageTitle = 'Settings';

			$env['adminObj'] = $this;

			$this->adminListObj = $this->CreateAdminListObj($env, true);

			// Call base constructor
			parent::__construct($env);
		}

		function ProcessActionButtons()
		{
			$myPluginObj = $this->myPluginObj;
			$myDBaseObj = $this->myDBaseObj;

			$SettingsUpdateMsg = '';

			if (WPCardzNetLibUtilsClass::IsElementSet('post', 'savechanges') || $this->isAJAXCall)
			{
				$this->CheckAdminReferer();

				if ($SettingsUpdateMsg === '')
				{
					$this->SaveSettings($myDBaseObj);
					//$myDBaseObj->saveOptions();

					WPCardzNetLibEscapingClass::Safe_EchoHTML('<div id="message" class="updated"><p>'.__('Settings have been saved', 'wpcardznet').'</p></div>');
 				}
				else
				{
					$this->Reload();

					WPCardzNetLibEscapingClass::Safe_EchoHTML('<div id="message" class="error"><p>'.$SettingsUpdateMsg.'</p></div>');
					WPCardzNetLibEscapingClass::Safe_EchoHTML('<div id="message" class="error"><p>'.__('Settings have NOT been saved.', 'wpcardznet').'</p></div>');
				}
			}

		}

		function Output_MainPage($updateFailed)
		{
			$myPluginObj = $this->myPluginObj;
			$myDBaseObj = $this->myDBaseObj;

			// Settings HTML Output - Start

			$formClass = 'wpcardznet'.'-admin-form';
			WPCardzNetLibEscapingClass::Safe_EchoHTML('<div class="'.$formClass.'">'."\n");
?>
	<form method="post">
<?php

			$this->WPNonceField();

			$this->adminListObj->detailsRowsDef = apply_filters('wpcardznet'.'_filter_settingslist', $this->adminListObj->detailsRowsDef, $this->myDBaseObj);

			/*
			Usage:

			add_filter('{DomainName}_filter_settingslist', 'XXXXXXXXFilterSettingsList', 10, 2);
			function XXXXXXXXFilterSettingsList($detailsRowsDef, $myDBaseObj)
			{
				$settingsCount = $myDBaseObj->getDbgOption('Dev_SettingCount');
				if (is_numeric($settingsCount))
				{
					$newDefs = array();
					$i = 0;
					foreach ($detailsRowsDef as $index => $def)
					{
						$tabParts = explode('-', $def['Tab']);
						if ((count($tabParts) == 4) && ($tabParts[3] !== 'paypal'))
						{
							continue;
						}
						$newDefs[] = $def;
						$i++;
						if ($i >= $settingsCount) break;
					}
					$detailsRowsDef = $newDefs;
				}

				return $detailsRowsDef;
			}
			*/


			// Get setting as stdClass object
			$results = $myDBaseObj->GetAllSettingsList();

			if (count($results) == 0)
			{
				WPCardzNetLibEscapingClass::Safe_EchoHTML("<div class='noconfig'>".__('No Settings Configured', 'wpcardznet')."</div>\n");
			}
			else
			{
				$this->adminListObj->OutputList($results, $updateFailed);

				if (!isset($this->adminListObj->editMode) || ($this->adminListObj->editMode))
				{
					if ((count($results) > 0) && !$this->usesAjax)
					{
						$this->OutputPostButton("savechanges", __("Save Changes", 'wpcardznet'), "button-primary");
					}
				}
			}

?>
	</form>
	</div>
<?php
		}

		function SaveSettings($dbObj)
		{
			$settingOpts = $this->adminListObj->GetDetailsRowsDefinition();

			// Save admin settings to database
			foreach ($settingOpts as $settingOption)
			{
				if (isset($settingOption[WPCardzNetLibTableClass::TABLEPARAM_READONLY]))
				{
					continue;
				}

				$controlId = $settingOption[WPCardzNetLibTableClass::TABLEPARAM_ID];
				if ($this->isAJAXCall && !WPCardzNetLibUtilsClass::IsElementSet('post', $controlId))
				{
					continue;
				}

				switch ($settingOption[WPCardzNetLibTableClass::TABLEPARAM_TYPE])
				{
					case WPCardzNetLibTableClass::TABLEENTRY_READONLY:
					case WPCardzNetLibTableClass::TABLEENTRY_VIEW:
						break;

					case WPCardzNetLibTableClass::TABLEENTRY_CHECKBOX:
						$controlId = $settingOption[WPCardzNetLibTableClass::TABLEPARAM_ID];
						$dbObj->adminOptions[$controlId] = WPCardzNetLibUtilsClass::IsElementSet('post', $controlId) ? true : false;
						break;

					case WPCardzNetLibAdminListClass::TABLEENTRY_DATETIME:
						// Text Settings are "Trimmed"
						$controlId = $settingOption[WPCardzNetLibTableClass::TABLEPARAM_ID];
						$dbObj->adminOptions[$controlId] = WPCardzNetLibUtilsClass::GetHTTPDateTime('post', $controlId);
						break;

					case WPCardzNetLibTableClass::TABLEENTRY_TEXT:
						// Text Settings are "Trimmed"
						$controlId = $settingOption[WPCardzNetLibTableClass::TABLEPARAM_ID];
						$dbObj->adminOptions[$controlId] = WPCardzNetLibMigratePHPClass::Safe_trim(WPCardzNetLibUtilsClass::GetHTTPTextElem('post', $controlId));
						break;

					case WPCardzNetLibTableClass::TABLEENTRY_TEXTBOX:
						// Text Settings are "Trimmed"
						$controlId = $settingOption[WPCardzNetLibTableClass::TABLEPARAM_ID];
						if (isset($settingOption[WPCardzNetLibTableClass::TABLEPARAM_ALLOWHTML]))
							$dbObj->adminOptions[$controlId] = WPCardzNetLibUtilsClass::GetHTTPTextHttpElem('post', $controlId);
						else
							$dbObj->adminOptions[$controlId] = WPCardzNetLibUtilsClass::GetHTTPTextareaElem('post', $controlId);
						break;

					default:
						$controlId = $settingOption[WPCardzNetLibTableClass::TABLEPARAM_ID];
						$dbObj->adminOptions[$controlId] = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', $controlId);
						break;
				}
			}

			if (defined('WPCARDZNETLIB_SETTINGS_SAVED'))
				$dbObj->adminOptions[WPCARDZNETLIB_SETTINGS_SAVED] = true;

			$dbObj->saveOptions();

			if ($this->isAJAXCall) $this->donePage = true;
		}

		function EditTemplate($templateID, $folder='emails', $isEMail = true)
		{
			if (!current_user_can( 'manage_options' )) return false;

			$pluginRoot = WPCardzNetLibMigratePHPClass::Safe_str_replace('plugins', 'uploads', dirname(dirname(__FILE__)));
			$pluginId = basename($pluginRoot);

/*
			$len = WPCardzNetLibMigratePHPClass::Safe_strlen($templateID);
			foreach (WPCardzNetLibUtilsClass::GetArrayKeys('post') as $postKey)
			{
				$postVal = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', $postKey);
				if (WPCardzNetLibMigratePHPClass::Safe_substr($postKey, 0, $len) !== $templateID) continue;
				$postKeyParts = explode('-', $postKey);
				if (count($postKeyParts) < 2) continue;
				if (($postKeyParts[1] === 'Button') || ($postKeyParts[1] === 'Save'))
				{
					$templateID = $postKeyParts[0];
					break;
				}
			}
*/
			if (WPCardzNetLibUtilsClass::IsElementSet('post', $templateID.'-Button'))
			{
				$templateFile = WPCardzNetLibUtilsClass::GetHTTPFilenameElem('post', $templateID);

				$templatePath = $pluginRoot;
				if ($folder != '') $templatePath .= '/'.$folder;
				$templatePath .= '/'.$templateFile;

				$editorID = $templateID.'-Editor';

				if ($templateFile == '')
				{
					$fileExtn = WPCardzNetLibUtilsClass::IsElementSet('post', $templateID.'-Extn');
					$templateFile = "{$pluginId}-custom.{$fileExtn}";
					$templatePath .= $templateFile;

					// TODO: - Create default file ....
					return false;
				}
				else
				{
					$contents = file_get_contents($templatePath);
				}
				if ($isEMail)
				{
					$pregRslt = preg_match('/(.*[\n])(.*[\n])([\s\S]*?)(\*\/[\s]*?\?\>)/', $contents, $matches);
					if ($pregRslt != 1)
					{
						WPCardzNetLibEscapingClass::Safe_EchoHTML('<div id="message" class="error"><p>'.__('Error parsing file.', 'wpcardznet').' - '.$templateFile. '</p></div>');
						$this->donePage = true;
						return true;
					}
					$subject = $matches[2];
					$contents = $matches[3];
					$htmlMode = (WPCardzNetLibMigratePHPClass::Safe_strpos($contents, '</html>') > 0);
					$styles = '';
					if ($htmlMode)
					{
						// Extract any styles from the source - Editor removes them
						if (preg_match_all('/\<style[\s\S]*?\>([\s\S]*?)\<\/style\>[\s\S]*?/', $contents, $matches) >= 1)
						{
							foreach ($matches[1] as $style)
							{
								$styles .= "\n<style>$style</style>\n";
							}
						}

						$pregRslt = preg_match('/\<body[\s\S]*?\>([\s\S]*?)\<\/body/', $contents, $matches);
						if ($pregRslt != 1)
						{
							WPCardzNetLibEscapingClass::Safe_EchoHTML('<div id="message" class="error"><p>'.__('Error parsing HTML in file', 'wpcardznet').' - '.$templateFile. '</p></div>');
							return true;
						}
						else
						{
							$contents = $matches[1];
						}
						$contents = WPCardzNetLibMigratePHPClass::Safe_str_replace("\n", "", $contents);		// Remove all line ends
						$mystyle = '
<style>
#'.$editorID.'_ifr
{
	border: solid black 1px;
}
</style>';
						$settings = array(
							'wpautop' => false,
						    'editor_css' => $mystyle
						);
					}
					else
					{
						WPCardzNetLibEscapingClass::Safe_EchoHTML('
<style>
#'.$editorID.'-tmce,
#qt_'.$editorID.'_toolbar,
#wp-'.$editorID.'-media-buttons
{
	display: none;
}
</style>');
						$settings = array();
					}
				}
				else
				{
					// Just need a text editor
					$htmlMode = false;
					$settings = array(
						'wpautop' => true,
					    'media_buttons' => false,
					    'editor_css' => '',
					    'tinymce' => false,
					    'quicktags' => false
						);
				}

				$saveButtonId = $templateID.'-Save';
				$buttonValue = __('Save', 'wpcardznet');
				$buttonCancel = __('Cancel', 'wpcardznet');

				$this->pageTitle .= " ($templateFile)";

				WPCardzNetLibEscapingClass::Safe_EchoHTML('<form method="post" id="'.$pluginId.'-fileedit">'."\n");
				if ($isEMail)
				{
					WPCardzNetLibEscapingClass::Safe_EchoHTML("<div id=".$pluginId."-fileedit-div-subject>\n");
					WPCardzNetLibEscapingClass::Safe_EchoHTML(__("Subject", 'wpcardznet')."&nbsp;<input name=\"$pluginId-fileedit-subject\" id=\"$pluginId-fileedit-subject\" type=\"text\" value=\"$subject\" maxlength=80 size=80 /></div>\n");
				}

				wp_editor($contents, $editorID, $settings);
				if ($htmlMode)
				{
					WPCardzNetLibEscapingClass::Safe_EchoHTML("<input name=\"$pluginId-fileedit-html\" id=\"$pluginId-fileedit-html\" type=\"hidden\" value=1 />\n");
				}
				WPCardzNetLibEscapingClass::Safe_EchoHTML("<input name=\"$pluginId-fileedit-isEMail\" id=\"$pluginId-fileedit-isEMail\" type=\"hidden\" value=\"$isEMail\" />\n");
				WPCardzNetLibEscapingClass::Safe_EchoHTML("<input name=\"$pluginId-fileedit-name\" id=\"$pluginId-fileedit-name\" type=\"hidden\" value=\"$templateFile\" />\n");
				WPCardzNetLibEscapingClass::Safe_EchoHTML("<input name=\"$pluginId-fileedit-folder\" id=\"$pluginId-fileedit-folder\" type=\"hidden\" value=\"$folder\" />\n");
				if (isset($styles)) WPCardzNetLibEscapingClass::Safe_EchoHTML("<input name=\"$pluginId-fileedit-styles\" id=\"$pluginId-fileedit-styles\" type=\"hidden\" value=\"".$styles."\" />\n");
				WPCardzNetLibEscapingClass::Safe_EchoHTML("<input class=\"button-primary\" name=\"$saveButtonId\" id=\"$saveButtonId\" type=\"submit\" value=\"$buttonValue\" />\n");
				WPCardzNetLibEscapingClass::Safe_EchoHTML("<input class=\"button-secondary\" type=\"submit\" value=\"$buttonCancel\" />\n");
				WPCardzNetLibEscapingClass::Safe_EchoHTML("</form>\n");

				$this->donePage = true;
				return true;
			}

			if (WPCardzNetLibUtilsClass::IsElementSet('post', $templateID.'-Save'))
			{
				$templateFile = WPCardzNetLibUtilsClass::GetHTTPFilenameElem('post', $pluginId.'-fileedit-name');
				$templateDir = WPCardzNetLibUtilsClass::GetHTTPFilenameElem('post', $pluginId.'-fileedit-folder');
				$fileParts = pathinfo($templateFile);
				$templateName = $fileParts['filename'];
				$templateExtn = $fileParts['extension'];
				$templateContents = stripslashes(WPCardzNetLibUtilsClass::GetArrayElement('post', $templateID.'-Editor'));
	
				switch($templateExtn)
				{
					case 'css':
					case 'js':
						$folderName = $templateExtn;
						$templateFolder = WPCARDZNETLIB_UPLOADS_PATH.'/'.$folderName.'/';
						$isPHP = false;
						$subject = '';
						break;

					default:
						if ($templateDir == '')
						{
							$folderName = '';
							$templateFolder = WPCARDZNETLIB_UPLOADS_PATH.'/';
							$isPHP = false;
							$subject = '';
							break;
						}
						$folderName = 'emails';
						$templateFolder = WPCARDZNETLIB_UPLOADS_PATH.'/'.$folderName.'/';
						$isPHP = true;
						$subject = WPCardzNetLibUtilsClass::GetHTTPTextElem('post', $pluginId.'-fileedit-subject')."\n";
						if (WPCardzNetLibMigratePHPClass::Safe_strpos($subject, '*/') || WPCardzNetLibMigratePHPClass::Safe_strpos($templateContents, '*/'))
						{
							WPCardzNetLibEscapingClass::Safe_EchoHTML('<div id="message" class="error"><p>'.__('Template not saved - Invalid Content', 'wpcardznet').'</p></div>');
							return false;
						}
					break;
				}
				$defaultTemplateFolder = WPCARDZNETLIB_DEFAULT_TEMPLATES_PATH.$folderName;
				if (file_exists($defaultTemplateFolder.'/'.$templateFile))
				{
					// The template is a default template - Save with new name
					$fileNumber = 1;
					while (true)
					{
						$destFileName = $templateName."-$fileNumber.".$templateExtn;
						if ($destFileName == $templateFolder.$templateFile)
							break;
						if (!file_exists($templateFolder.$destFileName))
						{
							$templateFile = $destFileName;
							$this->myDBaseObj->adminOptions[$templateID] = $destFileName;
							$this->myDBaseObj->saveOptions();
							break;
						}
						$fileNumber++;
						if ($fileNumber > 1000)
						{
							WPCardzNetLibEscapingClass::Safe_EchoHTML('<div id="message" class="error"><p>'.__('Template Not Saved - Could not rename file.', 'wpcardznet').'-'.$templateFile.'</p></div>');
							return false;
						}
					}
				}

				$htmlMode = WPCardzNetLibUtilsClass::IsElementSet('post', $pluginId.'-fileedit-html');

				$contents  = '';

				if ($isPHP)
				{
					$contents .= "<?php /* Hide template from public access ... Next line is email subject - Following lines are template body\n";
					$contents .= $subject;
				}

				if ($htmlMode)
				{
					$contents .= '
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8">';

					if (WPCardzNetLibUtilsClass::IsElementSet('post', "$pluginId-fileedit-styles"))
					{
						$contents .= WPCardzNetLibUtilsClass::GetHTTPTextElem('post', "$pluginId-fileedit-styles");
					}

					$contents .= '
</head>
<body text="#000000" bgcolor="#FFFFFF">';
					$contents .= WPCardzNetLibMigratePHPClass::Safe_str_replace("<br />", "<br />\n", $templateContents);
					$contents .= '
</body>
</html>
';
				}
				else
				{
					$contents .= $templateContents;
				}

				if ($isPHP)
				{
					$contents .= "\n*/ ?>\n";
				}

				file_put_contents($templateFolder.$templateFile, $contents);
				WPCardzNetLibEscapingClass::Safe_EchoHTML('<div id="message" class="updated"><p>'.__('Template Updated.', 'wpcardznet').' - '.$templateFile. '</p></div>');
			}

			return false;
		}

	}
}


