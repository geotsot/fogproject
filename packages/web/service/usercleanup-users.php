<<<<<<< HEAD
<?php
require('../commons/base.inc.php');
try
{
	print "#!start\n";
	foreach ($FOGCore->getClass('UserCleanupManager')->find() AS $User)
		print base64_encode($User->get('name'))."\n";
	print "#!end\n";
}
catch (Exception $e)
{
	print $e->getMessage();
}
=======
<?php
require('../commons/base.inc.php');
if ($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'])
{
	$index = 0;
	foreach($FOGCore->getClass('UserCleanupManager')->find() AS $User)
	{
		$Datatosend .= ($index == 0 ? "#!ok\n" : '')."#user$index=".$User->get('name')."\n";
		$index++;
	}
}
else
{
	$Datatosend = "#!start\n";
	foreach ($FOGCore->getClass('UserCleanupManager')->find() AS $User)
		$Datatosend .= base64_encode($User->get('name'))."\n";
	$Datatosend .= "#!end\n";
}
if ($FOGCore->getSetting('FOG_NEW_CLIENT') && $FOGCore->getSetting('FOG_AES_ENCRYPT'))
	print "#!en=".$FOGCore->aesencrypt($Datatosend,$FOGCore->getSetting('FOG_AES_PASS_ENCRYPT_KEY'));
else
	print $Datatosend;
>>>>>>> dev-branch
