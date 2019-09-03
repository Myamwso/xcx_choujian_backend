<?php
/**
 * 任务状态更新脚本
 * 未开始 -> 进行中（未开始的任务时间到了以后转变回进行中）
 */
if (PHP_SAPI != 'cli')
    exit;
header("Content-Type: text/html; charset=UTF-8");
date_default_timezone_set('Asia/ShangHai');
error_reporting(E_ALL ^ E_NOTICE);

global $_W;
$_W['now_uniacid'] = $argv[1];

class autoGetAvatar {
    /**
     * 数据库连接池
     * @var array
     */
    public $link=[];

    public $config=[];

    public $redis;
    public $redisConfig = [];

    public $ossConfig = [];

    /**
     * 机器人总数
     * @var int
     */
    public $machineTotal=0;

    public function __construct()
    {
        global $_W;
        require("./lib/lib.nohup.php");

        $this->redisConfig = $libConfig['redis'];

        $redis = new redis();
        $redis->connect($this->redisConfig['server'], $this->redisConfig['port']);
        if (! empty ($this->redisConfig['requirepass']) ) {
            $redis->auth($this->redisConfig['requirepass']);
        }
        $redis->select(0);
        $this->redis = $redis;

        $this->config['mysql'] = $libConfig['mysql'];
        $this->config['mysql']['we7_framework']['tablepre'] = $libConfig['module_prefix'][$_W['now_uniacid']];

        $mysqli  = $this->_connectMysql("we7_framework");
        $sql = sprintf("SELECT * from %schoujiang_base WHERE uniacid = %s", $this->config['mysql']['we7_framework']['tablepre'], $_W['now_uniacid']);
        $this->ossConfig = $this->_get($mysqli, $sql);
    }

    public function run()
    {
        global $_W;
        if(empty($_W['now_uniacid'])){
            $this->_log("请输入微擎uniacid");
            exit;
        }

        /// 操作
        $i = 0;
        while (1) {
            $avatarKey = sprintf("cj_update_avatar:%s",$_W['now_uniacid']);
            $list = $this->redis->hGetAll($avatarKey);

            if (! empty ($list) ) {
                $this->_log("开始更新用户数据".date("YmdHis"));
                foreach ($list as $key => $val) {
                    $result = $this->upLoadShareImage($key, $val);
                    if(!$result){
                        $this->_log("用户:".$key."头像缓存失败,".date("YmdHis"));
                    }else{
                        $this->redis->hDel($avatarKey, $key);
                        $this->_log("用户:".$key."头像已经缓存,".date("YmdHis"));
                    }
                }

            } else {
                sleep(10);
            }
        }
    }

    /*
     *初始化用户数据
     *
     */
    public function init()
    {
        global $_W;
        $mysqli  = $this->_connectMysql("we7_framework");
        $sql = sprintf("SELECT * from %schoujiang_user WHERE uniacid = %s AND is_machine = %s", $this->config['mysql']['we7_framework']['tablepre'], $_W['now_uniacid'], "0");
        $list = $this->_getList($mysqli, $sql);
        if (! empty ($list) ) {
            $this->_log("开始更新用户数据".date("YmdHis"));
            foreach ($list as $key => $val) {
                $result = $this->upLoadShareImage($val['id'], $val['avatar']);
                if(!$result){
                    $this->_log("用户:".$val['id']."头像缓存失败,".date("YmdHis"));
                }else{
                    $this->_log("用户:".$val['id']."头像已经缓存,".date("YmdHis"));
                }
            }
        }
    }

    /*
     *头像缓存
     *
     */

    public function upLoadShareImage($userId, $url)
    {
        global $_W;
        $filename="cj/avatar/".$_W['now_uniacid']."/".$userId.".jpg";
        $remote = $this->ossConfig;
//                $data = $this->curl_file_get_contents($url);
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

    public function curl_file_get_contents($durl){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $durl);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36');
        curl_setopt($ch, CURLOPT_REFERER,'https://dev.ymify.com');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $r = curl_exec($ch);
        curl_close($ch);   return $r;
    }

    /***************************************函数********************************************/

    /**
     * @param $mysqli
     * @param $sql
     * @param bool|false $key
     * @return array
     */
    private function _getList($mysqli, $sql, $key=false)
    {
        $result = mysqli_query($mysqli, $sql);
        $response = array();
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                if ($key) {
                    $response[$row[$key]] = $row;
                }else{
                    $response[] = $row;
                }
            }
        } else {
            $error = "查询失败\n\rsql:".$sql;
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

    private function _query($mysqli, $sql)
    {
        $result = mysqli_query($mysqli, $sql);

        $error = mysqli_error($mysqli);
        if (! empty($error)) {
            $error = "查询失败\n\rsql:".$sql."|==>{$error}";
            $this->_log($error);
        }

        ///
        return $result;
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
        print("[".date("Y-m-d H:i:s")."] ".sprintf("Process execution %f seconds, the space occupied %s", $useTime, $memory)."\n\r");
        print('--------------------------------------------------------------------------------'."\n\r");
    }

    /**
     * log
     */
    private function _log($str)
    {
        print("[".date("Y-m-d H:i:s")."]");
        print($str."\n\r");
    }
}

$obj = new autoGetAvatar();
if( isset($argv[2]) && $argv[2] == "init" ){
    $obj->init();
    exit;
}
$obj->run();