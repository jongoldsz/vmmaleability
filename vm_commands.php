<?php

	require_once("default_ssh.php");

	function list_vms($host)
	{
		$output = ssh_esxi_host($host,"vim-cmd vmsvc/getallvms");
		$vms = array();
		$x = 0;
		foreach($output as $line)
		{
			while(strpos($line,'  ') !== false)
				$line = str_replace('  ',' ',$line);
			$line = explode(" ",$line);
			if(count($line) > 4 && strpos($line[2],"]") === false) // fix the issue with one space in datastore name
			{
				if(strpos($line[2],"(") !== false)
				{
					$line[1] .= ' '.$line[2];
					array_splice($line,2,1);
				}
				if(strpos($line[2],"]") === false)
				{
					$line[2] .= '\ '.$line[3];
					array_splice($line,3,1);

				}
			}
		        if(count($line) > 4 && strpos($line[2],"[") !== false && strpos($line[2],"]") !== false)
			{
				$vms[$x]["vmid"] = $line[0];
				$vms[$x]["name"] = $line[1];
				$vms[$x]["vmx"] = $line[3];
				$datastore = substr($line[2],1,strlen($line[2])-2);
				$vms[$x]["datastore"] = $datastore;
				$path = explode("/",$line[3]);
				$files = ssh_esxi_host($host,'ls "/vmfs/volumes/'.$datastore.'/'.$path[0].'"');
				$vms[$x]["files"] = $files;
				$x++;
			}
		}
		return $vms;
	}
	function power_on($host,$vm_id)
        {
                /* turn on machine */
                $command = 'vim-cmd vmsvc/power.on '.$vm_id.' > /dev/null 2>/dev/null &';
                ssh_esxi_host($host,$command);
        }
	function power_off($host,$vm_id)
        {
                /* turn off machine */
                $command = 'vim-cmd vmsvc/power.off '.$vm_id;
                ssh_esxi_host($host,$command);
        }
	function register_vm($host,$destination_datastore,$clone_name)
        {
                $command = 'vim-cmd solo/registervm /vmfs/volumes/"'.$destination_datastore.'"/'.$clone_name.'/'.$clone_name.'.vmx';
                $vm_id = ssh_esxi_host($host,$command);
                return $vm_id[0];
        }
	function destroy_vm($host,$vmid)
        {
                $command = 'vim-cmd vmsvc/destroy '.$vmid;
                ssh_esxi_host($host,$command);
        }
	function unregister_vm($host,$vmid)
        {
                $command = 'vim-cmd vmsvc/unregister '.$vmid;
                ssh_esxi_host($host,$command);
        }
	function delete_vm($host,$destination_datastore,$clone_name)
        {
                $command = 'rm -rf /vmfs/volumes/"'.$destination_datastore.'"/'.$clone_name;
                ssh_esxi_host($host,$command);
        }

?>