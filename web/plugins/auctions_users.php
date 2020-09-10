<?php
include_once 'auctions.php';
class Auctions_users extends Auctions{
	public function view($prm=[]){
		if(CONTENT_TYPE!='application/json') {
			return;
		}
		$options=array(
			'tender_id'=>'1',
			'lot_id'=>'2',
		);
		extendData($prm,$options);
		//$db = DB::g();
		
		$p=S::g();
		if(empty($p->query[$prm['tender_id']]) || empty($p->query[$prm['lot_id']])) return ERROR(404);
		$auction=$this->getAuction($p->query[$prm['tender_id']],$p->query[$prm['lot_id']]);
		if(!$auction) return ERROR(404);
		$isEnd=time()>=$auction['endTime'];
		
		$p->ajaxRes=$this->viewSource($auction);
		if(time()>=$auction['startTime'] && !$isEnd){
			if($p->ajaxRes['is_bidder']=$this->bidInit($auction)){
				$bid=$this->bidAuth();
				$p->ajaxRes['lastBid']=$this->getLastBid($auction,$bid);
			}
			
			header('X-Sign: '.substr(hash('sha384',Tayna::shifrB(microtime())),0,40));
		}
		/*$p->ajaxRes=$auction;
		unset($p->ajaxRes['source']['data']['bids']);
		if(time()>=$auction['startTime']){
			$res=$this->getAuctionBids($auction);
			$p->ajaxRes['auctionsSteps']=[];
			//$maxStep=floor(time()-$auction['startTime'])/$this->bidStepTime;
			$stepTime=$this->getStepTime($auction);
			while($row=$res->fetch_assoc()){
				$step=$row['ctime']-$auction['startTime'];
				if($step<0) $step=0;
				else {
					$step=ceil($step/$stepTime);
				}
				//if($step>$maxStep) break;
				if($step && !$isEnd){
					$start=$auction['startTime']+($step-1)*$stepTime+($auction['source']['data']['bids'][$row['bid_id']]['__index__']+1)*($auction['source']['settings']['bidStepPause']+$auction['source']['settings']['bidStepTime']);
					if($start>time()) break;
				}
				$p->ajaxRes['auctionsSteps'][$step][$row['bid_id']]=[
					'id'=>$row['bid_id'],
					'value'=>$row['value'],
					'ctime'=>$row['ctime'],
				];
				//$p->ajaxRes['source']['data']['bids']
			}
			foreach($p->ajaxRes['auctionsSteps'] as &$v){
				$v=array_values($v);
			}
			unset($v);
			
			if($p->ajaxRes['is_bidder']=$this->bidInit($auction)){
				$bid=$this->bidAuth();
				$p->ajaxRes['lastBid']=$this->getLastBid($auction,$bid);
			}
		}*/
		
		if($isEnd) {
			unset($p->ajaxRes['is_bidder']);
			$this->saveCache($p->ajaxRes);
		}
	}
	public function viewSource(&$auction){
		$ret=$auction;
		$bids=&$ret['source']['data']['bids'];
		unset($ret['source']['data']['bids']);
		$isEnd=time()>=$auction['endTime'];
		if(time()>=$auction['startTime']){
			$res=$this->getAuctionBids($auction);
			$ret['auctionsSteps']=[];
			//$maxStep=floor(time()-$auction['startTime'])/$this->bidStepTime;
			$stepTime=$this->getStepTime($auction);
			$bidsIndexes=[];
			$lastStep=0;
			
			if(!$isEnd){
				$nowStep=(time()-$auction['startTime']);
				if($nowStep<0) $nowStep=0;
				else $nowStep=ceil($nowStep/$stepTime);
				$index=0;
				if($nowStep>0){
					$index=floor((time()-($auction['startTime']+($nowStep-1)*$stepTime))/($auction['source']['settings']['bidStepPause']+$auction['source']['settings']['bidStepTime']));
					$stopTime=$auction['startTime']+($nowStep-1)*$stepTime+($index)*($auction['source']['settings']['bidStepPause']+$auction['source']['settings']['bidStepTime']);
				}
			}
			
			while($row=$res->fetch_assoc()){
				$step=$row['ctime']-$auction['startTime'];
				if($step<0) $step=0;
				else {
					$step=ceil($step/$stepTime);
				}
				//if($step>$maxStep) break;
				
				if($step && !$isEnd){
					/*if($lastStep!=$step){
						//вычисляем новые порядковые номера ставок
						if(!$lastStep) $bidsIndexes['steps'][$lastStep]=$ret['auctionsSteps'][0];
						else $bidsIndexes['steps'][$lastStep]=$bidsIndexes['steps'][$lastStep-1];
						
						$bidsIndexes['values']=$bidsIndexes['pendingTime']=$bidsIndexes['index']=[];
						foreach($bidsIndexes['steps'][$lastStep] as $bid_id=>&$bv){
							$bidsIndexes['values'][$bid_id]=($bv['value']=(@$ret['auctionsSteps'][$lastStep][$bid_id]['value']?:$bv['value']));
							$bidsIndexes['pendingTime'][$bid_id]=$ret['auctionsSteps'][0][$bid_id]['pendingTime'];
							$bidsIndexes['index'][$bid_id]=$bids[$bid_id]['__index__'];
						}
						unset($bv);
						array_multisort($bidsIndexes['values'],SORT_REGULAR,SORT_DESC,$bidsIndexes['pendingTime'],SORT_REGULAR,SORT_DESC,$bidsIndexes['index'],SORT_NUMERIC,SORT_ASC,$bidsIndexes['steps'][$lastStep]);
						
						$i=0;
						$d=$bidsIndexes['steps'][$lastStep];
						$bidsIndexes['steps'][$lastStep]=[];
						foreach($d as &$bv){
							$bv['__index__']=$i;
							$bidsIndexes['steps'][$lastStep][$bv['id']]=$bv;
							$i++;
						}
						unset($bv,$d);
						
						$lastStep=$step;
					} */
					
					
					//$start=$auction['startTime']+($step-1)*$stepTime+($bidsIndexes['steps'][$step-1][$row['bid_id']]['__index__']+1)*($auction['source']['settings']['bidStepPause']+$auction['source']['settings']['bidStepTime']);
					//$start=$auction['startTime']+($step)*$stepTime-$auction['source']['settings']['bidStepPause'];
					//if($start>time()) break;
					if($row['ctime']>$stopTime) break;
				}
				$ret['auctionsSteps'][$step][$row['bid_id']]=[
					'id'=>$row['bid_id'],
					'value'=>$row['value'],
					'ctime'=>$row['ctime'],
				];
				if(!$step) $ret['auctionsSteps'][$step][$row['bid_id']]['pendingTime']=@$bids[$row['bid_id']]['pendingDate']?strtotime($bids[$row['bid_id']]['pendingDate']):0;
				//$p->ajaxRes['source']['data']['bids']
			}
			foreach($ret['auctionsSteps'] as &$v){
				$v=array_values($v);
			}
			unset($v);
		}
		if(time()>=$auction['endTime']){
			$ret['isEnded']=true;
			unset($ret['is_bidder']);
		}
		return $ret;
	}
	public function getStepTime(&$auction){
		return ($auction['source']['settings']['bidStepTime']+$auction['source']['settings']['bidStepPause'])*sizeof($auction['source']['data']['bids']);
	}
	public function getCurrentTime(){
		$p=S::g();
		$p->ajaxRes['currentTime']=time();
	}
	protected function bidInit(&$auction){
		if(!$this->bidAuth($auction)) return false;
		return true;
	}
	public function bidAuth(&$auction=false){
		$url=false;
		if(!$auction){
			if(empty($_SERVER['HTTP_REFERER'])) return false;
			$url=parse_url($_SERVER['HTTP_REFERER']);
			$qs=explode('/',$url['path']);
			if(empty($qs[2]) || empty($qs[3])) return false;
			$auction=$this->getAuction($qs[2],$qs[3]);
			if(!$auction) return false;
		}
		if(php_sapi_name()=='cli') unset($this->cache[__FUNCTION__]);
		if(isset($this->cache[__FUNCTION__][$auction['id']])) return $this->cache[__FUNCTION__][$auction['id']];
		$this->cache[__FUNCTION__][$auction['id']]=false;
		if(empty($_SERVER['HTTP_REFERER'])) return false;
		if(time()<$auction['startTime'] || time()>$auction['endTime']) return false;
		if(!$url) $url=parse_url($_SERVER['HTTP_REFERER']);
		if(empty($url['query'])) return false;
		$check=[
			'host'=>$_SERVER['HTTP_HOST'],
			'path'=>'/'.S::g()->query[0].'/'.$auction['tender_id'].'/'.$auction['lot_id'],
		];
		foreach($check as $k=>$v){
			if(@$url[$k]!=$v) return false;
		}
		$query=[];
		parse_str($url['query'],$query);
		if(empty($query['bid_id']) 
		|| !is_string($query['bid_id']) 
		|| empty($query['sign']) 
		|| !is_string($query['sign']) 
		|| empty($auction['source']['data']['bids'][$query['bid_id']])
		|| hash('sha256',Tayna::shifrB($query['sign']))!=$auction['source']['data']['bids'][$query['bid_id']]['sign']
		) return false;
		return $this->cache[__FUNCTION__][$auction['id']]=&$auction['source']['data']['bids'][$query['bid_id']];
	}
	protected function isCanBid(&$auction){
		if(!($bid=$this->bidAuth($auction))) return false;
		
		//проверяем мой ли шаг
		$stepTime=$this->getStepTime($auction);
		$step=floor((time()-$auction['startTime'])/$stepTime);
		//print_R([$step]);
		if($step<0) return false;
		
		$roundStart=$auction['startTime']+$step*$stepTime;
		$bidIndex=$auction['source']['data']['bids'][$bid['id']]['__index__'];
		
		if($step){
			//вычисляем новые порядковые номера ставок
			if(!isset($this->cache[__FUNCTION__])){
				$db = DB::g();
				$sql='select * from '.DB_PREF.'auctions_bids where auction_id="'.$auction['id'].'" and ctime<'.$roundStart.' order by id asc;';
				
				$res=$db->query($sql);
				$bids=&$auction['source']['data']['bids'];
				$bidsIndexes=[];
				$lastStep=0;
				$ret=[];
				while($row=$res->fetch_assoc()){
					$_step=$row['ctime']-$auction['startTime'];
					if($_step<0) $_step=0;
					else {
						$_step=ceil($_step/$stepTime);
					}
					/*if($lastStep!=$_step){
						for($i=$_step;$i<=$auction['source']['settings']['bidsSteps'];$i++){
							$ret['auctionsSteps'][$i]=$ret['auctionsSteps'][$lastStep];
						}
						//$ret['auctionsSteps'][$_step]=$ret['auctionsSteps'][$lastStep];
						
						$lastStep=$_step;
					}*/
					
					$ret['auctionsSteps'][$_step][$row['bid_id']]=[
						'id'=>$row['bid_id'],
						'value'=>$row['value'],
						'ctime'=>$row['ctime'],
						'pendingTime'=>@$bids[$row['bid_id']]['pendingDate']?strtotime($bids[$row['bid_id']]['pendingDate']):0,
					];
				}
				for($i=1;$i<=$auction['source']['settings']['bidsSteps'];$i++){
					foreach($ret['auctionsSteps'][$i-1] as $k=>$v){
						if(!isset($ret['auctionsSteps'][$i][$k])) $ret['auctionsSteps'][$i][$k]=$v;
					}
				}
				foreach($ret['auctionsSteps'] as $_step=>&$data){
					if(!$_step) continue;
					$bidsIndexes['values']=$bidsIndexes['pendingTime']=$bidsIndexes['index']=[];
					$bidsIndexes['steps'][$_step]=$data;
					foreach($bidsIndexes['steps'][$_step] as $bid_id=>&$bv){
						$bidsIndexes['values'][$bid_id]=$bv['value'];
						$bidsIndexes['pendingTime'][$bid_id]=$bv['pendingTime'];
						$bidsIndexes['index'][$bid_id]=$bids[$bid_id]['__index__'];
					}
					unset($bv);
					array_multisort($bidsIndexes['values'],SORT_REGULAR,SORT_DESC,$bidsIndexes['pendingTime'],SORT_REGULAR,SORT_DESC,$bidsIndexes['index'],SORT_NUMERIC,SORT_ASC,$bidsIndexes['steps'][$_step]);
					
					$i=0;
					$d=$bidsIndexes['steps'][$_step];
					$bidsIndexes['steps'][$_step]=[];
					foreach($d as &$bv){
						$bv['__index__']=$i;
						$bidsIndexes['steps'][$_step][$bv['id']]=$bv;
						$i++;
					}
					unset($bv,$d);
				}
				unset($data);
				
				$this->cache[__FUNCTION__]=&$bidsIndexes;
				//file_put_contents('111.txt',print_r([$bidsIndexes],1),FILE_APPEND);
				unset($bidsIndexes,$ret);
			}
			$bidIndex=$this->cache[__FUNCTION__]['steps'][$step][$bid['id']]['__index__'];
		}
		
		$start=$roundStart+$bidIndex*($auction['source']['settings']['bidStepPause']+$auction['source']['settings']['bidStepTime'])+$auction['source']['settings']['bidStepPause'];
		$t=time();
		if($t<$start || $t>($start+$auction['source']['settings']['bidStepTime'])) return false;
		//$bid['__step__']=$step;
		$bid['__stepStart__']=$start;
		return $bid;
	}
	public function bid($prm=[]){
		/*$options=array(
		);
		extendData($prm,$options);*/
		$auction=false;
		$this->getCurrentTime();
		if(!($bid=$this->isCanBid($auction))) return ERROR(403,'',[
			'code'=>'403.101',
			'message'=>'bid is not available',
		]);
		if(empty($_REQUEST['data']['value']) 
		|| !is_string($_REQUEST['data']['value'])
		|| !preg_match('/^[0-9]+(\.[0-9]{1,2})?$/',$_REQUEST['data']['value'])
		) return ERROR(422,'',[
			'code'=>'422.101',
			'message'=>'bid value is wrong',
		]);
		//$newValue=round((double)$_REQUEST['data']['value'],2);
		//$newValue=bcadd(0,$_REQUEST['data']['value'],2);
		if(bccomp($_REQUEST['data']['value'],0,2)!=1) return ERROR(422,'',[
			'code'=>'422.102',
			'message'=>'bid value is null',
		]);
		$db = DB::g();
		$p=S::g();
		$db->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
		$lastBids=$this->getLastBid($auction,$bid);
		$lastBid=$lastBids[0];
		if(!empty($bid['__stepStart__'])){
			foreach($lastBids as &$v){
				if($bid['__stepStart__']>$v['ctime']) $lastBid=$v;
				else break;
			}
			unset($v);
		}
		else {
			$lastBid=end($lastBids);
		}
		$newValue=bcadd($_REQUEST['data']['value'],'0',2);
		if(bccomp($newValue,bcsub($lastBid['value'],bcadd($auction['source']['data']['tender']['lot']['minStep'],'0',2),2),2)==1 && bccomp($newValue,$lastBid['value'],2)!=0){
			return ERROR(422,'',[
				'code'=>'422.103',
				'message'=>'bid minStep is wrong',
			]);
		}
		else {
			$dataBid=[
				'bid_id'=>$bid['id'],
				'auction_id'=>$auction['id'],
				'tender_id'=>$auction['tender_id'],
				'value'=>$newValue,
				'ctime'=>$_SERVER['REQUEST_TIME'],
				'ip'=>@$_SERVER['HTTP_X_FORWARDED_FOR']?:$_SERVER['REMOTE_ADDR'],
				'user_agent'=>$_SERVER['HTTP_USER_AGENT'],
			];
			$db->query('insert into '.DB_PREF.'auctions_bids set '.$db->getReplaceFields($dataBid).';');
			
			if($db->insert_id) {
				$p->ajaxRes['bid']=$db->insert_id;
				$lastBid['value']=$newValue;
				$lastBid['ctime']=time();
				$lastBid['ip']=$dataBid['ip'];
				//$p->ajaxRes['lastBid']=$lastBid;
				$p->ajaxRes['lastBid']=$this->getLastBid($auction,$bid);
				$this->bidersInform($auction,$bid,'sendmsg',json_encode([
					'lastBid'=>$p->ajaxRes['lastBid'],//тут список последних ставок
					'currentTime'=>time(),
				]));
			}
			else return ERROR(500,'',[
				'code'=>'500.101',
				'message'=>'bid add error',
			]);
		}
		if(!$db->commit()) return ERROR(500,'',[
			'code'=>'500.102',
			'message'=>'bid add error',
		]); 
	}
	protected function getLastBid(&$auction,&$bid){
		$db = DB::g();
		$res=$db->query('select value,ctime,ip from '.DB_PREF.'auctions_bids where auction_id="'.$auction['id'].'" and bid_id="'.$db->real_escape_string($bid['id']).'" order by id asc;');
		$ret=[];
		while($row=$res->fetch_assoc()){
			$row['__index__']=$bid['__index__'];
			$ret[]=$row;
		}
		return $ret;
	}
	public function getAuction($tender_id,$lot_id){
		$db = DB::g();
		$sql='select * from '.DB_PREF.'auctions where tender_id="'.$db->real_escape_string($tender_id).'" and lot_id="'.$db->real_escape_string($lot_id).'"';
		$sql.=PHP_EOL.'union select * from '.DB_PREF.'auctions_history where tender_id="'.$db->real_escape_string($tender_id).'" and lot_id="'.$db->real_escape_string($lot_id).'" order by id desc;';
		$res=$db->query($sql);
		if(!$res->num_rows) return false;
		$res=$res->fetch_assoc();
		$res['source']=@json_decode($res['source'],1);
		return $res;
	}
	protected function getAuctionBids(&$auction){
		$db = DB::g();
		$sql='select * from '.DB_PREF.'auctions_bids where auction_id="'.$auction['id'].'" order by id asc;';
		
		$res=$db->query($sql);
		if(!$res->num_rows){
			$sql='select * from '.DB_PREF.'auctions_bids_history where auction_id="'.$auction['id'].'" order by id asc;';
			$res=$db->query($sql);
		}
		
		return $res; 
	}
	protected function saveCache(&$res){
		$fileName=$this->getCacheFilename($res,1);
		return @file_put_contents($fileName,json_encode($res));
	}
	
	public function bidersInform(&$auction,&$bid,$command='sendmsg',$data=false){
		$localsocket = 'tcp://127.0.0.1:8001';
		// соединяемся с локальным tcp-сервером
		$instance = @stream_socket_client($localsocket);
		if(!$instance) return false;
		//stream_set_blocking($instance,false);
		// отправляем сообщение
		$send=[
			'command'=>$command,//sendmsg, close
			'reciver'=>[
				'auction_id' => $auction['id'],//auction_id  
				'bid_id' => $bid['id'],//bid_id
			],
		];
		if($data) $send['data']=$data;
		$r=fwrite($instance, json_encode($send)  . "\n");
		fclose($instance);
		return $r;
	}
}