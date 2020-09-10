<?php
class Auctions_history {
	public function move2history($callback=false,$time=false){
		$start=0;
		$limit=1000;
		$db=DB::g();
		
		if(!$time) $time=strtotime(date('Y-m-d 00:00:00'));
		$columns=[];
		$bid=['id'=>0];
		while(!$start || (!empty($res) && $res->num_rows)){
			$sql='select id,tender_id,lot_id from '.DB_PREF.'auctions a
				where a.id>"'.$start.'" and a.endTime<"'.$time.'" and (select count(*) from '.DB_PREF.'auctions a2 where a2.tender_id=a.tender_id and a2.endTime>="'.$time.'")=0
				ORDER by a.id
				LIMIT '.$limit.';';
			$res=$db->query($sql);
			
			$moveAuctions=[];
			$forDelete=[];
			while($row=$res->fetch_assoc()){
				if(!$start) $columns=[
					'auctions'=>implode(',',$this->getColumns('auctions')),
					'auctions_bids'=>implode(',',$this->getColumns('auctions_bids')),
				];
				if($callback && is_callable($callback)){
					if(!$callback($row)) continue;
				}
				$start=$row['id'];
				
				$moveAuctions[]=$row['id'];
				$forDelete[]='(tender_id="'.$db->real_escape_string($row['tender_id']).'" and lot_id="'.$db->real_escape_string($row['lot_id']).'")';
				
				Plugins::Auctions_users()->bidersInform($row,$bid,'close');
			}
			if(!empty($moveAuctions)){
				$moveAuctions='"'.implode('","',$moveAuctions).'"';
				$forDelete=implode(' or ',$forDelete);
				$sqls=[
					'delete from '.DB_PREF.'auctions_bids_history where auction_id in (select id from '.DB_PREF.'auctions_history where '.$forDelete.');',
					'delete from '.DB_PREF.'auctions_history where '.$forDelete.';',
					'insert into '.DB_PREF.'auctions_bids_history ('.$columns['auctions_bids'].') select SQL_NO_CACHE '.$columns['auctions_bids'].' from '.DB_PREF.'auctions_bids where auction_id in ('.$moveAuctions.');',
					'insert into '.DB_PREF.'auctions_history ('.$columns['auctions'].') select SQL_NO_CACHE '.$columns['auctions'].' from '.DB_PREF.'auctions where id in ('.$moveAuctions.');',
				];
				$db->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
				
				/*$db->multi_query(implode(PHP_EOL,$sqls));
				$db->freeResults();*/
				$errors=false;
				foreach($sqls as &$sql_item){
					$db->query($sql_item);
					if($db->error){
						$errors[]=$db->error;
						break;
					}
				}
				if($errors) $db->rollback();
				if(!$errors && $db->commit()){
					$sqls=[
						'delete from '.DB_PREF.'auctions where id in ('.$moveAuctions.');',
						'delete from '.DB_PREF.'auctions_bids where auction_id in ('.$moveAuctions.');',
					];
					$db->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
					
					/*$db->multi_query(implode(PHP_EOL,$sqls));
					$db->freeResults();*/
					$errors=false;
					foreach($sqls as &$sql_item){
						$db->query($sql_item);
						if($db->error){
							$errors[]=$db->error;
							break;
						}
					}
					if($errors) $db->rollback();
					else $db->commit();
				}
			}
			//echo $start,PHP_EOL;
			if($res->num_rows<$limit) break;
		}
	}
	protected function getColumns($table){
		$sql='SELECT `COLUMN_NAME` 
		FROM `INFORMATION_SCHEMA`.`COLUMNS` 
		WHERE `TABLE_SCHEMA`="'.DB_DATABASE.'" 
			AND `TABLE_NAME`="'.DB_PREF.$table.'";';
		$db=DB::g();
		$res=$db->query($sql);
		$ret=[];
		while($row=$res->fetch_assoc()){
			$ret[]=$row['COLUMN_NAME'];
		}
		return $ret;
	}
	public function restore($tender_id){
		$db=DB::g();
		
		$columns=[];
		$bid=['id'=>0];
		$sql='select id from '.DB_PREF.'auctions_history a
			where a.tender_id="'.$db->real_escape_string($tender_id).'"
			ORDER by a.id;';
		$res=$db->query($sql);
		
		$moveAuctions=[];
		while($row=$res->fetch_assoc()){
			if(empty($columns)) {
				$columns=[
					'auctions'=>implode(',',$this->getColumns('auctions')),
					'auctions_bids'=>implode(',',$this->getColumns('auctions_bids')),
				];
			}
			
			$moveAuctions[]=$row['id'];
		}
		if(!empty($moveAuctions)){
			$moveAuctions='"'.implode('","',$moveAuctions).'"';
			$sqls=[
				'insert into '.DB_PREF.'auctions_bids ('.$columns['auctions_bids'].') select SQL_NO_CACHE '.$columns['auctions_bids'].' from '.DB_PREF.'auctions_bids_history where auction_id in ('.$moveAuctions.');',
				'insert into '.DB_PREF.'auctions ('.$columns['auctions'].') select SQL_NO_CACHE '.$columns['auctions'].' from '.DB_PREF.'auctions_history where id in ('.$moveAuctions.');',
			];
			$db->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
			
			$errors=false;
			foreach($sqls as &$sql_item){
				$db->query($sql_item);
				if($db->error){
					$errors[]=$db->error;
					break;
				}
			}
			if($errors) $db->rollback();
			if(!$errors && $db->commit()){
				return true;
			}
		}
		return false;
	}
}