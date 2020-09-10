<?php
class Auctions {
	protected $bidStepTime=180;
	protected $bidStepPause=30;
	protected $bidsSteps=3;
	protected $cache=[];
	
	protected function getCacheFilename(&$auction,$mkdir=false){
		// ocds/prefix/UA/1511863200 123456/xx
		// ocds/prefix/UA/151/18/63200123456_xx/lot/index.json
		$dirs=explode('-',$auction['tender_id']);
		//if(!empty($dirs[3]) && is_numeric($dirs[3])){
			$_dirs=array_slice($dirs,0,3);
			/*$dirs[3]=substr($dirs[3],0,-6);
			$_dirs[]=date('Y-m',$dirs[3]);
			$_dirs[]=date('d',$dirs[3]);*/
			$_dirs[]=substr($dirs[3],0,3);
			$_dirs[]=substr($dirs[3],3,2);
			$_dirs[]=substr($dirs[3],5).(@$dirs[4]?'-'.$dirs[4]:null);
			$_dirs[]=$auction['lot_id'];
		//}
		
		$logdir=$this->getCacheRoot();
		if($mkdir && !is_dir($logdir)) @mkdir($logdir);
		foreach($_dirs as $v){
			$logdir.='/'.$v;
			if($mkdir && !is_dir($logdir)) @mkdir($logdir);
		}
		$fileName=$logdir.'/index.json';
		
		return $fileName;
	}
	public function getCacheRoot(){
		return $_SERVER['DOCUMENT_ROOT'].'/auctions';
	}
}