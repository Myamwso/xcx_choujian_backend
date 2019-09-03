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

class autoMachine {
    /**
     * 数据库连接池
     * @var array
     */
    public $link=[];

    public $config=[];

    public $redis;

    /**
     * 机器人总数
     * @var int
     */
    public $machineTotal=0;

    public function __construct()
    {
        global $_W;
        require("./lib/lib.nohup.php");
        $this->config['mysql'] = $libConfig['mysql'];
        $this->config['mysql']['we7_framework']['tablepre'] = $libConfig['module_prefix'][$_W['now_uniacid']];

        $this->redisConfig = $libConfig['redis'];

        $redis = new redis();
        $redis->connect($this->redisConfig['server'], $this->redisConfig['port']);
        if (! empty ($this->redisConfig['requirepass']) ) {
            $redis->auth($this->redisConfig['requirepass']);
        }
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
        /// 操作
        $i = 0;
        while (1) {
            $mysqli  = $this->_connectMysql("we7_framework");
            $sql = sprintf("SELECT * from %schoujiang_machine_num WHERE status=0", $this->config['mysql']['we7_framework']['tablepre']);
            $list = $this->_getList($mysqli, $sql);

            if (! empty ($list) ) {
                foreach ($list as $val) {
                    $this->handle($val);

                    $seconds = rand(1, 10);
                    sleep($seconds);
                }
            } else {
                sleep(600);
            }
        }
    }

    /**
     * 更新数据库
     * @param $data
     * @return bool
     */
    public function handle($data)
    {
        //当前奖品抽奖码发放锁是否已上锁
        $lockKey = sprintf('cj_lottery_code:%s', $data['goods_id']);
        if ($this->redis->get($lockKey)) { //已上锁
            return false;
        }

        $this->redis->set($lockKey, 1); //上锁

        $mysqli  = $this->_connectMysql("we7_framework");
        ///查询当前奖品状态
        $sql = sprintf("SELECT * from %schoujiang_goods WHERE id='%s' limit 0,1", $this->config['mysql']['we7_framework']['tablepre'], $data['goods_id']);
        $goods = $this->_get($mysqli, $sql);

        if ($goods['audit_status'] != 1) { //未通过的奖品跳过
            return $this->_getResult(false, $data['goods_id']);
        }

        if ($goods['status'] != 0 || $goods['is_del'] == -1) { ///非正在进行中的抽奖 -> 结束添加机器人
            $this->_log("非正在进行中的抽奖 -> 结束添加机器人". $data['goods_id']);
            $sql = sprintf("UPDATE %schoujiang_machine_num SET status=1 WHERE goods_id=%s", $this->config['mysql']['we7_framework']['tablepre'], $data['goods_id']);
            $list = $this->_query($mysqli, $sql);

            return $this->_getResult(false, $data['goods_id']);
        }

        if ($goods['smoke_set'] == 1) { //按人数开奖 -> 结束添加机器人
            if ($goods['smoke_num'] - $goods['canyunum'] <= 1) {
                $this->_log("按人数开奖 -> 结束添加机器人". $data['goods_id']);
                $sql = sprintf("UPDATE %schoujiang_machine_num SET status=1 WHERE goods_id=%s", $this->config['mysql']['we7_framework']['tablepre'], $data['goods_id']);
                $list = $this->_query($mysqli, $sql);

                return $this->_getResult(false, $data['goods_id']);
            }
        }

        //获取要添加的机器人
        $sql = sprintf("SELECT count(*) as total from %schoujiang_user as a, ims_choujiang_record as b where b.goods_id='%s' and a.is_machine=1", $this->config['mysql']['we7_framework']['tablepre'],$data['goods_id']);
        $isExist = $this->_get($mysqli, $sql);
        if ($isExist['total'] > 0) {
            $sql = sprintf("SELECT count(*) as total from %schoujiang_user where is_machine=1 and (openid not in (SELECT openid from %schoujiang_record where goods_id='%s' and is_machine=1))",
                $this->config['mysql']['we7_framework']['tablepre'],
                $this->config['mysql']['we7_framework']['tablepre'],
                $data['goods_id']);
            $count = $this->_get($mysqli, $sql);
            $machineTotal = $count['total'];
            $who = rand(0, $machineTotal);
            $sql = sprintf("SELECT * from %schoujiang_user where is_machine=1 and (openid not in (SELECT openid from %schoujiang_record where goods_id='%s' and is_machine=1)) limit %d,1",
                $this->config['mysql']['we7_framework']['tablepre'],
                $this->config['mysql']['we7_framework']['tablepre'],
                $data['goods_id'],
                $who);
            $user = $this->_get($mysqli, $sql);
        } else {
            $sql = sprintf("SELECT count(*) as total from %schoujiang_user as a where a.is_machine=1", $this->config['mysql']['we7_framework']['tablepre']);
            $count = $this->_get($mysqli, $sql);
            $machineTotal = $count['total'];
            $who = rand(0, $machineTotal);
            $sql = sprintf("SELECT a.* from %schoujiang_user as a where a.is_machine=1 limit %d,1", $this->config['mysql']['we7_framework']['tablepre'],
                $who);
            $user = $this->_get($mysqli, $sql);
        }
        if (empty($user)) {
            $this->_log("机器人用户信息为空");

            return $this->_getResult(false, $data['goods_id']);
        }


        ///添加参与抽奖记录
        $code = $goods['max_cj_code'] + 1;
        $sql = sprintf("INSERT INTO %schoujiang_record(uniacid,goods_id,status,create_time,goods_name,openid,nickname,avatar,is_machine,codes) value('%s','%s',0,'%s','%s','%s','%s','%s','%s','%s')", $this->config['mysql']['we7_framework']['tablepre'],
                $goods['uniacid'],
                $data['goods_id'],
                time(),
                $goods['goods_name'],
                $user['openid'],
                $user['nickname'],
                $user['avatar'],
                1,
                [$code]
            );
        $result = $this->_query($mysqli, $sql);

        if ($result) {
            if ($data['machine_num'] <= $data['added_machine_num']+1) {
                $sql = sprintf("UPDATE %schoujiang_machine_num SET status=1,max_cj_code='%s' WHERE goods_id=%s", $this->config['mysql']['we7_framework']['tablepre'], $code,$data['goods_id']);
                $this->_query($mysqli, $sql);
            }

            $canyunum = $goods['canyunum'] + 1;
            $sql = sprintf("UPDATE %schoujiang_goods SET canyunum=%s WHERE id='%s'", $this->config['mysql']['we7_framework']['tablepre'], $canyunum,$data['goods_id']);
            $this->_query($mysqli, $sql);

            $sql = sprintf("UPDATE %schoujiang_user SET mf_num=mf_num-1 WHERE openid='%s'", $this->config['mysql']['we7_framework']['tablepre'], $user['openid']);
            $this->_query($mysqli, $sql);

            $sql = sprintf("UPDATE %schoujiang_machine_num SET added_machine_num=added_machine_num+1 WHERE goods_id=%s", $this->config['mysql']['we7_framework']['tablepre'], $data['goods_id']);
            $this->_query($mysqli, $sql);
        }

        ///
        return $this->_getResult(true, $data['goods_id']);
    }

    /**
     * 返回结果，并删除抽奖码发放锁
     * @param bool|false $result
     * @param int $goodsId
     * @return bool
     */
    private function _getResult($result = false, $goodsId=0)
    {
        $lockKey = sprintf('cj_lottery_code:%s', $goodsId);
        $this->redis->del($lockKey);

        return $result;
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