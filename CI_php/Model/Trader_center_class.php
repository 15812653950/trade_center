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
		
		if(($buy_data['max'] > $sell_data['min']) && $sell_data['min'] != '' && $buy_data['max'] != ''){
			
			
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
		$this -> db -> select('*');
		$this -> db -> where('price >= ', $sell_min);
		$this -> db -> where('price <= ', $buy_max);
		$this -> db -> where('type','buy');
		$this -> db -> order_by('price', 'DESC');
		$this -> db -> order_by('insert_time', 'ASC');
		$this -> db -> order_by('number', 'DESC');
		$buy_data = $this -> db -> get('trader_order') -> result_array();
		
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
	
	
	
	//计算池队列计算
	public function show_cal_list()
	{
		#$this -> db -> select('*');
		#$this -> db -> where('type','buy');
		#$this -> db -> where('status', '处理中');
	}
	


}
