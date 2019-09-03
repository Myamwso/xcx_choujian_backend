<?php
/**
 * Created by chiZhe
 * User: chiZhe
 * Date: 2018/6/14 0014
 * Time: 下午 2:13
 */

//https://dev.ymify.com/app/index.php?i=7&t=0&v=1.0&from=wxapp&c=entry&a=wxapp&do=GetUid&m=choujiang_page
define('IN_IA', true);
define('IA_ROOT', str_replace("\\", '/', dirname(dirname(dirname(__FILE__)))));
require_once (IA_ROOT.'/framework/bootstrap.inc.php');
if(isset($argv[1])&&$argv[1]!="") {
    $_GPC['i'] = $argv[1];
}else{
    echo "请输入uniacid";
}
require_once (IA_ROOT.'/app/common/bootstrap.app.inc.php');

require_once __DIR__."/config.php";
require_once __DIR__ . "/common.func.php";

//global $_W;

// 获取参数，第一为uniacid，第二个为控制器，第三个为方法，第0个为调用的文件路径
//var_dump($argv);

$c = $argv[2];
//拼出类文件路径, 如果a为index crontab_path = index.controller.php
$crontab_path = IA_ROOT .'/addons/choujiang_page/wxapp.php';
//引入该文件
require $crontab_path;
//实例化类
$controller = new Choujiang_pageModuleWxapp;



if(isset($argv[2])&&$argv[2]!=""){
    $controller->doWebDoInvitation($argv[2],$access_token);
}else{
    echo "请输入商品ID";
}















