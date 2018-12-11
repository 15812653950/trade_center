<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Trader_order extends CI_Controller {

	
	public function __construct() {
    	parent::__construct();
    	$this->load->model('Trader_order_class', 'trader_order');
    }
	
	
	//下单
	public function push_order()
	{
		$type	 = $_GET['type'];
		$price   = $_GET['price'];
		$number  = $_GET['number'];
		$data = $this -> trader_order -> push_order($type, $price, $number);
		echo $data;
	}
	
	
	//展示当前价格情况
	public function show_price_now()
	{
		$data = $this -> trader_order -> show_price_now();
		echo $data;
	}
	
	//展示K线图
	public function show_price_K()
	{
		$data = $this -> trader_order -> show_price_K();
		echo $data;
	}
	
	//展示买卖五档
	public function show_level_list()
	{
		$data =$this -> trader_order -> show_list_order();
		$data = json_encode($data);
		print_r($data);
	}
	
	//
	public function test()
	{
		$this -> trader_order -> show_list_order();
	}
	
}
