<?php

/**
 * 用户统计
 */

if (PHP_SAPI != 'cli')
    exit;
header("Content-Type: text/html; charset=UTF-8");
date_default_timezone_set('Asia/ShangHai');
error_reporting(E_ALL ^ E_NOTICE);
define('ROOT_DIR', str_replace("\\", '/', dirname(dirname(dirname(dirname(dirname(__FILE__)))))));

global $_W;
$_W['now_uniacid'] = $argv[1];
$_W['start_at'] = $argv[2];
$_W['end_at'] = $argv[3];

class autoMachine {
    /**
     * 数据库连接池
     * @var array
     */
    public $link=[];

    public $config=[];

    public $redis;

    public $redisKey = [
        'user' => 'cj_user_amount:%s:%s'
    ];

    /**
     * 机器人总数
     * @var int
     */
    public $machineTotal=0;

    public function __construct()
    {
        global $_W;
        require(ROOT_DIR."/addons/choujiang_page/cron/lib/lib.cron.php");

        $this->redisConfig = $libConfig['redis'];

        $redis = new redis();
        $redis->connect($this->redisConfig['server'], $this->redisConfig['port']);
        $redis->select(0);
        $this->redis = $redis;

        $this->config['mysql'] = $libConfig['mysql'];
        $this->config['mysql']['we7_framework']['tablepre'] = $libConfig['module_prefix'][$_W['now_uniacid']];
    }

    public function run()
    {
        global $_W;
        if ($_W['now_uniacid'] <= 0 || $_W['now_uniacid'] > 10000) {
            $this->_log("请输入正确的uniacid".$_W['now_uniacid']);
            exit;
        }

        $startTime = $this->microtime_float();
        if ((!empty($_W['start_at']) &&  !empty($_W['end_at'])) && $_W['end_at'] >= $_W['start_at']) {//指定日期
            $startTime = strtotime($_W['start_at']);
            $endTime = strtotime($_W['end_at']);

            for ($i=$startTime;$i<=$endTime;$i=$i+86400) {
                $day = date("Ymd", $i);
                $this->handle($day);
            }
        } else {//默认昨天
            $day = date("Ymd", strtotime("-1 day"));
            $this->handle($day);
        }

        $endTime = $this->_end($startTime);
    }

    /**
     * 处理
     * @param $data
     * @return bool
     */
    public function handle($day)
    {
        global $_W;
        $channelKey = sprintf($this->redisKey['user'], $_W['now_uniacid'], $day);
        $data = $this->redis->hGetAll($channelKey);
        $insertData = [];
        if (! empty($data)) {
            $insertData['extand'] = json_encode($data);
            foreach ($data as $hour => $val) {
                $stat = json_decode($val, true);
                if (! empty($stat)) {
                    $insertData['user_visit'] += $stat['visit'];
                    $insertData['user_add'] += $stat['new'];
                }
            }

            ///入库
            $this->insertUpdate($insertData, $day);
        } else {
            $this->_log("无相关数据[$day]");
            return false;
        }
    }

    public function insertUpdate($data, $day){
        global $_W;
        $createAt = date("Y-m-d", strtotime($day));
        $updateAt = date("Y-m-d H:i:s");
        $delDay = date("Ymd", strtotime("{$day} -7 day"));
        if (empty($data)) {
            $this->_log("无相关数据[$day]");
            return false;
        }

        $mysqli  = $this->_connectMysql("we7_framework");

        $sql = sprintf("DELETE FROM %schoujiang_stat_user WHERE create_at='%s'",$this->config['mysql']['we7_framework']['tablepre'],
            $createAt
        );
        $result = $this->_query($mysqli, $sql);

        $sql = sprintf("INSERT INTO %schoujiang_stat_user(user_visit,user_add,extand,create_at,update_at) value('%s','%s','%s','%s','%s')", $this->config['mysql']['we7_framework']['tablepre'],
            $data['user_visit'],
            $data['user_add'],
            $data['extand'],
            $createAt,
            $updateAt
        );
        $result = $this->_query($mysqli, $sql);
        $this->_log("数据插入成功[$day]");

        $userKey = sprintf($this->redisKey['user'], $_W['now_uniacid'], $delDay);
        $this->redis->del($userKey);
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

$obj = new autoMachine();
$obj->run();