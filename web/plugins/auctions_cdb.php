<?php
include_once 'auctions.php';
class Auctions_cdb extends Auctions {
	public function create($prm=[],$data=false){
		/*$options=array(
			
		);
		extendData($prm,$options);*/
		
		if(!$data) $data=file_get_contents('php://input');
		if(empty($data)) return ERROR(403,'',[
			'error'=>[
				'code'=>'403.001',
				'message'=>'input data is empty',
			],
		]);
		if(php_sapi_name()!='cli'){
			if(API_CHECK && (!API_IPS_JSON || !in_array($_SERVER['REMOTE_ADDR'],API_IPS_JSON))) return ERROR(403,'',[
				'error'=>[
					'code'=>'403.002',
					'message'=>'REMOTE_ADDR is wrong',
				],
			]);
			if(!$this->checkSign($data,@$_GET['sign'])) return ERROR(403,'',[
				'error'=>[
					'code'=>'403.003',
					'message'=>'sign is wrong',
				],
			]);
		}
		else {
			$_SERVER['REQUEST_TIME']=time();
		}
		$data=@json_decode($data,1);
		if(empty($data['data'])) return ERROR(403,'',[
			'error'=>[
				'code'=>'403.004',
				'message'=>'input json data is empty',
			],
		]);
		
		if(empty($data['data']['tender']['lots'])) return ERROR(403,'',[
			'error'=>[
				'code'=>'403.005',
				'message'=>'input lots is empty',
			],
		]);
		if(empty($data['data']['bids'])) return ERROR(403,'',[
			'error'=>[
				'code'=>'403.006',
				'message'=>'input bids is empty',
			],
		]);
		$db = DB::g();

		$res=[
			'data'=>[
				'tender'=>[
					'id'=>$data['data']['tender']['id'],
					'lots'=>[],
				]
			]
		];
		
		$tender_id=$db->real_escape_string($data['data']['tender']['id']);
		$clean=true;
		//проверяем аукцион в рабочем состоянии
		if(!empty($data['id'])){
			$existAuction=$db->query(
				'select id from '.DB_PREF.'auctions 
					where tender_id="'.$tender_id.'"
					and source->>"$.id"="'.$db->real_escape_string($data['id']).'"
				union
				select id from '.DB_PREF.'auctions_history 
					where tender_id="'.$tender_id.'"
					and source->>"$.id"="'.$db->real_escape_string($data['id']).'";'
			);
			if($existAuction->num_rows){
				$clean=false;
				$res['exists']=true;
				return $res;
			}
		}
		
		if($clean){
			$db->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
			$db->query('delete from '.DB_PREF.'auctions where tender_id="'.$tender_id.'";');
			$db->query('delete from '.DB_PREF.'auctions_bids where tender_id="'.$tender_id.'";');
			$db->commit();
		}
				
		$sort=true;
		$indexes=[];
		$dates=[];
		$values=[];
		foreach($data['data']['bids'] as $k=>&$b){
			if(empty($b['pendingDate'])) {
				$sort=false;
				break;
			}
			$indexes[$k]=$k;
			$dates[$k]=strtotime($b['pendingDate']);
			preg_match('/\.([0-9]+)/',$b['pendingDate'],$time);
			if(!empty($time[1])) $dates[$k]=(double) ($dates[$k].'.'.$time[1]);
			
			$values[$k]=(double) $b['value'];
			if(!is_string($b['value'])) $b['value']=sprintf("%.2f",$b['value']);
		}
		unset($b);
		if($sort) array_multisort($values,SORT_REGULAR,SORT_DESC,$dates,SORT_REGULAR,SORT_DESC,$indexes,SORT_NUMERIC,SORT_DESC,$data['data']['bids']);
		unset($indexes,$dates,$values);
		
		foreach($data['data']['tender']['lots'] as &$l){
			$dataAuction=[
				'tender_id'=>$data['data']['tender']['id'],
				'lot_id'=>$l['id'],
				'startTime'=>strtotime($l['auctionPeriod']['startDate']),
				'source'=>$data,
				'ctime'=>$_SERVER['REQUEST_TIME'],
			];
			if(isset($l['eligibleMinimumDifference'])) $l['minStep']=$l['eligibleMinimumDifference'];
			$dataAuction['source']['data']['tender']['lot']=$l;
			unset($dataAuction['source']['data']['tender']['lots']);
			$dataAuction['source']['data']['bids']=[];
			
			$i=0;
			foreach($data['data']['bids'] as &$b){
				if(@$b['relatedLot']!=$l['id']) continue;
				$b['sign']=hash('sha256',Tayna::shifrB(@$b['sign']?:Tayna::genKey()));
				$b['__index__']=$i;
				$dataAuction['source']['data']['bids'][$b['id']]=$b;
				$i++;
			}
			unset($b);
			if($dataAuction['startTime']<$dataAuction['ctime']) {
				//тут маразм #2
				return ERROR(403,'',[
					'error'=>[
						'code'=>'403.008',
						'message'=>'auction already started',
					],
				]);
				//маразм закончился
				
				$res['data']['tender']['lots'][]=[
					'id'=>$l['id'],
					'error'=>[
						'code'=>'000.003',
						'message'=>'auction already started',
					]
				];
				continue;
			}
			if(sizeof($dataAuction['source']['data']['bids'])<2) {
				//тут маразм
				$db->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
				$db->query('delete from '.DB_PREF.'auctions where tender_id="'.$db->real_escape_string($data['data']['tender']['id']).'";');
				$db->query('delete from '.DB_PREF.'auctions_bids where tender_id="'.$db->real_escape_string($data['data']['tender']['id']).'";');
				$db->commit();
				return ERROR(403,'',[
					'error'=>[
						'code'=>'403.007',
						'message'=>'small bids count',
					],
				]);
				//маразм закончился
				
				$res['data']['tender']['lots'][]=[
					'id'=>$l['id'],
					'error'=>[
						'code'=>'000.001',
						'message'=>'small bids count',
					]
				];
				continue;
			}
			$dataAuction['endTime']=$dataAuction['startTime']+($this->bidStepTime+$this->bidStepPause)*sizeof($dataAuction['source']['data']['bids'])*$this->bidsSteps+$this->bidStepPause;
			$dataAuction['source']['settings']=[
				'bidStepTime'=>$this->bidStepTime,
				'bidStepPause'=>$this->bidStepPause,
				'bidsSteps'=>$this->bidsSteps,
			];
			
			//$auction=$this->getAuction($data['data']['tender']['id'],$l['id']);
			
			$db->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
			/*if($auction){
				//тут еще бы проверку на возможность перепланирования
				//$db->query('delete from '.DB_PREF.'auctions where')
			}*/
			
			$bids=$dataAuction['source']['data']['bids'];
			$dataAuction['source']=json_encode($dataAuction['source']);
			$db->query('insert into '.DB_PREF.'auctions set '.$db->getReplaceFields($dataAuction).';');
			//$sqls=[];
			$commit=false;
			if($id=$db->insert_id){
				$commit=true;
				foreach($bids as &$b){
					$dataBid=[
						'bid_id'=>$b['id'],
						'auction_id'=>$id,
						'tender_id'=>$data['data']['tender']['id'],
						'value'=>$b['value'],
						'ctime'=>$_SERVER['REQUEST_TIME'],
					];
					//$sqls[]='insert into '.DB_PREF.'auctions_bids set '.$db->getReplaceFields($dataBid).';';
					$db->query('insert into '.DB_PREF.'auctions_bids set '.$db->getReplaceFields($dataBid).';');
					if($db->error){
						$commit=false;
						break;
					}
				}
				unset($b);
				/*if(sizeof($sqls)){
					$db->multi_query(implode(PHP_EOL,$sqls));
					$db->freeResults();
				}*/
			}
			$_res=[
				'id'=>$l['id'],
			];
			if($commit && $db->commit()){
				$_res['success']=true;
				$cache=$this->getCacheFilename($dataAuction);
				if(is_file($cache)) unlink($cache);
			}
			else {
				$db->rollback();
				//тут маразм #2
				
				if(!$commit){
					$db->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
					$db->query('delete from '.DB_PREF.'auctions where tender_id="'.$db->real_escape_string($data['data']['tender']['id']).'";');
					$db->query('delete from '.DB_PREF.'auctions_bids where tender_id="'.$db->real_escape_string($data['data']['tender']['id']).'";');
					$db->commit();
				}
				
				return ERROR(403,'',[
					'error'=>[
						'code'=>'403.009',
						'message'=>'commit error',
					],
				]);
				//маразм закончился
				
				$_res['error']=[
					'code'=>'000.002',
					'message'=>'commit error',
				];
			}
			$res['data']['tender']['lots'][]=$_res;
		}
		unset($l);
		return S::g()->ajaxRes=$res;
	}
	
	protected function checkSign($data,$sign=false){
		if(!$sign) return false;
		return hash('sha256',$data.API_PASSWORD)==$sign;
	}
}