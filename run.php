<?php
	
	require_once("default_ssh.php");
	require_once("vm_commands.php");
	require_once("clone.php");

	$src_host = "192.168.2.101";
	$dst_host = "192.168.2.101";
	$dst_datastore = "VM\ Storage";
	$vm_name = "ubuntu-base";
	$offset = 5;

	$start = time();
	$vms = list_vms($src_host);
	foreach($vms as $vm)
	{
		if($vm["name"] == $vm_name)
		{
			batch_clone_vm($vm,$dst_datastore,$src_host,$dst_host,3,0,$offset);
		}
	}
	$total_time = time() - $start;

	echo "Run time: ".$total_time;

?>