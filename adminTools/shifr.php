<?php
chdir(pathinfo(__FILE__,PATHINFO_DIRNAME));
include_once '../web/admin/core.php';
file_put_contents(__FILE__.'.txt',Tayna::shifrB('InsertPasswordHere'));
