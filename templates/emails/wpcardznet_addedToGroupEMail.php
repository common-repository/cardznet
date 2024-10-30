<?php /* Hide template from public access ... Next line is email subject - Following lines are email body
[organisation] - Added to Cards Group
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
You have been added to the "[groupName]" group on the [organisation] website. <br />
<br />
[groupAdminName] ([groupAdminEMail]) can now add you to games that they set up.<br />
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