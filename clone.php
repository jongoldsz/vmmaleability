<?php

	require_once("default_ssh.php");
	require_once("vm_commands.php");
	function clone_vm($vm,$destination_datastore,$source_host,$destination_host,$maintain_state=0)
	{
		$clone_name = $vm["name"]."-clone";
		$vmx = load_vmx($source_host,$vm);
		$vmdk = load_vmdk($source_host,$vm,$vmx["scsi0:0.fileName"]);
		$delta_disk = get_delta_disk_name($vmx["scsi0:0.fileName"]);
		$vmx = update_vmx($vmx,$clone_name);
		$vmdk = update_vmdk($vm,$vmx,$vmdk,$clone_name,0);

		create_clone_folder($destination_host,$destination_datastore,$clone_name);
		save_vmdk($destination_host,$destination_datastore,$clone_name,$vmdk);
		save_vmx($destination_host,$destination_datastore,$clone_name,$vmx);
		copy_delta_disk($destination_host,$destination_datastore,$delta_disk,$vm,$clone_name);
		if($maintain_state)
			copy_state($destination_host,$destination_datastore,$delta_disk,$vm,$clone_name);
		$vm_id = register_vm($destination_host,$destination_datastore,$clone_name);
		power_on($destination_host,$vm_id);
		$question = get_question($destination_host,$vm_id);
		answer_question($destination_host,$vm_id,$question);
	}
	function batch_clone_vm($vm,$destination_datastore,$source_host,$destination_host,$count,$maintain_state=0,$offset=0)
	{
		$vmx = load_vmx($source_host,$vm);
		$vmdk = load_vmdk($source_host,$vm,$vmx["scsi0:0.fileName"]);
		$delta_disk = get_delta_disk_name($vmx["scsi0:0.fileName"]);
		$vm_ids = array();
		for($x = 0; $x < $count; $x++)
		{
			$clone_name = $vm["name"]."-clone-".($x+$offset);
                	$vmx = update_vmx($vmx,$clone_name);
                	$vmdk = update_vmdk($vm,$vmx,$vmdk,$clone_name,$x+$offset);

                	create_clone_folder($destination_host,$destination_datastore,$clone_name);
                	save_vmdk($destination_host,$destination_datastore,$clone_name,$vmdk);
                	save_vmx($destination_host,$destination_datastore,$clone_name,$vmx);
                	copy_delta_disk($destination_host,$destination_datastore,$delta_disk,$vm,$clone_name);
                	if($maintain_state)
				copy_state($destination_host,$destination_datastore,$delta_disk,$vm,$clone_name);
                	$vm_id = register_vm($destination_host,$destination_datastore,$clone_name);
                	$vm_ids[] = $vm_id;
			power_on($destination_host,$vm_id);
		}
		foreach($vm_ids as $vm_id)
		{
			$question = get_question($destination_host,$vm_id);
                	answer_question($destination_host,$vm_id,$question);
		}
	}
	function get_question($host,$vm_id)
	{
		/* get copied it question */
                $output = '';
                do
                {
			if($output != '')
                        	sleep(5);
                        $command = 'vim-cmd vmsvc/message '.$vm_id;
                        $output = ssh_esxi_host($host,$command);
                }
                while($output[0] == 'No message.');
		$output = explode(" ",$output[0]);
                $msg = $output[count($output)-1];
                $msg = trim(substr($msg,0,strlen($msg)-1));
		return $msg;
	}
	function answer_question($host,$vm_id,$question)
	{
		$command = 'vim-cmd vmsvc/message '.$vm_id.' '.$question.' 2';
                ssh_esxi_host($host,$command);
	}
	function copy_delta_disk($host,$destination_datastore,$delta_disk,$vm,$clone_name)
	{
		$command = '"cp /vmfs/volumes/'.$vm["datastore"].'/'.$vm["name"].'/'.$delta_disk.' /vmfs/volumes/'.$destination_datastore.'/'.$clone_name.'/'.$clone_name.'-delta.vmdk"';
		ssh_esxi_host($host,$command);
	}
	function copy_state($host,$destination_datastore,$delta_disk,$vm,$clone_name)
	{
		foreach($vm["files"] as $file)
		{
			if(strpos($file,'.vmss') !== FALSE)
			{
				$clone_file = explode("-",$file);
				$command = '"cp /vmfs/volumes/'.$vm["datastore"].'/'.$vm["name"].'/'.$file.' /vmfs/volumes/'.$destination_datastore.'/'.$clone_name.'/'.$file.'"';
				ssh_esxi_host($host,$command);
			}
		}
	}
	function get_delta_disk_name($disk)
	{
		$disk = explode(".",$disk);
		$disk = $disk[0].'-delta.vmdk';
		return $disk;
	}
	function load_vmx($host,$vm)
	{
		$vmx = array();
		$vmx_raw = ssh_esxi_host($host,'cat "/vmfs/volumes/'.$vm["datastore"].'/'.$vm["vmx"].'"');
		foreach($vmx_raw as $line)
		{
			$line = explode(" = ",$line);
			$vmx[$line[0]] = substr($line[1],1,strlen($line[1])-2);
		}
		return $vmx;
	}
	function update_vmx($vmx,$clone_name)
	{
		$vmx["scsi0:0.fileName"] = $clone_name.'.vmdk';
		return $vmx;
	}
	function load_vmdk($host,$vm,$vmdk_name)
	{
		$vmdk = array();
		$path = explode("/",$vm["vmx"]);
		$vmdk_raw = ssh_esxi_host($host,'cat "/vmfs/volumes/'.$vm["datastore"].'/'.$path[0].'/'.$vmdk_name.'"');
		return $vmdk_raw;
	}
	function update_vmdk($vm,$vmx,$vmdk,$clone_name,$num) // enabled delta disk
	{
		$path = explode("/",$vmx["sched.swap.derivedName"]);
		unset($path[count($path)-1]);
		$path = implode("/",$path);
		foreach($vmdk as $key => $value)
		{
			if(strpos($value,"parentFileNameHint=") !== FALSE && $num == 0)
			{
				$value = explode("=",$value);
				$value[1] = substr($value[1],1,strlen($value[1])-2);
				$value = $value[0].'="'.$path.'/'.$value[1].'"';
				$vmdk[$key] = $value;
			}
			else if(strpos($value,"RW") !== FALSE)
			{
				$value = explode(" ",$value);
				$value[count($value)-1] = '"'.$clone_name.'-delta.vmdk"';
				$value = implode(" ",$value);
				$vmdk[$key] = $value;
			}
		}
		return $vmdk;
	}
	function create_clone_folder($host,$destination_datastore,$clone_name)
	{
		ssh_esxi_host($host,'mkdir /vmfs/volumes/"'.$destination_datastore.'"/'.$clone_name);
	}
	function save_vmdk($host,$datastore,$clone_name,$vmdk)
	{
		$path = '/vmfs/volumes/"'.$datastore.'"/'.$clone_name.'/';
		$file = implode("\n",$vmdk);
		$result = ssh_send_file_esxi_host($host,$file,$path.$clone_name.'.vmdk');
	}
	function save_vmx($host,$datastore,$clone_name,$vmx)
	{
		$path = '/vmfs/volumes/"'.$datastore.'"/'.$clone_name.'/';
		$file = '';
		foreach($vmx as $key => $value)
		{
			$file .= $key.' = "'.$value.'"\n';
		}
		$result = ssh_send_file_esxi_host($host,$file,$path.$clone_name.'.vmx');
	}
?>