<?php

class BaseController extends Controller {

	/**
	 * Setup the layout used by the controller.
	 *
	 * @return void
	 */
	public function __construct(){
		            
                
		$this->beforeFilter(function(){
    		Event::fire('clockwork.controller.start');
		});

		$this->afterFilter(function()
		{
		    Event::fire('clockwork.controller.end');
		});
                
	}

	protected function setupLayout()
	{
		if ( ! is_null($this->layout))
		{
			$this->layout = View::make($this->layout);
		}
	}
    
//$path = realpath('E:/work/ari');
    public function getRecursivePaths($path){
        $path = realpath($path);
        $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);
        $paths = array();
        foreach($objects as $name => $object){
            if(!$object->isDir()){ 
                $paths[] = $name;
            }
        }
        return $paths;
    }
    

}