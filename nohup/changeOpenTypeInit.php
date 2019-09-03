<?php

/**
 * 修改开奖方式初始化脚本：按人数开奖 -> 按抽奖码开奖
 * Class changeOpenTypeInit
 */
if (PHP_SAPI != 'cli')
    exit;
header("Content-Type: text/html; charset=UTF-8");
date_default_timezone_set('Asia/ShangHai');
error_reporting(E_ALL ^ E_NOTICE);
define('ROOT_DIR', str_replace("\\", '/', dirname(dirname(dirname(dirname(__FILE__))))));

global $_W;
$_W['now_uniacid'] = $argv[1];
$_W['goods_id'] = $argv[2];
class changeOpenTypeInit
{
    /**
     * 数据库连接池
     * @var array
     */
    public $link=[];

    public $config=[];

    public $redis;

    public function __construct()
    {
        global $_W;
        require(ROOT_DIR."/addons/choujiang_page/nohup/lib/lib.nohup.php");
        $this->config['mysql'] = $libConfig['mysql'];
        $this->config['mysql']['we7_framework']['tablepre'] = $libConfig['module_prefix'][$_W['now_uniacid']];

        $this->redisConfig = $libConfig['redis'];

        $redis = new redis();
        $redis->connect($this->redisConfig['server'], $this->redisConfig['port']);
        $redis->select(0);
        $this->redis = $redis;
    }

    public function run()
    {
        global $_W;
        if(empty($_W['now_uniacid'])){
            $this->_log("请输入微擎uniacid");
            exit;
        }
        if(empty($_W['goods_id'])){
            $this->_log("请输入奖品id");
            exit;
        }

        //当前奖品抽奖码发放锁是否已上锁
        $lockKey = sprintf('cj_lottery_code:%s', $_W['goods_id']);
        if ($this->redis->get($lockKey)) { //已上锁
            $this->_log("uniacid:{$_W['now_uniacid']}:goods_id:{$_W['goods_id']}|=> 奖品有人正在参与抽奖，请稍后再试");
            return false;
        }

        $this->redis->set($lockKey, 1); //上锁
        $mysqli  = $this->_connectMysql("we7_framework");

        ///查询当前奖品状态
        $sql = sprintf("SELECT * from %schoujiang_goods WHERE id='%s' limit 0,1", $this->config['mysql']['we7_framework']['tablepre'], $_W['goods_id']);
        $goods = $this->_get($mysqli, $sql);

        $maxCode = 0;
        if ($goods['canyunum'] > 0) {
            for ($i=0;$i<$goods['canyunum'];$i++) {
                $sql = sprintf("SELECT * from %schoujiang_record WHERE goods_id='%s' limit %d,1", $this->config['mysql']['we7_framework']['tablepre'], $_W['goods_id'], $i);
                $record = $this->_get($mysqli, $sql);
                if (empty($record['openid'])) {
                    continue;
                }
                
                $maxCode = 10000000 + $i;
                $codes = [
                    $maxCode => [
                        'type' => 1,
                        'openid' => $record['openid']
                    ]
                ];
                $sql = sprintf("UPDATE %schoujiang_record set codes='%s',codes_amount=1 WHERE goods_id=%s AND openid='%s'", $this->config['mysql']['we7_framework']['tablepre'], json_encode($codes), $_W['goods_id'], $record['openid']);
                $this->_query($mysqli, $sql);
            }
        }

        ///更新奖品
        $sql = sprintf("UPDATE %schoujiang_goods set max_cj_code=%s WHERE id = %s", $this->config['mysql']['we7_framework']['tablepre'], $maxCode, $_W['goods_id']);
        $result = $this->_query($mysqli, $sql);
        $this->_log($sql);

        $this->redis->del($lockKey); //解锁
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

$obj = new changeOpenTypeInit();
$obj->run();