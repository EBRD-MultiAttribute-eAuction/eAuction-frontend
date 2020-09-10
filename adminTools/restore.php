<?php
if(php_sapi_name()!='cli') exit("Только для консольного режима!!!".PHP_EOL);
if(empty($argv[1]))  exit("Не указан [Tender.id]!!!".PHP_EOL);
//if(empty($argv[2]))  exit("Не указан [Lot.id]!!!".PHP_EOL);
chdir(pathinfo(__FILE__,PATHINFO_DIRNAME));

chdir('../web');

include_once '../server.php';
require_once 'admin/core.php';

echo (Plugins::Auctions_history()->restore($argv[1])?"Восстановление прошло успешно. Ожидайте повторный результат.":"Ошибка восстановления. Бог вам в помощь!!!"),PHP_EOL;