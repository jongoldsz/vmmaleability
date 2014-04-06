<?php

	require_once("default_ssh.php");
        require_once("vm_commands.php");
	
	$host = '192.168.2.101';
	
	$vms = list_vms($host);	
	
	foreach($vms as $vm)
	{
		if(strpos($vm["vmx"],"clone") !== false)
		{
			echo "Deleting ".$vm["vmid"]."\n";
			power_off($host,$vm["vmid"]);
			unregister_vm($host,$vm["vmid"]);
			delete_vm($host,$vm["datastore"],$vm["name"]);
		}
	}

?>