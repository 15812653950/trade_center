<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Trader_center extends CI_Controller {


	
	/**
	 * 
	 * 交易结算
	 * 
	 * 负责对下单集合进行统一分析计算
	 * 
	 * 1：排序	----价格优先 -时间优先 -如果价格，时间相同，则按照手数大小排列
	 * 	规则：
	 * 		买入委托：从高到低
	 * 		卖出委托：从低到高
	 * 
	 * 
	 * **/
	

	public function __construct() {
    	parent::__construct();
    	$this->load->model('Trader_center_class', 'trader_center');
    }
	
	
	//最终，得出最终结算价格
	public function out_of_trader()
	{
		echo "<pre>";
		$data = $this -> trader_center -> check_trader_open();
		//是否计算判定
		if($data['flag'] == 1){
				//开始计算 进行搜索对应可进入计算脚本，进入更新阶段
				$buy_max = $data['data']['buy']['max'];
				$buy_min = $data['data']['buy']['min'];
				
				$sell_max = $data['data']['sell']['max'];
				$sell_min = $data['data']['sell']['min'];
				
				//列出可进入计算脚本 --更新状态 插入列表中
				$sort_list = $this -> trader_center -> get_order_sort($buy_max, $buy_min, $sell_max, $sell_min);
				#print_r($sort_list);
				
				//更改下单状态 插入计算池
				$data = $this -> trader_center -> trader_center_in($sort_list);
				print_r($data);
				/*
				 * 对计算池结果进行运算
				 * 
				 * */
				
				
					
		}else{
			//继续最后初始价格
			
		}
	}
	
	
	
	

	
	
}
