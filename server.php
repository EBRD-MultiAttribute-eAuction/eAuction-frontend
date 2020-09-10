<?php
if(php_sapi_name()=='cli'){
	defined('CRON_MODE') || define('CRON_MODE',true);
}
defined('AJAX') || define('AJAX',false);
function setServer(){
	foreach(array(
		'DOCUMENT_ROOT'=>'/',
		'HTTP_HOST'=>'www.auction.kvsk',
		'SERVER_ADDR'=>'',
		//'HTTPS'=>'On',
	) as $k=>$v) {
		if(!empty($_SERVER[$k])) continue;
		switch($k){
			case 'DOCUMENT_ROOT':
				$_SERVER[$k]=getcwd();
				break;
			case 'HTTP_HOST':
				$_SERVER[$k]=$v;
				break;
			case 'SERVER_ADDR':
				$_SERVER[$k]=gethostbyname($_SERVER['HTTP_HOST']);
				break;
		}
	}
}
setServer();