<?php
if (PHP_SAPI != 'cli')
    exit;
header("Content-Type: text/html; charset=UTF-8");
date_default_timezone_set('Asia/ShangHai');
error_reporting(E_ALL && E_ERROR);

define('IN_MOBILE', true);
define('ROOT_DIR', str_replace("\\", '/', dirname(dirname(dirname(dirname(__FILE__))))));

class cjSystem
{
    public function __construct($params)
    {
        $this->params = $params;
        require(ROOT_DIR . "/addons/choujiang_page/cron/lib/lib.cron.php");
        require ROOT_DIR . '/framework/bootstrap.inc.php';
        load()->app('common');
        $_GPC['i'] = $params['acuid'];
        require IA_ROOT . '/app/common/bootstrap.app.inc.php';
        $this->redisConfig = $libConfig['redis'];
        $redis = new redis();
        $redis->connect($this->redisConfig['server'], $this->redisConfig['port']);
        if (! empty ($this->redisConfig['requirepass']) ) {
            $redis->auth($this->redisConfig['requirepass']);
        }
        $redis->select(0);
        $this->redis = $redis;
        $this->config['mysql'] = $libConfig['mysql'];
        $this->config['mysql']['we7_framework']['tablepre'] = $libConfig['module_prefix'][$this->params['acuid']];
        $this->mysqli = $this->_connectMysql("we7_framework");

        $sql = sprintf("SELECT * from %schoujiang_base WHERE uniacid = %s", $this->config['mysql']['we7_framework']['tablepre'], $this->params['acuid']);
        $this->ossConfig = $this->_get($this->mysqli, $sql);
    }

    public function run()
    {
        $nowYear=date('Y');
        $nowMonth=date('m');
        $nowDay=date('d');
        $nowHour=date('H');
        $nowMin=date('i');
        $nowSecond=date('s');
        ///每月刷新用户中奖次数
        if($nowDay == "01" &&$nowHour == "03" &&$nowMin == "00"){
            $startTime = $this->microtime_float();

            $this->refreshWinningNum();

            $this->_end($startTime);
        }

        ///每小时10分更新用户参与抽奖排名
        if($nowMin == "10"){
            $startTime = $this->microtime_float();

            $this->updateRecordOrder();

            $this->_end($startTime);
        }

        //每分钟执行更新用户头像信息
//        $startTime = $this->microtime_float();
//        $this->isHasAvatar();
//        $this->_end($startTime);
    }

    /*
     * 更新全部未开奖奖品排名
     *
     * */
    public function updateRecordOrder()
    {
        $uniacid = $this->params['acuid'];
        //十分钟之内开奖删除redis数据
        $sqlEnd = sprintf("SELECT id,goods_name,`status`,send_time FROM %schoujiang_goods where `status`=1 and (unix_timestamp(now())-send_time)<3600 ORDER BY send_time DESC",$this->config['mysql']['we7_framework']['tablepre']);
        $endGoods = $this->_getAll($this->mysqli, $sqlEnd);
        if(!empty($endGoods)){
            foreach ($endGoods as $k => $v) {
                $endKey=sprintf("cj_update_rownum:%s",$v['id']);
                $endKeys = $this->redis->keys($endKey.'*');
                if (!empty($endKeys)) {
                    $this->redis->delete($endKeys);
                }
            }
        }

        //所有为开奖奖品
        $sql = sprintf("SELECT id from %schoujiang_goods WHERE uniacid='%s' and status=0 and is_del!='-1' ", $this->config['mysql']['we7_framework']['tablepre'], $uniacid);
        $goods = $this->_getAll($this->mysqli, $sql);

        //给每个奖品排名
        foreach($goods as $key => $val){

            $sql=sprintf("SELECT @rownum :=@rownum + 1 AS rownums,avatar,openid, nickname,codes_amount,id,codes from %schoujiang_record,(SELECT @rownum := 0) a WHERE uniacid = '%s' and is_group_member = 0 and goods_id = '%s' ORDER BY codes_amount DESC, id ASC", $this->config['mysql']['we7_framework']['tablepre'], $uniacid, $val['id']);
            $order = $this->_getAll($this->mysqli, $sql);
            //给每个参与抽奖用户排名
            foreach($order as $key_order => $val_order){

                $keys=sprintf("cj_update_rownum:%s:%s",$val['id'],$val_order['id']);
                $redisUserInfo = $this->redis->exists($keys);
                if (!$redisUserInfo) {
                    $this->userOder($val,$val_order);
                }
                $this->userOder($val,$val_order);

            }

        }
    }

    /***
     * 更新用户排名
     * @param $val
     * @param $val_order
     */
    public function userOder($val,$val_order){
        // changed 可用值 1:上升 2:下降 3:不变
        $changed = 3;
        $default_rownums = 10000000;
        $keys=sprintf("cj_update_rownum:%s:%s",$val['id'],$val_order['id']);
        $redisUserInfo = $this->redis->hGetAll($keys);

        if(empty($redisUserInfo)) {
            $rownums = $default_rownums;
        }else{
                if($redisUserInfo['rownums']>$val_order['rownums']){
                    $changed = 1;
                }
                if($redisUserInfo['rownums']<$val_order['rownums']){
                    $changed = 2;
                }
                if($redisUserInfo['rownums']==$val_order['rownums']){
                    $changed = 3;
                }
                $rownums = $val_order['rownums'];
        }
            $this->redis->hSet($keys,'rownums',$rownums);
            if($val_order['codes_amount']==1){
                $changed = 3;
            }
            if(!empty($redisUserInfo)) {
                $sqlUpdate = sprintf("UPDATE %schoujiang_record SET `order_changed`='%s' WHERE id='%s'", $this->config['mysql']['we7_framework']['tablepre'],$changed, $val_order['id']);

                $result = $this->_query($this->mysqli, $sqlUpdate);
                if (!$result) {
                    $str = $val_order['id'] . ":" . date("Y-m-d H:i:s") . "数据库写入失败-----{$sqlUpdate}";
                    $this->_log($str);
                }
            }
    }

    /**
     * 系统相关
     * 1.每月刷新用户中奖次数
     */
    public function refreshWinningNum()
    {
        $sql = sprintf("SELECT * from %schoujiang_base WHERE uniacid='%s' limit 0,1", $this->config['mysql']['we7_framework']['tablepre'], $this->params['acuid']);
        $base = $this->_get($this->mysqli, $sql);
        $winning_num = $base['winning_num'];
        $time = time();
        $refreshDate = $this->redis->get('cj_refresh_winning_num');
        $month = date("Ym");
        if ($month > $refreshDate) {
            $sql = sprintf("update %schoujiang_user set send_time='{$time}',winning_num='{$winning_num}'  ", $this->config['mysql']['we7_framework']['tablepre']);
            mysqli_query($this->mysqli, $sql);
            $this->redis->set('cj_refresh_winning_num', $month);
            $this->_log("每月刷新用户中奖次数[{$month}]：{$sql}");
        }
    }


    /**
     * @param $userId
     * @param $url
     * @return bool|string
     *
     *
     *
     */
    public function isHasAvatar()
    {
        $avatarKey = sprintf("cj_update_avatar:%s", $this->params['acuid']);
        $list = $this->redis->hGetAll($avatarKey);

        if (!empty ($list)) {
            $this->_log("开始更新用户数据" . date("YmdHis"));
            foreach ($list as $key => $val) {
                $result = $this->upLoadShareImage($key, $val);
                if (!$result) {
                    $this->_log("用户:" . $key . "头像缓存失败," . date("YmdHis"));
                } else {
                    $this->redis->hDel($avatarKey, $key);
                    $this->_log("用户:" . $key . "头像已经缓存," . date("YmdHis"));
                }
            }
            $this->_log("更新用户数据结束" . date("YmdHis"));
        }
    }

    /*
     *头像缓存
     *
     */

    public function upLoadShareImage($userId, $url)
    {
//        global $_W;
        $uniacid = $this->params['acuid'];
        $filename="cj/avatar/".$uniacid."/".$userId.".jpg";
        $remote = $this->ossConfig;

        $data = file_get_contents($url);
        if(empty($data)){
            $this->_log("获取头像失败".date("YmdHis"));
            return false;
        }

        if ($remote['type'] == 1)   //阿里云oss 开启
        {
            //将服务器上的图片转移到阿里云oss
            $bucket = explode("@@", $remote['bucket']);
            require_once(IA_ROOT . '/framework/library/alioss/autoload.php');
            $endpoint = $remote['location'];

            try {
                $ossClient = new \OSS\OssClient($remote['aliosskey'], $remote['aliosssecret'], $endpoint);
                if($ossClient->doesObjectExist($bucket[0], $filename)){
                    $ossClient->deleteObject($bucket[0], $filename);
                }
                $ossClient->putObject($bucket[0], $filename, $data);//上传内存数据
            } catch (\OSS\Core\OssException $e) {
                $this->_log($e->getMessage());
            }

            $fname = $remote['url'] . $filename;

        } else if ($remote['type'] == 0)    //远程存储关闭
        {
            $pinfo = pathinfo($filename);
            $baseDestination = '/attachment/choujiang_page/'.$pinfo["dirname"]."/";
            $destination_folder = IA_ROOT.$baseDestination;  //图片文件夹路径
            //创建存放图片的文件夹
            if (!is_dir($destination_folder)) {
                $res = mkdir($destination_folder, 0777, true);
            }
            $destination = $destination_folder . $pinfo["basename"];
            if(file_exists($destination)){
                unlink($destination);
            }
            file_put_contents( $destination, $data);
            $fname = $baseDestination . $pinfo['basename'];
        }
        return $fname;
    }

    /**
     * 计算脚本运行时间 开始
     * @return float
     */
    public function microtime_float()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    /**
     * 结束
     */
    private function _end($startTime)
    {
        /// Time
        $endTime = $this->microtime_float();
        $useTime = $endTime - $startTime;

        /// Memory
        $size = memory_get_usage(true);
        $unit = array('b', 'kb', 'mb', 'gb', 'tb', 'pb');
        $memory = @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];

        ///
        print("[" . date("Y-m-d H:i:s") . "] " . sprintf("Process execution %f seconds, the space occupied %s", $useTime, $memory) . "\n\r");
        print('--------------------------------------------------------------------------------' . "\n\r");
    }

    /**
     * log
     */
    private function _log($str)
    {
        print("[" . date("Y-m-d H:i:s") . "]");
        print($str . "\n\r");
    }

    /**
     * 连接数据库
     * @param $dbName
     *
     * @return resource
     */
    private function _connectMysql($dbName)
    {
        $HOST = $this->config['mysql'][$dbName]['host'];
        $USER = $this->config['mysql'][$dbName]['username'];
        $PASSWORD = $this->config['mysql'][$dbName]['password'];
        $PORT = $this->config['mysql'][$dbName]['port'];
        $DB = $this->config['mysql'][$dbName]['database'];
        $CHARSET = $this->config['mysql'][$dbName]['charset'];

        if (empty($this->link['mysqli']["mysqli_" . $dbName])) {
            ///
            $this->link['mysqli']["mysqli_" . $dbName] = mysqli_connect($HOST, $USER, $PASSWORD, $DB, $PORT) or die(mysqli_connect_errno() . ":" . mysqli_connect_error());
            mysqli_set_charset($this->link['mysqli']["mysqli_" . $dbName], $CHARSET);
        }

        ///
        return $this->link['mysqli']["mysqli_" . $dbName];
    }

    /**
     * @param $mysqli
     * @param $sql
     * @return mixed
     */
    private function _get($mysqli, $sql)
    {
        $result = mysqli_query($mysqli, $sql);
        $response = array();
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $response[] = $row;
            }
        } else {
            $error = "查询失败\n\rsql:" . $sql;
            $this->_log($error);
        }

        ///
        return $response[0];
    }

    /**
     * @param $mysqli
     * @param $sql
     * @return mixed
     */
    private function _getAll($mysqli, $sql)
    {
        $result = mysqli_query($mysqli, $sql);
        $response = array();
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $response[] = $row;
            }
        } else {
            $error = "查询失败\n\rsql:" . $sql;
            $this->_log($error);
        }

        ///
        return $response;
    }

    /**
     * @param $mysqli
     * @param $sql
     * @return mixed
     */
    private function _query($mysqli, $sql)
    {
        $result = mysqli_query($mysqli, $sql);
        return $result;
    }

    private function _update($mysqli, $sql)
    {
        $result = mysqli_multi_query($mysqli, $sql);
        $response = array();
        if ($result) {
            while ($row = mysqli_fetch_row($result)) {
                $response[] = $row;
            }
        } else {
            $error = "查询失败\n\rsql:" . $sql;
            $this->_log($error);
        }

        ///
        return $response[0];
    }
}

$acuid = empty($argv[1]) ? 0 : $argv[1];
$params = [
    'acuid' => $acuid
];
if ($params['acuid'] < 1) {
    var_dump("请输入acuid");
    exit;
}
$obj = new cjSystem($params);
$obj->run();