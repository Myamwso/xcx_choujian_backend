<?php
require_once(dirname(__FILE__)."/IP4datx.class.php");
$ipAddr = str_replace('"',"",$argv[1]);
$ipObj = new IP();
$result = $ipObj->find($ipAddr);
echo $result[0].'#'.$result[1].'#'.$result[2]; 
exit;
//var_dump($result);
