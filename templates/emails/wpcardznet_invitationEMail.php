<?php /* Hide template from public access ... Next line is email subject - Following lines are email body
[organisation] - Invitation to Play Cards
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<style>
#emailblock
{
	width: 750px;
	padding: 5px;
	border: 1px black solid;
}

#centre
{
	
}

.button 
{
    border-radius: 2px;
}

.button a 
{
    padding: 8px 12px;
    border: 1px solid #ED2939;
    border-radius: 2px;
    font-family: Helvetica, Arial, sans-serif;
    font-size: 14px;
    color: #ffffff; 
    text-decoration: none;
    font-weight: bold;
    display: inline-block;  
}
</style>
</head>
<body text="#000000" bgcolor="#FFFFFF">
<div id="emailblock"><p><a href="[url]"><img src="[logoimg]" alt="[organisation]" /></a><br />
<br />
Hi [inviteFirstName]<br />
<br />
[groupAdminName] ([groupAdminEMail]) has sent you an invitation to play cards with them on the [organisation] website<br />
<br />
If you accept the invitation a login will be created for you on the site.<br />
</p>
<table>
<tbody>
<tr>
<td colspan=2><h3>Details:</h3> </td>
</tr>
<tr>
<td>Your Name: </td>
<td>[inviteFirstName] [inviteLastName] </td>
</tr>
<tr>
<td>Your Email: </td>
<td>[inviteEMail] </td>
</tr>
</tbody>
</table>

<br />

<table>
<tbody>
<tr>
<td>To accept the invitation click on the button below or enter the link URL in your internet browser:</td>
</tr>
</tbody>
</table>

<table width="100%" cellspacing="0" cellpadding="0">
<tr>
<td>
<table cellspacing="0" cellpadding="0">
<tr>
<td class="button" bgcolor="#ED2939">
<a  class="link" href="[inviteURL]" target="_blank">Click here to Accept</a>
</td>
</tr>
</table>
</td>
</tr>
</table>

URL: [inviteURL] <br>

</div>
</body>
</html>
*/ ?>