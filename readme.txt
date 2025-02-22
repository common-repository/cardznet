=== CardzNet - Multiplayer Card Games ===
Contributors: Malcolm-OPH
Tags: pages, games, card games, network, playing cards, cards, multiuser, multiplayer, whist, black maria, canasta, hearts
Requires at least: 5.0
Tested up to: 6.3
Stable tag: 2.5.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The CardzNet plugin allows you to play cards over the internet

== Description ==

Features Summary

* Adds a Playing Card Game server to your WP Website
* Works with touch screens, mouse or keyboard input
* Players can share devices but keep their cards private
* Games available: Canasta, Hearts, Black Maria, Whist and One-Eyed Jacks
* Standard and High Visibility Card Faces available

== Installation ==

1. Upload "wpcardznet" folder to the "/wp-content/plugins/" directory
2. Activate the "CardzNet" plugin through the "Plugins" menu in WordPress
3. Go to the CardzNet Settings admin page on your Wordpress Desktop 
4. Review and save the settings
5. Go to the CardzNet Groups admin page and create a group
6. Add Members to the group using the buttons for the Group
7. Go to the CardzNet Games admin page and add a game
8. Go to the CardzNet Overview admin page and copy the shortcode
9. Create a wordpress page on your website and add the CardzNet shortcode to it
10. All players can now play the game by visiting the page you just added 

== Frequently Asked Questions ==

= Why do players need to log in =

This determines which player the device is being used by.

= What are Groups =

CardzNet uses player groups, with a specified administrator, to administer games. Each group can have an unlimited number of players, but only one concurrent game is permitted for each group. The group administrator can add new players by selecting the "Invite New Member" button, and then enter the name and email address of the person they wish to invite. The new member will be added to the group when they accept the invitation using the link in the email that CardzNet sends them.

Note: Users with Administrator privileges can add an existing Wordpress user to a group directly.

= How do players share devices =

Each device used to play must be logged in with a different username, and this usually determines who will play the game. If more than one player uses the same device, then the group administrator should add an entry for each of these players on the "Add Game Details" admin page, with the same Username but add an entry for the Player Name. This Player Name is then used to identify the player. 

During play, a players cards are hidden on a shared screen each time the screen changes from one player to another. The cards can be revealed by either clicking on a button, or pressing a key on the keyboard.

= How can I customise the EMails? =

The EMails generated by the CardzNet plugin are defined by a template file. 
Template defaults are in the {Plugins Folder}/wpcardznet/templates/email folder, which is copied to the {Uploads Folder}/wpcardznet/email when the plugin is Activated or Updated. The default template can be copied to new a file in the uploads folder, which can then be used to create a custom template, which can then in turn be selected using the Admin->Settings page.

The template file can be modified as required. A number of "Tags" can be included in the EMail template, which will be replaced by data relating to the sale extracted from the database. 

= What tags can be used in the EMail template? =

The following tags can be used in the EMail template:

* [inviteFirstName] - Player Invitation First Name
* [inviteLastName] - Player Invitation Last Name
* [inviteEMail] - Player Invitation Email
* [inviteDateTime] - Player Invitation Date and Time Sent
* [inviteHash] - Player Invitation Hash for Acceptance
* [groupName] - Player Invitation Group Name
* [groupAdminName] - Player Invitation Name of the Group Administrator
* [groupAdminEMail] - Player Invitation EMail of the Group Administrator
* [username] - The Players Login Username
* [password] - The Players Login Password
* [inviteURL] - Player Invitation Response URL
* [organisation] - The Organisation ID (as on the Settings Page)
* [url] - The URL of the Site Home Page
* [logoimg] - The URL of the EMail Logo Image (set on the Settings Page)

= Does CardzNet use Wordpress User Capabilities? =

Yes! CardzNet uses Wordpress Capabilities to control access to games and admin pages. The following Capabilities are implemented:

cardznet_player  - A user that can play a game
cardznet_manager - A user that can start a game and add players to a group
cardznet_admin   - A user that can administer WPPlayCards (except for settings)
cardznet_setup   - A user that can change WPPlayCards settings

Users added by CardzNet are created with the Subscriber Role with additional cardznet_player capability. 
When CardzNet adds an existing user to a group by CardzNet the cardznet_player capability is added to the user if they do not already have it.

= Does CardzNet use Wordpress User Capabilities? =

Yes! CardzNet uses Wordpress Capabilities to control access to games and admin pages. The following Capabilities are implemented:

= Does CardzNet need a specific Wordpress Theme =

No. CardzNet should remove all the theme CSS from any page with the CardzNet shortcode on it, and replace it with its' own CSS. It has been tested with the WP default themes from Twenty-Ten onwards.

== Screenshots ==

1. Screenshot 1: Black Maria Cards View
2. Screenshot 2: Groups Admin Page
3. Screenshot 3: Games Admin Page

== Changelog ==

= 2.5.1 (12/10/2023) =
* Bug Fix: One Eyed Jacks display format breaks after 1st card played

= 2.5 (19/07/2023) =
* Updated for compatibility with PHP 9.0 
* Null parameter values to string functions trapped 
* All echo calls escaped to follow updated plugin design guidelines

= 2.4.2 (23/10/2022) =
* Fixed "Deprecated: Required parameter follows optional parameter" warning ongroupd admin page
* Added English(UK) translation

= 2.4.1 (22/10/2022) =
* Bug Fix: One Eyed Jacks no longer recognised - use name rather than class to identify
* Bug Fix: New line fanfare persists once first line is achieved

= 2.3 (17/05/2022) =
* Core code aligned with StageShow plugin
* Updated "Tested up to" to 6.0

= 2.2 (18/04/2022) =
* Bug Fix: Fatal Error on playing Hearts opening hand 
* Bug Fix: PHP Warning on Games Admin screen with PHP 8.0
* Bug Fix: Deprecated usort call with PHP 8.0
* Updated "Tested up to" to 5.9.3

= 2.1 (10/01/2022) =
* Bug Fix: Form element on main page had duplicated id - renamed wpcardznet_form 
* Added Fullscreen Button to Header
* Added Reload Button to Header
* Removed Edit Page Link (for admin users)
* Removed WP div elements now have unique ids
* Reorganised header to have 3 blocks 
* Moved Canasta "swop" button to header 
* Updated "Tested up to" to 5.8.3

= 2.0.4 (09/05/2021) =
* Bug Fix: Black Maria & Hearts - Round scores incorrect (since 2.0.3)

= 2.0.3 (03/05/2021) =
* Bug Fix: Sequence - Last Cards played using active player rather than next player 

= 2.0.2 (21/03/2021) =
* Bug Fix: Max score for Hearts should be 26 (not 23)

= 2.0.1 (15/03/2021) =
* Minor update of email templates
* Fixed EMail Test on Tools page

= 2.0 (07/03/2021) =
* Added Canasta to games available
* Added Hearts to games available
* Added "Fanfare" mp3 to 1-Eyed Jacks
* Added limit to number of lines to 1-Eyed Jacks options
* Added "Enable Slam Score" to Black Maria and Hearts 
* Added "Break Hearts" option to Hearts Games
* Added "Slam Scores Maximum" option to Black Maria Games
* Added "Show Play History" buttons to 1-Eyed Jacks
* Added "Manager is First Player" option to settings
* Added "Player already active action" option 
* Selecting Text in all Divs disabled 
* Caching of Game, Round and Hands data added
* Updated Joker Card images
* Disabled right click (ctrl+right click still works) 
* Increased Spacing of Cards in Hand 
* Improved Formatting of Action Buttons 
* Whist etc.: Show last card played in final trick 
* Added rules link to card table 
* Tick counter files now specific to a loginId
* Tested with wp5.7

= 1.0 (21/11/2020) =
* First public release

= 1.0.1 (24/11/2020) =
* Bug Fix: Cannot Publish page with CardzNet shortcode

= 1.0.2 (25/11/2020) =
* Bug Fix: Fatal Error creating One-Eyed Jacks games if E_STRICT enabled

= 1.0.3 (26/11/2020) =
Bug Fix: Players deck blank when using Large-Face cards with One-Eyed-Jacks 
Bug Fix: Code warning when adding One-Eyed_Jacks games 
Hearts: Implemented "Break Hearts" functionality 

= 1.0.4 (05/12/2020) =
Added Generic Whist to available games 
Whist (and derivatives): Added "Show Scores" button so last trick of rounds remains visible
Whist (and derivatives): Added number of rounds to scores
Whist (and derivatives): Added optional limit to number of rounds

