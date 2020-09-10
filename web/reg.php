<?php
$GLOBALS['registry']=array (
  /*'^/auctions/create$' => 
  array (
	'desc' => 'Planing auction',
	'get' => 
	array (
	  'sign' => '^[0-9a-zA-Z]{64}$',
	),
	'ajax' => 0,
	'plugins' => 
	array (
	  0 => 
	  array (
		'name' => 'auctions_cdb',
		'functions' => 
		array (
		  0 => 
		  array (
			'name' => 'create',
			'prm' => 
			array (			  
			),
			'ajax' => '0',
		  ),
		),
		'ajax' => 0,
	  ),
	),
  ),*/
  //ocds-prefix-UA-1511863200123456-xx
  '^/auctions/ocds-[0-9a-zA-Z]{6}-[a-zA-Z]{2}-[0-9]{13,16}(-[0-9a-zA-Z]{2})?/[0-9a-zA-Z]{8}-[0-9a-zA-Z]{4}-[0-9a-zA-Z]{4}-[0-9a-zA-Z]{4}-[0-9a-zA-Z]{12}/index.json$' => 
  array (
	'desc' => 'View auction',
	//'tpl'=>'view.htm',
	'get' => 
	array (
	),
	'ajax' => 1,
	'plugins' => 
	array (
	  0 => 
	  array (
		'name' => 'auctions_users',
		'functions' => 
		array (
		  0 => 
		  array (
			'name' => 'view',
			'prm' => 
			array (              
			),
			'ajax' => '1',
		  ),
		  10 => 
		  array (
			'name' => 'getCurrentTime',
			'prm' => 
			array (              
			),
			'ajax' => '1',
		  ),
		),
		'ajax' => 1,
	  ),
	),
  ),
  '^/auctions/bid$' => 
  array (
	'desc' => 'bid auction',
	'get' => 
	array (
	),
	'ajax' => 2,
	'plugins' => 
	array (
	  0 => 
	  array (
		'name' => 'auctions_users',
		'functions' => 
		array (
		  0 => 
		  array (
			'name' => 'bid',
			'prm' => 
			array (              
			),
			'ajax' => '2',
		  ),
		),
		'ajax' => 2,
	  ),
	),
  ),
  '^/auctions/sinhroTime$' => 
  array (
	'desc' => 'sinhroTime',
	'get' => 
	array (
	),
	'ajax' => 1,
	'plugins' => 
	array (
	  0 => 
	  array (
		'name' => 'auctions_users',
		'functions' => 
		array (
		  0 => 
		  array (
			'name' => 'getCurrentTime',
			'prm' => 
			array (              
			),
			'ajax' => '1',
		  ),
		),
		'ajax' => 1,
	  ),
	),
  ),
);