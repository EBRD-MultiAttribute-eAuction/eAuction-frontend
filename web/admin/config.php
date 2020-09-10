<?php
$GLOBALS['mainSettings']=array (
  'DB_' => 
  array (
	'descr' => 'Подключение к базе данных',
	'items' => 
	array (
	  'URL' => 
	  array (
		'value' => 'localhost',
		'descr' => 'Адрес базы данных',
	  ),
	  'USERNAME' => 
	  array (
		'value' => 'root',
		'descr' => 'Имя пользователя базы данных',
	  ),
	  'PASSWORD' => 
	  array (
		'value' => '',
		'descr' => 'Пароль доступа к базе данных',
	  ),
	  'DATABASE' => 
	  array (
		'value' => 'auction',
		'descr' => 'Имя базы данных',
	  ),
	  'PREF' => 
	  array (
		'value' => 'site_',
		'descr' => 'Префикс таблиц',
	  ),
	),
  ),
  'API_' => 
  array (
	'descr' => 'Прием данных',
	'items' => 
	array (
	  'CHECK' => 
	  array (
		'value' => '',
		'descr' => 'Проверять ли IP адресс',
	  ),
	  'IPS_JSON' => 
	  array (
		'value' => '',
		'descr' => 'Список допустимых IP в виде json',
	  ),
	  'PASSWORD' => 
	  array (
		'value' => '񜡖󾂘󂵤𘺹󽝤􍁇肇񔐣򍶔󢆽𥡅𯜜򝎨򖱨񆫟򏿷𓅳󌡊🅛󃕠񾸽򌶰󺊤򟂁󈋄񹓜񫠼򀧤񞙋򫖰󎢔𽟕񉆵򴟤󆳉𳏻􇲀򮌆򏳔𷕊񜡗󾃯󂴊',
		'descr' => 'Ключ для проверки подписи',
	  ),
	),
  ),
  'KAFKA_' => 
  array (
	'descr' => 'KAFKA',
	'items' => 
	array (
	  'BROKERS' => 
	  array (
		'value' => 'dev.AK-node1.eprocurement.systems:9092,dev.AK-node2.eprocurement.systems:9092,dev.AK-node3.eprocurement.systems:9092',
		'descr' => 'addBrokers',
	  ),
	  'IN' => 
	  array (
		'value' => 'auction-front-in',
		'descr' => 'входящие',
	  ),
	  'OUT' => 
	  array (
		'value' => 'auction-front-out',
		'descr' => 'исходящие',
	  ),
	),
  ),
  'ACCESS_' => 
  array (
	'descr' => 'Доступ к сайту',
	'items' => 
	array (
	  'ALLOW' => 
	  array (
		'value' => '1',
		'descr' => 'Отображение сайта: 1 - разрешено, 0 - запрещено',
	  ),
	  'BYPASS' => 
	  array (
		'value' => '0',
		'descr' => 'Доступ по паролю: 1 - разрешено, 0 - запрещено',
	  ),
	  'LOGIN' => 
	  array (
		'value' => 'admin',
		'descr' => 'Логин доступа по паролю',
	  ),
	  'PASSWORD' => 
	  array (
		'value' => '񜡇󾂏󂵊𘺍󽝎􍀝肙',
		'descr' => 'Пароль доступа к сайту',
	  ),
	),
  ),
);