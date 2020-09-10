<?php
define('LANGURL','/');
class Plugins{
	private $plugins=array();
	public static function __callStatic($name, $arguments) {
		$obj=self::g();
		$path=false;
		if(!empty($arguments)) @list($path)=$arguments;
		if($path){
			$_path=explode('/',$path);
			$pl_key=$_path[0];
		}
		else {
			$pl_key='default';
		}
		if(!isset($obj->plugins[$pl_key][$name])){
			$class=$name;
			if(class_exists($class)) $obj->plugins[$pl_key][$name] = new $class;
			else {
				if($path){
					$plugin_file=$_SERVER['DOCUMENT_ROOT'].'/plugins/'.$path.'/triKita.php';
				}
				else {
					$fname=strtolower($name);
					$plugin_file=$_SERVER['DOCUMENT_ROOT'].'/plugins/'.$fname.'.php';
				}
				if(is_file($plugin_file)) {
					include_once($plugin_file);
					if(class_exists($class)) $obj->plugins[$pl_key][$name] = new $class;
					else $obj->plugins[$pl_key][$name]=new DefaultPlugin;
				}
				else $obj->plugins[$pl_key][$name]=new DefaultPlugin;
			}
		}
		return $obj->plugins[$pl_key][$name];
	}
	protected static function g(){
		static $c=null;
		if($c==null) {
			$cn=__CLASS__;
			$c=new $cn;
		}
		return $c;
	}
}
class DefaultPlugin {
	 public function __call($name, $arguments){}
	 public static function __callStatic($name, $arguments){}
}
class S extends SO {}

function regRule($var) {
	if(empty($GLOBALS['check_page'])) $page='/'.S::g()->query_string;
	else {
		$page=$GLOBALS['check_page'];
	}
	$var=str_replace('/','\/',$var);
	return preg_match('/'.$var.'/',$page);
}
function ERROR($code=404,$redirect='',$msg=false){
	if(defined('CRON_MODE') && CRON_MODE){
		return $msg;
	}
	switch($code){
		case 'main':
			header('Location: '.(empty($_SERVER['HTTPS'])?'http':'https').'://'.$_SERVER['HTTP_HOST'].LANGURL);
			break;
		case 'reload':
			$get=$_GET;
			$get=http_build_query($get);
			$q=preg_replace('|^/|','',$_SERVER['QUERY_STRING']);
			header('Location: '.(empty($_SERVER['HTTPS'])?'http':'https').'://' . $_SERVER['HTTP_HOST'].LANGURL.$q.(empty($get)?'':'?'.$get));
			exit;
			break;
		case 'redirect':
			header('Location: '. $redirect);
			exit;
			break;
		default:
			if(is_numeric($code)) {
				switch($code){
					case 404:
						header("HTTP/1.0 404 Not Found");
						header("HTTP/1.1 404 Not Found");
						break;
					case 403:
						header("HTTP/1.0 403 Forbidden");
						header("HTTP/1.1 403 Forbidden");
						break;
				}
			}
			if(AJAX || !empty(S::g()->error)) exit(@$msg['code']?:(string) $code);
			switch(CONTENT_TYPE){
				case 'application/json':
					$error=['error'=>[]];
					if($msg) $error['error']=$msg;
					$error['error']['http_code']=$code;
					header('Content-Type: application/json; charset=utf-8');
					echo json_encode($error);
					exit;
					break;
			}

			S::g()->setQuery('/errors/'.$code);
			S::g()->error=$code;
			getPage();
	}
	exit;
}

function getPage(){
	include 'reg.php';

	$e404=true;
	foreach(array_filter(array_keys($GLOBALS['registry']), "regRule") as $v) {
		$GLOBALS['registry']=$GLOBALS['registry'][$v];
		$e404=false;
		break;
	}

	if($e404) {
		ERROR(404);
		exit;
	}
	if(!($GLOBALS['registry']['ajax']==1 || (AJAX && $GLOBALS['registry']['ajax']==2) || (!AJAX && $GLOBALS['registry']['ajax']==0))) ERROR(AJAX?'Что-то напутано':404);
	
	$get=array();
	if(!empty($GLOBALS['registry']['get']))
		foreach($GLOBALS['registry']['get'] as $getname=>$getreg){
			$getreg=str_replace('/','\/',$getreg);
			if(isset($_GET[$getname]) && is_string($_GET[$getname]) && preg_match('/'.$getreg.'/',$_GET[$getname])) {
				$get[$getname]=$_GET[$getname];
			}
			elseif(isset($_GET[$getname]) && is_array($_GET[$getname])) $get[$getname]=$_GET[$getname];
		}
	$_GET=$get;
	unset($get);

	foreach($GLOBALS['registry']['plugins'] as $pl){
		if($pl['ajax']==1 || (AJAX && $pl['ajax']==2) || (!AJAX && $pl['ajax']==0)){
			$class=ucfirst(strtolower($pl['name']));
			foreach($pl['functions'] as $func){
				if(($func['ajax']==1 || (AJAX && $func['ajax']==2) || (!AJAX && $func['ajax']==0)) && (method_exists(Plugins::$class(),$func['name']) || method_exists(Plugins::$class(),'__call'))) call_user_func_array(array(Plugins::$class(),$func['name']),[$func['prm']]);
			}
		}
	}
	if(AJAX){
		
	}
	else {
		if(CONTENT_TYPE!='application/json' && !empty($GLOBALS['registry']['tpl']) && is_file($tpl='tpl/'.$GLOBALS['registry']['tpl'])) readfile($tpl);
		else {
			header('Content-Type: application/json; charset=utf-8');
			echo @json_encode($GLOBALS['_RESULT']); // Вывод пользователю
		}
	}
}
function onDBConnectError(){
	exit('Где-то тут моя БД была, немогу найти.');
}