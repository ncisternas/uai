<?php

spl_autoload_register(function ($class) {
	global $CFG;
	//We expect all of our clases to be like: Uai\<group>\any\thing\in\between\<Class>
	$exploded = explode("\\",$class,3);
	if($exploded[0]!="Uai"){ //autoloads only the Uai namespace!
		return;
	}
	$groupname = $exploded[1];
	$classname = $exploded[2];
	$options = array();
	require('autoload.settings.php');
	
	
	$prefix="";
	if(isset($options["knownpaths"][$groupname])){
		$path = $options["knownpaths"][$groupname];
	}else{
		$path = $options["pluginpath"].'\\'.$options["pluginprefix"].$groupname;
		
	}
	
	//PHP Namespaces use '\', we trim that from our strings, and then replace the rest with the
	//Directory separator character.
	$path = str_replace("\\",DIRECTORY_SEPARATOR,trim($path,"\\"));
	$classname = str_replace("\\",DIRECTORY_SEPARATOR,trim($classname,"\\"));
	
	//We use an all lowercase 
	$file = $CFG->dirroot.DIRECTORY_SEPARATOR.strtolower($path.DIRECTORY_SEPARATOR.$classname).".class.php";
	
	if(file_exists($file)){
		require_once($file);
	}else{
		echo $file;
		throw new Exception("The class {$class} does not exist in the $exploded[0] namespace. Tried to get it from $file");
	}
});