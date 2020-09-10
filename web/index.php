<?php
$time_start = microtime(1);
if(!empty($_SERVER['HTTP_USER_AGENT'])) {
	$pattern = '/msie [0-9]/i';
	$k=preg_match($pattern, $_SERVER['HTTP_USER_AGENT'], $matches, PREG_OFFSET_CAPTURE);
	if($k) $matches=explode(' ',$matches[0][0]);
	if($k && $matches[1]<=7) exit('You must have MSIE greater 7!!!');
}
include_once 'admin/core.php';
if(!empty($_REQUEST['JsHttpRequest'])){
	require_once "admin/JsHttpRequest.php";
	$JsHttpRequest = new JsHttpRequest("utf-8");
	$GLOBALS['result']=&$_RESULT;
	define('AJAX',true);
}else {
	define('AJAX',false);
}
define('BUGREPORT',false);

$ct='default';
if(!empty($_SERVER["CONTENT_TYPE"])){
	$ct=explode(';',$_SERVER["CONTENT_TYPE"]);
	$ct=trim($ct[0]);
}
define('CONTENT_TYPE',$ct);
unset($ct);

S::g()->setQuery();
if(ACCESS_ALLOW) {
	
	if(ACCESS_BYPASS && empty($_SESSION['adminpanel']) && empty($_SESSION['bypass'])) {
		if(!empty($_POST['siteLogin']) && !empty($_POST['sitePass']) && $_POST['siteLogin']==ACCESS_LOGIN && $_POST['sitePass']==ACCESS_PASSWORD) {
			$_SESSION['bypass']=1;
			ERROR('reload');
			exit();
		}
		header('HTTP/1.1 503 Service Temporarily Unavailable');
		header('Status: 503 Service Temporarily Unavailable');
		header('Retry-After: 86400');
		echo file_get_contents('tpl/accessbypass.htm');
	}
	else {
		getPage();
	}
}
else {
	ERROR(403);
}