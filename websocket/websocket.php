<?php
chdir(pathinfo(__FILE__,PATHINFO_DIRNAME));
require_once 'Workerman/Autoloader.php';
use \Workerman\Worker;
use \Workerman\Lib\Timer;
chdir('../web');
//define('MySQL_p',1);
include_once '../server.php';
require_once 'admin/core.php';

//TransmitterProcessAction();

// Channel Server. 
$channel_server = new Workerman\Channel\Server('127.0.0.1', 2206);

// массив для связи соединения пользователя и необходимого нам параметра
$users = [];
$usersInOtherProcesses = [];

$inner_tcp_worker = new Worker("tcp://127.0.0.1:8001");

$inner_tcp_worker->count=1;
$inner_tcp_worker->name = 'InnerInformer';
$inner_tcp_worker->onWorkerStart = function($worker)
{
	// Channel client connect to Channel Server.
	Workerman\Channel\Client::connect('127.0.0.1', 2206);
};
$inner_tcp_worker->onMessage = function($connection, $data) {
	// Publish broadcast event to all worker processes.
	Workerman\Channel\Client::publish('system_to_users', $data);
};
$inner_tcp_worker->onConnect = function($connection){
	//тут еще надо что-то отправить, если процесс упал
	
	$connection->send(json_encode([
		'status'=>'ok',
		'currentTime'=>time(),
	]));
};

// создаём ws-сервер, к которому будут подключаться все наши пользователи
$ws_worker = new Worker("websocket://127.0.0.1:8000");
$ws_worker->count = 4;//для большего числа нужны большие махинации, т.к. дочерние процессы имеют свои независимые экземпляры переменных
$ws_worker->name = 'WebSockets';

// создаём обработчик, который будет выполняться при запуске ws-сервера
$ws_worker->onWorkerStart = function() use (&$users,&$usersInOtherProcesses,&$ws_worker/*,&$inner_tcp_worker*/)
{
	// Channel client connect to Channel Server.
	Workerman\Channel\Client::connect('127.0.0.1', 2206);
	// Subscribe broadcast event . 
	Workerman\Channel\Client::on('system_to_users', function($data)use(&$ws_worker,&$users,&$usersInOtherProcesses){
		/*foreach ($worker->connections as $connection) {
		   $connection->send($data);
		}*/
		$data = @json_decode($data,1);
		if(!$data) return;
		// отправляем сообщение пользователю по userId
		//file_put_contents('111.txt',print_r([$data,$users],1),FILE_APPEND);
		if(!isset($users[$data['reciver']['auction_id']])) return;
		if($data['command']=='close'){
			foreach($users[$data['reciver']['auction_id']] as &$connections){
				foreach($connections as &$webconnection){
					if(!empty($webconnection->websocketInfo)) cleanUsers($webconnection->websocketInfo);
					//$webconnection->close('{"info":"closed"}'."\x88");
					$webconnection->close(pack('H*', '8800'), true);
				}
				unset($webconnection);
			}
			unset($connections,$usersInOtherProcesses[$data['reciver']['auction_id']]);
			return;
		}
		if(!isset($users[$data['reciver']['auction_id']][$data['reciver']['bid_id']])) return;
		foreach($users[$data['reciver']['auction_id']][$data['reciver']['bid_id']] as &$webconnection){
			$webconnection->send($data['data']);
		}
		unset($webconnection);
	});
	
	$back=function($data)use(&$ws_worker,&$users,&$usersInOtherProcesses){
		$_data=json_decode($data,1);
		/*foreach ($worker->connections as $connection) {
		   $connection->send($data);
		}*/
		$bidders=getBidders($_data['auction']);
		
		if(isset($_data['worker answer to'])) {
			Workerman\Channel\Client::publish('user_connect_to_users'.$_data['worker answer to'], json_encode([
				'bidders'=>$bidders,
				'auction'=>$_data['auction'],
				'worker answer from'=>$ws_worker->id,
			]));
			$usersInOtherProcesses[$_data['auction']][$_data['worker answer to']]=$_data['bidders'];
		}
		if(isset($_data['worker answer from'])){
			$usersInOtherProcesses[$_data['auction']][$_data['worker answer from']]=$_data['bidders'];
		}
		if(!empty($users[$_data['auction']])){
			$_data['bidders']=$bidders;
			foreach($usersInOtherProcesses[$_data['auction']] as &$v) {
				if(!empty($v)) $_data['bidders']=array_merge($_data['bidders'],$v);
			}
			
			$_data['bidders']=array_values(array_unique($_data['bidders']));
			unset($_data['worker answer from'],$_data['worker answer to']);
			
			$data=json_encode($_data);
			foreach($users[$_data['auction']] as &$biddersList){
				foreach($biddersList as &$webconnection){
					$webconnection->send($data);
				}
				unset($webconnection);
			}
			unset($biddersList);
		}
	};
	Workerman\Channel\Client::on('user_connect_to_users', $back);
	Workerman\Channel\Client::on('user_connect_to_users'.$ws_worker->id, $back);
	
	//очистка соединений по окончанию аукциона
	Timer::add(60,function() use (&$ws_worker,&$users){
		foreach ($ws_worker->connections as &$connection) {
			if($connection->lastActive<time()-120
				|| (!empty($connection->websocketInfo['endTime']) && $connection->websocketInfo['endTime']<time()-10)) {
			   //echo 'auto close',PHP_EOL;
			   $connection->close(pack('H*', '8800'), true);
			}
		}
	});
};

$ws_worker->onConnect = function($connection) use (&$users,&$ws_worker)
{
	$connection->onWebSocketConnect = function($connection) use (&$users,&$ws_worker)
	{
		// при подключении нового пользователя сохраняем get-параметр, который же сами и передали со страницы сайта
		setServer();
		$req=explode('?',$_SERVER['REQUEST_URI']);
		S::g()->setQuery($req[0]);
		$_SERVER['HTTP_REFERER']=$_SERVER['HTTP_ORIGIN'].$req[0].'?'.$_SERVER['QUERY_STRING'];
		$auction=false;
		//file_put_contents('111.txt',print_r([$_SERVER,$_GET],1));
		$auth=Plugins::Auctions_users()->bidAuth($auction);
		if(!$auction) {
			$connection->send(json_encode([
				'error'=>[
					'code'=>'403.101',
					'message'=>'auction not available',
				],
			]));
			$connection->close(pack('H*', '8800'), true);
			return;
		}
		if(time()<$auction['startTime']) {
			$connection->send(json_encode([
				'error'=>[
					'code'=>'403.102',
					'message'=>'auction not started',
				],
			]));
			$connection->close(pack('H*', '8800'), true);
			return;
		}
		if(time()>$auction['endTime']) {
			$connection->send(json_encode([
				'error'=>[
					'code'=>'403.103',
					'message'=>'auction ended',
				],
			]));
			$connection->close(pack('H*', '8800'), true);
			return;
		}
		
		$connection->lastActive=time();
		if(!$auth) $_GET['bid_id']='guest';
		
		saveLog([
			'command'=>'ws connect',
			'status'=>'ok',
			'auction'=>[
				'id'=>$auction['id'],
				'tender_id'=>$auction['tender_id'],
				'lot_id'=>$auction['lot_id'],
			],
			'time'=>nanotime(),
			'bid_id'=>$_GET['bid_id'],
			'_SERVER'=>$_SERVER,
		],(string)$ws_worker->id,$ws_worker->name.date('/Y/m/d'));
		
		
		//file_put_contents('111.txt',print_r([$_SERVER,$_GET,$_POST,file_get_contents('php://input'),S::g()->query],1));
		//$users[$_GET['user']] = $connection;
		$users[$auction['id']][$_GET['bid_id']][] = &$connection;
		$user=$connection->websocketInfo=[
			'auction'=>$auction['id'],
			'endTime'=>$auction['endTime'],
			'bidder'=>$_GET['bid_id'],
			'index'=>sizeof($users[$auction['id']][$_GET['bid_id']])-1,
			'_SERVER'=>$_SERVER,
		];
		if(!empty($users[$user['auction']])){
			$msg=false;
			if($bidders=getBidders($user['auction'])) $msg=json_encode([
				'bidders'=>$bidders,
				'auction'=>$user['auction'],
				'worker answer to'=>$ws_worker->id,
			]);
			if($msg){
				Workerman\Channel\Client::publish('user_connect_to_users', $msg);
			}
		}
		//if($bidders=getBidders($auction['id'])) $connection->send(json_encode(['bidders'=>$bidders]));
	};
};
$ws_worker->onMessage = function($connection, $data)
{
	// Send hello $data
	$connection->send(json_encode([
		'status'=>'ok',
		'currentTime'=>($connection->lastActive=time()),
	]));
};
$ws_worker->onClose = function($connection) use(&$users,&$ws_worker)
{
	// удаляем параметр при отключении пользователя
	//$user = array_search($connection, $users);
	//unset($users[$user]);
	//file_put_contents('111.log',print_r([$connection->websocketInfo],1),FILE_APPEND);
	if(!empty($connection->websocketInfo)) {
		//echo 'closing',PHP_EOL;
		$user=$connection->websocketInfo;
		cleanUsers($user);
	
		//if(!empty($users[$user['auction']])){
			$msg=json_encode([
				'bidders'=>getBidders($user['auction']),
				'auction'=>$user['auction'],
				'worker answer from'=>$ws_worker->id,
			]);
			if($msg){
				Workerman\Channel\Client::publish('user_connect_to_users', $msg);
				/*foreach($users[$user['auction']] as &$biddersList){
					foreach($biddersList as &$webconnection){
						$webconnection->send($msg);
					}
					unset($webconnection);
				}
				unset($biddersList);*/
			}
		//}
		
		saveLog([
			'command'=>'ws connect',
			'status'=>'close',
			'auction'=>[
				'id'=>$user['auction'],
			],
			'time'=>nanotime(),
			'bid_id'=>$user['bidder'],
			'_SERVER'=>$user['_SERVER'],
		],(string)$ws_worker->id,$ws_worker->name.date('/Y/m/d'));
	}
};

if(extension_loaded('rdkafka')){
	$Transmitter = new Worker();
	$Transmitter->count = 1;
	$Transmitter->name = 'Auctions create';

	function auctions_create(&$worker){
		//setServer();
		Timer::add(10,function() use (&$worker){
			$conf = new RdKafka\Conf();

			// Set a rebalance callback to log partition assignments (optional)
			$conf->setRebalanceCb(function (RdKafka\KafkaConsumer $kafka, $err, array $partitions = null) {
				switch ($err) {
					case RD_KAFKA_RESP_ERR__ASSIGN_PARTITIONS:
						$kafka->assign($partitions);
						break;
					case RD_KAFKA_RESP_ERR__REVOKE_PARTITIONS:
						$kafka->assign(NULL);
						break;
					default:
							//throw new \Exception($err);
				}
			});

			// Configure the group.id. All consumer with the same group.id will consume
			// different partitions.
			$conf->set("group.id", "auction.module");
			$conf->set("enable.auto.commit", 0);
			$conf->set("enable.auto.offset.store", 0);

			// Initial list of Kafka brokers
			$conf->set('metadata.broker.list', KAFKA_BROKERS);

			$topicConf = new RdKafka\TopicConf();

			// Set where to start consuming messages when there is no initial offset in
			// offset store or the desired offset is out of range.
			// 'smallest': start from the beginning
			$topicConf->set('auto.offset.reset', 'smallest');
			
			// Set the configuration to use for subscribed/assigned topics
			$conf->setDefaultTopicConf($topicConf);

			$consumer = new RdKafka\KafkaConsumer($conf);

			// Subscribe to topic 'test'
			$consumer->subscribe([KAFKA_IN]);

			while (true) {
				$msg = $consumer->consume(120*1000);
				
				if($msg && $msg->err==RD_KAFKA_RESP_ERR_NO_ERROR){
					if($res=Plugins::Auctions_cdb()->create([],$in=trim($msg->payload))){
						//отправка результата о создании аукциона
						$send=false;
						$in=@json_decode($in,1);
						if(isset($in['id']) && !isset($res['id'])) $res['id']=$in['id'];
						if(isset($res['error'])) {
							$res['errors'][]=$res['error'];
							unset($res['error']);
							$send=true;
						}
						$res['version']='0.0.1';
						if($send) sendKafkaMsg(json_encode($res));
						
						$consumer->commit($msg);
						
						saveLog([
							'command'=>'kafka create auction',
							'result'=>$res,
							'time'=>nanotime(),
							'message'=>$msg,
						],(string)$worker->id,$worker->name.date('/Y/m/d'));
					}
				}
				else break;
			}
			unset($conf,$consumer,$msg,$res,$in);
			
			auctions_create($worker);
		},[],false);
	}
	
	$Transmitter->onWorkerStart = function($worker){
		auctions_create($worker);
		
		//auctions_restart($worker);
	};
}
$archiving = new Worker();
$archiving->count = 1;
$archiving->name = 'Auctions archiver';
function auctions_archive(&$worker){
	//setServer();
	Timer::add(60,function() use (&$worker){
		//Результаты по аукционам
		$time=time()-5;
		$auctions=[];
		Plugins::Auctions_history()->move2history(function(&$row) use (&$auctions){
			$auction=Plugins::Auctions_users()->getAuction($row['tender_id'],$row['lot_id']);
			$auctions[$auction['tender_id']]['lots'][]=[
				'auction'=>&$auction,
				'source'=>Plugins::Auctions_users()->viewSource($auction),
			];
			return false;
		},$time);
		Plugins::Auctions_history()->move2history(function(&$row) use (&$auctions){
			if(empty($auctions[$row['tender_id']])) return false;
			if(!empty($auctions[$row['tender_id']]['kafka'])) return true;
			
			/*
			{
			  "tender": {
				"id": "ocds-prefix-timstamp-postfix-tender",
				"electronicAuctions": {
				  "details": [
					{
					  "id": "",
					  "relatedLot": "",
					  "auctionPeriod": {
						"startDate": "",
						"endDate": ""
					  },
					  "electronicAuctionProgress": [
						{
						  "id": "1",
						  "period": {},
						  "breakdown": [
							{
							  "tenderer": "1",
							  "dateMet": "2018-09-20T09:03:18.017770+00:00",
							  "value": {
								"amount": "",
								"currency": ""
							  }
							},
							{
							  "tenderer": "2",
							  "dateMet": "2018-09-20T09:03:18.017770+00:00",
							  "value": {
								"amount": "",
								"currency": ""
							  }
							},
							{
							  "tenderer": "3",
							  "dateMet": "2018-09-20T09:03:18.017770+00:00",
							  "value": {
								"amount": "",
								"currency": ""
							  }
							}
						  ]
						}
					  ]
					}
				  ]
				}
			  }
			}
			*/
			
			$out=[
				'id'=>[],
				'command'=>'auctionsEnd',
				'data'=>[
					'tender'=>[
						'id'=>$row['tender_id'],
						'electronicAuctions'=>[
							'details'=>[]
						]
					]
				],
				'version'=>'0.0.1'
			];
			$results=&$out['data']['tender']['electronicAuctions']['details'];
			foreach($auctions[$row['tender_id']]['lots'] as &$v){
				$out['id'][]=$v['auction']['id'];
				$data=[
					'id'=>$v['auction']['id'],
					'relatedLot'=>$v['auction']['lot_id'],
					'auctionPeriod'=>[
						'startDate'=>gmdate('c',$v['auction']['startTime']),
						'endDate'=>gmdate('c',$v['auction']['endTime']),
					],
					'electronicAuctionProgress'=>[],
				];
				
				if(!isset($out['data']['tender']['auctionPeriod']['startDate']) || $v['auction']['startTime']<strtotime($out['data']['tender']['auctionPeriod']['startDate'])) $out['data']['tender']['auctionPeriod']['startDate']=$data['auctionPeriod']['startDate'];
				if(!isset($out['data']['tender']['auctionPeriod']['endDate']) || $v['auction']['endTime']>strtotime($out['data']['tender']['auctionPeriod']['endDate'])) $out['data']['tender']['auctionPeriod']['endDate']=$data['auctionPeriod']['endDate'];
				
				$stepTime=Plugins::Auctions_users()->getStepTime($v['auction']);
				for($i=1;$i<=$v['auction']['source']['settings']['bidsSteps'];$i++){
					$start=$v['auction']['startTime']+($i-1)*$stepTime+$v['auction']['source']['settings']['bidStepPause'];
					$end=$start+$stepTime-$v['auction']['source']['settings']['bidStepPause'];
					
					$breakdown=[];
					foreach($v['auction']['source']['data']['bids'] as &$bid){
						/*
						"tenderer": "1",
							  "dateMet": "2018-09-20T09:03:18.017770+00:00",
							  "value": {
								"amount": "",
								"currency": ""
							  }
						*/
						$setBid=false;
						$fromStep=false;
						$fromSteps=[];
						if(!empty($v['source']['auctionsSteps'][$i])) $fromSteps=[$i];
						if($i==1) $fromSteps[]=0;
						if(/*$fromStep!==false*/sizeof($fromSteps)) {
							foreach($fromSteps as $j){
								foreach($v['source']['auctionsSteps'][$j] as &$bid_step){
									if($bid_step['id']==$bid['id']){
										$fromStep=$j;
										$setBid=$bid_step;
										break 2;
									}
								}
								unset($bid_step);
							}
							if($fromStep===0) $setBid['ctime']=$end;
						}
						if(!$setBid){
							$setBid=[
								'value'=>end($data['electronicAuctionProgress'])['breakdown'][$bid['__index__']]['value']['amount'],
								'ctime'=>$end,
							];
						}
						$setBid['value']=round($setBid['value'],2);
						$breakdown[]=[
							'relatedBid'=>$bid['id'],
							'dateMet'=>gmdate('c',$setBid['ctime']),
							'value'=>[
								'amount'=>$setBid['value'],
							],
						];
						if($i==$v['auction']['source']['settings']['bidsSteps']){
							$data['electronicAuctionResult'][]=[
								'relatedBid'=>$bid['id'],
								'value'=>[
									'amount'=>$setBid['value'],
								],
							];
						}
					}
					unset($bid,$setBid,$fromStep);
					$data['electronicAuctionProgress'][]=[
						'id'=>(string)$i,
						'period'=>[
							'startDate'=>gmdate('c',$start),
							'endDate'=>gmdate('c',$end),
						],
						'breakdown'=>$breakdown,//по ставкам
					];
					
				}
				
				$results[]=$data;
			}
			unset($v,$results,$data,$breakdown);
			$out['id']=implode('.',$out['id']);
			if(sendKafkaMsg(json_encode($out))) {
				$auctions[$row['tender_id']]['kafka']=true;
				return true;
			}
			return false;
		},$time);
		auctions_archive($worker);
	},[],false);
};
$archiving->onWorkerStart = function($worker){
	auctions_archive($worker);
};

$cleaner = new Worker();
$cleaner->count = 1;
$cleaner->name = 'Auctions cache cleaner';
function auctions_cleaner(&$worker){
	//setServer();
	$getRelativePath=function ($thispath,$rootpath=false){
		if(!$rootpath) $rootpath=$_SERVER['DOCUMENT_ROOT'];
		$thispath=pathinfo($thispath);
		$thispath=realpath($thispath['dirname']).'/'.$thispath['basename'];
		$thispath=explode('/',str_replace('\\','/',$thispath));
		$rootpath=pathinfo($rootpath);
		$rootpath=realpath($rootpath['dirname']).'/'.$rootpath['basename'];
		$rootpath=explode('/',str_replace('\\','/',$rootpath));
		$dotted = 0;
		$rc=count($rootpath);
		$tc=count($thispath);
		$relpath=[];
		for ($i = 0; $i < $rc; $i++) {
			if ($i >= $tc) {
				$dotted++;
			}
			elseif ($thispath[$i] != $rootpath[$i]) {
				$relpath[] = $thispath[$i]; 
				$dotted++;
			}
		}
		if($dotted>1) return false;
		return str_repeat('../', $dotted) . implode('/', array_merge($relpath, array_slice($thispath, $rc)));
	};
	$rrmdir=function ($dir,$timeRemove=false/*,&$removes=[]*/) use(&$getRelativePath,&$rrmdir) {
		if (!is_dir($dir)) return;
		$objects = scandir($dir);
		$remove=true;
		foreach ($objects as $object) {
			if ($object != "." && $object != "..") {
				$object=$dir."/".$object;
				if (filetype($object) == "dir") {
					if(!$rrmdir($object,$timeRemove/*,$removes*/)) $remove=false;
				}
				else {
					if(!$timeRemove || $timeRemove>filemtime($object)) {
						//$removes[]=sha1($getRelativePath($object));
						unlink($object);
					}
					else $remove=false;
				}
			}
		}
		reset($objects);
		if($remove) {
			//$removes[]=sha1($getRelativePath($dir));
			return rmdir($dir);
		}
		else return false;
	};
	
	Timer::add(3600,function() use (&$worker,&$rrmdir){
		//Очистка кеша json файлов завершенных аукционов
		clearstatcache();
		$rrmdir(Plugins::Auctions()->getCacheRoot(),strtotime('-6 month'));
		$rrmdir('../logs/WebSockets',strtotime('-6 month'));
		$rrmdir('../logs/Auctions create',strtotime('-6 month'));
		auctions_cleaner($worker);
	},[],false);
};
$cleaner->onWorkerStart = function($worker){
	auctions_cleaner($worker);
};

function sendKafkaMsg($data){
	$kafka=false;
	if(empty($kafka)){
		$kafka['rk'] = new RdKafka\Producer();
		//$kafka['rk']->setLogLevel(LOG_DEBUG);
		$kafka['rk']->addBrokers(KAFKA_BROKERS);

		$kafka['topic'] = $kafka['rk']->newTopic(KAFKA_OUT);
	}

	$kafka['topic']->produce(RD_KAFKA_PARTITION_UA, 0, $data);
	$kafka['rk']->poll(0);
	
	while ($kafka['rk']->getOutQLen() > 0) {
		$kafka['rk']->poll(50);
	}
	unset($kafka);
	return true;
}

function getBidders($auction_id){
	global $users;
	
	if(empty($users[$auction_id])) return false;
	foreach($users[$auction_id] as &$connections){
		foreach($connections as &$webconnection){
			if(!empty($webconnection->lastActive) && $webconnection->lastActive<time()-120) {
				$webconnection->close(pack('H*', '8800'), true);
				if(!empty($webconnection->websocketInfo)) cleanUsers($webconnection->websocketInfo);
			}
			
		}
		unset($webconnection);
	}
	unset($connections);
	if(empty($users[$auction_id])) return false;
	return array_keys($users[$auction_id]);
}
function cleanUsers($user){
	global $users;
	
	unset($users[$user['auction']][$user['bidder']][$user['index']]);
	if(empty($users[$user['auction']][$user['bidder']])) unset($users[$user['auction']][$user['bidder']]);
	if(empty($users[$user['auction']])) unset($users[$user['auction']]);
}
function saveLog($data,$file,$subFolder=''){
	if(is_array($data)) $data=json_encode($data);
	if(!is_string($data)) return false;
	
	$logdir='../logs';
	if(!empty($subFolder)){
		$subFolder=explode('/',$subFolder);
		foreach($subFolder as $v){
			$logdir.='/'.$v;
			if(!is_dir($logdir)) mkdir($logdir);
		}
	}
	
	$fileName=$logdir.'/'.$file.'.log';
	$data.=PHP_EOL;
	
	return @file_put_contents($fileName,$data,FILE_APPEND);
}
function nanotime(){
	list($usec, $sec) = explode(" ", microtime());
	return $sec.substr($usec,1);
}

Worker::$pidFile=__FILE__.'.pid';
// Run worker
Worker::runAll();