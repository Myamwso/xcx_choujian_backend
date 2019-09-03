<?php
if (PHP_SAPI != 'cli')
    exit;
header("Content-Type: text/html; charset=UTF-8");
date_default_timezone_set('Asia/ShangHai');
error_reporting(E_ALL && E_ERROR);

define('IN_MOBILE', true);
define('ROOT_DIR', str_replace("\\", '/', dirname(dirname(dirname(dirname(__FILE__))))));

class cjWishingReleaseGoods
{
    /**
     * 数据库连接池
     * @var array
     */
    public $link=[];

    public $config=[];

    public $mysqli=[];

    public $redis;

    public $level = 0;

    public function __construct($params)
    {
//        global $_GPC, $_W;
        $this->params = $params;
        require(ROOT_DIR . "/addons/choujiang_page/nohup/lib/lib.nohup.php");

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
        $this->allBaseConfig = $this->_get($this->mysqli, $sql);

    }

    public function run()
    {
        try {

            while (true) {
                $startTime = $this->microtime_float();

                $this->level = 0;

                if ($this->redis->ping() !== "+PONG") {
                    $redis = new redis();
                    $redis->connect($this->redisConfig['server'], $this->redisConfig['port']);
                    if (!empty ($this->redisConfig['requirepass'])) {
                        $redis->auth($this->redisConfig['requirepass']);
                    }
                    $redis->select(0);
                    $this->redis = $redis;
                    unset($redis);
                }
                $this->wishingLikesParticipate();
                $this->_end($startTime);
                sleep(10);
            }
        }catch (Exception $e){
            var_dump($e->getFile(),$e->getLine(),$e->getMessage());
        }

    }

    /*
     * 心愿用户参与抽奖并发送通知
     */
    public function wishingLikesParticipate(){
        $editGoodsKey = "cj_wishing_goods:waitPushMessage";
        $waitingPush = $this->redis->hGetAll($editGoodsKey);

        if ( !empty($waitingPush) ) {
            foreach ( $waitingPush as $redisKey => $redisVal ) {
                //未通知心愿商品，同时获取用户openid formid 心愿发布者user_id
                $tablePre = $this->config['mysql']['we7_framework']['tablepre'];
                $sql = sprintf("SELECT WG.wishing_id, WG.goods_id, W.openid, W.formid, U.id AS wishing_user_id FROM %schoujiang_wishing_goods AS WG LEFT JOIN %schoujiang_wishing AS W ON WG.wishing_id = W.id LEFT JOIN %schoujiang_user AS U ON W.openid = U.openid WHERE WG.is_notice = 0 AND WG.wishing_id=%s", $tablePre, $tablePre, $tablePre, $redisKey);
                $wishingGoods = $this->_getAll($this->mysqli, $sql);
                if ( !empty($wishingGoods) ) {

                    foreach ($wishingGoods as $key => $val) {
                        /// 更新wishing_goods表 为已通知状态
                        $sql = sprintf("update %schoujiang_wishing_goods set is_notice=1 WHERE wishing_id={$val['wishing_id']} ", $this->config['mysql']['we7_framework']['tablepre']);
                        $this->_query($this->mysqli, $sql);

                        $joinInfo = [
                            'id' => $val['goods_id'],
                            'cj_share_c' => -1,
                        ];

                        /// 取2个formid使用，并修改数据库记录
                        $formIds = json_decode($val['formid'],true);
                        $formIdArr = [];
                        $tempArr = [];
                        foreach ($formIds as $keyFormid => $valFormid) {
                            if (strtotime($valFormid['dataTime']) > time()-(3600*24*7)) {
                                if (count($formIdArr)<2) {
                                    $formIdArr[] = $valFormid;
                                } else {
                                    $tempArr[]= $valFormid;
                                }
                            }
                        }
                        $formIdsJson = json_encode($tempArr);

                        $sql = sprintf("update %schoujiang_wishing set formid='{$formIdsJson}' WHERE id={$val['wishing_id']} ", $this->config['mysql']['we7_framework']['tablepre']);
                        $this->_query($this->mysqli, $sql);

                        $this->_participatePush( $joinInfo, $val['openid'], $val['goods_id'], $formIdArr );

                        /// 抽奖码全给心愿发布者，奖分享用户id设置为心愿发布者
                        //                $_GPC['cj_share_u'] = $val['wishing_user_id'];
                        /// 设置分享goods_id
                        $joinInfo['cj_share_id'] = $val['goods_id'];

                        $this->_wishingParticipate( $joinInfo, $val['wishing_id'], $val['goods_id'], $val['wishing_user_id'] );

                        /// 消息推送完成，修改奖品状态为发布时状态
                        $sql = sprintf("update %schoujiang_goods set audit_status={$redisVal} WHERE id={$val['goods_id']} ", $this->config['mysql']['we7_framework']['tablepre']);
                        $this->_query($this->mysqli, $sql);

                        /// 单个心愿推送完成，删除redis记录
                        $this->redis->Del($editGoodsKey, $redisKey);

                        sleep(5);

                    }
                }
            }
        }
    }

    /**
     * 一个心愿定时流程
     */
    private function _wishingParticipate( $joinInfo, $wishingId, $goods_id, $goods_user_id, $max_list = 2000, $num = 0 )
    {
        if ( !$max_list ) {
            $max_list = 2000;
        }
        $sql = sprintf("SELECT * FROM %schoujiang_wishing_record WHERE wishing_id = %s ORDER BY id ASC LIMIT %s,%s", $this->config['mysql']['we7_framework']['tablepre'], $wishingId, $num*$max_list, $max_list);
        $wishingRecords = $this->_getAll($this->mysqli, $sql);

        if ( !empty($wishingRecords) ) {
            foreach ( $wishingRecords as $key => $val ) {

                /// 取2个formid使用，并修改数据库记录
                $formIds = json_decode($val['formid'],true);
                $formIdArr = [];
                $tempArr = [];
                foreach ($formIds as $keyFormid => $valFormid) {
                    if (strtotime($valFormid['dataTime']) > time()-(3600*24*7)) {
                        if (count($formIdArr)<2) {
                            $formIdArr[] = $valFormid;
                        } else {
                            $tempArr[]= $valFormid;
                        }
                    }
                }
                $formIdsJson = json_encode($tempArr);
                $sql = sprintf("update %schoujiang_wishing_record set formid='{$formIdsJson}' WHERE id={$val['id']} ", $this->config['mysql']['we7_framework']['tablepre']);
                $this->_query($this->mysqli, $sql);

                /// 设置分享用户id,如果没有分享者就把抽奖码给心愿发布者
                $joinInfo['cj_share_u'] = $val['share_id'] == 0 ? $goods_user_id : $val['share_id'];

                $this->_participatePush( $joinInfo, $val['openid'], $goods_id, $formIdArr );
            }
            $num++;
            $this->_wishingParticipate( $joinInfo, $wishingId, $goods_id, $goods_user_id,'', $num);
        } else {
            return false;
        }
    }

    /**
     * 参与抽奖推送消息
     */
    private function _participatePush( $joinInfo, $openid, $goods_id, $formids )
    {
        global $_W;

        if ( count($formids) == 0) {
            $joinFormid = '';
            $messageFormid = '';
        } else if ( count($formids) == 1) {
            $joinFormid = '';
            $messageFormid = $formids[0]['formid'];
        } else {
            $joinFormid = $formids[1]['formid'];
            $messageFormid = $formids[0]['formid'];
        }

        $joinInfo['openid'] = $openid;
        $joinInfo['formid'] = $joinFormid;

        /// 自动参与抽奖
        $participateResult = $this->doPageParticipate($joinInfo);

        /// 奖品发布推送信息
        $sql = sprintf("SELECT * from %schoujiang_goods WHERE id = %s", $this->config['mysql']['we7_framework']['tablepre'], $goods_id);
        $goods = $this->_get($this->mysqli, $sql);

        $data['open_id'] = $openid;
        $data['form_id'] = $messageFormid;
        $data['goods_id'] = $goods_id;
        $data['goods_name'] = $goods['goods_name'];
        $data['template_id'] = $this->allBaseConfig['day_template_id'];
        $wishingPushMessageKey = sprintf($_W['redis_key'][ 'wishing_push_message'], $this->params['acuid']);
        $this->redis->lPush($wishingPushMessageKey, json_encode($data));

    }

    // 参与抽奖
    public function doPageParticipate( $share_info=[] )
    {
        $uniacid = $this->params['acuid'];
        $openid = $share_info['openid'];
        $goods_id = $id = $share_info['id'];
        $payfu = '';
        $sy_num = 0;

        //删除memberInfo KEY
        $key = sprintf('member_info:%s:%s',$uniacid,$openid);
        $this->redis->del($key);


        ///抽奖码发放锁
        $lockKey = sprintf('cj_lottery_code:%s', $id);
        if ($this->redis->get($lockKey)) {//锁未释放
            $this->doPageParticipate( $is_return = 0 );
            return false;
        }

        $base = $this->allBaseConfig;
        $sql = sprintf("SELECT * FROM %schoujiang_user WHERE uniacid = %s and openid = '{$openid}'", $this->config['mysql']['we7_framework']['tablepre'], $this->params['acuid']);
        $user = $this->_get($this->mysqli, $sql);
        if(empty($user['openid'])||empty($user['nickname'])){
            /// 用户信息不全
//            $this->doPageParticipate( $is_return = 0 );
            return false;
        }

        if ($base['smoke_num'] == 0 || $payfu == 1) {   //不限制
            $sy_num = 1;
        } else {
            if ($user['smoke_num'] > 0) {
                $pata['smoke_num'] = $user['smoke_num'] - 1;
                $sy_num = 1;
            } else if ($user['smoke_share_num'] > 0) {
                $pata['smoke_share_num'] = $user['smoke_share_num'] - 1;
                $sy_num = 1;
            }
        }

        if ($sy_num == 1) {
            /// 参与抽奖有剩余次数
            $sql = sprintf("SELECT * from %schoujiang_record WHERE uniacid = %s  and goods_id = {$goods_id} and openid = '{$openid}'", $this->config['mysql']['we7_framework']['tablepre'], $this->params['acuid']);
            $join = $this->_get($this->mysqli, $sql);
            if (empty($join)) {
                /// 未参与过抽奖
                $sql = sprintf("SELECT * FROM %schoujiang_goods WHERE uniacid = %s AND id = {$goods_id}", $this->config['mysql']['we7_framework']['tablepre'], $this->params['acuid']);
                $ret = $this->_get($this->mysqli, $sql);

                $data['goods_id'] = $id;
                $data['uniacid'] = $uniacid;
                if ($ret['goods_status'] == 1) {
                    $data['goods_name'] = '红包：' . $ret['red_envelope'] . '元';
                } else {
                    $data['goods_name'] = $ret['goods_name'];
                }
                $data['openid'] = $openid;
                $data['nickname'] = $user['nickname'];
                $data['status'] = '0';
                $sql = sprintf("SELECT COUNT(*) AS total FROM %schoujiang_record WHERE uniacid=%s AND goods_id = {$goods_id} AND openid = '{$openid}'", $this->config['mysql']['we7_framework']['tablepre'], $this->params['acuid']);
                $count = $this->_get($this->mysqli, $sql);
                $record = $count['total'];
                $data['formid'] = $share_info['formid'];
                $data['create_time'] = time();
                $data['avatar'] = $user['avatar'];
                if($ret['smoke_set'] == 0){
                    ///按时间开奖
                    if(strtotime($ret['smoke_time'])<time()){
                        ///已开奖
                        $this->_log("奖品{$goods_id}已经开奖");
                        return false;
                    }else{
                        ///未开奖
                        $addResult = $this->_doPageAddRecord($data, $record, $openid, $ret, $share_info);
                        $str['status'] = $addResult['status'];
                    }
                }else if ($ret['smoke_set'] == 1 ) {
                    ///按人数开奖
                    if ($ret['smoke_num'] > $ret['canyunum'] || strtotime($ret['create_time']) < time()-86400*3) {
                        ///未开奖 - 人数未满或奖品日期未到3天
                        $addResult = $this->_doPageAddRecord($data, $record, $openid, $ret, $share_info);
                        $str['status'] = $addResult['status'];
                    } else {
                        ///已开奖
                        $this->_log("奖品{$goods_id}已经开奖");
                        return false;
                    }
                } else {
                    ///手动开奖
                    if(strtotime($ret['create_time']) > time()-86400*3){
                        ///未开奖 - 3天未手动开奖，则自动开奖
                        $addResult = $this->_doPageAddRecord($data, $record, $openid, $ret, $share_info);
                        $str['status'] = $addResult['status'];
                    }else{
                        ///已开奖
                        $this->_log("奖品{$goods_id}已经开奖");
                        return false;
                    }
                }

                if ($str['status'] == 1 && $base['smoke_num'] > 0 && $payfu != 1) {
                    $setStr = '';
                    foreach ($pata as $pataK => $pataV) {
                        $setStr .= $pataK . "='" . $pataV . "',";
                    }
                    $setStr = preg_replace("/,$/", "" , $setStr);
                    $sql = sprintf("update %schoujiang_user set {$setStr} WHERE id={$user['id']} ", $this->config['mysql']['we7_framework']['tablepre']);
                    $this->_query($this->mysqli, $sql);
                }
                $sql = sprintf("SELECT * FROM %schoujiang_default_addr WHERE openid = '{$openid}'", $this->config['mysql']['we7_framework']['tablepre']);
                $defaultAddr = $this->_get($this->mysqli, $sql);
                $str['alert_show'] = $defaultAddr['alert_show'];
                $str['avatar'] = $user['avatar'];
                $str['code'] = $addResult['code'];
            } else {
                /// 已经参与抽奖
                $this->_log("用户已经参与抽奖{$goods_id}");
                return false;
            }
        } else {
            /// 参与抽奖次数上限
            $this->_log("参与抽奖次数上限{$goods_id}");
            return false;
        }


        return $str;
    }

    //添加参与抽奖记录
    private function _doPageAddRecord($data, $record, $openid, $ret, $share_info)
    {
        if ($record < 1) {
            if ($openid) {
                ///抽奖码发放锁
                $lockKey = sprintf('cj_lottery_code:%s', $ret['id']);
                $this->redis->set($lockKey, 1);

                $code = $ret['max_cj_code'] == 0 ? 10000000: $ret['max_cj_code'] + 1;

                $data['codes'] = json_encode([
                    $code => [
                        'type' => 1,
                        'openid' => $openid
                    ]
                ]);
                $data['codes_amount'] = 1;
                $data['ex_create_at'] = date('Y-m-d H:i:s');
                $value = '';
                $field = '';
                foreach ($data as $dataKey => $dataVal) {
                    $field .= '`' . $dataKey . '`,';
                    $value .= "'" . $dataVal . "',";
                }
                $value = preg_replace("/,$/", "" , $value);
                $field = preg_replace("/,$/", "" , $field);
//                $sql = sprintf("INSERT INTO `%schoujiang_record`({$field}) VALUES ({$value})", $this->config['mysql']['we7_framework']['tablepre']);
                $sql = "INSERT INTO `{$this->config['mysql']['we7_framework']['tablepre']}choujiang_record`({$field}) VALUES ({$value})";
                $status = $this->_query($this->mysqli, $sql);
                $canyunum = $ret['canyunum'] + 1;
                if($status) {
                    $sql = sprintf("update %schoujiang_goods set `canyunum`='{$canyunum}', `max_cj_code`='{$code}' WHERE id={$ret['id']} ", $this->config['mysql']['we7_framework']['tablepre']);
                    $this->_query($this->mysqli, $sql);

                    //是否有新用户红包
                    $sql = sprintf("SELECT * FROM %schoujiang_red_packets WHERE openid = '{$openid}'", $this->config['mysql']['we7_framework']['tablepre']);
                    $newUserRed = $this->_get($this->mysqli, $sql);
                    if($newUserRed['is_get_new_money']==0 && $newUserRed['new_money'] > 0){
                        $sql = sprintf("SELECT * FROM %schoujiang_goods WHERE id = '{$data['goods_id']}'", $this->config['mysql']['we7_framework']['tablepre']);
                        $goodsInfo_red = $this->_get($this->mysqli, $sql);
                        /// 是否审核通过的奖品
                        if($goodsInfo_red['audit_status']){
                            $sql = sprintf("update %schoujiang_red_packets set `is_get_new_money`='1', `total_money`=total_money+{$newUserRed['new_money']} WHERE openid={$openid} ", $this->config['mysql']['we7_framework']['tablepre']);
                            $this->_query($this->mysqli, $sql);
                        }
                    }

                    ///通过别人分享进来的 - 分享人增加抽奖码
                    if ($share_info['cj_share_c'] == -1 && $share_info['cj_share_u'] > 0 && $share_info['cj_share_id'] == $ret['id']) {
                        $sql = sprintf("SELECT * FROM %schoujiang_user WHERE id = '{$share_info['cj_share_u']}'", $this->config['mysql']['we7_framework']['tablepre']);
                        $shareUserInfo = $this->_get($this->mysqli, $sql);

                        //奖品发起者只能有一个抽奖码
                        if ($ret['goods_openid'] != $shareUserInfo['openid']) {
                            $sql = sprintf("SELECT * FROM %schoujiang_record WHERE goods_id = '{$ret['id']}' AND openid = '{$shareUserInfo['openid']}'", $this->config['mysql']['we7_framework']['tablepre']);
                            $shareUserRecord = $this->_get($this->mysqli, $sql);

                            $shareUserCodes = json_decode($shareUserRecord['codes'], true);
                            $nextCode = $code + 1;
                            $shareUserCodes[$nextCode] = [
                                'type' => 2,
                                'openid' => $openid
                            ];
                            $upInfo = [
                                'codes' => json_encode($shareUserCodes),
                                'codes_amount' => $shareUserRecord['codes_amount'] + 1
                            ];
                            $sql = sprintf("update %schoujiang_record set `codes`='{$upInfo['codes']}', `codes_amount`='{$upInfo['codes_amount']}' WHERE id={$shareUserRecord['id']}", $this->config['mysql']['we7_framework']['tablepre']);
                            $result = $this->_query($this->mysqli, $sql);

                            if($result) {
                                $sql = sprintf("update %schoujiang_goods set `max_cj_code`='{$nextCode}' WHERE id={$ret['id']}", $this->config['mysql']['we7_framework']['tablepre']);
                                $result = $this->_query($this->mysqli, $sql);
                            }
                        }
                    }
                }
                $reutnrData['code'] = $code;
                $reutnrData['status'] = $status;
                $this->redis->del($lockKey);

                return $reutnrData;
            }
        }

        return false;
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

        if (empty($this->link['mysqli']["mysqli_" . $dbName]) || $this->link['mysqli']["mysqli_" . $dbName]->errno || $this->link['mysqli']["mysqli_" . $dbName]->error) {
            ///
            $this->link['mysqli']["mysqli_" . $dbName] = mysqli_connect($HOST, $USER, $PASSWORD, $DB, $PORT) or die(mysqli_connect_errno() . ":" . mysqli_connect_error());
//            mysqli_set_charset($this->link['mysqli']["mysqli_" . $dbName], $CHARSET);
            mysqli_set_charset($this->link['mysqli']["mysqli_" . $dbName], "utf8mb4");
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
        if (mysqli_errno($mysqli) || mysqli_error($mysqli)) {
            $this->mysqli = $this->_connectMysql("we7_framework");
            $error = "查询失败\n\rsql:" . $sql;
            $mysqlErrorNo = mysqli_errno($mysqli);
            $mysqlError = mysqli_error($mysqli);
            $this->_log("Mysql错误码：{$mysqlErrorNo}，Mysql错误信息：{$mysqlError}");
            $this->_log($error);
            $this->level +=1;
            if ($this->level<4) {
                $new = $this->_get($this->mysqli, $sql);
                return $new;
            }
        } else {
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $response[] = $row;
                }
            } else {
                $error = "查询结果为空\n\rsql:" . $sql;
                $this->_log($error);
            }
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

        if (mysqli_errno($mysqli) || mysqli_error($mysqli)) {
            $this->mysqli = $this->_connectMysql("we7_framework");
            $error = "查询失败\n\rsql:" . $sql;
            $mysqlErrorNo = mysqli_errno($mysqli);
            $mysqlError = mysqli_error($mysqli);
            $this->_log("Mysql错误码：{$mysqlErrorNo}，Mysql错误信息：{$mysqlError}");
            $this->_log($error);
            $this->level +=1;
            if ($this->level<4) {
                $new = $this->_getAll($this->mysqli, $sql);
                return $new;
            }
        } else {
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $response[] = $row;
                }
            } else {
                $error = "查询结果为空\n\rsql:" . $sql;
                $this->_log($error);
            }
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
        if (mysqli_errno($mysqli) || mysqli_error($mysqli)) {
            $this->mysqli = $this->_connectMysql("we7_framework");
            $result = [];
            $error = "查询失败\n\rsql:" . $sql;
            $mysqlErrorNo = mysqli_errno($mysqli);
            $mysqlError = mysqli_error($mysqli);
            $this->_log("Mysql错误码：{$mysqlErrorNo}，Mysql错误信息：{$mysqlError}");
            $this->_log($error);
            $this->level +=1;
            if ($this->level<4) {
                $new = $this->_query($this->mysqli, $sql);
                return $new;
            }
        }
        return $result;
    }

    private function _update($mysqli, $sql)
    {
        $result = mysqli_multi_query($mysqli, $sql);
        $response = array();

        if (mysqli_errno($mysqli) || mysqli_error($mysqli)) {
            $this->mysqli = $this->_connectMysql("we7_framework");
            $error = "查询失败\n\rsql:" . $sql;
            $mysqlErrorNo = mysqli_errno($mysqli);
            $mysqlError = mysqli_error($mysqli);
            $this->_log("Mysql错误码：{$mysqlErrorNo}，Mysql错误信息：{$mysqlError}");
            $this->_log($error);
            $this->level +=1;
            if ($this->level<4) {
                $new = $this->_update($this->mysqli, $sql);
                return $new;
            }
        } else {
            if ($result) {
                while ($row = mysqli_fetch_row($result)) {
                    $response[] = $row;
                }
            } else {
                $error = "查询失败\n\rsql:" . $sql;
                $this->_log($error);
            }
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
$obj = new cjWishingReleaseGoods($params);
$obj->run();
