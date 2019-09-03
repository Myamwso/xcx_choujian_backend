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

$_GPC['i'] = $argv[1];
require_once (IA_ROOT.'/app/common/bootstrap.app.inc.php');

require_once __DIR__."/config.php";
require_once __DIR__ . "/common.func.php";

//global $_W;
// 获取参数，第一为uniacid，第二个为控制器，第三个为方法，第0个为调用的文件路径
//var_dump($argv);

$c = $argv[2];
$a = $argv[3];
//拼出类文件路径, 如果a为index crontab_path = index.controller.php
$crontab_path = IA_ROOT .'/addons/choujiang_page/wxapp.php';
//引入该文件
require $crontab_path;
//实例化类
$controller = new $c;
//SELECT * FROM `ims_choujiang_goods` WHERE UNIX_TIMESTAMP(smoke_time) <= UNIX_TIMESTAMP(NOW()) ORDER BY id DESC
//按时间自动开奖
$sql = "SELECT id FROM " . tablename_cj('choujiang_goods') . " WHERE `uniacid`=:uniacid AND `status`=:status  AND `is_del`=:is_del AND `smoke_set`=:smoke_set AND UNIX_TIMESTAMP(smoke_time) <= UNIX_TIMESTAMP(NOW()) AND `canyunum`>0 ORDER BY id ASC";
$sql1 = "SELECT id FROM " . tablename_cj('choujiang_goods') . " WHERE `uniacid`=:uniacid AND `status`=:status  AND `is_del`=:is_del AND `smoke_set`=:smoke_set AND UNIX_TIMESTAMP(smoke_time) <= UNIX_TIMESTAMP(NOW()) AND `canyunum`=0 ORDER BY id ASC";
$params = array();
$params[':uniacid'] = $_W['uniacid'];
//是否已删除
$params[':is_del'] = 1;
//开奖方式
$params[':smoke_set'] = 0;
//开奖状态
$params[':status'] = 0;
//全部定时未开奖信息
$noTimeSmoke = pdo_fetchall_cj($sql, $params);

foreach($noTimeSmoke as $k => $v){
    var_dump($v['id']);
    $controller->$a($v['id']);
}
file_put_contents(IA_ROOT . '/addons/choujiang_page/uuuu.log', "已开奖1" . date('Y-m-d h:i:s', time()) . "\n", FILE_APPEND);
//当开奖时未有人参加，则视为过期
$noTimeSmoke1 = pdo_fetchall_cj($sql1, $params);
foreach($noTimeSmoke1 as $k => $v){
    pdo_update_cj('choujiang_goods', array('status' => 2,'send_time'=>time()), array('id' => $v['id']));
    $controller->doPageInform1($v['id']);

}
file_put_contents(IA_ROOT . '/addons/choujiang_page/uuuu.log', "已开奖2" . date('Y-m-d h:i:s', time()) . "\n", FILE_APPEND);


//按人数开奖，人数达到上限时，自动开奖
$sql = "SELECT id FROM " . tablename_cj('choujiang_goods') . " WHERE `uniacid`=:uniacid AND `status`=:status AND `is_del`=:is_del AND `smoke_set`=:smoke_set AND smoke_num = canyunum AND `canyunum`>0 ORDER BY id ASC";
$params = array();
//是否已删除
$params[':is_del'] = 1;
$params[':uniacid'] = $_W['uniacid'];
$params[':smoke_set'] = 1;
$params[':status'] = 0;
//全部人数未开奖信息
$noNumSmoke = pdo_fetchall_cj($sql, $params);
foreach($noNumSmoke as $key => $val){
    //调用该方法
    $controller->$a($val['id']);
//    echo "\n";
}





//按人数抽奖 超过三天人数未满 设置自动开奖
//SELECT * FROM `ims_choujiang_goods` WHERE  smoke_num = canyunum
$sql = "SELECT id FROM " . tablename_cj('choujiang_goods') . " WHERE `uniacid`=:uniacid AND `status`=:status AND `is_del`=:is_del AND `smoke_set`=:smoke_set AND `canyunum`>0 AND UNIX_TIMESTAMP(create_time) <= UNIX_TIMESTAMP(date_sub(now(),interval 3 day)) ORDER BY id ASC";
$sql1 = "SELECT id FROM " . tablename_cj('choujiang_goods') . " WHERE `uniacid`=:uniacid AND `status`=:status AND `is_del`=:is_del AND `smoke_set`=:smoke_set  AND UNIX_TIMESTAMP(create_time) <= UNIX_TIMESTAMP(date_sub(now(),interval 3 day)) AND `canyunum`=0   ORDER BY id ASC";
$params = array();
//是否已删除
$params[':is_del'] = 1;
$params[':uniacid'] = $_W['uniacid'];
$params[':smoke_set'] = 1;
$params[':status'] = 0;
//全部人数未开奖信息
$noNumFullSmoke = pdo_fetchall_cj($sql, $params);
foreach($noNumFullSmoke as $key => $val){
    //调用该方法
    $controller->$a($val['id']);
//    echo "\n";
}
//当开奖时未有人参加，则视为过期
$noNumFullSmoke1 = pdo_fetchall_cj($sql1, $params);
foreach($noNumFullSmoke1 as $key => $val){
    pdo_update_cj('choujiang_goods', array('status' => 2 ,'send_time'=>time()), array('id' => $val['id']));
    $controller->doPageInform1($val['id']);
}

//中奖者超过一天未填写收货信息，则取消中奖资格
$nowMin=date('i');
if($nowMin%5==0) {    //每5分钟更新一次
    $sql = "select * from " . tablename_cj("choujiang_record") . "where `uniacid`=:uniacid  and `status`=:status and `user_name` is null  and `finish_time`<=  UNIX_TIMESTAMP(date_sub(now(),interval 1 day))";
    $params = array();
    $params[':uniacid'] = $_W['uniacid'];
    $params[':status'] = 1;
    $user_record = pdo_fetchall_cj($sql, $params);
    foreach ($user_record as $k => $v) {
        $sqlAddr = "select * from " . tablename_cj("choujiang_default_addr") . "where `openid`=:openid";
        $paramsAddr[':openid'] = $v['openid'];
        $user_record_addr = pdo_fetch_cj($sqlAddr, $paramsAddr);
        if ($user_record_addr && $user_record_addr['name'] && $user_record_addr['tel'] && $user_record_addr['area'] && $user_record_addr['zip_code']) {
            pdo_update_cj('choujiang_record', array('user_name' => $user_record_addr['name'], 'user_address' => $user_record_addr['area'] . $user_record_addr['address'], 'user_zip' => $user_record_addr['zip_code'], 'user_tel' => $user_record_addr['tel']), array('id' => $v['id']));
        } else {
            pdo_update_cj('choujiang_record', array('status' => -1), array('id' => $v['id']));
        }
    }
}



//手动开奖超过三天未开奖则视为过期
$sql = "SELECT id FROM " . tablename_cj('choujiang_goods') . " WHERE `uniacid`=:uniacid AND `status`=:status AND `is_del`=:is_del AND `smoke_set`=:smoke_set  AND UNIX_TIMESTAMP(create_time) <= UNIX_TIMESTAMP(date_sub(now(),interval 3 day))  ORDER BY id ASC";
$params = array();
//是否已删除
$params[':is_del'] = 1;
$params[':uniacid'] = $_W['uniacid'];
$params[':smoke_set'] = 2;
$params[':status'] = 0;
$noNumFullSmoke = pdo_fetchall_cj($sql, $params);
foreach($noNumFullSmoke as $key => $val){
    pdo_update_cj('choujiang_goods', array('status' => 2,'send_time'=>time()), array('id' => $val['id']));
    $controller->doPageInform1($val['id']);
}
file_put_contents(IA_ROOT . '/addons/choujiang_page/uuuu.log', "手动开奖超过三天未开奖则视为过期" . date('Y-m-d h:i:s', time()) . "\n", FILE_APPEND);










