<?php

	define("ADMIN","root");
	function ssh_esxi_host($host,$cmd)
	{
		$ssh = "ssh ". ADMIN ."@".$host." ".$cmd;
		exec($ssh,$output);
		return $output;
	}

	function ssh_send_file_esxi_host($host,$file,$destination)
	{
		$file = str_replace('"','\"',$file);
		$ssh = 'echo "'.$file.'" | ssh '. ADMIN .'@'.$host.' "cat > \"'.$destination.'\""';
		exec($ssh,$output);
                return $output;
	}

?>