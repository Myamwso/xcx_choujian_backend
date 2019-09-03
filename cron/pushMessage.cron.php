<?php
if (PHP_SAPI != 'cli')
    exit;
header("Content-Type: text/html; charset=UTF-8");
date_default_timezone_set('Asia/ShangHai');
error_reporting(E_ALL && E_ERROR);
set_time_limit(0);
define('IN_MOBILE', true);
define('ROOT_DIR', str_replace("\\", '/', dirname(dirname(dirname(dirname(__FILE__))))));

class pushMessage
{
    public $redis;
    public $params = [];
    public $redisConfig = [];

    public function __construct($params)
    {
        $this->params = $params;

        require(ROOT_DIR."/addons/choujiang_page/cron/lib/lib.cron.php");
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
    }

    /**
     * 运行
     */
    public function run()
    {
        try {
            $startTime = $this->microtime_float();
            $listKey = sprintf("cj_message_user_list:%s", $this->params['acuid']);
            $mysqli = $this->_connectMysql("we7_framework");
            //最新奖品信息
            $sql = sprintf("SELECT * from %schoujiang_goods WHERE uniacid='%s' and audit_status=1 order by id desc limit 0,1", $this->config['mysql']['we7_framework']['tablepre'], $this->params['acuid']);
            $goods = $this->_get($mysqli, $sql);
            //每日推荐模板id
            $sql = sprintf("SELECT * from %schoujiang_base WHERE uniacid='%s' limit 0,1", $this->config['mysql']['we7_framework']['tablepre'], $this->params['acuid']);
            $templateId = $this->_get($mysqli, $sql)['day_template_id'];
            $num = $this->redis->zsize($listKey);
            if($num>0){
                for($i=0;$i<$num;$i=$i+500){
                    var_dump($i.'###'.$i+499);
                    $this->sendMessage($listKey,$i,$i+499,$goods,$templateId);
                }
            }
            $this->_end($startTime);
        }catch (Exception $e){
            var_dump($e->getFile(),$e->getLine(),$e->getMessage());
        }
    }

    /**
     * @param $listKey
     * @param $start
     * @param $end
     * @param $goods
     * @param $templateId
     */
    private function sendMessage($listKey,$start,$end,$goods,$templateId){
        $openid = $this->redis->zRange($listKey, $start, $end);
        foreach ($openid as $k => $v) {
            $itemKey = sprintf("cj_message_user_item:%s", $v);
            $myFormIds = $this->redis->hGetAll($itemKey);
            if ($goods['id'] > $myFormIds['id']) {
                $formIds = json_decode($myFormIds['form_id'], true);
                $data['access_token'] = $this->_getAccessToken();
                $data['open_id'] = $v;
                $data['form_id'] = $formIds[0]['form_id'];
                $data['goods_id'] = $goods['id'];
                $data['goods_name'] = $goods['goods_name'];
                $data['template_id'] = $templateId;
                $this->push($data);
                unset($formIds[0]);
                $FormId = [
                    'open_id' => $v,
                    'id' => $goods['id'],
                    'form_id' => json_encode(array_values($formIds))
                ];
                $this->redis->hMset($itemKey, $FormId);
            }
        }
    }

    private function push($params)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=' . $params['access_token'];
        $dd = array();
        $dd['form_id'] = $params['form_id'];
        $dd['touser'] = $params['open_id'];
        $content = array(
            "keyword1" => array(
                "value" => $params['goods_name'],
                "color" => "#FF0000"
            ),
            "keyword2" => array(
                "value" => '这个礼物倍有面！【'.$params['goods_name'].'】'.PHP_EOL.PHP_EOL.'免费抽，快来参与',
                "color" => "#FF0000"
            ),
        );
        $dd['template_id'] = $params['template_id'];
        $dd['page'] = 'choujiang_page/drawDetails/drawDetails?id=' . $params['goods_id'];  //点击模板卡片后的跳转页面，仅限本小程序内的页面。支持带参数,该字段不填则模板无跳转
        $dd['data'] = $content;                        //模板内容，不填则下发空模板
        $dd['emphasis_keyword'] = 'keyword1.DATA';    //模板需要放大的关键词，不填则默认无放大
        $result = $this->https_curl_json($url, $dd, 'json');
    }

    private function https_curl_json($url, $data, $type)
    {
        if ($type == 'json') {
            $headers = array("Content-type: application/json;charset=UTF-8", "Accept: application/json", "Cache-Control: no-cache", "Pragma: no-cache");
            $data = json_encode($data);
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $output = curl_exec($curl);
        if (curl_errno($curl)) {
            echo 'Errno' . curl_error($curl);//捕抓异常
        }
        curl_close($curl);
        return $output;
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
     * 获取微信AccessToken
     * @return array|mixed
     */
    private function _getAccessToken()
    {
        $wxapp = WeAccount::create();
        $access_token = $wxapp->getAccessToken();

        return $access_token;
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
}

$acuid = empty($argv[1]) ? 0 : $argv[1];
$params = [
    'acuid' => $acuid
];
if ($params['acuid'] < 1) {
    var_dump("请输入acuid");
    exit;
}

$obj = new pushMessage($params);
$obj->run();
