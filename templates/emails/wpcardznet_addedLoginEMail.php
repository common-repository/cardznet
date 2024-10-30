<?php /* Hide template from public access ... Next line is email subject - Following lines are email body
[organisation] - Login Added
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
[groupAdminName] ([groupAdminEMail]) has created a login for you on the [organisation] website. <br />
</p>
<table>
<tbody>
<tr>
<td colspan=2><h3>Login Details:</h3></td>
</tr>
<tr>
<td>Login Name: </td>
<td>[username]</td>
</tr>
<tr>
<td>Password:</td>
<td>[password]</td>
</tr>
<tr>
<td>Your Name:</td>
<td>[inviteFirstName] [inviteLastName]</td>
</tr>
<tr>
<td>Your Email:</td>
<td>[inviteEMail]</td>
</tr>
</tbody>
</table>

You have been also been added to the [groupName] group on the website. <br />
<br />
[groupAdminName] can now add you to games that they set up.<br />
<br />

<table width="100%" cellspacing="0" cellpadding="0">
<tr>
<td>
<table cellspacing="0" cellpadding="0">
<tr>
<td class="button" bgcolor="#ED2939">
<a  class="link" href="[loginURL]" target="_blank">Click here to Login</a>
</td>
</tr>
</table>
</td>
</tr>
</table>

</div>
</body>
</html>
*/ ?>