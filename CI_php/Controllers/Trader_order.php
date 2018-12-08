<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Trader_order extends CI_Controller {

	
	public function __construct() {
    	parent::__construct();
    	$this->load->model('Trader_order_class', 'trader_order');
    }
	
	public function index()
	{
		
	}
}
