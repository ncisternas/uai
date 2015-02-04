<?php
if(!isset($options)){
	throw new Exception("Illegal access.");
}
$options = array(
		"pluginprefix"=>"uai_",
		"pluginpath"=>"local",
		"knownpaths"=>array(
				"Common"=>"local\uai\\framework\common",
				"Mod"	=>"mod",
				"Blocks"	=>"blocks",
				"Framework"=>"local\uai\\framework"
				)
		);