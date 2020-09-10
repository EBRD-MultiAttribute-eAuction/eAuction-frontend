<?php
date_default_timezone_set('Europe/Kiev');
mb_internal_encoding("UTF-8");
ini_set('precision', 100);
ini_set('serialize_precision', 100);

//if(!ini_get('log_errors')){
	ini_set('log_errors', 'On');
	ini_set('error_log', pathinfo(__FILE__,PATHINFO_DIRNAME).'/../../logs/php.error');
//}

include $_SERVER['DOCUMENT_ROOT'].'/admin/config.php';
if(empty($GLOBALS['mainSettings'])) exit('Куда-то делась конфигурация!!!');
foreach ($GLOBALS['mainSettings'] as $k=>$v){
	foreach ($v['items'] as $m=>$n){
		if(preg_match("/password/i",$m)) $n['value']=Tayna::shifrB($n['value']);
		if(preg_match('/_json$/i',$m)) $n['value']=@json_decode($n['value'],1);
		if($k!=$m) define($k.$m,$n['value']);
		else define($k,$n['value']);
	}
}
unset($GLOBALS['mainSettings'],$k,$v,$m,$n);

include_once 'library.php';
function extendData(&$data,$option=array()){
	$data_=array();
	foreach($option as $k=>$v) {
		if(is_array($v)){
			if(!isset($data[$k]) || !is_array($data[$k])) $data_[$k]=$v;
			else {
				$data_[$k]=$data[$k];
				extendData($data_[$k],$v);
			}
		}
		else {
			if(!isset($data[$k]) || (!is_string($data[$k]) && !is_numeric($data[$k]) && !is_bool($data[$k]))) $data_[$k]=$v;
			else $data_[$k]=$data[$k];
		}
	}
	$data=$data_;
}
class DB {
	public static function __callStatic($name, $arguments) {
		$obj=self::g();
		if(method_exists($obj, $name)) {
			return call_user_func_array(array($obj,$name),$arguments);
		}
	}
	public static function g(){
		static $c=null;
		if($c==null) {
			$cn=__CLASS__;
			$url='';
			if(defined('MySQL_p')) $url.='p:';
			$url.=DB_URL;
			$c=@new DBi($url, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
			@$c->set_charset("utf8");
			if(mysqli_connect_errno() && function_exists('onDBConnectError')){
				onDBConnectError();
			}
		}
		return $c;
	}
}
class DBi extends mysqli{
	protected $args=null;
	public function __construct(){
		$args=func_get_args();
		if(empty($args) && $this->args) $args=$this->args;
		if(!$this->args) $this->args=$args;
		call_user_func_array([__CLASS__,'parent::'.__FUNCTION__],$args);
	}
	public function getReplaceFields($in,$stripslashes=false){
		$replace=array();
		foreach($in as $k=>$v){
			if($stripslashes) $v=stripslashes($v);
			$replace[]='`'.$k.'`="'.$this->real_escape_string($v).'"';
		}
		return implode(',',$replace);
	}
	protected function checkConnection(){
		if(php_sapi_name()=='cli'){
			while(!$this->ping()){
				sleep(3);
				$this->__construct();
			}
		}
		else {
			if(!$this->ping()){
				sleep(3);
				$this->__construct();
			}
		}
	}
	public function begin_transaction($flags = NULL, $name = NULL){
		$this->checkConnection();
		$args=func_get_args();
		call_user_func_array([__CLASS__,'parent::'.__FUNCTION__],$args);
	}
	public function query($sql,$mode=MYSQLI_STORE_RESULT){
		$this->checkConnection();
		$res=parent::query($sql,$mode);
		$dir=pathinfo(__FILE__,PATHINFO_DIRNAME).'/../../logs/mysql';
		if($this->error){
			$fileName=$dir.'/sql_error_'.str_replace(array(':','+','T'),'_',date('c')).'_'.microtime(1);
			@file_put_contents($fileName,print_r([$sql,$this->error],1));
		}
		if($this->errno==2006) {
			if(php_sapi_name()=='cli'){
				if(is_dir($dir)){
					$fileName=$dir.'/'.str_replace(array(':','+','T'),'_',date('c')).'_'.microtime(1);
					$_fileName=$fileName;
					while(is_file($_fileName.'.log')){
						$_fileName=$fileName.'_'.rand(0,10000);
					}
					$fileName=$_fileName.'.log';
				}
				@file_put_contents($fileName,'ping DB #');
			}
			else {
				exit('SERVER ERROR: Can\'t connect to database.');
			}
		}
		return $res;
	}
	public function multi_query($sql){
		$this->checkConnection();
		$this->lastMultiquery=$sql;
		return parent::multi_query($sql);
	}
	public function freeResults(){
		$sqlWrite=false;
		$i=0;
		do {
			if($this->error || @$this->errno) {
				if(!$sqlWrite) {
					$dir=pathinfo(__FILE__,PATHINFO_DIRNAME).'/../../logs/mysql';
					$sqlWrite=$dir.'/sql_multi_error_'.str_replace(array(':','+','T'),'_',date('c')).'_'.microtime(1);
					file_put_contents($sqlWrite,print_r([$this->lastMultiquery],1),FILE_APPEND);
				}
				file_put_contents($sqlWrite,print_r([
					'numSQL'=>$i,
					'error'=>$this->error,
					'errorno'=>@$this->errno,
				],1),FILE_APPEND);
			}
			if ($result = $this->store_result()) $result->close();
			$i++;
		} while ($this->more_results() && $this->next_result());
		$this->lastMultiquery=null;
	}
}

abstract class SO {
	public $obj;
	public $error=false;
	public $query=array();
	public $origQuery=null;
	public $query_string='';
	public $ajaxRes=null;
	public static function g(){
		static $c=null;
		if($c==null) {
			//$cn=__CLASS__;
			$cn=get_called_class();
			$c=new $cn;
			//$c->init();
		}
		return $c;
	}
	public function setQuery($url=false){
		$set_QS=false;
		if(!$url){
			if(isset($_SERVER['REDIRECT_URL'])) {
				$_SERVER['QUERY_STRING']=$_SERVER['REDIRECT_URL'];
			}
			else $_SERVER['QUERY_STRING']='/';
			$url=$_SERVER['QUERY_STRING'];
			$set_QS=true;
		}
		$this->query=explode('/',$url); //парсинг УРЛ запроса
		array_shift($this->query);
		if(!sizeof($this->query)) $this->query=array('');
		$this->query_string=implode('/',$this->query);
		if($set_QS) $_SERVER['QUERY_STRING']=$this->query_string;
		if($this->origQuery===null) $this->origQuery=$this->query;

		if(is_null($this->ajaxRes)){
			global $_RESULT;
			$this->ajaxRes=&$_RESULT;
		}
	}
}

/**
* validate string session id
*
* @see http://www.devnetwork.net/viewtopic.php?f=34&t=88685#p520259
*
* @param string $sessionId
* @return bool
*/
/*function isValidId($sessionId=false){
	$iniGet=is_callable('ini_get');
	if($sessionId===false){
		if($iniGet) $sessionName=ini_get('session.name');
		else $sessionName='PHPSESSID';
		if(isset($_COOKIE[$sessionName])) $sessionId=$_COOKIE[$sessionName];
		elseif(isset($_GET[$sessionName])) $sessionId=$_GET[$sessionName];
		elseif(isset($_REQUEST[$sessionName])) $sessionId=$_REQUEST[$sessionName];
	}
	$strId = (string) $sessionId;
	if ($strId !== $sessionId) return FALSE;
	// session.hash_bits_per_character: '4' (0-9, a-f), '5' (0-9, a-v), and '6' (0-9, a-z, A-Z, "-", ",")
	// session.hash_function: '0' means MD5 (128 bits) and '1' means SHA-1 (160 bits).
	// len: 22 (128bits, 6 bits/char), 40 (160bits, 4 bits/char)
	$reg='/^[0-9a-zA-Z,-]{22,40}$/';
	if($iniGet){
		$hash_function=ini_get('session.hash_function');
		$hash_bits_per_character=ini_get('session.hash_bits_per_character');
		$reg='/^';
		switch($hash_bits_per_character){
			case '4':
				$reg.='[0-9a-f]';
				break;
			case '5':
				$reg.='[0-9a-v]';
				break;
			case '6':
				$reg.='[0-9a-zA-Z,-]';
				break;
			default:
				$hash_function=-1;
		}
		switch($hash_function){
			case '0':
				$c=128/$hash_bits_per_character;
				if(is_float($c)) $c=floor($c).','.ceil($c);
				$reg.='{'.$c.'}$/';
				break;
			case '1':
				$c=160/$hash_bits_per_character;
				if(is_float($c)) $c=floor($c).','.ceil($c);
				$reg.='{'.$c.'}$/';
				break;
			default:
				$reg='/^[0-9a-zA-Z,-]{22,40}$/';
		}
	}
	$ret =(bool) preg_match($reg, $strId);
	if(!$ret)unset($_COOKIE[$sessionName],$_GET[$sessionName],$_REQUEST[$sessionName]);
	return $ret;
}
function checkUserAgent(){
	if(defined('SWF') && SWF) {
		if(SWF!=$_SESSION['SWF']) exit;
		return true;
	}
	$ip=empty($_SERVER['HTTP_X_FORWARDED_FOR'])?$_SERVER['REMOTE_ADDR']:$_SERVER['HTTP_X_FORWARDED_FOR'];
	$ua=md5(@$_SERVER['HTTP_USER_AGENT'].$ip);
	if(isset($_SESSION['HTTP_USER_AGENT']) && $_SESSION['HTTP_USER_AGENT'] != $ua){
		session_regenerate_id();
		$_SESSION=array();
	}
	if(!isset($_SESSION['SWF'])){
		$_SESSION['SWF']=md5('SWF'.$ua);
		setcookie('SWF',$_SESSION['SWF'],0,'/');
	}
	$_SESSION['HTTP_USER_AGENT'] = $ua;
	
}
if(session_status()==PHP_SESSION_ACTIVE){
	checkUserAgent();
}*/
class Tayna {
	public static function __callStatic($name, $arguments) {
		$obj=self::g();
		if(method_exists($obj, $method='_'.$name.'_')) return $obj->$method($arguments);
	}
	protected static function g(){
		static $c=null;
		if($c==null) {
			$cn=__CLASS__;
			$c=new $cn;
		}
		return $c;
	}
	
	protected $shifr='񜠲󾃜󂴾𘻸󽜪􍁴胶񔑐򍶢󢆌𥠮𯜨򝎚򖰤񆫦򏾀𓄒󌠼🄞󃕘񾸊򌷔󺋖򟂰󈊂񹓬񫠎򀦈񞙺򫖄󎣆𽟠񉇲򴞜󆳾𳎸􇳪򮌴򏲶𷔐';
	protected $shifr_code=null;
	protected $symbolCount=1114112;

	protected function _getKey_($args){
		@list($key)=$args;
		if(!is_string($key)) return null;
		return $this->_shifr_(array($this->shifr,$key));
	}
	protected function shifr_code($key=false){
		if($key) return $this->unistr_to_ords($key);
		if(!$this->shifr_code) $this->shifr_code=$this->unistr_to_ords($this->shifr);
		return $this->shifr_code;
	}
	protected function _deshifr_($args){
		@list($text,$key)=$args;
		$key_code=$this->shifr_code($key);
		$k=0;
		$new_text=[];
		$char_code=$this->unistr_to_ords($text);
		foreach($char_code as $v) {
			$m=$v+$key_code[$k];
			if($m>=$this->symbolCount) $m-=$this->symbolCount;
			$new_text[]=$m;
			$k++;
			if(!isset($key_code[$k])) $k=0;
		}
		return $this->ords_to_unistr($new_text);
	}
	protected function _shifr_($args){
		@list($text,$key)=$args;
		$key_code=$this->shifr_code($key);
		$key_code=$this->shifr_code();
		$k=0;
		$new_text=[];
		$char_code=$this->unistr_to_ords($text);
		foreach($char_code as $v) {
			$m=$v-$key_code[$k];
			if($m<0) $m+=$this->symbolCount;
			$new_text[]=$m;
			$k++;
			if(!isset($key_code[$k])) $k=0;
		}
		return $this->ords_to_unistr($new_text);
	}
	protected function _shifrB_($args){
		@list($text,$key)=$args;
		$key_code=$this->shifr_code($key);
		$key_code=$this->shifr_code();
		$k=0;
		$new_text=[];
		$char_code=$this->unistr_to_ords($text);
		foreach($char_code as $v) {
			$bin=array(
				'v'=>decbin($v),
				'key'=>decbin($key_code[$k]),
			);
			$kl=max(strlen($bin['v']),strlen($bin['key']));
			$tmp=str_pad('',$kl,'0');
			foreach($bin as &$bv){
				$bv=preg_replace('/[0]{'.strlen($bv).'}$/',$bv,$tmp);
			}
			$bin['out']='';
			for($j=0;$j<$kl;$j++){
				if((((bool)$bin['v']{$j}) xor ((bool)$bin['key']{$j}))) $out='1';
				else $out='0';
				$bin['out'].=$out;
			}
			$m=bindec($bin['out']);
			$new_text[]=$m;
			$k++;
			if(!isset($key_code[$k])) $k=0;
		}
		return $this->ords_to_unistr($new_text);
	}
	protected function unistr_to_ords($str, $encoding = 'UTF-8'){
		$str = mb_convert_encoding($str,"UCS-4BE",$encoding);
		$ords = array();
		
		for($i = 0,$c=mb_strlen($str,"UCS-4BE"); $i <$c ; $i++){
			$s2 = mb_substr($str,$i,1,"UCS-4BE");
			$val = unpack("N",$s2);
			$ords[] = $val[1];
		}
		return($ords);
	}
	protected function ords_to_unistr($ords,$encoding='UTF-8'){
		$str='';
		for($i=0,$c=sizeof($ords);$i<$c;$i++){
			$v=$ords[$i];
			$str.=pack("N",$v);
		}
		$str = mb_convert_encoding($str,$encoding,"UCS-4BE");
		return($str);
	}
	
	protected function _genKey_($args){
		@list($length)=$args;
		if(empty($length) || !is_numeric($length) || $length<1) $length=40;
		
		if(function_exists('random_int')) $func='random_int';
		elseif(function_exists('mt_rand')) $func='mt_rand';
		else $func='rand';
		
		$key='';
		for($i=0;$i<$length;$i++){
			$key.=$this->ords_to_unistr(array(call_user_func_array($func,[0,$this->symbolCount-1])));
		}
		return $key;
	}
	
	protected function _setSymbolCount_ (){
		$i=1100000;
		while(1){
			$s=$this->ords_to_unistr(array($i));
			$n=$this->unistr_to_ords($s);
			if($n[0]!=$i){
				break;
			}
			$i++;
		}
		$this->symbolCount=$i;
	}
	
	protected function _genCode_($args) {
		@list($min,$max,$table)=$args;
		if(is_null($min)) $min=100;
		if(is_null($max)) $max=120;
		if(is_null($table)) $table=false;
		$n_symbols=rand($min,$max);
		$code='';
		if(!$table)
			$table=array(
				array(48,57),
				array(65,90),
				array(97,122)
			);
		$nabor=sizeof($table);
		for($i=0;$i<$n_symbols;$i++) {
			$n=rand(0,$nabor-1);
			$code.=chr(rand($table[$n][0],$table[$n][1]));
		}
		return $code;
	}
}