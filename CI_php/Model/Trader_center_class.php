<?php

class Trader_center_class extends CI_Model {
	
	
	public function __construct() {
		parent::__construct();
      	$this -> load -> database();
	}
	
	
	//找到买入最大值  找到卖出最小值
	public function check_trader_open()
	{
		//找到最大值买入
		#echo "<pre>";
		$buy_data = $this -> search_price('buy');
		$sell_data = $this -> search_price('sell');
		$array = array('buy'=>$buy_data,'sell'=>$sell_data);
		
		if(($buy_data['max'] >= $sell_data['min']) && $sell_data['min'] != '' && $buy_data['max'] != ''){
			
			
			return array('flag'=>1,'data'=>$array);
		}else{
			return array('flag'=>0,'data'=>$array);
		}

	}
	
	//搜索最大最小值 price 买卖排列
	public function search_price($type)
	{
		$this -> db -> select_min('price');
		$this -> db -> where('type', $type);
		$this -> db -> where('status','未结算');
		$min_data = $this -> db -> get('trader_order') -> result_array(); 
		$this -> db -> select_max('price');
		$this -> db -> where('type', $type);
		$this -> db -> where('status','未结算');
		$max_data = $this -> db -> get('trader_order') -> result_array(); 
		
		return array('max'=>$max_data[0]['price'],'min'=>$min_data[0]['price']);
	}
	
	//整理出对应的买卖排序情况	
	public function get_order_sort($buy_max, $buy_min, $sell_max, $sell_min)
	{
		//买入
		$this -> db -> select('*');
		$this -> db -> where('price >= ', $sell_min);
		$this -> db -> where('price <= ', $buy_max);
		$this -> db -> where('type','buy');
		$this -> db -> order_by('price', 'DESC');
		$this -> db -> order_by('insert_time', 'ASC');
		$this -> db -> order_by('number', 'DESC');
		$buy_data = $this -> db -> get('trader_order') -> result_array();
		
		//卖出
		$this -> db -> select('*');
		$this -> db -> where('price >= ', $sell_min);
		$this -> db -> where('price <= ', $buy_max);
		$this -> db -> where('type','sell');
		$this -> db -> order_by('price', 'ASC');
		$this -> db -> order_by('insert_time', 'ASC');
		$this -> db -> order_by('number', 'DESC');
		$sell_data = $this -> db -> get('trader_order') -> result_array();
		
		$array = array('buy'=>$buy_data,'sell'=>$sell_data);
		return $array;
	}
	
	//将队列插入计算中 -- 同时更新状态
	public function trader_center_in($sort_list)
	{
		$insert_list = array();
		$update_list = array();
		foreach($sort_list['buy'] as $keys => $values){
			$insert = array();
			$insert['log_id'] = $values['log_id'];
			$insert['price']  = $values['price'];
			$insert['number'] = $values['number'];
			$insert['last_number'] = $values['number'];
			$insert['type']   = $values['type'];
			$insert['status'] = '处理中';
			array_push($insert_list, $insert);
			
			$update = array();
			$update['log_id'] = $values['log_id'];
			$update['status'] = '结算中';
			array_push($update_list, $update);
		}
		$this -> db -> insert_batch('trader_center', $insert_list);
		$this -> db -> update_batch('trader_order', $update_list, 'log_id');
		
		$insert_list = array();
		$update_list = array();
		foreach($sort_list['sell'] as $keys => $values){
			$insert = array();
			$insert['log_id'] = $values['log_id'];
			$insert['price']  = $values['price'];
			$insert['number'] = $values['number'];
			$insert['last_number'] = $values['number'];
			$insert['type']   = $values['type'];
			$insert['status'] = '处理中';
			array_push($insert_list, $insert);
			
			$update = array();
			$update['log_id'] = $values['log_id'];
			$update['status'] = '结算中';
			array_push($update_list, $update);
		}
		
		print_r($insert_list);
		print_r($update_list);
		$this -> db -> insert_batch('trader_center', $insert_list);
		$this -> db -> update_batch('trader_order', $update_list, 'log_id');
		
		
		
	}
	
	//计算过程总结
	public function cal_trade()
	{
		//循环检测，进行执行，得出最后的价格
		//检测是否为 最大值 大于 最小值
		echo "<pre>";
		$now = strtotime('now');
		$time_desc = date('Y-m-d H:i:s', $now);
		$last_price   = 0.00;
		$volume 	  = 0.00;
		$volume_money = 0.00;
		$data = $this -> check_cal_list();
		while($this -> check_cal_list()){
			//开始执行计算
			$data = $this -> show_cal_price();
			$last_data = $this -> cal_open($data['buy'], $data['sell'], $last_price, $volume, $volume_money);
			$volume 	  = $last_data['volume'];
			$last_price   = $last_data['last_price'];
			$volume_money = $last_data['volume'];
			#print_r($last_data);
		}
		print_r('价格综合结果：'.$last_price.',成交量：'.$volume.'，成交金额：'.$volume_money);
		$array = array('time_desc'=>$time_desc,'change_time'=>$now,'price'=>$last_price,'volume'=>$volume,'volume_money'=>$volume_money);
		$this -> db -> insert('trade_day', $array);
	}
	
	
	//计算池队列检测是否符合
	public function check_cal_list()
	{
		//队列池比对
		$this -> db -> select_max('price');
		$this -> db -> where('type','buy');
		$this -> db -> where('last_number > ', 0);
		$this -> db -> where('status', '处理中');
		$buy_data = $this -> db -> get('trader_center') -> result_array();
		
		$this -> db -> select_min('price');
		$this -> db -> where('type', 'sell');
		$this -> db -> where('last_number >', 0);
		$this -> db -> where('status','处理中');
		$sell_data = $this -> db -> get('trader_center') -> result_array();
		
		if($buy_data[0]['price'] != '' && $sell_data[0]['price'] != '' && $buy_data[0]['price'] >= $sell_data[0]['price']){
			return TRUE;
		}else{
			return FALSE;
		}		
	}
	
	//计算价格展示
	public function show_cal_price()
	{
		$this -> db -> select_max('price');
		$this -> db -> where('type','buy');
		$this -> db -> where('last_number > ', 0);
		$this -> db -> where('status', '处理中');
		$buy_data = $this -> db -> get('trader_center') -> result_array();
		$buy_price = $buy_data[0]['price'];
		
		$this -> db -> select_min('price');
		$this -> db -> where('type', 'sell');
		$this -> db -> where('last_number >', 0);
		$this -> db -> where('status','处理中');
		$sell_data = $this -> db -> get('trader_center') -> result_array();
		$sell_price = $sell_data[0]['price'];
		
		$array = array('buy'=>$buy_price,'sell'=>$sell_price);
		return $array;		
		
	}
	
	//计算开启
	public function cal_open($buy_price, $sell_price, $last_price, $volume, $volume_money)
	{
		//买入
		$this -> db -> select('*');
		$this -> db -> where('type','buy');
		$this -> db -> where('price',$buy_price);
		$this -> db -> where('status','处理中');
		$this -> db -> where('last_number >',0);                               
		$this -> db -> order_by('log_id','asc');
		$buy_data =  $this -> db -> get('trader_center') -> result_array();
		$buy_data = $buy_data[0];
		
		//卖出
		$this -> db -> select('*');
		$this -> db -> where('type','sell');
		$this -> db -> where('price',$sell_price);
		$this -> db -> where('status','处理中');
		$this -> db -> where('last_number >',0); 
		$this -> db -> order_by('log_id','asc');
		$sell_data =  $this -> db -> get('trader_center') -> result_array();
		#print_r($sell_data);
		#die();
		$sell_data = $sell_data[0];
		
		//索引ID
		$buy_list_id = $buy_data['list_id'];
		$sell_list_id = $sell_data['list_id'];
		$buy_log_id = $buy_data['log_id'];
		$sell_log_id = $sell_data['log_id'];

		
		if($buy_data['last_number'] > $sell_data['last_number']){
			
			$buy_last = $buy_data['last_number'] - $sell_data['last_number'];
			$sell_last = 0;
			$buy_out_number = $sell_data['last_number'];
			$sell_out_number = $sell_data['last_number']; 
			$buy_array = array('list_id'=>$buy_list_id,'log_id'=>$buy_log_id,'last_number'=>$buy_last,'out_number'=>$buy_out_number);
			$sell_array = array('list_id'=>$sell_list_id,'log_id'=>$sell_log_id,'last_number'=>$sell_last,'out_number'=>$sell_out_number);
			$array = array('buy'=>$buy_array,'sell'=>$sell_array);
			$this -> change_log($array);
			
			//整理平均价，与成交量,成交金额
			$volume = $volume + $sell_data['last_number'];
			$last_price = round(($buy_data['price'] + $sell_data['price']) * $sell_data['last_number'] / (2 * $sell_data['last_number']),2);
			$now_money  = round($last_price * $sell_data['last_number'], 2);
			$volume_money = $volume_money + $now_money;
			
		}else if($buy_data['last_number'] < $sell_data['last_number']){
			$sell_last = $sell_data['last_number'] - $buy_data['last_number'];
			$buy_last = 0;
			$buy_out_number = $buy_data['last_number'];
			$sell_out_number = $buy_data['last_number']; 
			$buy_array = array('list_id'=>$buy_list_id,'log_id'=>$buy_log_id,'last_number'=>$buy_last,'out_number'=>$buy_out_number);
			$sell_array = array('list_id'=>$sell_list_id,'log_id'=>$sell_log_id,'last_number'=>$sell_last,'out_number'=>$sell_out_number);
			$array = array('buy'=>$buy_array,'sell'=>$sell_array);
			$this -> change_log($array);
			
			//整理平均价，与成交量
			$volume = $volume + $buy_data['last_number'];
			$last_price = round(($buy_data['price'] + $sell_data['price']) * $buy_data['last_number'] / (2 * $buy_data['last_number']),2);
			$now_money  = round($last_price * $buy_data['last_number'], 2);
			$volume_money = $volume_money + $now_money;
		}else{
			$buy_last = 0;
			$sell_last = 0;
			$buy_out_number = $buy_data['last_number'];
			$sell_out_number = $sell_data['last_number']; 
			$buy_array = array('list_id'=>$buy_list_id,'log_id'=>$buy_log_id,'last_number'=>$buy_last,'out_number'=>$buy_out_number);
			$sell_array = array('list_id'=>$sell_list_id,'log_id'=>$sell_log_id,'last_number'=>$sell_last,'out_number'=>$sell_out_number);
			$array = array('buy'=>$buy_array,'sell'=>$sell_array);
			$this -> change_log($array);
			
			
			$volume = $volume + $buy_data['last_number'];
			$last_price = round(($buy_data['price'] + $sell_data['price']) * $buy_data['last_number'] / (2 * $buy_data['last_number']),2);
			$now_money  = round($last_price * $sell_data['last_number'], 2);
			$volume_money = $volume_money + $now_money;
		}
		
		return array('volume'=>$volume,'last_price'=>$last_price, 'volume_price'=>$volume_money);
	}


	//修改状态
	public function change_log($array)
	{
		$buy_data = $array['buy'];
		$sell_data = $array['sell'];
		
		$this -> db -> select('*');
		$this -> db -> where('list_id',$buy_data['list_id']);
		$buy_now_data = $this -> db -> get('trader_center') -> result_array();
		
		$this -> db -> select('*');
		$this -> db -> where('list_id', $sell_data['list_id']);
		$sell_now_data = $this -> db -> get('trader_center') -> result_array();
		
		if($buy_data['last_number'] > 0){
			$out_number = $buy_data['out_number'] + $buy_now_data[0]['out_number'];
			$new_array = array('out_number'=>$out_number,'last_number'=>$buy_data['last_number']);
			$this -> db -> where('list_id', $buy_data['list_id']);
			$this -> db -> update('trader_center', $new_array);
		}else{
			//更新中心，同时更新日志
			$out_number = $buy_data['out_number'] + $buy_now_data[0]['out_number'];
			$new_array = array('out_number'=>$out_number,'last_number'=>$buy_data['last_number'],'status'=>'处理完毕');
			$this -> db -> where('list_id', $buy_data['list_id']);
			$this -> db -> update('trader_center', $new_array);
			
			$new_array = array('out_number'=>$buy_data['out_number'],'last_number'=>$buy_data['last_number'],'status'=>'已结算');
			$this -> db -> where('log_id', $buy_data['log_id']);
			$this -> db -> update('trader_order', $new_array);
		}
		
		if($sell_data['last_number'] > 0){
			$out_number = $sell_data['out_number'] + $sell_now_data[0]['out_number'];
			$new_array = array('out_number'=>$out_number,'last_number'=>$sell_data['last_number']);
			$this -> db -> where('list_id', $sell_data['list_id']);
			$this -> db -> update('trader_center', $new_array);
		}else{
			//更新中心，同时更新日志
			$out_number = $sell_data['out_number'] + $sell_now_data[0]['out_number'];
			$new_array = array('out_number'=>$out_number,'last_number'=>$sell_data['last_number'],'status'=>'处理完毕');
			$this -> db -> where('list_id', $sell_data['list_id']);
			$this -> db -> update('trader_center', $new_array);
			
			$new_array = array('out_number'=>$sell_data['out_number'],'last_number'=>$sell_data['last_number'],'status'=>'已结算');
			$this -> db -> where('log_id', $sell_data['log_id']);
			$this -> db -> update('trader_order', $new_array);
		}
		
		
		
	}


	//继续最后的价格
	public function final_last_price()
	{
		$now = strtotime('now');
		$time_desc = date('Y-m-d H:i:s', $now);
		
		$this -> db -> select('*');
		$this -> db -> order_by('id', 'DESC');
		$this -> db -> limit(1);
		$data  = $this -> db -> get('trade_day') -> row_array();
		
		$array = array('price'=>$data['price'],'volume'=>0,'volume_money'=>0,'change_time'=>$now, 'time_desc'=>$time_desc);
		$this -> db -> insert('trade_day', $array);
	}
}
