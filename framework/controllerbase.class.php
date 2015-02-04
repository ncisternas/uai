<?php
namespace Uai\Framework;

abstract class ControllerBase
{
	
	protected $action;
	protected $ajax = false;
	protected $methodsuffix ="Action";
	private $pluginroot;
	private $_inspector;
	public function __construct($pluginroot){
		global $PAGE;
		$this->_inspector = new Inspector($this);
		$this->pluginroot = $pluginroot;
		
		if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
			$this->ajax=true;
			$this->methodsuffix ="Ajax";
		}
	}
	//funcion
	public final function pass($action,$params=array())
	{
		try{
		global $CFG;
		$this->action = $action;
		$response = array();
		//genera el nombre de la funcion a llamar. Ej: gradingAction
		$function = $this->action.$this->methodsuffix;
		$meta = $this->_inspector->getMethodMeta($function);//get meta from the methods.
		$reflection =$this->_inspector->getMethodReflection($function);
		
		$refparams=$reflection->getParameters();
		$moodleparams = array();
		if($meta!=null){
			if(array_key_exists('@moodleparams', $meta)){
				$moodleparams = $meta['@moodleparams'];
			}
		}else{
			$meta=array();
		}
		
		$finalparams=array();
		foreach($refparams as $param){
			
			if(array_key_exists($param->name,$params)){
				$finalparams[$param->name]=$params[$param->name];
				$added=true;
			}else if(array_key_exists($param->name,$moodleparams)){//if is set to replace with moodleparam, then get it
				//The param is not in the function, we must get it from moodle!
					
					if($param->isDefaultValueAvailable()){
						$finalparams[$param->name]=\optional_param($param->name,$param->getDefaultValue(),$moodleparams[$param->name]);
					}else{
						$finalparams[$param->name]=\required_param($param->name,$moodleparams[$param->name]);
					}
				
			}else{
				//The param is neither in the function, nor in moodle, last chance is that it was set as default.
				
				
				if($param->isDefaultValueAvailable()){
					$finalparams[$param->name]=$param->getDefaultValue();//first default
					$added=true;
				}else{
					//Note: in a function, the parameters to the left of any required parameter automatically become mandatory.
					//Example: foo($bar="default") //$bar is optional
					//		   foo($bar="default",$badperson) //$bar is no longer optional.
					
					throw new \Exception("No se declaró ninguna fuente para el parámetro obligatorio: \$$param->name.");
				}
			}
			
		}
		$response = $reflection->invokeArgs($this,$finalparams);
		
		//$response = $this->{$function}();
		//render the response
		
		if(array_key_exists('@norender',$meta)){
			echo $response;
		}else if(!$this->ajax){
			$view = $this->action;
			if(array_key_exists('@template',$meta)){
				if(!$meta['@template'][0]){
					$view = $this->action;
				}else{

					$view = $meta['@template'][0];
				}
			}
			$file = $CFG->dirroot.'/'.$this->pluginroot.'/view/'.$view.'.view.php';
			
			extract($response);
			
			if(file_exists($file)){
				include($file);
			}else{
				throw new \Exception("Couldnt find the proper view to render this page: $file");
			}
		}else{
			header('Content-Type: application/json');
			if(isset($response['content'])){
				echo $response['content'];
			}else{
				echo $response;
			}
		}
		}catch(\dml_exception $ex){
				echo $ex->getMessage();
				echo "....";
				echo $ex->getTraceAsString();
				echo "</br>";
				echo $ex->debuginfo;
				echo "</br>";
				
		}
		catch(\Exception $ex){
			if($this->ajax){
				echo $ex->getMessage();
				echo "....";
				echo $ex->getTraceAsString();
			}else{
				throw $ex;
			}
		}
	}
}
