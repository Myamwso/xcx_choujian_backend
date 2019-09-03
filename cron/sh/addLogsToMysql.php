<?php
if (PHP_SAPI != 'cli')
    exit;
header("Content-Type: text/html; charset=UTF-8");
date_default_timezone_set('Asia/ShangHai');
error_reporting(E_ALL && E_ERROR);

define('IN_MOBILE', true);
define('ROOT_DIR', str_replace("\\", '/', dirname(dirname(dirname(dirname(dirname(__FILE__)))))));

class pushMessage
{
//    public $redis;
    public $params = [];
//    public $redisConfig = [];

    public function __construct($params)
    {
        $this->params = $params;

        require(ROOT_DIR."/addons/choujiang_page/cron/lib/lib.cron.php");
        require ROOT_DIR . '/framework/bootstrap.inc.php';
        load()->app('common');
        $_GPC['i'] = $params['acuid'];
        require IA_ROOT . '/app/common/bootstrap.app.inc.php';

//        $this->redisConfig = $libConfig['redis'];
//
//        $redis = new redis();
//        $redis->connect($this->redisConfig['server'], $this->redisConfig['port']);
//        $redis->select(0);
//        $this->redis = $redis;

        $this->config['mysql'] = $libConfig['mysql'];
        $this->config['mysql']['we7_framework']['tablepre'] = $libConfig['module_prefix'][$this->params['acuid']];
    }

    /*
     * 运行
     */
    public function run()
    {
        $startTime = $this->microtime_float();
        $prefix = $this->config['mysql']['we7_framework']['tablepre'];
        $do = $this->params['do'];
        if($do=="ip"){
            $fields = "openid,login_time,ip,country,province,city,create_time";
            $table  = $prefix."choujiang_ip_historical";
        }elseif($do=="ua"){
            $fields = "openid,login_time,ua,create_time";
            $table  = $prefix."choujiang_ua_historical";
        }elseif($do=="ph"){
            $fields = "openid,login_time,model,system,version,brand,create_time";
            $table  = $prefix."choujiang_equipment_historical";
        }else{
            echo "操作参数必填！";
        }

        $fileName = $this->params['file_name'];
        if(!file_exists($fileName)){
            echo "$do:日志文件不存在";
            return false;
        }
        $file = fopen($fileName,"r");
        $i = 1;
        $com = '';
        $inData = array();
        $inDataTemp = array();
        $startInsert = false;
        $repeatNum = 0;
        $failNum = 0;
        $successNum = 0;
        $errorData = [];
        $mysqli  = $this->_connectMysql('we7_framework');
        while(!feof($file)){
            $row = fgets($file);
            $row = str_replace(array("\r\n", "\r", "\n"), "", $row);
            if($do=="ph"){
                $row = urldecode($row);
                $row = str_replace("@", "#", $row);
                $row = str_replace("model:", "", $row);
                $row = str_replace("system:", "", $row);
                $row = str_replace("version:", "", $row);
                $row = str_replace("brand:", "", $row);
            }
            $arrRow = explode("#",$row);
            if($arrRow[0]!=$com){
                $inData=$inDataTemp;
                $inDataTemp=[];
                $inDataTemp[]=$row;
                $com=$arrRow[0];
                if($i!=1){
                    $startInsert = true;
                }
            }else{
                $inDataTemp[] = $row;
            }
            if($startInsert){
                $send=$this->addData($mysqli, $table, $fields, $inData, $do);
                $inData = [];
                $startInsert =false;
                if( isset($send['repeat']) ){
                    $repeatNum++;
                }
                if( isset($send['error']) && $send['error'] ){
                    $failNum++;
                    $errorData[] = $send['info'];
                }elseif( isset($send['error']) && !$send['error'] ){
                    $successNum++;
                }
            }
            $i++;
        }
        fclose($file);
        $return = [
            'total'     =>$successNum + $failNum,
            'success'   =>$successNum,
            'fail'      =>$failNum,
            'repeat'    =>$repeatNum,
            'errotData' =>$errorData,
        ];
        echo "{$do}:".json_encode($return);
        $this->_end($startTime);
    }

    /*
     * 执行数据插入
     */
    private function addData($link, $table, $fields, $data, $do)
    {
        $values = "";
        $insertFirst = false;
        if(count($data)>0){
            $hasData = explode("#",$data[0]);
            if($do=="ip"){
                $has = $this->get($link , $table , "openid='{$hasData[0]}' and ip='{$hasData[2]}'");
                if($has){
                    $maxId = $this->get($link , $table , "openid='{$hasData[0]}'", "MAX(id) as max_id");
                    if($maxId[0]['max_id']==$has[0]['id']){
                        unset($data[0]);
                        $insertFirst = true;
                    }
                }
            }elseif($do=="ua"){
                $has = $this->get($link , $table , "openid='{$hasData[0]}' and ua='{$hasData[2]}'");
                if($has){
                    $maxId = $this->get($link , $table , "openid='{$hasData[0]}'", "MAX(id) as max_id");
                    if($maxId[0]['max_id']==$has[0]['id']){
                        unset($data[0]);
                        $insertFirst = true;
                    }
                }
            }elseif($do=="ph"){
                $maxId = $this->get($link , $table , "openid='{$hasData[0]}'", "MAX(id) as max_id");
                if($maxId){
                    $maxIdInfo = $this->get($link , $table , "id={$maxId[0]['max_id']}");
                    $phInfoArr = explode("#",$data[0]);
                    if($maxIdInfo[0]['model']==$phInfoArr[2]&&$maxIdInfo[0]['system']==$phInfoArr[3]&&$maxIdInfo[0]['version']==$phInfoArr[4]){
                        unset($data[0]);
                        $insertFirst = true;
                    }
                }
            }
        }
        if(!empty($data)){
            foreach($data as $key=>$val){
                $arrVal = explode("#",$val);
                if($insertFirst){
                    $length = count($data);
                }else{
                    $length = count($data)-1;
                }
                if($key == $length){
                    $values .= "('".join("','",$arrVal)."','" . date("Y-m-d H:i:s") . "')";
                }else{
                    $values .= "('".join("','",$arrVal)."','" . date("Y-m-d H:i:s") . "'),";
                }
            }
        }
        $res = [];
        if(empty($values)){
            $res = ['repeat'=>true,'info'=>'重复数据'];
        }else{
            $result = $this->insertMore($link, $table, $fields, $values);
            if(!$result){
                $res = ['error'=>true,'info'=>$values];
            }else{
                $res = ['error'=>false,'id'=>$result];
            }
            //$aaa = insert($link, "ims_choujiang_ip_historical", $data);
        }
        return $res;
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

        if ( empty($this->link['mysqli']["mysqli_".$dbName]) ) {
            ///
            $this->link['mysqli']["mysqli_".$dbName] = mysqli_connect($HOST, $USER, $PASSWORD, $DB, $PORT) or die(mysqli_connect_errno().":".mysqli_connect_error());
            mysqli_set_charset($this->link['mysqli']["mysqli_".$dbName], $CHARSET);
        }

        ///
        return $this->link['mysqli']["mysqli_".$dbName];
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
            $error = "查询失败\n\rsql:".$sql;
            $this->_log($error);
        }

        ///
        return $response[0];
    }

    /**
     * 获取一条数据
     */
    private function get($link , $table , $where , $fields = '*', $order="id", $limit="1")
    {
        $sql = "select $fields from $table where $where order by $order desc limit $limit";

        $result = mysqli_query($link , $sql);

        $error = mysqli_error($link);
        if (empty($error)) {
            while ($rows = mysqli_fetch_assoc($result)) {
                $data[] = $rows;
            }
            return $data;
        } else {
            $error = "查询失败\n\rsql:".$sql.$error;
            $this->_log($error);
            return false;
        }
    }

    private function insertMore($mysqli , $table , $fields, $values)
    {
        $sql = "insert into $table($fields) values$values";

        $result = mysqli_query($mysqli , $sql);


        if ($result && mysqli_affected_rows($mysqli)) {
            return mysqli_insert_id($mysqli);
        } else {
            $error = mysqli_error($mysqli);
            $error = "写入数据失败\n\rsql:".$sql."|==>{$error}";
            $this->_log($error);
            return false;
        }

    }

    private function _query($mysqli, $sql)
    {
        $result = mysqli_query($mysqli, $sql);

        $error = mysqli_error($mysqli);
        if (! empty($error)) {
            $error = "查询失败\n\rsql:".$sql."|==>{$error}";
            $this->_log($error);
        }

        return $result;
    }
}

$acuid                  = empty($argv[1]) ? 0 : $argv[1];
$do                     = empty($argv[3]) ? '' : $argv[2];
$fileName               =  empty($argv[2]) ? '' : $argv[3];
$params = [
    'acuid'           => $acuid,
    'do'               => $do,
    'file_name'       =>$fileName,
];
if ($params['acuid'] < 1) {
    var_dump("请输入acuid");
    exit;
}
if (empty($params['do'])) {
    var_dump("请输入操作参数");
    exit;
}
if (empty($params['file_name'])) {
    var_dump("请输入文件");
    exit;
}

$obj = new pushMessage($params);
$obj->run();




