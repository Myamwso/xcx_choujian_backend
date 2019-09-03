<?php
/**
 * 行业宝模块微站定义
 *
 * @author wangbosichuang
 * @url
 */

defined('IN_IA') or exit('Access Denied');

require_once __DIR__ . "/config.php";
//require_once __DIR__ . "/classes/cjdb.class.php";
//new CJDB('master');
require_once __DIR__ . "/common.func.php";


global $_GPC;
///新入口
$object = $_GPC['state'];
$action = $_GPC['do'];
if(preg_match('/cj_/',$object)){
    require_once __DIR__ . "/framework/autoload.php";
    $obj = new $object();
    $obj->$action();
    exit;
}

pdo_run_cj("set names utf8mb4");

class Choujiang_pageModuleSite extends WeModuleSite
{
    protected $attachurl;
    private $floorNum = 0.2;

    public function __construct()
    {
        global $_W;
        $uniacid = $_W['uniacid'];
        $this->baseConfig = $item = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_base') . " WHERE uniacid = :uniacid", array(':uniacid' => $uniacid));
        $priceArr =explode('-',$this->baseConfig['wechat_rand_price']);
        $priceList =json_decode($this->baseConfig['probability_num'],true);
        $this->baseConfig['loopPrice']=['min'=>$priceArr[0],'max'=>$priceArr[1],'floorNum'=>$this->floorNum,'pirceList'=>$priceList];
        if ($item['type']) {
            $this->attachurl = $item['url'];
        } else {
            $this->attachurl = $_W['attachurl'];
        }


    }

    /**
     * 支付回调
     */
    public function payResult($log)
    {
        file_put_contents( '/4T/www/linsd/WeEngine/addons/choujiang_page/uuuu.log', "site支付回调测试" . date('Y-m-d h:i:s', time()) . "\n", FILE_APPEND);
    }

    /**
     *  红包补发，状态修改
     */
    public function doWebchoujiang_red_reissue()
    {
        global $_W, $_GPC;
        $op = $_GPC['op'];
        $opArr = ['restatus','reissue','info', 'reissueInfo'];

        if(!in_array($op,$opArr)){
            $res=[
                'error' => 1,
                'message'=> "非法操作！"
            ];
            return json_encode($res);
        }

        $res=[
            'error' => 1,
            'message'=> "此红包状态支付成功，非法操作！"
        ];
        $id = intval($_GPC['id']);
        $redDate = 0;
        if(isset($_GPC['redDate'])){
            $redDate = $_GPC['redDate'];
        }

        //判断新数据还是据数据
        if($redDate==1){
            if($op == 'info'){
                $payInfo = pdo_get_cj('choujiang_red_packets_record_old', ['id' => $id]);
            }else{
                $payInfo = pdo_get_cj('choujiang_red_packets_old', ['id' => $id]);
            }
        }else{
            $payInfo = pdo_get_cj('choujiang_red_packets_record', ['id' => $id]);
        }


        $writeTime = date("Y-m-d H:i:s");

        if($redDate==1){
            ///旧数据补发红包功能

            //补发红包
            $res=[
                'error' => 1,
                'message'=> "此用户无余额，补发失败，非法操作！"
            ];
            if ($op == 'reissue') {
                $UserInfo = pdo_get_cj('choujiang_user', ['openid' => $payInfo['openid']]);
                if($UserInfo['wechat_blacklist']==1){
                    $res = [
                        'error' => 1,
                        'message' => "黑名单用户不可补发红包！"
                    ];
                    return json_encode($res);
                }
                if($payInfo['total_money']-$payInfo['pay_money'] > 0 ) { /// 有红包余额需要补发
                    $payResult = $this->reissueRedPacketsOld($id);
                    if(0==$payResult['fail']){

                        $UpDataTotal = pdo_update_cj("choujiang_red_packets_old",['pay_money +='=>$payResult['payment_price']],['openid'=>$payInfo['openid']]);
                        if(!$UpDataTotal){
                            $res = [
                                'error' => 1,
                                'message' => "红包补发成功，修改用户支付总额失败！"
                            ];

                        }else{
                            $res = [
                                'error' => 0,
                                'message' => "红包补发成功！"
                            ];
                        }
                        return json_encode($res);

                    }else{
                        $res = [
                            'error' => 1,
                            'message' => "补发红包失败！"
                        ];
                        return json_encode($res);
                    }
                }
            }

        }else{




            //修改红包支付状态
            if ($op == 'restatus') {
                $UserInfo = pdo_get_cj('choujiang_user', ['openid' => $payInfo['openid']]);
                if($UserInfo['wechat_blacklist']==1){
                    $res = [
                        'error' => 1,
                        'message' => "黑名单用户提现状态不可修改！"
                    ];
                    return json_encode($res);
                }
                if($payInfo['pay_status']==2 || ($payInfo['pay_status']==1 && is_null($payInfo['extact']))) { /// 支付记录有误需要修复
                    if($payInfo['pay_status']==2){ ///修改为成功状态
                        $dataUpRecord = ['pay_status'=>1, 'update_at'=>$writeTime];
                    }else{ ///写入扩展信息
                        $extact = json_encode(['reset_status'=>"客服修改状态",'operator'=>$_W['username']]);
                        $dataUpRecord = ['extact'=>$extact, 'update_at'=>$writeTime];
                    }
                    $UpData = pdo_update_cj("choujiang_red_packets_record",$dataUpRecord,['id'=>$id]);
                    if($UpData){
                        $res = [
                            'error' => 0,
                            'message' => "信息修改成功！"
                        ];
                    }else{
                        $res = [
                            'error' => 1,
                            'message' => "修改状态失败！"
                        ];
                        return json_encode($res);
                    }
                    if($payInfo['pay_types']==1){

                    /// 补发分享用户金额
                    $newUserTotal = pdo_get_cj('choujiang_red_packets', ['openid' => $payInfo['openid']]);
                    $shareUser = pdo_get_cj('choujiang_user_share', ['user_id' => $newUserTotal['uid']]);
                    /// 分享者红包在路上转到可提现
                    $UpDataNewTotal = pdo_update_cj("choujiang_red_packets",['is_get_new_money'=>0, 'new_money'=>$shareUser['new_user_money']],['openid'=>$payInfo['openid']]);
                    $updateShareUser = pdo_update_cj("choujiang_red_packets",['total_money +='=>$shareUser['share_money'], 'share_success +='=>$shareUser['share_money'], 'update_at'=>$writeTime],['uid'=>$shareUser['share_user_id']]);
                    if(!$updateShareUser){
                        $res = [
                            'error' => 1,
                            'message' => "修改分享金额失败！"
                        ];
                        return json_encode($res);
                    }
                }
            }
        }
        //重新支付失败红包
        if ($op == 'reissue') {
            $UserInfo = pdo_get_cj('choujiang_user', ['openid' => $payInfo['openid']]);
            if($UserInfo['wechat_blacklist']==1){
                $res = [
                    'error' => 1,
                    'message' => "黑名单用户不可补发红包！"
                ];
                return json_encode($res);
            }
            if($payInfo['pay_status']==2 || ($payInfo['pay_status']==1 && is_null($payInfo['extact']))) { /// 红包支付失败需要补发
                $payResult = $this->reissueRedPackets($id);
                if(0==$payResult['fail']){
                    if($payInfo['pay_status']==2){ ///修改为成功状态
                        $dataUpRecord = ['pay_status'=>1, 'update_at'=>$writeTime];
                    }else{ ///写入扩展信息
                        $extact = json_encode(['reset_status'=>"客服补发红包，并修改状态"]);
                        $dataUpRecord = ['extact'=>$extact, 'update_at'=>$writeTime];
                    }
                    $UpData = pdo_update_cj("choujiang_red_packets_record",$dataUpRecord,['id'=>$id]);
                    if($UpData){
                        $res = [
                            'error' => 0,
                            'message' => "补发成功，信息修改成功！"
                        ];
                    }else{
                        $res = [
                            'error' => 1,
                            'message' => "红包补发成功，修改状态失败！"
                        ];
                        return json_encode($res);
                    }
                    if($payInfo['pay_types']==1){

                        /// 补发分享用户金额
                        $newUserTotal = pdo_get_cj('choujiang_red_packets', ['openid' => $payInfo['openid']]);
                        $shareUser = pdo_get_cj('choujiang_user_share', ['user_id' => $newUserTotal['uid']]);
                        /// 分享者红包在路上转到可提现
                        $UpDataNewTotal = pdo_update_cj("choujiang_red_packets",['is_get_new_money'=>0, 'new_money'=>"-".$shareUser['new_user_money']],['openid'=>$payInfo['openid']]);
                        $updateShareUser = pdo_update_cj("choujiang_red_packets",['total_money +='=>$shareUser['share_money'], 'share_success +='=>$shareUser['share_money'], 'update_at'=>$writeTime],['uid'=>$shareUser['share_user_id']]);
                        if(!$updateShareUser){
                            $res = [
                                'error' => 1,
                                'message' => "红包补发成功，修改分享金额失败！"
                            ];
                            return json_encode($res);
                        }
                    }
                }else{
                    $res = [
                        'error' => 1,
                        'message' => "补发红包失败！".$payResult['payment_str']
                    ];
                    return json_encode($res);
                }
            }
        }



        }

        //详细信息
        if ($op == 'info') {
            if($payInfo){
                $res = [
                    'error' => 0,
                    'message' => json_decode($payInfo['extact'])
                ];
            }
        }

        //补发详细信息
        if ($op == 'reissueInfo') {
            if($payInfo){
                $receiveInfo = pdo_get_cj('choujiang_red_packets_record_repeat', ['old_out_trade_no' => $payInfo['out_trade_no']]);
                if($receiveInfo){
                    $receiveInfo['extact'] = json_decode($receiveInfo['extact']);
                    $res = [
                        'error' => 0,
                        'message' => $receiveInfo
                    ];
                }
            }
        }
        return json_encode($res);

    }



    /**
     * 企业付款接口:失败红包补发接口
     */
    public function reissueRedPackets($id=0)
    {
        if($id==0){
            return false;
        }
        global $_W;
        require_once "wxpay.php";

        $maxPay = 100;//最大支付金额为100元
        $sslcert = __DIR__."/../".$this->baseConfig['upfile'];
        $sslkey = __DIR__."/../".$this->baseConfig['keypem'];

        $appid = $this->baseConfig['appid'];
        $mch_id = $this->baseConfig['mch_id'];
        $key = $this->baseConfig['appkey'];
        $body = $this->baseConfig['title'];

        $payInfo = pdo_get_cj('choujiang_red_packets_record', array('id' => $id));
        $openid = $payInfo['openid'];

        $redis = connect_redis();
        $lockKeys = sprintf("cj_pay_record_lock:%s",$openid);

        ///验证用户是否有支付信息正在处理，防止重复提交
        if($redis->exists($lockKeys)){
            $res =[
                'fail' =>1,
                'payment_time' =>date("Y-m-d H:i:s"),
                'payment_str' =>"信息重复提交，请等待上一个订单支付完成。",
            ];
            return $res;
        }
        ///没有用户支付信息正在处理设置处理锁
        $redis->set($lockKeys,1);
        $redis->expire($lockKeys,300);


        $out_trade_no = substr(md5($mch_id . $openid . time() . rand(10000,99999)),4);


        $desc = [
            'desc'=>"新用户专享红包",
            'sslcert'=>$sslcert,
            'sslkey'=>$sslkey,
        ];

        $total_fee = $payInfo['receive_money']*100;
        $repeatPayTotal = pdo_get_cj('choujiang_red_packets', array('openid' => $openid));


        if((date("Y-m-d")==date("Y-m-d",strtotime($repeatPayTotal['update_at']))) && $repeatPayTotal['get_time']>9){
            $redis->del($lockKeys);
            $res =[
                'fail' =>1,
                'payment_time' =>date("Y-m-d H:i:s"),
                'payment_str' =>"超过今日红包领取上限",
            ];
//            file_put_contents(__DIR__.'/choujiang_pay.log', "-超过今日红包领取上限".$redis->get($lockKeys)."\n", FILE_APPEND);
            return $res;
        }

        $writeTime = date("Y-m-d H:i:s");
        $realip = '';
        if(isset($_SERVER)){
            if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])){
                $realip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            }elseif(isset($_SERVER['HTTP_CLIENT_IP'])) {
                $realip = $_SERVER['HTTP_CLIENT_IP'];
            }else{
                $realip = $_SERVER['REMOTE_ADDR'];
            }
        }

        $data = [
            'openid' => $openid,
            'receive_money' => $payInfo['receive_money'],
            'all_balance' =>$payInfo['all_balance'],
            'balance' =>$payInfo['balance'],
            'out_trade_no' =>$out_trade_no,
            'old_out_trade_no' =>$payInfo['out_trade_no'],
            'pay_status' =>1,
            'pay_types' =>$payInfo['pay_types'],
            'operator' =>$_W['username'],
            'ip_address' =>$realip,
            'create_at' =>$writeTime,
            'update_at' =>$writeTime,
        ];

        $insertData = pdo_insert_cj('choujiang_red_packets_record_repeat',$data);
//        if($insertData){
//            file_put_contents(__DIR__.'/choujiang_pay.log', "-choujiang_red_packets_record插入成功", FILE_APPEND);
//        }else{
//            file_put_contents(__DIR__.'/choujiang_pay.log', "-choujiang_red_packets_record插入失败", FILE_APPEND);
//        }
        $redRecordId = pdo_insertid_cj();


        $weixinpay = new WeixinPay($appid, $openid, $mch_id, $key, $out_trade_no, $body, $total_fee);
        $return = $weixinpay->transfers($desc);

        ///判断支付情况
        if($return['result_code']=='SUCCESS'&& $return['return_code']=='SUCCESS'){
            $res =[
                'fail' =>0,
                'payment_time' =>$return['payment_time'],
                'payment_price' =>number_format($total_fee/100, 2),
            ];
//            pdo_update_cj("choujiang_red_packets",['is_get_new_money' => 0, 'update_at'=>$writeTime],['openid'=>$openid]);
        }else{
            $res =[
                'fail' =>1,
                'payment_time' =>date("Y-m-d H:i:s"),
            ];
            if($return['err_code']=='V2_ACCOUNT_SIMPLE_BAN'){
                $res['payment_str'] ="非实名用户账号，红包不可发放";
            }else{
                $res['payment_str'] ="红包发放失败";
            }

//            //载入日志函数
//            load()->func('logging');
//            //记录支付失败信息
//            logging_run("支付失败记录：用户openid{$openid}，支付单号：{$out_trade_no}，支付金额：{$total_fee}分，支付标题：{$body}，用户登入信息：{$_GPC['c_auth']}");
        }
        $data = [
            'extact' =>json_encode($return),
        ];
        if(isset($return['err_code']) || !($return['result_code']=='SUCCESS'&& $return['return_code']=='SUCCESS')){
            $data['pay_status'] = 2;
        }
        $upData = pdo_update_cj('choujiang_red_packets_record_repeat',$data,['id'=>$redRecordId]);

//        file_put_contents(__DIR__.'/choujiang_pay.log', "\n", FILE_APPEND);

        ///支付完成删除锁
        $redis->del($lockKeys);
        return $res;
    }


    /**
     * 企业付款接口:旧数据红包补发接口
     */
    public function reissueRedPacketsOld($id=0)
    {
        if($id==0){
            return false;
        }
        global $_W;
        require_once "wxpay.php";

        $maxPay = 100;//最大支付金额为100元
        $sslcert = __DIR__."/../".$this->baseConfig['upfile'];
        $sslkey = __DIR__."/../".$this->baseConfig['keypem'];

        $appid = $this->baseConfig['appid'];
        $mch_id = $this->baseConfig['mch_id'];
        $key = $this->baseConfig['appkey'];
        $body = $this->baseConfig['title'];

        $payInfo = pdo_get_cj('choujiang_red_packets_old', array('id' => $id));
        $openid = $payInfo['openid'];

        $redis = connect_redis();
        $lockKeys = sprintf("cj_pay_record_lock:%s",$openid);

        ///验证用户是否有支付信息正在处理，防止重复提交
        if($redis->exists($lockKeys)){
            $res =[
                'fail' =>1,
                'payment_time' =>date("Y-m-d H:i:s"),
                'payment_str' =>"信息重复提交，请等待上一个订单支付完成。",
            ];
            return $res;
        }
        ///没有用户支付信息正在处理设置处理锁
        $redis->set($lockKeys,1);
        $redis->expire($lockKeys,300);

        $out_trade_no = substr(md5($mch_id . $openid . time() . rand(10000,99999)),4);


        $desc = [
            'desc'=>"新用户专享红包",
            'sslcert'=>$sslcert,
            'sslkey'=>$sslkey,
        ];

        $total_fee = ($payInfo['total_money']-$payInfo['pay_money'])*100;
        if($total_fee>$maxPay*100){
            $total_fee = $maxPay*100;
        }
        $repeatPayTotal = pdo_get_cj('choujiang_red_packets', array('openid' => $openid));


        if((date("Y-m-d")==date("Y-m-d",strtotime($repeatPayTotal['update_at']))) && $repeatPayTotal['get_time']>9){
            $redis->del($lockKeys);
            $res =[
                'fail' =>1,
                'payment_time' =>date("Y-m-d H:i:s"),
                'payment_str' =>"超过今日红包领取上限",
            ];
//            file_put_contents(__DIR__.'/choujiang_pay.log', "-超过今日红包领取上限".$redis->get($lockKeys)."\n", FILE_APPEND);
            return $res;
        }

        $writeTime = date("Y-m-d H:i:s");
        $realip = '';
        if(isset($_SERVER)){
            if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])){
                $realip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            }elseif(isset($_SERVER['HTTP_CLIENT_IP'])) {
                $realip = $_SERVER['HTTP_CLIENT_IP'];
            }else{
                $realip = $_SERVER['REMOTE_ADDR'];
            }
        }

        $data = [
            'openid' => $openid,
            'receive_money' => $total_fee/100,
            'all_balance' =>$payInfo['total_money'],
            'balance' =>$payInfo['total_money']-$payInfo['pay_money']-$total_fee/100,
            'out_trade_no' =>$out_trade_no,
            'old_out_trade_no' =>'',
            'pay_status' =>1,
            'pay_types' =>3,
            'operator' =>$_W['username'],
            'ip_address' =>$realip,
            'create_at' =>$writeTime,
            'update_at' =>$writeTime,
        ];

        $insertData = pdo_insert_cj('choujiang_red_packets_record_repeat_old',$data);
//        if($insertData){
//            file_put_contents(__DIR__.'/choujiang_pay.log', "-choujiang_red_packets_record插入成功", FILE_APPEND);
//        }else{
//            file_put_contents(__DIR__.'/choujiang_pay.log', "-choujiang_red_packets_record插入失败", FILE_APPEND);
//        }
        $redRecordId = pdo_insertid_cj();


        $weixinpay = new WeixinPay($appid, $openid, $mch_id, $key, $out_trade_no, $body, $total_fee);
        $return = $weixinpay->transfers($desc);



        ///判断支付情况
        if($return['result_code']=='SUCCESS'&& $return['return_code']=='SUCCESS'){
            $res =[
                'fail' =>0,
                'payment_time' =>$return['payment_time'],
                'payment_price' =>number_format($total_fee/100, 2),
            ];
//            pdo_update_cj("choujiang_red_packets",['is_get_new_money' => 0, 'update_at'=>$writeTime],['openid'=>$openid]);
        }else{
            $res =[
                'fail' =>1,
                'payment_time' =>date("Y-m-d H:i:s"),
            ];
            if($return['err_code']=='V2_ACCOUNT_SIMPLE_BAN'){
                $res['payment_str'] ="非实名用户账号，红包发放失败";
            }else{
                $res['payment_str'] ="红包发放失败";
            }

//            //载入日志函数
//            load()->func('logging');
//            //记录支付失败信息
//            logging_run("支付失败记录：用户openid{$openid}，支付单号：{$out_trade_no}，支付金额：{$total_fee}分，支付标题：{$body}，用户登入信息：{$_GPC['c_auth']}");
        }
        $data = [
            'extact' =>json_encode($return),
        ];
        if(isset($return['err_code']) || !($return['result_code']=='SUCCESS'&& $return['return_code']=='SUCCESS')){
            $data['pay_status'] = 2;
        }
        $upData = pdo_update_cj('choujiang_red_packets_record_repeat_old',$data,['id'=>$redRecordId]);

//        file_put_contents(__DIR__.'/choujiang_pay.log', "\n", FILE_APPEND);
        $redis->del($lockKeys);
        return $res;
    }


    public function doWebChoujiang_brand()
    {
        global $_GPC;
        if ($_GPC['op'] == 'content') {
            $total = pdo_fetch_cj('SELECT count(*) as count FROM ' . tablename_cj('choujiang_brand') . " where 1");
            $result = pdo_getall_cj("choujiang_brand", [], [], '', ["update_at {$_GPC['sort']}"],[$_GPC['page'], $_GPC['pageNum']]);

            echo json_encode([
                'code' => 10000,
                'msg' => 'success',
                'total' =>(int)$total['count'],
                'data' => $result
            ]);
            exit;
        }

        include $this->template('choujiang_brand');
    }


    /**
     * 获取图片路径
     * @param $img
     * @return bool|string
     */
    public function getImgPath($img){
        if (strpos($img, "?") == true && strpos($img, ".com/") == true) {
            $start = strpos($img, ".com/") + 5;
            $length = strpos($img, "?") - $start;
            $img = strpos($img, "?") == false ? substr($img, $start) : substr($img, $start, $length);
        }
        return $img;
    }

    /**
     * 返回img 数组
     * @param $str json格式 或字符串
     * @return array|mixed
     */
    public function getImgArray($str){
        $img_array = json_decode($str);
        if(!$img_array){
            $img_array[] = $str;
        }
        foreach ($img_array as $k=>$v){
            $imgUrl[] = $this->getImage($v);
        }
        return $imgUrl;
    }

    //红包列表
    public function doWebChoujiang_red_packets(){
        global $_GPC,$_W;
        $uniacid = $_W['uniacid'];
        $op = $_GPC['op'] ? $_GPC['op']: 'content';
        $pindex = max(1, intval($_GPC['page']));
        $pindex1 = max(1, intval($_GPC['page1']));
        $psize = 10;//每页显示个数
        $field = $_GPC['field'];
        $types = $_GPC['types'];
        $keyword = $_GPC['keyword'];
        $sort = $_GPC['sort'];
        $redDate = 0;
        if(isset($_GPC['redDate'])){
            $redDate = $_GPC['redDate'];
        }
        if($redDate == 1){
            //
            if ($op=='content') {

                if(!$keyword){
                    $condition = '';
                }else{
                    if($field==1){
                        $condition = "where U.openid='{$keyword}'";
                    }elseif ($field==2) {
                        $condition = "where U.nickname='{$keyword}'";
                    }
                }
                $sql = "SELECT R.*, U.nickname, U.avatar FROM " . tablename_cj('choujiang_red_packets_old') . " AS R LEFT JOIN " . tablename_cj('choujiang_user') . " AS U ON R.uid = U.id {$condition} order by id desc limit " . ($pindex - 1) * $psize . ',' . $psize;
                $sql1 = "SELECT count(*) FROM " . tablename_cj('choujiang_red_packets_old') . " AS R LEFT JOIN " . tablename_cj('choujiang_user') . " AS U ON R.uid = U.id {$condition}";
                $packetsList = pdo_fetchall_cj($sql);
                $total = pdo_fetchcolumn_cj($sql1);
                foreach ($packetsList as $k => $v) {
//                    $user = pdo_get_cj('choujiang_user',array('openid'=>$v['openid']));
//                    $packetsList[$k]['nickname'] = $user['nickname'];
                    $packetsList[$k]['avatar'] = $this->getImage($packetsList[$k]['avatar']);
                    $packetsList[$k]['balance'] = number_format($packetsList[$k]['total_money']-$packetsList[$k]['pay_money'], 2);
                }
                $pager = pagination($total, $pindex, $psize);
            }


            ///红包补发列表
            if ($op=='repeatList') {

                if(!$keyword){
                    $condition = 'where 1 ';
                }else{
                    if($field==1){
                        $condition = "where U.openid='{$keyword}' ";
                    }elseif ($field==2) {
                        $condition = "where U.nickname='{$keyword}' ";
                    }elseif ($field==3) {
                        $condition = "where R.out_trade_no='{$keyword}' ";
                    }
                }

                $sql = "SELECT R.*, U.nickname, U.avatar,U.id AS uid FROM " . tablename_cj('choujiang_red_packets_record_repeat_old') . " AS R LEFT JOIN " . tablename_cj('choujiang_user') . " AS U ON R.openid = U.openid {$condition} order by R.id desc limit " . ($pindex - 1) * $psize . ',' . $psize;
                $sql1 = "SELECT COUNT(*) FROM " . tablename_cj('choujiang_red_packets_record_repeat_old') . " AS R LEFT JOIN " . tablename_cj('choujiang_user') . " AS U ON R.openid = U.openid {$condition}";
                $packetsList = pdo_fetchall_cj($sql);
                $total = pdo_fetchcolumn_cj($sql1);
                foreach ($packetsList as $k => $v) {

                    $packetsList[$k]['total_money'] = number_format(abs($packetsList[$k]['new_money']), 2);
                    $packetsList[$k]['pay_money'] = $packetsList[$k]['new_money']>0 ? number_format(0, 2) : number_format(abs($packetsList[$k]['new_money']), 2);
                    $packetsList[$k]['extact'] = json_decode($packetsList[$k]['extact'],true);
                    $packetsList[$k]['avatar'] = $this->getImage($packetsList[$k]['avatar']);
                }
            }



            if ($op == 'detail') {

                $userInfo['id'] = $_GPC['uid'];


                $sql = "SELECT R.*, RR.id AS new_id FROM " . tablename_cj('choujiang_red_packets_record_old') . " AS R LEFT JOIN " . tablename_cj('choujiang_red_packets_record_repeat_old') . " AS RR ON RR.openid=R.openid where R.openid='{$_GPC['openid']}' AND R.really_require=1 OR R.openid='{$_GPC['openid']}' AND R.pay_status=1 AND R.extact IS NOT null AND R.create_at<'2018-09-07' order by R.id desc limit " . ($pindex - 1) * $psize . ',' . $psize;
                $sql1 = "SELECT count(*) from " . tablename_cj('choujiang_red_packets_record_old') . "  where openid='{$_GPC['openid']}' AND really_require=1 OR openid='{$_GPC['openid']}' AND pay_status=1 AND extact IS NOT null AND create_at<'2018-09-07'";
                $record = pdo_fetchall_cj($sql);
                $total = pdo_fetchcolumn_cj($sql1);
                $pager = pagination_cj($total, $pindex, $psize);

                $sql = "SELECT * FROM " . tablename_cj('choujiang_user_share_old') . " where share_user_id='{$userInfo['id']}' order by id desc limit " . ($pindex1 - 1) * $psize . ',' . $psize;
                $sql1 = "SELECT count(*) FROM " . tablename_cj('choujiang_user_share_old') . " where share_user_id='{$userInfo['id']}'";
                $share = pdo_fetchall_cj($sql);
                $total1 = pdo_fetchcolumn_cj($sql1);
                $pager1 = pagination_cj($total1, $pindex1, $psize, '', array('before' => 5, 'after' => 4, 'ajaxcallback' => '', 'callbackfuncname' => ''), 'page1') ;

                $sql = "SELECT * FROM " . tablename_cj('choujiang_user_share_old') . " where user_id='{$userInfo['id']}'";
                $userNew = pdo_fetchall_cj($sql);

            }
        }else{
            if ($op=='content') {

                if(!$keyword){
                    $condition = 'where 1 ';
                }else{
                    if($field==1){
                        $condition = "where openid='{$keyword}'";
                    }elseif ($field==2) {
                        $condition = "where nickname='{$keyword}'";
                    }
                }
                if($sort){
                    if($sort == 1){
                        $OrderBy = " ORDER BY total_money asc";
                    }elseif($sort == 2){
                        $OrderBy = " ORDER BY total_money desc";
                    }elseif($sort == 3){
                        $OrderBy = " ORDER BY pay_money asc";
                    }elseif($sort == 4){
                        $OrderBy = " ORDER BY pay_money desc";
                    }
                }else{
                    $OrderBy = " order by id desc";
//                    $sort = 0;
                }

                $sql = "SELECT * FROM " . tablename_cj('choujiang_red_packets') . " {$condition} " . $OrderBy . " limit " . ($pindex - 1) * $psize . ',' . $psize;
                $sql1 = "SELECT count(*) from " . tablename_cj('choujiang_red_packets') . " {$condition}";
                $packetsList = pdo_fetchall_cj($sql);
                $total = pdo_fetchcolumn_cj($sql1);
                foreach ($packetsList as $k => $v) {
                    $user = pdo_get_cj('choujiang_user',array('openid'=>$v['openid']));
                    $packetsList[$k]['nickname'] = $user['nickname'];
                    $packetsList[$k]['avatar'] = $this->getImage($user['avatar']);
                }
            }

        if ($op=='newContent') {

            if(!$keyword){
                $condition = 'where 1';
            }else{
                if($field==1){
                    $condition = "where openid='{$keyword}'";
                }elseif ($field==2) {
                    $condition = "where nickname='{$keyword}'";
                }
            }
            $sql = "SELECT * FROM " . tablename_cj('choujiang_red_packets') . " {$condition} and new_money!=0 order by id desc limit " . ($pindex - 1) * $psize . ',' . $psize;
            $sql1 = "SELECT count(*) from " . tablename_cj('choujiang_red_packets') . " {$condition} and new_money!=0";
            $packetsList = pdo_fetchall_cj($sql);
            $total = pdo_fetchcolumn_cj($sql1);
            foreach ($packetsList as $k => $v) {
                $user = pdo_get_cj('choujiang_user',array('openid'=>$v['openid']));
                $packetsList[$k]['nickname'] = $user['nickname'];
                $packetsList[$k]['total_money'] = number_format(abs($packetsList[$k]['new_money']), 2);
                $packetsList[$k]['pay_money'] = $packetsList[$k]['new_money']>0 ? number_format(0, 2) : number_format(abs($packetsList[$k]['new_money']), 2);
                $packetsList[$k]['avatar'] = $this->getImage($user['avatar']);
            }
        }

            ///红包发放列表
            if ($op=='allRecord') {

                if(!$keyword){
                    $condition = 'where 1 ';
                }else{
                    if($field==1){
                        $condition = "where U.openid='{$keyword}' ";
                    }elseif ($field==2) {
                        $condition = "where U.nickname='{$keyword}' ";
                    }elseif ($field==3) {
                        $condition = "where R.out_trade_no='{$keyword}' ";
                    }
                }
                if(1==$types){
                    $condition .= " AND R.pay_types=1 ";
                }elseif(2==$types){
                    $condition .= " AND R.pay_types=2 ";
                }elseif(3==$types){
                    $condition .= " AND R.pay_status=1";
                }elseif(4==$types){
                    $condition .= " AND R.pay_status=2";
                }
                $sql = "SELECT R.*, U.nickname, U.avatar,U.wechat_blacklist,U.id AS uid FROM " . tablename_cj('choujiang_red_packets_record') . " AS R LEFT JOIN " . tablename_cj('choujiang_user') . " AS U ON R.openid = U.openid {$condition} order by R.id desc limit " . ($pindex - 1) * $psize . ',' . $psize;
                $sql1 = "SELECT count(*) FROM " . tablename_cj('choujiang_red_packets_record') . " AS R  LEFT JOIN " . tablename_cj('choujiang_user') . " AS U ON R.openid = U.openid {$condition}";
                $packetsList = pdo_fetchall_cj($sql);
                $total = pdo_fetchcolumn_cj($sql1);
                foreach ($packetsList as $k => $v) {
                    $repeatInfo = pdo_get_cj('choujiang_red_packets_record_repeat',array('old_out_trade_no'=>$v['out_trade_no']));
                    $packetsList[$k]['new_id'] = 0;
                    if($repeatInfo){
                        $packetsList[$k]['new_id'] = $repeatInfo['id'];
                    }
//                $user = pdo_get_cj('choujiang_user',array('openid'=>$v['openid']));
//                $packetsList[$k]['nickname'] = $user['nickname'];
                    $packetsList[$k]['total_money'] = number_format(abs($packetsList[$k]['new_money']), 2);
                    $packetsList[$k]['pay_money'] = $packetsList[$k]['new_money']>0 ? number_format(0, 2) : number_format(abs($packetsList[$k]['new_money']), 2);
                    $packetsList[$k]['extact'] = json_decode($packetsList[$k]['extact'],true);
                    $packetsList[$k]['avatar'] = $this->getImage($packetsList[$k]['avatar']);
                }
            }

            ///红包补发列表
            if ($op=='repeatList') {

                if(!$keyword){
                    $condition = 'where 1 ';
                }else{
                    if($field==1){
                        $condition = "where U.openid='{$keyword}' ";
                    }elseif ($field==2) {
                        $condition = "where U.nickname='{$keyword}' ";
                    }
                }
                if(1==$types){
                    $condition .= " AND R.pay_types=1 ";
                }elseif(2==$types){
                    $condition .= " AND R.pay_types=2 ";
                }elseif(3==$types){
                    $condition .= " AND R.pay_status=1";
                }elseif(4==$types){
                    $condition .= " AND R.pay_status=2";
                }
                $sql = "SELECT R.*, U.nickname, U.avatar,U.id AS uid FROM " . tablename_cj('choujiang_red_packets_record_repeat') . " AS R LEFT JOIN " . tablename_cj('choujiang_user') . " AS U ON R.openid = U.openid {$condition} order by R.id desc limit " . ($pindex - 1) * $psize . ',' . $psize;
                $sql1 = "SELECT COUNT(*) FROM " . tablename_cj('choujiang_red_packets_record_repeat') . " AS R LEFT JOIN " . tablename_cj('choujiang_user') . " AS U ON R.openid = U.openid {$condition}";
                $packetsList = pdo_fetchall_cj($sql);
                $total = pdo_fetchcolumn_cj($sql1);
                foreach ($packetsList as $k => $v) {
//                $repeatInfo = pdo_get_cj('choujiang_red_packets_record_repeat',array('old_out_trade_no'=>$v['out_trade_no']));
//                $packetsList[$k]['new_id'] = 0;
//                if($repeatInfo){
//                    $packetsList[$k]['new_id'] = $repeatInfo['id'];
//                }
//                $user = pdo_get_cj('choujiang_user',array('openid'=>$v['openid']));
//                $packetsList[$k]['nickname'] = $user['nickname'];
                    $packetsList[$k]['total_money'] = number_format(abs($packetsList[$k]['new_money']), 2);
                    $packetsList[$k]['pay_money'] = $packetsList[$k]['new_money']>0 ? number_format(0, 2) : number_format(abs($packetsList[$k]['new_money']), 2);
                    $packetsList[$k]['extact'] = json_decode($packetsList[$k]['extact'],true);
                    $packetsList[$k]['avatar'] = $this->getImage($packetsList[$k]['avatar']);
                }
            }

            ///红包统计
            if ($op == 'CashStat') {
                $NowDay = date('Y-m-d') . " 00:00:00";
                $todaySuccessMoney = pdo_fetch_cj("SELECT SUM(receive_money) as total FROM " .tablename_cj('choujiang_red_packets_record'). " where pay_status = 1 and create_at >= '{$NowDay}';");
                $todayFailMoney = pdo_fetch_cj("SELECT SUM(receive_money) as total FROM " .tablename_cj('choujiang_red_packets_record'). " where pay_status = 2 and create_at >= '{$NowDay}';");
            }

            if ($op == 'detail') {

                $sql = "SELECT R.*, RR.id AS new_id FROM " . tablename_cj('choujiang_red_packets_record') . " AS R LEFT JOIN " . tablename_cj('choujiang_red_packets_record_repeat') . " AS RR ON RR.old_out_trade_no=R.out_trade_no where R.openid='{$_GPC['openid']}' order by R.id desc limit " . ($pindex - 1) * $psize . ',' . $psize;
                $sql1 = "SELECT count(*) from " . tablename_cj('choujiang_red_packets_record') . "  where openid='{$_GPC['openid']}'";
                $record = pdo_fetchall_cj($sql);
                foreach($record as $k => $v){
                    $userInfo = pdo_get_cj('choujiang_user',['openid'=>$_GPC['openid']]);
                    $record[$k]['wechat_blacklist']=$userInfo['wechat_blacklist'];
                }
                $total = pdo_fetchcolumn_cj($sql1);

            }
            $pager = pagination($total, $pindex, $psize);
        }

        include $this->template('choujiang_red_packets');
    }

    /**
     *  晒单管理
     */
    public function doWebChoujiang_share_order()
    {
        global $_W, $_GPC;
        $op = $_GPC['op'];
        $op = $op ? $op : 'content';
        $pindex = max(1, intval($_GPC['page']));
        $psize = 10;//每页显示个数
        $openid = $_GPC['openid'];
        $status = $_GPC['status'];
        $sort = $_GPC['sort'];
        if ($_GPC['sort']) {
            $order_sort = "order by sort {$sort}";
        } else {
            $order_sort = "order by sort desc";
        }
        if ($status == 3) {
            $statu = 0;
        } else {
            $statu = $status;
        }
        $keyword = $_GPC['keyword'];
        $condition = '';
        if ($op == 'content') {
            if ($openid && $keyword) {
                $condition = "openid='{$keyword}'";
            }
            if ($status) {
                if ($condition) {
                    $condition = $condition . " and status={$statu}";
                } else {
                    $condition = "status={$statu}";
                }
            }
            if ($keyword && !$openid) {
                if ($condition) {
                    $condition = $condition . " and goods_name like '%{$keyword}%'";
                } else {
                    $condition = "goods_name like '%{$keyword}%'";
                }
            }
            if ($condition) {
                $condition = 'where ' . $condition;
            }
            $sql = "SELECT * FROM " . tablename_cj('choujiang_share_order') . " {$condition}  {$order_sort} limit " . ($pindex - 1) * $psize . ',' . $psize;
            $sql1 = "SELECT count(*) from " . tablename_cj('choujiang_share_order') . " {$condition}";
            $shareOrderList = pdo_fetchall_cj($sql);
            $total = pdo_fetchcolumn_cj($sql1);
            foreach ($shareOrderList as $k => $v) {
                $shareOrderList[$k]['avatar'] = $this->getImage($v['avatar']);
                $goodsInfo = pdo_get_cj('choujiang_goods', array('id' => $v['goods_id']));
                $shareOrderList[$k]['goods_icon'] = $this->getImgArray($v['goods_icon'])[0];
                $imglist = $this->getImgArray($v['img']);
                $shareOrderList[$k]['img'] = $imglist;
                //是否填写收货地址
                $record = pdo_get_cj('choujiang_record', array('goods_id' => $v['goods_id'],'openid'=>$v['openid']));
                $shareOrderList[$k]['address'] = $record['user_address'];
            }
            $pager = pagination($total, $pindex, $psize);
        }
        //晒单信息页面
        if ($op == 'post') {
            $sql = "SELECT max(sort) as sort FROM " . tablename_cj('choujiang_share_order') . " ";
            $sort = pdo_fetch_cj($sql);
            $item = pdo_get_cj('choujiang_share_order', array('id' => $_GPC['id']));

            if ($item) {
//                $item['goods_icon'] = $this->getImgArray($item['goods_icon']);
                $item['img'] = json_decode($item['img']);
                if ($item['img']) {
                    foreach ($item['img'] as $k => $v) {
                        $item['img'][$k] = $this->getImage($v);
                    }
                }
            } else {
                $item['sort'] = $sort['sort'] + 1;
            }
        }
        //晒信息编辑
        if ($op == 'add') {
            $goods = pdo_get_cj('choujiang_goods', array('id' => $_GPC['goods_id'], 'status' => 1));
            if ($goods) {
                $user = pdo_get_cj('choujiang_user', array('openid' => $_GPC['openid']));
                $share = pdo_get_cj('choujiang_share_order', array('openid' => $_GPC['openid'], 'goods_id' => $_GPC['goods_id']));
                $data['goods_id'] = $_GPC['goods_id'];
                $data['goods_name'] = $goods['goods_name'];
                $data['goods_icon'] = $goods['goods_icon'];
                $data['nickname'] = $user['nickname'];
                $data['openid'] = $user['openid'];
                $data['avatar'] = $user['avatar'];
                $data['content'] = $_GPC['content'];
                $data['sort'] = $_GPC['sort'];
                $data['refuse_reason'] = $_GPC['refuse_reason'];
                $data['update_at'] =date('Y-m-d H:i:s');
                if ($_GPC['index']) {
                    $st = strpos($_GPC['index'], ".com/") + 5;
                    $lg = strpos($_GPC['index'], "?") - $st;
                    $data['cover_img'] = strpos($_GPC['index'], "?") == false ? substr($_GPC['index'], $st) : substr($_GPC['index'], $st, $lg);
                }
                foreach ($_GPC['goods_images'] as $k => $v) {
                    if (strpos($v, "?") == false && strpos($v, ".com/") == false) {
                        $remotestatus = $this->file_remote_upload($v);
                        if (is_error($remotestatus)) {
                            file_delete($v);
                            message('远程附件上传失败，请检查配置并重新上传', '', 'error1');
                        }
                        $img_url[] = $v;
                    } else {
                        $start = strpos($v, ".com/") + 5;
                        $length = strpos($v, "?") - $start;
                        $img_url[] = strpos($v, "?") == false ? substr($v, $start) : substr($v, $start, $length);
                    }
                }
                $data['img'] = json_encode($img_url);
                $data['status'] = $_GPC['status'];

                if (!$_GPC['sd_id']) {  //新增晒单
                    if (!$share) {     //一个用户只能晒一单
                        $res = pdo_insert_cj('choujiang_share_order', $data);
                    }
                } else {                 //更新晒单
                    $res = pdo_update_cj('choujiang_share_order', $data, array('id' => $_GPC['sd_id']));
                }
                if ($res) {
                    if($data['status'] == -1 ){  //晒单被拒后消息通知
                        $this->doPageInform($_GPC['sd_id']);
                    }
                    message('提交成功', '', 'success');
                } else {
                    message('提交失败', '', 'error');
                }
            } else {
                message('请填写已开奖的奖品id', '', 'error');
            }
        }
        //删除
        if ($op == 'delete') {
            $id = intval($_GPC['id']);
            $res = pdo_delete_cj('choujiang_share_order', array('id' => $id));
            if ($res) {
                message('删除成功!', $this->createWeburl('choujiang_share_order', array('op' => 'content')), 'success');
            }
        }
        //多删
        if (!empty($_GPC['deleteall'])) {
            $res = array();
            for ($i = 0; $i < count($_GPC['deleteall']); $i++) {
                $res[] = pdo_delete_cj('choujiang_share_order', array('id' => $_GPC['deleteall'][$i]));
            }
            if ($res) {
                message('删除成功!', $this->createWeburl('choujiang_share_order', array('op' => 'content')), 'success');
            }
        }
        //获取奖品信息
        if ($op == 'goodsInfo') {
            $goods = pdo_get_cj('choujiang_goods', array('id' => $_GPC['id']));
            $records = pdo_getall_cj('choujiang_record', array('goods_id' => $_GPC['id'], 'status' => 1));
            $info = [
                'goods_name' => $goods['goods_name'],
                'goods_icon' => $this->getImgArray($goods['goods_icon'])[0]
            ];
            foreach ($records as $k => $v) {
                $info['user'][$k]['nickname'] = $v['nickname'];
                $info['user'][$k]['openid'] = $v['openid'];
            }
            if ($records) {
                message($info, '', 'success');
            } else {
                message('该奖品未开奖或不存在', '', 'error');
            }

        }
        include $this->template('choujiang_share_order');
    }

    //晒单拒绝 模板通知
    public function doPageInform($id)
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];
        $base = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_base') . "where `uniacid`='{$uniacid}' ");
        $template_id = trim($base['refuse_template_id']);
        $wxapp = WeAccount::create();
        $access_token = $wxapp->getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=' . $access_token;
        $dd = array();
        $sdInfo = pdo_fetch_cj("SELECT openid,formid,goods_name,refuse_reason FROM " . tablename_cj('choujiang_share_order') . " where `id`='{$id}'");
        $dd['form_id'] = $sdInfo['formid'];
        $dd['touser'] = $sdInfo['openid'];
//        var_dump($sdInfo['refuse_reason']);
        $content = array(
            "keyword1" => array(
                "value" => '【'.$sdInfo['goods_name'].'】'.'晒单评论被拒',
                "color" => "#4a4a4a"
            ),
            "keyword2" => array(
                "value" => $sdInfo['refuse_reason'],
                "color" => "#9b9b9b"
            ),
        );
        $dd['template_id'] = $template_id;
        $dd['page'] = 'choujiang_page/shareEvaluate/shareEvaluate?id=' . $id;  //点击模板卡片后的跳转页面，仅限本小程序内的页面。支持带参数,该字段不填则模板无跳转
        $dd['data'] = $content;                        //模板内容，不填则下发空模板
        $dd['color'] = '';                        //模板内容字体的颜色，不填默认黑色
        $dd['emphasis_keyword'] = '';    //模板需要放大的关键词，不填则默认无放大
        $result = $this->https_curl_json($url, $dd, 'json');
        return $result;
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
     *  物流管理
     */
    public function doWebChoujiang_express()
    {
        global $_W, $_GPC;
        $op = $_GPC['op'];
        $op = $op ? $op : 'content';
        //列表页
        if ($op == 'content') {
            $pindex = max(1, intval($_GPC['page']));
            $psize = 20;//每页显示个数
            $condition = [];
            $expressList = pdo_getall_cj("choujiang_express", $condition, [], '', ['id desc'], [$pindex, $psize]);
        }
        //编辑页面
        if ($op == 'post') {
            $condition = [
                'id' => $_GPC['id']
            ];
            $expressInfo = pdo_get_cj("choujiang_express", $condition, [], '');
        }
        //保存物流信息
        if ($op == 'add') {
            if ($_GPC['id']) {   //更新
                $data['express_name'] = $_GPC['express_name'];
                $data['update_at'] = date('Y-m-d H:i:s', time());
                $res = pdo_update_cj('choujiang_express', $data, array('id' => $_GPC['id']));
            } else {             //增加
                $data['express_name'] = $_GPC['express_name'];
                $data['create_at'] = date('Y-m-d H:i:s', time());
                $data['update_at'] = date('Y-m-d H:i:s', time());
                $res = pdo_insert_cj('choujiang_express', $data);
            }
            if ($res) {
                message('保存成功', $this->createWeburl('choujiang_express', array('op' => 'content')), 'success');
                exit;
            }

        }
        //删除
        if ($op == 'delete') {
            $id = intval($_GPC['id']);
            $res = pdo_delete_cj('choujiang_express', array('id' => $id));
            if ($res) {
                message('删除成功!', $this->createWeburl('choujiang_express', array('op' => 'content')), 'success');
            }
        }
        //多删
        if (!empty($_GPC['deleteall'])) {
            $res = array();
            for ($i = 0; $i < count($_GPC['deleteall']); $i++) {
                $res[] = pdo_delete_cj('choujiang_express', array('id' => $_GPC['deleteall'][$i]));
            }
            if ($res) {
                message('删除成功!', $this->createWeburl('choujiang_express', array('op' => 'content')), 'success');
            }
        }
        include $this->template('choujiang_express');
    }


    /**
     * 数据管理
     */
    public function doWebChoujiang_data()
    {
        global $_W,$_GPC;
        $op = $_GPC['op'];
        $op = $op ? $op : 'trends';
        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;//每页显示个数
        $field = $_GPC['field'];
        $keyword = $_GPC['keyword'];
        //条件查询
        if($field && $keyword){
            if ($field == 1) {
                $user = pdo_get_cj("choujiang_user", ['openid' => $keyword]);
            }else{
                $user = pdo_get_cj("choujiang_user", ['nickname' => $keyword]);
            }
            $where = "where user_id={$user['id']}";
        }
        if ($op != 'trends') {
            $start = $psize * ($pindex - 1);
            $num = $pindex * $psize;
            if ($op == 'drainage') {
                $sql = "SELECT id,sum(amount) as amount,user_id FROM " . tablename_cj('choujiang_stat_user_share') . " {$where}  GROUP BY user_id limit {$start},{$num}";
                $sql1 = "SELECT count(*) FROM " . tablename_cj('choujiang_stat_user_share') . "  {$where} GROUP BY user_id";
                $total = count(@pdo_fetchall_cj($sql1));
                $products = @pdo_fetchall_cj($sql);
            }
            if ($op == 'detail') {
                $table = "choujiang_user_share";
                $where = [
                    "share_user_id" => $_GPC['user_id']
                ];
                $products = pdo_getall_cj($table, $where, [], '', ['id desc'], [$pindex, $psize]);
                $total = pdo_getcolumn_cj($table, $where, "COUNT(*)");
                //上级用户信息
                $where = [
                    "user_id" => $_GPC['user_id']
                ];
                $info = pdo_get_cj($table, $where);
                $where = [
                    "id" => $info['share_user_id']
                ];
                $top = pdo_get_cj("choujiang_user", $where);
            }
            if($products){
                foreach ($products as $k => $v) {
                    $where = [
                        'id'=>$v['user_id']
                    ];
                    $userInfo = pdo_get_cj("choujiang_user", $where);
                    $products[$k]['nickname'] = $userInfo['nickname'];
                    $products[$k]['openid'] = $userInfo['openid'];
                    $products[$k]['avatar'] = $userInfo['avatar'];
                }
            }
            $pager = pagination($total, $pindex, $psize);
        }
        include $this->template('choujiang_data');
    }

    /**
     * 获取数据管理图标数据
     */
    public function doWebgetChartData()
    {
        global $_W, $_GPC;
        $op = $_GPC['date'] ? $_GPC['date'] : 'today';
        $redis = connect_redis();
        $column = array('visit', 'new');
        $uniacid = $_W['uniacid'];
        //今天、昨天用户统计
        if ($op == 'today' || $op == 'yesterday') {
            if ($op == 'today') {
                $key = date("Ymd");
                $time = date("Y-m-d");
            } else {
                $key = date('Ymd', strtotime("-1 day"));
                $time = date("Y-m-d", strtotime("-1 day"));
            }
            $date = array(date("Y-m-d"), $time);
            $userAmount = $this->ChartInit($date, $column);  //获取时间轴的初始值
            $userAmountKey = sprintf("cj_user_amount:%s:%s", $uniacid, $key);
            $userAmountTmp = $redis->hGetAll($userAmountKey);
            if ($userAmountTmp) {
                foreach ($userAmountTmp as $k => $v) {
                    $info = json_decode($v);
                    $userAmount[(int)$k] = [
                        'date' => $k . ":00-" . $k . ":59",
                        $column[0] => (int)$info->visit,
                        $column[1] => (int)$info->new,
                    ];
                }
            }
        } //近7、30天用户统计
        elseif ($op == 'lastseven' || $op == 'lastthirty') {
            if ($op == 'lastseven') {
                $start = date('Y-m-d', strtotime("-7 day"));
            } else {
                $start = date('Y-m-d', strtotime("-30 day"));
            }
            $end = date("Y-m-d", strtotime("-1 day"));
            $date = array($end, $start);
            $userAmount = $this->ChartInit($date, $column);  //获取时间轴的初始值
            $userAmountTmp = pdo_fetchall_cj("SELECT * FROM " . tablename_cj('choujiang_stat_user') . " WHERE create_at >= '{$start}' and create_at <= '{$end}'");
            foreach ($userAmountTmp as $k => $v) {
                $date = date('m-d', strtotime($v['create_at']));
                $userAmount[$date] = [
                    'date' => $date,
                    $column[0] => $v['user_visit'],
                    $column[1] => $v['user_add'],
                ];
            }
        }
        $userAmount = array_values($userAmount);
        return json_encode($userAmount);
    }

    /**
     * 获取时间轴的初始值
     * @param $date  数组，(截止时间，起止时间)
     * @param $column 数组，
     * @return array
     */
    public function ChartInit($date, $column)
    {
        $userAmount = [];
        $day = intval((strtotime($date[0]) - strtotime($date[1])) / 86400);
        if ($day <= 1) {
            for ($i = 0; $i <= 24; $i++) {
                $k = $i;
                if ($i < 10) {
                    $i = '0' . $i;
                }
                $userAmount[$k] = [
                    'date' => $i . ":00-" . $i . ":59",
                ];
                foreach ($column as $v) {
                    $userAmount[$k][$v] = 0;
                }
            }
        } else {
            for ($i = 0; $i <= $day; $i++) {
                $time = date('m-d', strtotime($date[0]) - 86400 * $i);
                $userAmount[$time] = [
                    'date' => $time,
                ];
                foreach ($column as $v) {
                    $userAmount[$time][$v] = 0;
                }
            }
            $userAmount = array_reverse($userAmount);
        }

        return $userAmount;
    }

    /**
     * 推广码管理
     */
    public function doWebChoujiang_channel_code()
    {
        global $_W, $_GPC;
        $op = $_GPC['op'];
        $ops = array('content', 'stat', 'post');
//        $date = date("Y-m-d",strtotime(date("Y-m-d")) - 86400);
        $op = in_array($op, $ops) ? $op : 'stat';
        $uniacid = $_W['uniacid'];

        if ($op == 'stat') {
            $pindex = max(1, intval($_GPC['page']));
            $psize = 20;//每页显示个数
            $start = ($pindex - 1) * $psize;
            $condition = [];

//            $total = pdo_getcolumn_cj("choujiang_stat_channel", $condition, "COUNT(*)");
//            $sql = "SELECT S.*, C.title, C.page_url FROM " . tablename_cj('choujiang_stat_channel') . " as S LEFT JOIN " . tablename_cj('choujiang_channel_code') . " as C ON S.channel_id=C.id  WHERE S.create_at='{$date}' ORDER BY id DESC LIMIT {$start}, {$psize}";
//            $params = [];
//            $products = pdo_fetchall_cj($sql);
            $channels = pdo_getall_cj("choujiang_channel_code", $condition, [], '', ['id desc']);
//            $pager = pagination($total, $pindex, $psize);
        }

        if ($op == 'post') {
            if (!empty($_GPC['id'])) {
                $products = pdo_get_cj('choujiang_channel_code', ['id' => $_GPC['id']]);
                $arr_result = explode('&', $products['channel']);
                foreach ($arr_result as $v) {
                    $param = explode('=', $v);
                    if ($param[0] == 'g') {
                        $goods_id = $param[1];
                        break;
                    }
                }
                $products['goods_id'] = $goods_id;
            }

            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                //编辑推广码
                if (!empty($_GPC['edit'])) {
                    $id = $_GPC['id'];
                    $scene = "c=" . $id;
                    if ("choujiang_pages/drawDetails/drawDetails" == $_GPC['page_url']) {
                        if ("" == $_GPC['goods_id']) {
                            message('奖品详情，ID不能为空！', '', 'error');
                        }
                        $scene = $scene . "&g=" . $_GPC['goods_id'];
                    }
                    $wxapp = WeAccount::create();
                    $access_token = $wxapp->getAccessToken();
                    $url = sprintf("https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=%s", $access_token);
                    $result = $this->api_notice_increment($url, json_encode([
                        'width' => $_GPC['size'],
                        'scene' => $scene,
                        'page' => $_GPC['page_url'],
                    ]));
                    $image_name = md5(uniqid(rand())) . ".jpg";
                    $filepath = "../attachment/choujiang_page/{$image_name}";
                    $file_put = file_put_contents($filepath, $result);
                    if ($file_put) {
                        $up_data = [
                            'title' => $_GPC['title'],
                            'size' => $_GPC['size'],
                            'page_url' => $_GPC['page_url'],
                            'channel' => $scene,
                            'wx_code' => $image_name . "?" . time(),
                        ];

                        $result = pdo_update_cj('choujiang_channel_code', $up_data, array('id' => $id));
                        message('编辑成功!', $this->createWeburl('choujiang_channel_code', array('op' => 'content')), 'success');
                    } else {
                        message('编辑小程序码失败！', '', 'error');
                    }
                }

                //添加推广码
                $data = [
                    'title' => $_GPC['title'],
                    'size' => $_GPC['size'],
                    'page_url' => $_GPC['page_url'],
                    'create_at' => date("Y-m-d H:i:s"),
                ];
                $insertInfo = pdo_insert_cj('choujiang_channel_code', $data);
                if ($insertInfo) {
                    $channel_id = pdo_insertid_cj();
                    $scene = "c=" . $channel_id;
                    if ("choujiang_pages/drawDetails/drawDetails" == $_GPC['page_url']) {
                        if ("" == $_GPC['goods_id']) {
                            message('奖品详情，ID不能为空！', '', 'error');
                        }
                        $scene = $scene . "&g=" . $_GPC['goods_id'];
                    }

                    $wxapp = WeAccount::create();
                    $access_token = $wxapp->getAccessToken();
                    $url = sprintf("https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=%s", $access_token);
                    $result = $this->api_notice_increment($url, json_encode([
                        'width' => $_GPC['size'],
                        'scene' => $scene,
                        'page' => $_GPC['page_url'],
                    ]));
                    $image_name = md5(uniqid(rand())) . ".jpg";
                    $filepath = "../attachment/choujiang_page/{$image_name}";
                    $file_put = file_put_contents($filepath, $result);
                    if ($file_put) {
                        $up_data = [
                            'channel' => $scene,
                            'wx_code' => $image_name . "?" . time(),
                        ];
                        $result = pdo_update_cj('choujiang_channel_code', $up_data, array('id' => $channel_id));
                        message('添加成功!', $this->createWeburl('choujiang_channel_code', array('op' => 'content')), 'success');
                    } else {
                        message('生成小程序码失败！', '', 'error');
                    }
                } else {
                    message('添加渠道失败！', '', 'error');//数据库写入失败
                }
            }
        }

        if ($op == 'content') {
            $pindex = max(1, intval($_GPC['page']));
            $psize = 20;//每页显示个数
            $condition = [];

            $products = pdo_getall_cj("choujiang_channel_code", $condition, [], '', ['id desc'], [$pindex, $psize]);
            $total = pdo_getcolumn_cj("choujiang_channel_code", $condition, "COUNT(*)");
            $pager = pagination($total, $pindex, $psize);
        }

        include $this->template('choujiang_channel_code');
    }

    /**
     * 获取推广统计图标数据
     */
    public function doWebgetChannelData()
    {
        global $_W, $_GPC;
        $date = $_GPC['select_date'];
        $uniacid = $_W['uniacid'];
        $channelId = $_GPC['select_channel'];
        $redis = connect_redis();
        $column = array("scan", "visit", "new");
        $op = $_GPC['op'];
        if ($op == 'post') {

            if ($date == '0' || $date == '1') {
                if ($date == '0') {
                    $key = date("Ymd");
//                    $dateSql = date("Y-m-d", strtotime(date("Y-m-d")) - 86400);
                } else {
                    $key = date('Ymd', strtotime("-1 day"));
//                    $dateSql = date("Y-m-d", strtotime(date("Y-m-d")) - 86400 * $_GPC['select_date']);
                }
                $dateType = array($key, $key);

                $channelAmount = $this->ChartInit($dateType, $column);
                $channelAmountKey = sprintf("cj_qr_channel_amount:%s:%s", $uniacid, $key);
                if ($channelId == 0) {
//                    $channelSelectSqlTmp = "";
                    $channelAmountTmp = $redis->hGetAll($channelAmountKey);
                    if ($channelAmountTmp) {
                        foreach ($channelAmountTmp as $k => $v) {
                            //$k ==渠道id
                            $info = json_decode($v, true);
                            foreach ($info as $k1 => $v1) {
                                //$k1==当前时间
                                $i = (int)$k1;
                                $scan = !isset($v1['scan']) ? 0 : $v1['scan'];
                                $visit = !isset($v1['visit']) ? 0 : $v1['visit'];
                                $new = !isset($v1['new']) ? 0 : $v1['new'];
                                $channelAmount[$i] = [
                                    'date' => $k1 . ":00-" . $k1 . ":59",
                                    $column[0] => $channelAmount[$i]['scan'] + $scan,
                                    $column[1] => $channelAmount[$i]['visit'] + $visit,
                                    $column[2] => $channelAmount[$i]['new'] + $new,
                                ];
                            }
                        }
                    }
                } else {
//                    $channelSelectSqlTmp = "AND id='{$channelId}'";
                    $channelAmountTmp = $redis->hGet($channelAmountKey, $channelId);
                    if ($channelAmountTmp) {
                        $info = json_decode($channelAmountTmp, true);
                        foreach ($info as $k => $v) {
                            //$k ==当前时间
                            $i = (int)$k;
                            $scan = !isset($v['scan']) ? 0 : $v['scan'];
                            $visit = !isset($v['visit']) ? 0 : $v['visit'];
                            $new = !isset($v['new']) ? 0 : $v['new'];
                            $channelAmount[$i] = [
                                'date' => $k . ":00-" . $k . ":59",
                                $column[0] => $scan,
                                $column[1] => $visit,
                                $column[2] => $new,
                            ];
                        }
                    }
                }

            } elseif ($date == '2' || $date == '3') {
                if ($date == '2') {
                    $start = date('Y-m-d', strtotime("-7 day"));
                } elseif ($date == '3') {
                    $start = date('Y-m-d', strtotime("-30 day"));
                }
                $end = date("Y-m-d", strtotime("-1 day"));
                $dateType = array($end, $start);
                $channelAmount = $this->ChartInit($dateType, $column);
                if ($channelId == 0) {
//                    $channelSelect = "";
                    $channelSelectSqlTmp = "";
                } else {
//                    $channelSelect = "AND S.channel_id='{$channelId}'";
                    $channelSelectSqlTmp = "AND id='{$channelId}'";
                }

                $channelAmountTmp = pdo_fetchall_cj("SELECT * FROM " . tablename_cj('choujiang_stat_channel') . " WHERE create_at >= '{$start}' and create_at <= '{$end}' " . $channelSelectSqlTmp);
                foreach ($channelAmountTmp as $k => $v) {
                    $date = date('m-d', strtotime($v['create_at']));
                    $channelAmount[$date][$column[0]] += $v['sweep_time'];
                    $channelAmount[$date][$column[1]] += $v['sweep_user'];
                    $channelAmount[$date][$column[2]] += $v['sweep_add'];
                }
                $channelAmount = array_values($channelAmount);
            }

            $reulst['channelAmount'] = $channelAmount;
            $list = $this->doWebgetChannelDataList(1);
//            $reulst['total'] = (int)$total['total'];
            $reulst['total'] = $list['total'];
            $reulst['date_list'] = $list['date_list'];
//            $reulst['date_list'] = $date_list;
            return json_encode($reulst);
        }
    }

    /**
     * 获取推广统计列表
     */
    public function doWebgetChannelDataList($arrayReturn=0)
    {
        global $_W, $_GPC;
        $date = $_GPC['select_date'];
        $uniacid = $_W['uniacid'];
        $channelId = $_GPC['select_channel'];
        $page = $_GPC['page'];
        $pageSize = $_GPC['pageSize'];
        $field = $_GPC['field'];
        $order = $_GPC['order'];
//        $column = array("scan", "visit", "new");
        $op = $_GPC['op'];
        if ($op == 'post') {
            if ($channelId == 0) {
                $channelSelect = "";
                $channelSelectSqlTmp = "";
                $channelSelectSqlTotal = "";
            } else {
                $channelSelect = "AND C.id='{$channelId}'";
                $channelSelectSqlTmp = "AND channel_id='{$channelId}'";
                $channelSelectSqlTotal = "AND id='{$channelId}'";
            }
            if($page){
                $start = ($page-1)*$pageSize;
                $limit = " LIMIT {$start}, {$pageSize}";
            }
            if(!empty($order)&&!empty($field)){
                if($order == "descending"){
                    $order = "DESC";
                }elseif($order == "ascending"){
                    $order = "ASC";
                }
                $order = " ORDER BY S.{$field} {$order}, C.id DESC ";
            }else{
                $order = " ORDER BY C.id DESC ";
            }
            $sql = "SELECT COUNT(*) AS total FROM " . tablename_cj('choujiang_channel_code') . " WHERE 1 AND is_del = 0 ".$channelSelectSqlTotal;
            $params = [];
            $total = pdo_fetch_cj($sql, $params);

            if ($date == '0' || $date == '1') {
                if ($date == '0') {
//                    $key = date("Ymd");
                    $key = date('Ymd', strtotime("-1 day"));
//                    $dateSql = date("Y-m-d", strtotime(date("Y-m-d")) - 86400);

                } else {
                    $key = date('Ymd', strtotime("-1 day"));
//                    $dateSql = date("Y-m-d", strtotime(date("Y-m-d")) - 86400 * $_GPC['select_date']);
                }

                $sql = "SELECT S.*, DATE_FORMAT(C.create_at,'%Y-%m-%d') AS create_at, C.title, C.page_url FROM " . tablename_cj('choujiang_channel_code') . " AS C LEFT JOIN (SELECT MAX(create_at) AS _create_at, channel_id, sum(sweep_time) as sweep_time, sum(sweep_add) as sweep_add, sum(sweep_user) as sweep_user FROM " . tablename_cj('choujiang_stat_channel') . " WHERE create_at = '{$key}'" .$channelSelectSqlTmp. " GROUP BY channel_id) AS S ON C.id = S.channel_id WHERE 1 " .$channelSelect. $order . $limit;
                $params = [];
                $date_list = pdo_fetchall_cj($sql, $params);

            } elseif ($date == '2' || $date == '3') {
                if ($date == '2') {
                    $start = date('Y-m-d', strtotime("-7 day"));
                } elseif ($date == '3') {
                    $start = date('Y-m-d', strtotime("-30 day"));
                }
                $end = date("Y-m-d", strtotime("-1 day"));

                //$sql = "SELECT S.*, C.title, C.page_url FROM " . tablename_cj('choujiang_stat_channel') . " as S LEFT JOIN " . tablename_cj('choujiang_channel_code') . " as C ON S.channel_id=C.id  WHERE  S.create_at >= '{$start}' AND S.create_at <= '{$end}' " . $channelSelect . " ORDER BY id DESC ";//LIMIT {$start}, {$psize}
                $sql = "SELECT S.*, DATE_FORMAT(C.create_at,'%Y-%m-%d') AS create_at, C.title, C.page_url FROM " . tablename_cj('choujiang_channel_code') . " AS C LEFT JOIN (SELECT MAX(create_at) AS _create_at, channel_id, sum(sweep_time) as sweep_time, sum(sweep_add) as sweep_add, sum(sweep_user) as sweep_user FROM " . tablename_cj('choujiang_stat_channel') . " WHERE create_at >= '{$start}' AND create_at <= '{$end}' " .$channelSelectSqlTmp. " GROUP BY channel_id) AS S ON C.id = S.channel_id WHERE 1 " .$channelSelect. $order . $limit;
                $params = [];
                $date_list = pdo_fetchall_cj($sql, $params);

            }

            //$reulst['require'] = $_GPC;
            $reulst['total'] = (int)$total['total'];
            $reulst['date_list'] = $date_list;
            if($arrayReturn){
                return $reulst;
            }else{
                return json_encode($reulst);
            }

        }
    }

    // 基本信息
    public function doWebChoujiang_base()
    {
        global $_GPC, $_W;
        $op = $_GPC['op'];
        $ops = array('display', 'post');
        $op = in_array($op, $ops) ? $op : 'display';
        $uniacid = $_W['uniacid'];
        load()->func('file'); //调用上传函数
        $dir_url = '../web/cert/choujiang_page/'; //上传路径
        mkdirs($dir_url);

        //创建目录
        if ($_FILES["upfile"]["name"]) {
            $upfile = $_FILES["upfile"];
            //获取数组里面的值
            $name = $upfile["name"];//上传文件的文件名
            $size = $upfile["size"];//上传文件的大小
            if ($size > 2 * 1024 * 1024) {

                message("文件过大，不能上传大于2M的文件!", $this->createWebUrl("choujiang_base", array("op" => "display")), "success");
                exit();
            }
            if (file_exists($dir_url . $settings["upfile"])) @unlink($dir_url . $settings["upfile"]);
            $cfg['upfile'] = TIMESTAMP . ".pem";
            move_uploaded_file($_FILES["upfile"]["tmp_name"], $dir_url . $upfile["name"]); //移动到目录下
            $upfiles = $dir_url . $name;

        }
        if ($_FILES["keypem"]["name"]) {
            $upfile = $_FILES["keypem"];
            //获取数组里面的值
            $name = $upfile["name"];//上传文件的文件名
            //$type=$upfile["type"];//上传文件的类型
            $size = $upfile["size"];//上传文件的大小
            if ($size > 2 * 1024 * 1024) {
                message("文件过大，不能上传大于2M的文件!", $this->createWebUrl("choujiang_base", array("op" => "display")), "success");
                exit();
            }
            if (file_exists($dir_url . $settings["keypem"])) @unlink($dir_url . $settings["keypem"]);
            move_uploaded_file($_FILES["keypem"]["tmp_name"], $dir_url . $upfile["name"]); //移动到目录下
            $keypems = $dir_url . $name;
        }


        if ($op == 'display') {
            $item = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_base') . " WHERE uniacid = :uniacid", array(':uniacid' => $uniacid));
            $img_url = $this->attachurl . $item['app_icon'];

            $priceArr =explode('-',$item['wechat_rand_price']);
            $priceList =json_decode($item['probability_num'],true);
            $item["score"] =json_decode($item['score'],true);
            $item['loopPrice'] =['min'=>$priceArr[0],'max'=>$priceArr[1],'floorNum'=>$this->floorNum,'pirceList'=>$priceList];

            if (checksubmit('submit')) {
                $_GPC['set']["probability_num"]=json_encode($_GPC['set']["probability_num"]);
                $_GPC['set']["score"]=json_encode($_GPC['set']["score"]);
                if ($_GPC['upfile1'] == '') {
                    $_GPC['set']['upfile'] = $upfiles;
                } else {
                    $_GPC['set']['upfile'] = $_GPC['upfile1'];
                }
                if ($_GPC['keypem1'] == '') {
                    $_GPC['set']['keypem'] = $keypems;
                } else {
                    $_GPC['set']['keypem'] = $_GPC['keypem1'];
                }
                if ($_GPC['set']['extensions_price'] < 0.01) {
                    message('使用扩展高级功能费用，必须大于等于0.01元!', $this->createWebUrl('choujiang_base', array('op' => 'display')), 'error');
                    exit();
                }


                if (empty($item)) {
                    $_GPC['set']['uniacid'] = $uniacid;
                    $str = pdo_insert_cj('choujiang_base', $_GPC['set']);
                } else {
//                    $products = pdo_fetchall_cj("select * from " . tablename_cj("choujiang_user") . " where uniacid=:uniacid", array("uniacid" => $uniacid));
//                    foreach ($products as $key => $value) {
//                        //$data['mf_num'] = $_GPC['set']['join_num'];
//                        //$data['smoke_num'] = $_GPC['set']['smoke_num'];
//                        //$data['winning_num'] = $_GPC['set']['winning_num'];
//                        //pdo_update_cj('choujiang_user', $data, array('id' => $value['id']));
//                    }

                    if ($this->baseConfig['type']) {
                        $pathname = $_GPC['set']['app_icon'];
                        $remotestatus = $this->file_remote_upload($pathname);
                        if (is_error($remotestatus)) {
                            file_delete($pathname);
                            message('远程附件上传失败，请检查配置并重新上传', '', 'error');
                        }
                    }
                    $str = pdo_update_cj('choujiang_base', $_GPC['set'], array('uniacid' => $uniacid));
                    $record = $_GPC['set'];
                    $record['operator'] = $_W['username'];
                    $record['time'] = date("Y-m-d H:i:s");
                    $realip = '';
                    if(isset($_SERVER)){
                        if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])){
                            $realip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                        }elseif(isset($_SERVER['HTTP_CLIENT_IP'])) {
                            $realip = $_SERVER['HTTP_CLIENT_IP'];
                        }else{
                            $realip = $_SERVER['REMOTE_ADDR'];
                        }
                    }
                    $record['ip'] = $realip;
                    file_put_contents(__DIR__.'/projectRecord/choujiang_base.log', json_encode($record)."\n", FILE_APPEND);
                    unset($record);
                }
                message('基础信息更新成功!', $this->createWebUrl('choujiang_base', array('op' => 'display')), 'success');

            }
        }
        include $this->template('choujiang_base');
    }

    // 用户列表 以及地址
    public function doWebChoujiang_users()
    {
        global $_W, $_GPC;
        $id = $_GPC['id'];
        $op = $_GPC['op'] ? $_GPC['op'] : 'content';
        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;//每页显示个数
        $uniacid = $_W['uniacid'];
        if ($op == 'content') {
            $pindex = max(1, intval($_GPC['page']));
            $psize = 20;//每页显示个数
            $keyword = trim($_GPC['keyword']);
            $condition = ["uniacid" => $uniacid];

            if ($keyword) {
                $condition['nickname like'] = "%{$keyword}%";
            }
            if ($_GPC['is_machine_state'] > 0) {
                $condition['is_machine'] = $_GPC['is_machine_state'] - 1;
            }
            $products = pdo_getall_cj("choujiang_user", $condition, [], '', ['id desc'], [$pindex, $psize]);

            $total = pdo_getcolumn_cj("choujiang_user", $condition, "COUNT(*)");
            foreach ($products as $key => $value) {
                $products[$key]['create_time'] = date('Y-m-d H:i', $value['create_time']);
                $products[$key]['join_num'] = $value['yu_num'] + $value['mf_num'];
                $products[$key]['smoke_num'] = $value['smoke_num'] + $value['smoke_share_num'];
                //用户图片地址
                $products[$key]['avatar'] = $this->getImage($value['avatar']);
            }
            $pager = pagination($total, $pindex, $psize);
        }

        if ($op == 'detail') {

            $psize = 10;//每页显示个数
            $pindex = max(1, intval($_GPC['page']));
            $pindex_ip = max(1, intval($_GPC['pageIp']));
            $pindex_ua = max(1, intval($_GPC['pageUa']));
            $pindex_ep = max(1, intval($_GPC['pageEp']));


            $start = $psize * ($pindex - 1);
            $num = $pindex * $psize;

            //用户数据
            $userInfo = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_user') . " WHERE id = {$id}");
            //拉新总人数
            $userShare = pdo_fetch_cj("SELECT count(us.user_id) as share_num FROM " . tablename_cj('choujiang_user_share') . " us LEFT JOIN " . tablename_cj('choujiang_user') . " u on us.share_user_id = u.id where u.id = {$id};");
            //拉新红包总金额
            $totalMoney = pdo_fetch_cj("SELECT rp.total_money FROM " .tablename_cj('choujiang_user_share'). " us LEFT JOIN " .tablename_cj('choujiang_red_packets'). " rp on us.user_id = rp.uid where us.user_id = {$id};");
            //提现总额
            $CashMoney = pdo_fetch_cj("SELECT SUM(receive_money) as total FROM " .tablename_cj('choujiang_red_packets_record'). " where openid = '{$userInfo['openid']}';");
            //今日 昨日 最近七天
            $where = "where user_id={$id}";

            //用户参与抽奖次数
            $drawTimes = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_record') . ' where openid=:openid ', array(':openid' => $userInfo['openid']));

            //用户默认地址
            $userAddress = pdo_fetch_cj("SELECT area,address,tel,name FROM " .tablename_cj('choujiang_default_addr'). " where openid = '{$userInfo['openid']}';");


            $sql = "SELECT id,sum(amount) as amount,user_id FROM " . tablename_cj('choujiang_stat_user_share') . " {$where}  GROUP BY user_id limit {$start},{$num}";
            $sql1 = "SELECT count(*) FROM " . tablename_cj('choujiang_stat_user_share') . "  {$where} GROUP BY user_id";
            $total = count(@pdo_fetchall_cj($sql1));
            $products = @pdo_fetchall_cj($sql);

            if($products){
                foreach ($products as $k => $v) {
                    $where = [
                        'id'=>$v['user_id']
                    ];
                    $userInfo = pdo_get_cj("choujiang_user", $where);
                    $products[$k]['nickname'] = $userInfo['nickname'];
                    $products[$k]['openid'] = $userInfo['openid'];
                    $products[$k]['avatar'] = $userInfo['avatar'];
                }
            }

            //上下级关系
            $oldShare = $_GPC['oldShare'] ? $_GPC['oldShare'] : 0;
            if($oldShare){

                $table = "choujiang_user_share_old";
                $tableRecord = "choujiang_red_packets_record_old";

            }else{

                $table = "choujiang_user_share";
                $tableRecord = "choujiang_red_packets_record";

            }


            //上下级关系
//            $table = "choujiang_user_share";
            $where = [
                "share_user_id" => $id
            ];
//            $reProducts = pdo_getall_cj($table, $where, [], '', ['id desc'], [$pindex, $psize]);
//            $reTotal = pdo_getcolumn_cj($table, $where, "COUNT(*)");
            $sql = "SELECT * FROM " . tablename_cj($table) . " where share_user_id='{$id}' order by id desc limit " . ($pindex - 1) * $psize . ',' . $psize;
            $sql1 = "SELECT count(*) FROM " . tablename_cj($table) . " where share_user_id='{$id}'";
            $reProducts = pdo_fetchall_cj($sql);
            $reTotal = pdo_fetchcolumn_cj($sql1);
            $Pager = pagination_cj($reTotal, $pindex, $psize, '', array('before' => 5, 'after' => 4, 'ajaxcallback' => '', 'callbackfuncname' => ''), 'page') ;


//            //上级用户信息
            $where = [
                "user_id" => $id
            ];
            $info = pdo_get_cj($table, $where);
            $where = [
                "id" => $info['share_user_id']
            ];
            $top = pdo_get_cj("choujiang_user", $where);
            $topUserCashMoney = pdo_fetch_cj("SELECT SUM(receive_money) as total FROM " .tablename_cj($tableRecord). " where openid = '{$top['openid']}';");

            if($reProducts){
                foreach ($reProducts as $k => $v) {
                    $where = [
                        'id'=>$v['user_id']
                    ];
                    $reUserInfo = pdo_get_cj("choujiang_user", $where);
                    //提现总额
                    $oneUserCashMoney = pdo_fetch_cj("SELECT SUM(receive_money) as total FROM " .tablename_cj($tableRecord). " where openid = '{$reUserInfo['openid']}';");
                    $reProducts[$k]['cash_money'] = $oneUserCashMoney['total']?$oneUserCashMoney['total']:0;
                    $reProducts[$k]['nickname'] = $reUserInfo['nickname'];
                    $reProducts[$k]['openid'] = $reUserInfo['openid'];
                    $reProducts[$k]['avatar'] = $reUserInfo['avatar'];
                }
            }


            $pager = pagination($reTotal, $pindex, $psize);
            //ip历史信息
            $openid = $userInfo['openid'];
            $ipWhere = "openid = '{$openid}'";
            $sql = "SELECT * FROM " . tablename_cj('choujiang_ip_historical') . " WHERE {$ipWhere} ORDER BY login_time desc limit " . ($pindex_ip - 1) * $psize . ',' . $psize;
            $sql1 = "SELECT count(*) FROM " . tablename_cj('choujiang_ip_historical') . " WHERE {$ipWhere}";
            $ipInfo = pdo_fetchall_cj($sql);
            $ipTotal = pdo_fetchcolumn_cj($sql1);
            $ipPager = pagination_cj($ipTotal, $pindex_ip, $psize, '', array('before' => 5, 'after' => 4, 'ajaxcallback' => '', 'callbackfuncname' => ''), 'pageIp') ;


            //ua历史信息
            $sql = "SELECT * FROM " . tablename_cj('choujiang_ua_historical') . " WHERE {$ipWhere} ORDER BY create_time desc, id desc limit " . ($pindex_ua - 1) * $psize . ',' . $psize;
            $sql1 = "SELECT count(*) FROM " . tablename_cj('choujiang_ua_historical') . " WHERE {$ipWhere}";
            $uaInfo = pdo_fetchall_cj($sql);
            $uaTotal = pdo_fetchcolumn_cj($sql1);
            $uaPager = pagination_cj($ipTotal, $pindex_ua, $psize, '', array('before' => 5, 'after' => 4, 'ajaxcallback' => '', 'callbackfuncname' => ''), 'pageUa') ;


            //设备历史信息
            $sql = "SELECT * FROM " . tablename_cj('choujiang_equipment_historical') . " WHERE {$ipWhere} ORDER BY create_time desc, id desc limit " . ($pindex_ep - 1) * $psize . ',' . $psize;
            $sql1 = "SELECT count(*) FROM " . tablename_cj('choujiang_equipment_historical') . " WHERE {$ipWhere}";
            $epInfo = pdo_fetchall_cj($sql);
            $epTotal = pdo_fetchcolumn_cj($sql1);
            $epPager = pagination_cj($epTotal, $pindex_ep, $psize, '', array('before' => 5, 'after' => 4, 'ajaxcallback' => '', 'callbackfuncname' => ''), 'pageEp') ;
        }

        if ($op == 'normal') {
            $str = pdo_update_cj('choujiang_user', array('wechat_blacklist'=>0), array('id' => $id));
            if ($str) {
                message('红包黑名单更新成功!', $this->createWebUrl('choujiang_users', array('op' => 'detail')), 'success');
            } else {
                message('红包黑名单更新失败!', $this->createWebUrl('choujiang_users', array('op' => 'detail')), 'error');
            }

        }

        if ($op == 'black') {
            $str = pdo_update_cj('choujiang_user', array('wechat_blacklist'=>1), array('id' => $id));
            if ($str) {
                message('红包黑名单更新成功!', $this->createWebUrl('choujiang_users', array('op' => 'detail')), 'success');
            } else {
                message('红包黑名单更新失败!', $this->createWebUrl('choujiang_users', array('op' => 'detail')), 'error');
            }
        }

        include $this->template('choujiang_users');
    }

    // 奖品列表
    public function doWebChoujiang_goods()
    {
        global $_W, $_GPC;
        $path = __DIR__ . '/resource/';
        $op = $_GPC['op'];
        $ops = array('post', 'content', 'delete', 'user', 'stick', 'stick_cancel');
        $op = in_array($op, $ops) ? $op : 'content';
        $uniacid = $_W['uniacid'];
        $churl = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_base') . " WHERE uniacid = :uniacid", array(':uniacid' => $uniacid));
        if (!$churl['type']) {
            $churl['url'] = $_W['attachurl'];
        }

        //置顶
        if ($op == 'stick') {
            //载入日志函数
            load()->func('logging');
            //记录文本日志
            logging_run("置顶操作：奖品id：{$_GPC['id']},操作发起ip地址：{$_W['clientip']}",$type = 'trace', $filename = 'stick');
            $res = pdo_update_cj('choujiang_goods', ['stick_time' => date("Y-m-d H:i:s")], ['id' => $_GPC['id']]);
            if(!$res) {
                message('置顶失败!', $this->createWeburl('choujiang_goods', array('op' => 'content')), 'fails');
            }
            message('置顶成功!', $this->createWeburl('choujiang_goods', array('op' => 'content')), 'success');
        }

        //取消置顶
        if ($op == 'stick_cancel') {
            $res = pdo_update_cj('choujiang_goods', ['stick_time' => 0], ['id' => $_GPC['id']]);
            if(!$res) {
                message('取消置顶失败!', $this->createWeburl('choujiang_goods', array('op' => 'content')), 'fails');
            }
            message('取消置顶成功!', $this->createWeburl('choujiang_goods', array('op' => 'content')), 'success');
        }

        if ($op == 'content') {
            $pindex = max(1, intval($_GPC['page']));
            $psize = 10;//每页显示个数
            $keyword = $_GPC['keyword'];
            $goods_state = $_GPC['goods_state'];
            $version = $_GPC['version'];
            if ($goods_state > 0) {
                if ($goods_state == 1) {   //已开奖
                    $condition = " and status = 1";
                } else if ($goods_state == 5) {  //已过期
                    $condition = " and status = 2";
                } else if ($goods_state == 2) {  //待开奖
                    $condition = " and audit_status = 1 and status = 0";
                } else if ($goods_state == 3) {  //待审核
                    $condition = " and audit_status = 0";
                } else if ($goods_state == 4) {  //审核未通过
                    $condition = " and audit_status = -1";
                }
            }
            if ($version == 1) {
                $condition = $condition . " and is_pintuan = 0";
            } elseif ($version == 2) {
                $condition = $condition . " and is_pintuan = 1";
            }
            if ($keyword) {
                $condition = $condition . " and goods_name like '%{$keyword}%' ";
            }
            $products = pdo_fetchall_cj("SELECT G.*, V.haibao FROM " . tablename_cj("choujiang_goods") . " AS G LEFT JOIN " . tablename_cj("choujiang_verification") . " AS V ON G.id = V.goods_id AND G.uniacid = V.uniacid WHERE G.uniacid=:uniacid AND G.is_del != -1 " . $condition . "   ORDER BY G.stick_time desc,G.id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array("uniacid" => $uniacid));
            $total = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_goods') . " where uniacid=:uniacid  and is_del != -1  " . $condition . " ", array(':uniacid' => $uniacid));

//            if (!empty($keyword) and $goods_state > 0) {
//                $products = pdo_fetchall_cj("select * from " . tablename_cj("choujiang_goods") . " where uniacid=:uniacid and is_del != -1  and goods_name like '%{$keyword}%' " . $condition . " ORDER BY id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array("uniacid" => $uniacid));
//                $total = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_goods') . " where goods_name like '%{$keyword}%' and uniacid=:uniacid and is_del != -1 " . $condition, array(':uniacid' => $uniacid));
//            } else if ($keyword and $goods_state == 0) {
//                $products = pdo_fetchall_cj("select * from " . tablename_cj("choujiang_goods") . " where uniacid=:uniacid and is_del != -1 " . $condition . "   and goods_name like '%{$keyword}%'   ORDER BY id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array("uniacid" => $uniacid ));
//                $total = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_goods') . " where goods_name like '%{$keyword}%' and uniacid=:uniacid and is_del != -1 " . $condition . "  ", array(':uniacid' => $uniacid));
//            } else if (empty($keyword) and $goods_state > 0) {
//                $products = pdo_fetchall_cj("select * from " . tablename_cj("choujiang_goods") . " where uniacid=:uniacid and is_del != -1" . $condition . " ORDER BY id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array("uniacid" => $uniacid));
//                $total = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_goods') . " where uniacid=:uniacid and is_del != -1 " . $condition, array(':uniacid' => $uniacid ));
//            } else {
//                $products = pdo_fetchall_cj("select * from " . tablename_cj("choujiang_goods") . " where uniacid=:uniacid and is_del != -1  ORDER BY id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array("uniacid" => $uniacid ));
//                $total = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_goods') . " where uniacid=:uniacid  and is_del != -1  ", array(':uniacid' => $uniacid));
//            }
            foreach ($products as $key => $value) {
                $status = $value['status'];
                $audit_status = $value['audit_status'];
                if ($audit_status == 0) {
                    $products[$key]['zh_status'] = '待审核';
                } else if ($audit_status == -1) {
                    $products[$key]['zh_status'] = '未通过';
                } else if ($status == 1) {
                    $products[$key]['zh_status'] = '已开奖';
                } else if ($status == 0) {
                    $products[$key]['zh_status'] = '待开奖';
                } else if ($status == 2) {
                    $products[$key]['zh_status'] = '已过期';
                }
                if ($value['goods_status'] == 1) {
                    $products[$key]['goods_name'] = $value['red_envelope'] . '元/人';
                    $products[$key]['goods_status_name'] = '红包';
                } else if ($value['goods_status'] == 0) {
                    $products[$key]['goods_status_name'] = '实物';
                } else if ($value['goods_status'] == 2) {
                    $products[$key]['goods_status_name'] = '电子卡';
                }
                if ($value['smoke_set'] == 0) {
                    $products[$key]['smoke_name'] = '到时开奖';
                } else if ($value['smoke_set'] == 1) {
                    $products[$key]['smoke_name'] = '人数开奖';
                } else if ($value['smoke_set'] == 2) {
                    $products[$key]['smoke_name'] = '手动开奖';
                } else if ($value['smoke_set'] == 3) {
                    $products[$key]['smoke_name'] = '现场开奖';
                }
                $userinfo = pdo_fetch_cj("select * from " . tablename_cj("choujiang_user") . " where uniacid=:uniacid and openid = :openid", array("uniacid" => $uniacid, "openid" => $value['goods_openid']));
                $products[$key]['goods_username'] = $userinfo['nickname'];
                //奖品缩略图
                $products[$key]['goods_icon'] = $this->getImgArray($value['goods_icon'])[0];
            }
            $pager = pagination($total, $pindex, $psize);


        }
        if ($op == 'delete') {
            $id = intval($_GPC['id']);
            $record_num = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_record') . " WHERE goods_id = :id and uniacid = :uniacid", array(':id' => $id, ':uniacid' => $uniacid));
            $row = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_goods') . " WHERE id = :id and uniacid = :uniacid  and is_del != -1", array(':id' => $id, ':uniacid' => $uniacid));
            if (empty($row)) {
                message('信息不存在或是已经被删除！');
            }
            if ($record_num) {
                message('已有人参与，不可删除！');
            }
            pdo_update_cj('choujiang_goods', array('is_del' => -1), array('id' => $id, 'uniacid' => $uniacid));
            message('删除成功!', $this->createWeburl('choujiang_goods', array('op' => 'content')), 'success');
        }
        //多删
        if (!empty($_GPC['deleteall'])) {
            for ($i = 0; $i < count($_GPC['deleteall']); $i++) {
                pdo_update_cj('choujiang_goods', array('is_del' => -1), array('id' => $_GPC['deleteall'][$i]));
            }
            message('删除成功!', $this->createWeburl('choujiang_goods', array('op' => 'content')), 'success');

        }

        //奖品区域
//        $_GPC['cj']['province'] = $_GPC['cj']['area']['province'];
//        $_GPC['cj']['city'] = $_GPC['cj']['area']['city'];
//        $_GPC['cj']['district'] = $_GPC['cj']['area']['district'];
//        unset($_GPC['cj']['area']);
        if ($op == 'post') {
            $curDate = date("Y-m-d");
            $_GPC['cj']['content'] = preg_replace("/-([a-w]|[A-W]|-)*=/i", "data-reg=", str_replace(["&quot;"], [], $_POST['cj']['content']));
            $id = intval($_GPC['id']);
//            $item = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_goods') . " WHERE id = :id  and is_del != -1", array(':id' => $id));
            if (!empty($id)) {
                $item = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_goods') . " WHERE id = :id  and is_del != -1", array(':id' => $id));
                $smoke_time = $item['smoke_time'];
                $userinfo = pdo_fetch_cj("select * from " . tablename_cj("choujiang_user") . " where uniacid=:uniacid and openid = :openid", array("uniacid" => $uniacid, "openid" => $item['goods_openid']));
                $machine = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_machine_num') . " WHERE goods_id = :goods_id", array(':goods_id' => $id));

                //奖品缩略图
                $item['goods_icon']= $this->getImgArray($item['goods_icon']);
                $item['goods_username'] = $userinfo['nickname'];
                $item['date'] = substr($smoke_time, 0, 10);
                $item['time'] = substr($smoke_time, 11, 5);
                $card_info = unserialize($item['card_info']);
                $goods_images = unserialize($item['goods_images']);
                if ($goods_images) {
                    foreach ($goods_images as $key => $value) {
                        if (strstr($value, $_W['attachurl'])) {
                            $goods_images[$key] = str_replace($_W['attachurl'], '', $value);
                        }
                    }
                }


                //奖品区域
                $item['area'] = [
                    'province'=>$item['province'],
                    'city'=>$item['city'],
                    'district'=>$item['district']
                ];
                $item['goods_images'] = $goods_images;
                $newarr = array();
                $i = 0;
                if ($card_info) {
                    foreach ($card_info as $key => $value) {
                        $newarr[$i][0] = $key;
                        $newarr[$i][1] = $value;
                        $i++;

                    }
                }

                $item['card_info'] = $newarr;
                // echo "<pre>";
                // print_r($item);
                // echo "</pre>";
                //   	exit;
                if (empty($item)) {
                    message('抱歉，信息不存在或是已经删除！', '', 'error');
                }

            }

            if (checksubmit('submit')) {
                $machineNum = $_GPC['machine_num'];
                unset($_GPC['machine_num']);

                $goods_status = $_GPC['cj']['goods_status'];
                if ($goods_status == 0) {  //实物
                    if (empty($_GPC['goods_name1'])) {
                        message('奖品名称不能为空，请输入奖品名称！', '', 'error');
                    }
                    if (empty($_GPC['goods_num1']) || $_GPC['goods_num1'] == 0) {
                        message('奖品数量不能为空！', '', 'error');
                    }
                    $_GPC['cj']['goods_name'] = $_GPC['goods_name1'];
                    $_GPC['cj']['goods_num'] = $_GPC['goods_num1'];

                }
//                else if ($goods_status == 1) {  //红包
//                    if (empty($_GPC['cj']['red_envelope'])) {
//                        message('红包金额不能为空，请输入金额！', '', 'error');
//                    }
//                    if (empty($_GPC['goods_num2']) || $_GPC['goods_num2'] == 0) {
//                        message('奖品数量不能为空！', '', 'error');
//                    }
//                    $_GPC['cj']['goods_num'] = $_GPC['goods_num2'];
//                } else if ($goods_status == 2) {   //电子卡
//                    if (empty($_GPC['goods_name3'])) {
//                        message('电子卡名称不能为空，请输入名称！', '', 'error');
//                    }
//                    $_GPC['cj']['goods_name'] = $_GPC['goods_name3'];
//                    $card_number = $_GPC['card_number'];
//                    $card_password = $_GPC['card_password'];
//                    if (count($card_number) <= 0 || count($card_password) <= 0) {
//                        message('请填写电子卡信息', '', 'error');
//                    } else {
//                        $newarr = array();
//                        foreach ($card_number as $key => $value) {
//                            $newarr[$value] = $card_password[$key];
//                        }
//                        $_GPC['cj']['card_info'] = serialize($newarr);
//                        $_GPC['cj']['goods_num'] = count($newarr);
//                    }
//                }
//                $_GPC['cj']['goods_images'] = serialize($_GPC['goods_images']);
                $_GPC['cj']['smoke_time'] = $_GPC['date'] . ' ' . $_GPC['time'];
                if (strtotime($_GPC['cj']['smoke_time']) <= time() && $_GPC['cj']['smoke_set'] == 0) {
                    message('开奖时间不得小于当前时间', '', 'error');
                }

                if (empty($_GPC['cj']['goods_openid'])) {
                    message('发起人不能为空，请添加发起人！', '', 'error');
                }
                $openid_arr = array();
                if ($_GPC['cj']['The_winning'] == 1) {
                    $user = $_GPC['cj']['goods_winning'];
                    $new_user = explode("++", $user);
                    if (count($new_user) > $_GPC['cj']['goods_num']) {
                        message('中奖人数不得大于奖品数量', '', 'error');
                    } else {
                        foreach ($new_user as $key => $value) {
                            $openidd = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_user') . " WHERE nickname = :nickname and uniacid = :uniacid", array(':uniacid' => $uniacid, ':nickname' => $value));
                            if (!empty($openidd)) {
                                array_push($openid_arr, $openidd['openid']);
                            }
                        }
                    }

                }

                //上传图片到服务器
                $pathname = $_GPC['cj']['goods_icon'];
                if(empty($pathname)){
                    message('图片不能为空', '', 'error');
                }
                if(count($pathname) >5){
                    message('图片最多5张', '', 'error');
                }
                //奖品缩略图
                foreach ($_GPC['cj']['goods_icon'] as $k => $v) {
//                    $image = $this->getImgPath($v);
//                    $fileContent = json_decode(file_get_contents($this->attachurl . $image."?x-oss-process=image/info"),true);
                    if (strpos($v,'http:') !== false || strpos($v,'https:') !== false) {
                        $temp = file_get_contents($v);
                    } else {
                        $temp = file_get_contents(ATTACHMENT_ROOT .$v);
                    }
                    $fileContent = json_decode($temp,true);
                    $width = $fileContent['ImageWidth']['value'];
                    $height = $fileContent['ImageHeight']['value'];
                    if ($width > 750 || $height > 400) {
                        message('图片尺寸不符,请重新上传', '', 'error');
                    }
                }
                //晒单封面
                if($item['share_img']!=$_GPC['cj']['share_img']){
                    $remotestatus = $this->file_remote_upload($this->getImgPath($_GPC['cj']['share_img']));
                    if (is_error($remotestatus)) {
                        file_delete($v);
                        message('远程附件上传失败，请检查配置并重新上传', '', 'error');
                    }
                }


                if ($id) {
//                    $pathname = $_GPC['cj']['goods_icon'];
                    foreach ($pathname as $k => $v) {
                        $img_url[] = $this->getImgPath($v);
                    }
                    $_GPC['cj']['goods_icon'] = json_encode($img_url);
                } else {
                    if ($this->baseConfig['type']) {
//                        $pathname = $_GPC['cj']['goods_icon'];
                        foreach ($pathname as $k => $v) {
                            $remotestatus = $this->file_remote_upload($v);
                            if (is_error($remotestatus)) {
                                file_delete($v);
                                message('远程附件上传失败，请检查配置并重新上传', '', 'error');
                            }
                        }
                    }
                    $_GPC['cj']['goods_icon'] = json_encode($_GPC['cj']['goods_icon']);
                }


                $_GPC['cj']['openid_arr'] = serialize($openid_arr);
                $sql = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_user') . " WHERE openid = :openid and uniacid = :uniacid", array(':uniacid' => $uniacid, ':openid' => $_GPC['cj']['goods_openid']));

                //区域限制关闭，区域信息置空
                $is_area = $_GPC['cj']['is_area'];
                if(!$is_area){
                    $_GPC['cj']['province'] = '';
                    $_GPC['cj']['city'] = '';
                }
                if (empty($id)) {
                    $_GPC['cj']['uniacid'] = $uniacid;

                    if ($_GPC['cj']['is_zq'] == 0) {
                        if ($sql['mf_num'] <= 0 && $sql['yu_num'] <= 0) {

//                            message('该发起人次数已达上限', '', 'error');

                        } else if ($sql['mf_num'] > 0) {

                            $data = array('mf_num' => $sql['mf_num'] - 1);

                        } else if ($sql['yu_num'] > 0) {

                            $data = array('yu_num' => $sql['yu_num'] - 1);

                        }
                    } else {
                        if (!$sql["is_manager"]) {
                            if ($sql["extensions_num"] == 0) {
//                                message('该发起人次数已达上限', '', 'error');
                            } else {
                                $data = array('extensions_num' => 0);
                            }
                        }
                    }


                    $wishingId = $_GPC['wishingId'];
                    if ( !empty($wishingId) ) {
                        $wishingError = '';
                        require_once __DIR__ . "/framework/autoload.php";
                        $wishing = new cj_admin_wishingWall();

                        if ( $_GPC['cj']['audit_status'] == 1 ) {
                            /// 将奖品发布时，状态保存至redis中
                            $redis = connect_redis();
                            $editGoodsKey = "cj_wishing_goods:waitPushMessage";
                            $RedisStatus = $_GPC['cj']['audit_status'];
                            $redis->hSet($editGoodsKey, $wishingId, $RedisStatus);
                            $_GPC['cj']['audit_status'] = 2;
                        }
                    }

                        $strs = 1;

                    if (!empty($strs)) {
                        $_GPC['cj']['create_time'] = date('Y-m-d H:i', time());
                        $str = pdo_insert_cj('choujiang_goods', $_GPC['cj']);
                    }
                    $goods_id = pdo_insertid_cj();

                    if ( !empty($wishingId) ) {
                        $wishingAddResult = $wishing->releaseGoods($wishingId, $goods_id);
                        if ( $wishingAddResult['error'] == 1 ) {
                            /// 奖品发布成功，奖品心愿关联失败
                            $redis->Del($editGoodsKey, $wishingId);
                            message($wishingAddResult['message'], '', 'error');
                        }
                    }

                    //添加机器人
                    if ($machineNum > 0) {
                        $machineData = [
                            'goods_id' => $goods_id,
                            'machine_num' => $machineNum,
                            'status' => 0,
                        ];
                        $machineStr = pdo_insert_cj('choujiang_machine_num', $machineData);
                    }

                    //添加奖品二维码
                    //$this->doWebInvitation($goods_id);
                    //是否拼团
                    if ($_GPC['cj']['is_pintuan'] == 0) {
                        $_GPC['cj']['pintuan_maxnum'] = 0;
                    } else {
//                        $this->doWebGroupsInvitation($goods_id);
                    }

                } else {
                    $goods_id = $id;
                    $record = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_record') . " WHERE goods_id = :id", array(':id' => $id));
                    if (empty($record)) {
                        if ($item['is_zq'] != $_GPC['cj']['is_zq']) {
                            if ($_GPC['cj']['is_zq'] == 0) {
                                $_GPC['cj']['is_pintuan'] = 0;
                                $_GPC['cj']['pintuan_maxnum'] = 0;
                                if ($sql['mf_num'] <= 0 && $sql['yu_num'] <= 0) {
//                                    message('该发起人次数已达上限', '', 'error');
                                } else if ($sql['mf_num'] > 0) {
                                    $result = array('mf_num' => $sql['mf_num'] - 1);
                                } else if ($sql['yu_num'] > 0) {
                                    $result = array('yu_num' => $sql['yu_num'] - 1);
                                }
                            } else {
                                //添加组团奖品二维码
//                                $this->doWebGroupsInvitation($goods_id);
                                if (!$sql["is_manager"]) {
                                    if ($sql["extensions_num"] == 0) {
//                                        message('该发起人次数已达上限', '', 'error');
                                    } else {
                                        $result = array('extensions_num' => $sql['extensions_num'] - 1);
                                    }
                                }
                            }
                            if (!$sql["is_manager"]) {
                                pdo_update_cj('choujiang_user', $result, array('id' => $sql['id'], 'uniacid' => $uniacid));
                            }
                        } else {
                            if ($_GPC['cj']['is_pintuan'] == 0) {
                                $_GPC['cj']['pintuan_maxnum'] = 0;
                            }
                        }

                        /// 编辑状态为通过时，自动参与抽奖
                        $wishingId = pdo_get_cj('choujiang_wishing_goods', ['goods_id' => $id]);
                        if ( !empty($wishingId) ) {
                            if ( $_GPC['cj']['audit_status'] == 1 ) {
                                /// 将奖品发布时，状态保存至redis中
                                $redis = connect_redis();
                                $editGoodsKey = "cj_wishing_goods:waitPushMessage";
                                $RedisStatus = $_GPC['cj']['audit_status'];
                                $redis->hSet($editGoodsKey, $wishingId['wishing_id'], $RedisStatus);
                                $_GPC['cj']['audit_status'] = 2;
                            }
                        }

                        $str = pdo_update_cj('choujiang_goods', $_GPC['cj'], array('id' => $id, 'uniacid' => $uniacid));
                        //添加机器人
                        if ($machineNum > 0) {
                            $machineData = [
                                'goods_id' => $goods_id,
                                'machine_num' => $machineNum
                            ];
                            $machineStr = pdo_update_cj('choujiang_machine_num', $machineData, ['goods_id' => $goods_id]);
                        }
                    } else {

                        /// 编辑状态为通过时，自动参与抽奖
                        $wishingId = pdo_get_cj('choujiang_wishing_goods', ['goods_id' => $id]);
                        if ( !empty($wishingId) ) {
                            if ( $_GPC['cj']['audit_status'] == 1 ) {
                                /// 将奖品发布时，状态保存至redis中
                                $redis = connect_redis();
                                $editGoodsKey = "cj_wishing_goods:waitPushMessage";
                                $RedisStatus = $_GPC['cj']['audit_status'];
                                $redis->hSet($editGoodsKey, $wishingId['wishing_id'], $RedisStatus);
                                $_GPC['cj']['audit_status'] = 2;
                            }
                        }

                        ///状态可以修改
                        $editStatus = pdo_update_cj('choujiang_goods', [
                            'audit_status' => $_GPC['cj']['audit_status']
                        ], ['id' => $goods_id]);

                        if ($editStatus) {
                            message('审核状态已修改，其他信息修改失败（已有人参与抽奖）', '', 'success');
                        } else {
                            message('已有人参与该活动、不可编辑（仅可修改审核状态）', '', 'error');
                        }
                    }
                    if (empty($str) && empty($machineStr)) {
                        message('数据未变更!', '', 'error');
                    }

                }
                if (!empty($str) || !empty($machineStr)) {
                    message('信息 添加/修改 成功!', $this->createWeburl('choujiang_goods', array('op' => 'content')), 'success');
                }
            }
        }

        if ($op == 'user') {
            if (!empty($_GPC['user_nickname'])) {
                $keyword = $_GPC['user_nickname'];
                $condition = "uniacid=" . $uniacid . " AND nickname='" . $keyword . "'";
                $records = pdo_fetch_cj("SELECT openid,nickname,avatar FROM " . tablename_cj("choujiang_user") . " WHERE " . $condition . " LIMIT 1");
                if (!empty($records)) {
                    $fmdata = array(
                        "success" => 1,
                        "data" => $records['openid'],
                    );
                } else {
                    $fmdata = array(

                        "success" => -1,

                        'data' => '此用户未找到',
                    );
                }
                echo json_encode($fmdata);
                exit;
            }
            $keyword = $_GPC['keyword_user'];

            $condition = [
                'uniacid' => $uniacid,
                'nickname LIKE' => "%{$keyword}%"
            ];

            if (!$_GPC['machine_canyu']) {
                $condition['is_machine'] = 0;
            }
            $records = pdo_getall_cj("choujiang_user", $condition, ['openid', 'nickname', 'avatar'], '', [], [0, 40]);
            $fmdata = array(
                "success" => 1,
                "data" => $records,
            );
            echo json_encode($fmdata);
            exit;
        }
        include $this->template('choujiang_goods');
    }

    // 小程序推荐
    public function doWebChoujiang_xcx()
    {
        global $_W, $_GPC;
        $op = $_GPC['op'];
        $ops = array('post', 'content', 'delete', 'index');
        $op = in_array($op, $ops) ? $op : 'content';
        $uniacid = $_W['uniacid'];
        if ($op == 'content') {
            $pindex = max(1, intval($_GPC['page']));
            $psize = 10;//每页显示个数
            $keyword = $_GPC['keyword'];
            if ($keyword) {
                $products = pdo_fetchall_cj("select * from " . tablename_cj("choujiang_xcx") . " where uniacid=:uniacid and name like '%{$keyword}%' ORDER BY id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array("uniacid" => $uniacid));
                $total = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_xcx') . " where uniacid=:uniacid  and name like '%{$keyword}%'", array(':uniacid' => $uniacid));
            } else {
                $products = pdo_fetchall_cj("select * from " . tablename_cj("choujiang_xcx") . " where uniacid=:uniacid ORDER BY id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array("uniacid" => $uniacid));
                $total = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_xcx') . ' where uniacid=:uniacid ', array(':uniacid' => $uniacid));
            }

            $pager = pagination($total, $pindex, $psize);
        }
        if ($op == 'index') {
            $xcx_id = intval($_GPC['xcx_id']);
            $row = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_xcx') . " WHERE id = :id and uniacid = :uniacid ", array(':id' => $xcx_id, ':uniacid' => $uniacid));
            if ($row['status'] == 0) {
                $data['status'] = 1;
            } else if ($row['status'] == 1) {
                $data['status'] = 0;
            }
            $str = pdo_update_cj('choujiang_xcx', $data, array('id' => $xcx_id, 'uniacid' => $uniacid));
            $fmdata = array(
                "success" => 1,
                "data" => $data['status'],
            );
            echo json_encode($fmdata);
            exit;
        }
        if ($op == 'delete') {
            $id = intval($_GPC['id']);
            $row = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_xcx') . " WHERE id = :id and uniacid = :uniacid ", array(':id' => $id, ':uniacid' => $uniacid));
            if (empty($row)) {
                message('信息不存在或是已经被删除！');
            }
            pdo_delete_cj('choujiang_xcx', array('id' => $id, 'uniacid' => $uniacid));
            message('删除成功!', $this->createWeburl('choujiang_xcx', array('op' => 'content')), 'success');
        }
        //多删
        if (!empty($_GPC['deleteall'])) {
            for ($i = 0; $i < count($_GPC['deleteall']); $i++) {
                pdo_delete_cj('choujiang_xcx', array('id' => $_GPC['deleteall'][$i]));
            }
            message('删除成功!', $this->createWeburl('choujiang_xcx', array('op' => 'content')), 'success');

        }

        if ($op == 'post') {

            $id = intval($_GPC['id']);
            if (!empty($id)) {
                $item = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_xcx') . " WHERE id = :id", array(':id' => $id));
                if (empty($item)) {
                    message('抱歉，信息不存在或是已经删除！', '', 'error');
                }

            }
            if (checksubmit('submit')) {
                if (empty($_GPC['cj']['name'])) {
                    message('奖品名称不能为空，请输入奖品名称！');
                }
                if (empty($id)) {
                    $_GPC['cj']['uniacid'] = $uniacid;
                    $str = pdo_insert_cj('choujiang_xcx', $_GPC['cj']);

                } else {
                    $str = pdo_update_cj('choujiang_xcx', $_GPC['cj'], array('id' => $id, 'uniacid' => $uniacid));
                }
                if (!empty($str)) {

                    message('信息 添加/修改 成功!', $this->createWeburl('choujiang_xcx', array('op' => 'content')), 'success');

                }
            }
        }
        include $this->template('choujiang_xcx');
    }


    // 常见问题
    public function doWebChoujiang_problems()
    {
        global $_W, $_GPC;
        $op = $_GPC['op'];
        $ops = array('post', 'content', 'delete', 'index');
        $op = in_array($op, $ops) ? $op : 'content';
        $uniacid = $_W['uniacid'];
        //筛选出序号最大值
        $maxSort = pdo_fetch_cj("select max(sort) as maxSort from" . tablename_cj("choujiang_problems"));
        $item['sort'] = $maxSort['maxSort'] + 1;
        if ($op == 'content') {
            $pindex = max(1, intval($_GPC['page']));
            $psize = 10;//每页显示个数
            $keyword = $_GPC['keyword'];
            if ($keyword) {
                $products = pdo_fetchall_cj("select * from " . tablename_cj("choujiang_problems") . " where uniacid=:uniacid and title like '%{$keyword}%' ORDER BY sort asc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array("uniacid" => $uniacid));
                $total = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_problems') . " where uniacid=:uniacid  and title like '%{$keyword}%'", array(':uniacid' => $uniacid));
            } else {
                $products = pdo_fetchall_cj("select * from " . tablename_cj("choujiang_problems") . " where uniacid=:uniacid ORDER BY sort asc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array("uniacid" => $uniacid));
                $total = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_problems') . ' where uniacid=:uniacid ', array(':uniacid' => $uniacid));
            }
            foreach ($products as $k => $v) {
                $products[$k]['answer'] = str_replace(" ", " ", str_replace("\n", "<br/>", $v['answer']));

            }
            $pager = pagination($total, $pindex, $psize);
        }
        if ($op == 'index') {
            $xcx_id = intval($_GPC['xcx_id']);
            $row = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_problems') . " WHERE id = :id and uniacid = :uniacid ORDER BY sort asc ", array(':id' => $xcx_id, ':uniacid' => $uniacid));
            if ($row['status'] == 0) {
                $data['status'] = 1;
            } else if ($row['status'] == 1) {
                $data['status'] = 0;
            }
            $str = pdo_update_cj('choujiang_problems', $data, array('id' => $xcx_id, 'uniacid' => $uniacid));
            $fmdata = array(
                "success" => 1,
                "data" => $data['status'],
            );
            echo json_encode($fmdata);
            exit;
        }
        if ($op == 'delete') {
            $id = intval($_GPC['id']);
            $row = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_problems') . " WHERE id = :id and uniacid = :uniacid ", array(':id' => $id, ':uniacid' => $uniacid));
            if (empty($row)) {
                message('信息不存在或是已经被删除！');
            }
            pdo_delete_cj('choujiang_problems', array('id' => $id, 'uniacid' => $uniacid));
            message('删除成功!', $this->createWeburl('choujiang_problems', array('op' => 'content')), 'success');
        }
        //多删
        if (!empty($_GPC['deleteall'])) {
            for ($i = 0; $i < count($_GPC['deleteall']); $i++) {
                pdo_delete_cj('choujiang_problems', array('id' => $_GPC['deleteall'][$i]));
            }
            message('删除成功!', $this->createWeburl('choujiang_problems', array('op' => 'content')), 'success');

        }

        if ($op == 'post') {

            $id = intval($_GPC['id']);
            if (!empty($id)) {
                $item = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_problems') . " WHERE id = :id", array(':id' => $id));
                if (empty($item)) {
                    message('抱歉，信息不存在或是已经删除！', '', 'error');
                }

            }
            if (checksubmit('submit')) {
                if (empty($_GPC['cj']['title'])) {
                    message('问题不能为空，请输入问题名称！');
                }
                if (empty($id)) {
                    $_GPC['cj']['uniacid'] = $uniacid;
                    $str = pdo_insert_cj('choujiang_problems', $_GPC['cj']);

                } else {
                    $str = pdo_update_cj('choujiang_problems', $_GPC['cj'], array('id' => $id, 'uniacid' => $uniacid));
                }
                if (!empty($str)) {

                    message('信息 添加/修改 成功!', $this->createWeburl('choujiang_problems', array('op' => 'content')), 'success');

                }
            }
        }
        include $this->template('choujiang_problems');
    }

    // 中奖记录
    public function doWebChoujiang_record()
    {
        global $_W, $_GPC;
        $op = $_GPC['op'];
        $ops = array('post', 'content', 'send');
        $op = in_array($op, $ops) ? $op : 'content';
        $uniacid = $_W['uniacid'];
        if ($op == 'content') {
            $pindex = max(1, intval($_GPC['page']));
            $psize = 10;//每页显示个数
            $keyword = $_GPC['keyword'];
            $re_status = $_GPC['status'];
            $field = $_GPC['field'];

            if ($re_status == 1) {
                //已中奖
                $condition = 'and' . tablename_cj('choujiang_record') . '.status=1';
            } elseif ($re_status == 2) {
                //未中奖
                $condition = 'and' . tablename_cj('choujiang_record') . '.status=0 and ' . tablename_cj('choujiang_goods') . '.status=1';
            } elseif ($re_status == 3) {
                //已作废
                $condition = 'and' . tablename_cj('choujiang_record') . '.status=-1';
            } elseif ($re_status == 4) {
                //未开奖
                $condition = 'and' . tablename_cj('choujiang_goods') . '.status=0';
            }
            if ($keyword) {
                $products = pdo_fetchall_cj("select " . tablename_cj('choujiang_record') . ".*, " . tablename_cj("choujiang_goods") . " .goods_openid as goods_openid," . tablename_cj("choujiang_goods") . " .status as _status," . tablename_cj("choujiang_goods") . " .goods_icon," . tablename_cj("choujiang_goods") . " .is_del from" . tablename_cj('choujiang_record') . "left join" . tablename_cj('choujiang_goods') . "on" . tablename_cj('choujiang_record') . ".goods_id=" . tablename_cj('choujiang_goods') . ".id WHERE " . tablename_cj('choujiang_record') . ".uniacid=:uniacid {$condition} and " . tablename_cj('choujiang_goods') . ".is_del!=-1 and " . tablename_cj('choujiang_record') . ".goods_name like '%{$keyword}%' ORDER BY " . tablename_cj('choujiang_record') . ".id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array("uniacid" => $uniacid));
                if ($field == 1) {
                    $lCondition = ".nickname like '%{$keyword}%'";
                } else {
                    $lCondition = ".goods_name like '%{$keyword}%'";
                }
                $products = pdo_fetchall_cj("select " . tablename_cj('choujiang_record') . ".*, " . tablename_cj("choujiang_goods") . " .goods_openid as goods_openid," . tablename_cj("choujiang_goods") . " .status as _status," . tablename_cj("choujiang_goods") . " .goods_icon," . tablename_cj("choujiang_goods") . " .is_del from" . tablename_cj('choujiang_record') . "left join" . tablename_cj('choujiang_goods') . "on" . tablename_cj('choujiang_record') . ".goods_id=" . tablename_cj('choujiang_goods') . ".id WHERE " . tablename_cj('choujiang_record') . ".uniacid=:uniacid {$condition} and " . tablename_cj('choujiang_goods') . ".is_del!=-1 and " . tablename_cj('choujiang_record') . "{$lCondition} ORDER BY " . tablename_cj('choujiang_record') . ".id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array("uniacid" => $uniacid));
                $total = pdo_fetchcolumn_cj("select count(*) from" . tablename_cj('choujiang_record') . "left join" . tablename_cj('choujiang_goods') . " on " . tablename_cj('choujiang_record') . ".goods_id=" . tablename_cj('choujiang_goods') . ".id WHERE " . tablename_cj('choujiang_record') . ".uniacid=:uniacid {$condition} and " . tablename_cj('choujiang_goods') . ".is_del!=-1 and " . tablename_cj('choujiang_record') . ".goods_name like '%{$keyword}%' ", array("uniacid" => $uniacid));
            } else {
                $products = pdo_fetchall_cj("select " . tablename_cj('choujiang_record') . ".*, " . tablename_cj("choujiang_goods") . " .goods_openid as goods_openid,". tablename_cj("choujiang_goods") . " .status as _status," . tablename_cj("choujiang_goods") . " .goods_icon," . tablename_cj("choujiang_goods") . " .is_del from" . tablename_cj('choujiang_record') . "left join" . tablename_cj('choujiang_goods') . "on" . tablename_cj('choujiang_record') . ".goods_id=" . tablename_cj('choujiang_goods') . ".id WHERE " . tablename_cj('choujiang_record') . ".uniacid=:uniacid {$condition} and " . tablename_cj('choujiang_goods') . ".is_del!=-1 ORDER BY " . tablename_cj('choujiang_record') . ".id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array("uniacid" => $uniacid));
                $total = pdo_fetchcolumn_cj("select count(*) from" . tablename_cj('choujiang_record') . "left join" . tablename_cj('choujiang_goods') . " on " . tablename_cj('choujiang_record') . ".goods_id=" . tablename_cj('choujiang_goods') . ".id WHERE " . tablename_cj('choujiang_record') . ".uniacid=:uniacid {$condition} and " . tablename_cj('choujiang_goods') . ".is_del!=-1", array("uniacid" => $uniacid));
            }
            foreach ($products as $k => $v) {
                $products[$k]['goods_icon'] = $this->getImgArray($v['goods_icon'])[0];
//
                if ($v['status'] == 0 && $v['_status'] == 1) {
                    $str = '未中奖';
                } elseif ($v['_status'] == 0 && $v['status'] == 0) {
                    $str = '未开奖';
                } elseif ($v['status'] == 1) {
                    $str = '已中奖';
                } elseif ($v['status'] == -1) {
                    $str = '已作废(未填写地址)';
                }
                $products[$k]['state'] = $str;
                $products[$k]['create_time'] = date('Y-m-d H:i', $v['create_time']);
                $products[$k]['finish_time'] = date('Y-m-d H:i', $v['finish_time']);

                $openIds[$v['goods_openid']] = $v['goods_openid'];
            }

            if (! empty($openIds)) {
                $goodsUser = pdo_getall_cj("choujiang_user", ['openid' => $openIds], ['openid','tel'], $keyfield = 'openid');
            }

            $pager = pagination($total, $pindex, $psize);

        }
        if ($op == 'post') {
            $id = $_GPC['id'];
            $item = pdo_fetch_cj("select * from " . tablename_cj("choujiang_record") . " where uniacid=:uniacid and id = :id", array(":id" => $id, "uniacid" => $uniacid));
            $good =  pdo_fetch_cj("select * from " . tablename_cj("choujiang_goods") . " where uniacid=:uniacid and id = :id", array("id" => $item['goods_id'], "uniacid" => $uniacid));
            $expressList = pdo_getall_cj("choujiang_express", '', [], '', ['id desc']);
        }
        //更新物流信息
        if ($op == 'send') {
            $data['express_no'] = $_GPC['express_no'];
            $data['express_company'] = $_GPC['express_company'];
            $data['ex_create_at'] = date('Y-m-d H:i:s', time());
            $res = pdo_update_cj('choujiang_record', $data, array('id' => $_GPC['id'], 'uniacid' => $uniacid));
            $recordInfo = pdo_get_cj('choujiang_record', array('id' => $_GPC['id']));
            $goods = pdo_get_cj('choujiang_goods', array('id' => $recordInfo['goods_id']));
            $condition = [
                'goods_id' => $recordInfo['goods_id'],
                'openid' => $recordInfo['openid']
            ];
            $shareOrderInfo = pdo_get_cj('choujiang_share_order', $condition);
            if($shareOrderInfo['status'] == '-2'){
                $result['status'] = 0;
                $result['update_at'] = date('Y-m-d H:i:s');
                pdo_update_cj('choujiang_share_order', $result,$condition);
            }
            if ($res) {
                message('保存成功', '', 'success');
            }
            exit;
        }
        include $this->template('choujiang_record');
    }

    /*
     *向微信接口请求二维码
     *
     */
    public function getWxInvitation($noncestr)
    {
        $wxapp = WeAccount::create();
        $access_token = $wxapp->getAccessToken();
        $width = 430;
        $post_data = '{"path":"' . $noncestr . '","width":' . $width . '}';
        $url = 'https://api.weixin.qq.com/wxa/getwxacode?access_token=' . $access_token;
        $result = $this->api_notice_increment($url, $post_data);
        return $result;
    }

    // 新增奖品生成二维码图片
    public function doWebInvitation($id = '')
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $goods_id = $id;
        if ($_GPC['op'] == 'refresh') {
            $goods_id = $_GPC['id'];
        }
        $repeatTime = 3;
        $noncestr = '/choujiang_page/drawDetails/drawDetails?id=' . $goods_id;
        $result = $this->getWxInvitation($noncestr);
        $jsonResult = @json_decode($result, true);
        $l = 0;
        if (!is_null($jsonResult)) {
            for ($i = 0; $i < $repeatTime; $i++) {
                $cacheKey = "accesstoken:{$this->baseConfig['appid']}";
                cache_delete($cacheKey);
                $result = $this->getWxInvitation($noncestr);
                $jsonResult = @json_decode($result, true);
                if (is_null($jsonResult)) {
                    break;
                }
                $l++;
            }
        }

        $image_name = md5(uniqid(rand())) . ".jpg";
        $filepath = "../attachment/choujiang_page/{$image_name}";
        $file_put = file_put_contents($filepath, $result);

        if ($file_put) {
            $sql = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_verification') . " where uniacid=:uniacid and goods_id = :id", array(":uniacid" => $uniacid, ':id' => $goods_id));
            if (empty($sql)) {
                if ($l == 3) {
                    $datas = array('verification' => $image_name, 'uniacid' => $uniacid, 'goods_id' => $goods_id, 'haibao' => '1');
                } else {
                    $datas = array('verification' => $image_name, 'uniacid' => $uniacid, 'goods_id' => $goods_id, 'haibao' => '0');
                }
                pdo_insert_cj("choujiang_verification", $datas);
            } else {
                if ($l == 3) {
                    $datas = array('verification' => $image_name, 'haibao' => '1');
                } else {
                    $datas = array('verification' => $image_name, 'haibao' => '0');
                }
                pdo_update_cj("choujiang_verification", $datas, array('goods_id' => $goods_id, 'uniacid' => $uniacid));
            }
        } else {
            $filepath = "attachment/choujiang_page/{$image_name}";
        }

        if ($_GPC['op'] == 'refresh') {
            if ($l == 3) {
                echo json_encode(["success" => -1]);
                exit;
            } else {
                echo json_encode(["success" => 1]);
                exit;
            }
        }
        return $filepath;

    }

    // 新增奖品生成组团二维码图片
    public function doWebGroupsInvitation($id)
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $goods_id = $id;

        $result = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_base') . " where uniacid=:uniacid", array(":uniacid" => $uniacid));
//        $APPID = $result['appid'];
//        $SECRET = $result['appsecret'];
//        $tokenUrl = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$APPID}&secret={$SECRET}";
//        $getArr = array();
//        $tokenArr = json_decode($this->send_post($tokenUrl, $getArr, "GET"));
//        $access_token = $tokenArr->access_token;
        $noncestr = 'choujiang_page/groupsinvitation/groupsinvitation?id=' . $goods_id;
        $result = $this->getWxInvitation($noncestr);

        $image_name = md5(uniqid(rand())) . ".jpg";
        $filepath = "../attachment/choujiang_page/{$image_name}";
        $file_put = file_put_contents($filepath, $result);
        if ($file_put) {
            $sql = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_verification') . " where uniacid=:uniacid and goods_id = :id", array(":uniacid" => $uniacid, ':id' => $goods_id));
            if (empty($sql)) {
                $datas = array('group_verification' => $image_name, 'uniacid' => $uniacid, 'goods_id' => $goods_id);
                pdo_insert_cj("choujiang_verification", $datas);
            } else {
                $datas = array('group_verification' => $image_name);
                pdo_update_cj("choujiang_verification", $datas, array('goods_id' => $goods_id, 'uniacid' => $uniacid));
            }
        } else {
            $filepath = "attachment/choujiang_page/{$image_name}";
        }
        return $filepath;
    }

    private function send_post($url, $post_data, $method = 'POST')
    {
        $postdata = http_build_query($post_data);
        $options = array(
            'http' => array(
                'method' => $method, //or GET
                'header' => 'Content-type:application/x-www-form-urlencoded',
                'content' => $postdata,
                'timeout' => 15 * 60 // 超时时间（单位:s）
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return $result;
    }

    private function api_notice_increment($url, $data)
    {
        $ch = curl_init();
//        $header = "Accept-Charset: utf-8";
        $header = "Accept-Charset: utf-8";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
//        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $tmpInfo = curl_exec($ch);
        if (curl_errno($ch)) {
            return false;
        } else {
            return $tmpInfo;
        }
    }

    // 支付管理
    // 常见问题
    public function doWebChoujiang_pay()
    {
        global $_W, $_GPC;
        $op = $_GPC['op'];
        $ops = array('post', 'content', 'delete');
        $op = in_array($op, $ops) ? $op : 'content';
        $uniacid = $_W['uniacid'];
        if ($op == 'content') {
            $pindex = max(1, intval($_GPC['page']));
            $psize = 10;//每页显示个数
            $keyword = $_GPC['keyword'];
            if ($keyword) {
                $products = pdo_fetchall_cj("select * from " . tablename_cj("choujiang_vip_num") . " where uniacid=:uniacid and title like '%{$keyword}%' ORDER BY id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array("uniacid" => $uniacid));
                $total = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_vip_num') . " where uniacid=:uniacid  and title like '%{$keyword}%'", array(':uniacid' => $uniacid));
            } else {
                $products = pdo_fetchall_cj("select * from " . tablename_cj("choujiang_vip_num") . " where uniacid=:uniacid ORDER BY id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array("uniacid" => $uniacid));
                $total = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_vip_num') . ' where uniacid=:uniacid ', array(':uniacid' => $uniacid));
            }

            $pager = pagination($total, $pindex, $psize);
        }
        if ($op == 'delete') {
            $id = intval($_GPC['id']);
            $row = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_vip_num') . " WHERE id = :id and uniacid = :uniacid ", array(':id' => $id, ':uniacid' => $uniacid));
            if (empty($row)) {
                message('信息不存在或是已经被删除！');
            }
            pdo_delete_cj('choujiang_vip_num', array('id' => $id, 'uniacid' => $uniacid));
            message('删除成功!', $this->createWeburl('choujiang_pay', array('op' => 'content')), 'success');
        }
        //多删
        if (!empty($_GPC['deleteall'])) {
            for ($i = 0; $i < count($_GPC['deleteall']); $i++) {
                pdo_delete_cj('choujiang_vip_num', array('id' => $_GPC['deleteall'][$i]));
            }
            message('删除成功!', $this->createWeburl('choujiang_pay', array('op' => 'content')), 'success');

        }

        if ($op == 'post') {

            $id = intval($_GPC['id']);
            if (!empty($id)) {
                $item = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_vip_num') . " WHERE id = :id", array(':id' => $id));
                if (empty($item)) {
                    message('抱歉，信息不存在或是已经删除！', '', 'error');
                }

            }
            if (checksubmit('submit')) {
                if (empty($_GPC['cj']['title'])) {
                    message('问题不能为空，请输入问题名称！');
                }
                if (empty($id)) {
                    $_GPC['cj']['uniacid'] = $uniacid;
                    $str = pdo_insert_cj('choujiang_vip_num', $_GPC['cj']);

                } else {
                    $str = pdo_update_cj('choujiang_vip_num', $_GPC['cj'], array('id' => $id, 'uniacid' => $uniacid));
                }
                if (!empty($str)) {

                    message('信息 添加/修改 成功!', $this->createWeburl('choujiang_pay', array('op' => 'content')), 'success');

                }
            }
        }
        include $this->template('choujiang_pay');
    }

    public function doWebChoujiang_pay_record()
    {
        global $_W, $_GPC;
        $op = $_GPC['op'];
        $uniacid = $_W['uniacid'];
        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;//每页显示个数
        $keyword = $_GPC['keyword'];
        if ($keyword) {
            $products = pdo_fetchall_cj("select * from " . tablename_cj("choujiang_pay_record") . " where uniacid=:uniacid and nickname like '%{$keyword}%' ORDER BY id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array("uniacid" => $uniacid));
            $total = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_pay_record') . " where uniacid=:uniacid and nickname like '%{$keyword}%'", array(':uniacid' => $uniacid));

        } else {
            $products = pdo_fetchall_cj("select * from " . tablename_cj("choujiang_pay_record") . " where uniacid=:uniacid ORDER BY id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array("uniacid" => $uniacid));
            $total = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_pay_record') . ' where uniacid=:uniacid ', array(':uniacid' => $uniacid));

        }
        foreach ($products as $key => $value) {
            $openid = $value['openid'];
            if ($value['status'] == 1) {
                $pay_type = pdo_fetch_cj("SELECT title FROM " . tablename_cj('choujiang_vip_num') . " WHERE id = :id and uniacid = :uniacid", array(':id' => $value['vip_id'], ':uniacid' => $uniacid));
                $products[$key]['pay_type'] = $pay_type['title'];
            } else if ($value['status'] == 2) {
                $products[$key]['pay_type'] = '发起红包抽奖';
            } else if ($value['status'] == 6) {
                $products[$key]['pay_type'] = '增加小程序跳转';
            } else if ($value['status'] == 3) {
                $products[$key]['pay_type'] = '付费抽奖';
                $products[$key]['y_total'] = $value['total'];
            } else if ($value['status'] == 4) {
                $products[$key]['pay_type'] = '用户提现';
                $products[$key]['total'] = '-' . $value['total'];
            } else if ($value['status'] == 7) {
                $products[$key]['pay_type'] = '扩展付费';
                $products[$key]['total'] = $value['total'];
            }
            // else if($value['status'] == 5){
            // 	$products[$key]['pay_type'] = '中奖收益';
            // 	$products[$key]['total'] = '-'.$value['total'];
            // }

            $products[$key]['create_time'] = date('Y-m-d H:i', $value['create_time']);
        }
        $pager = pagination($total, $pindex, $psize);
        include $this->template('choujiang_pay_record');

    }

    // 用户提现
    public function doWebChoujiang_withdrawal()
    {
        global $_W, $_GPC;
        $op = $_GPC['op'];
        $ops = array('content', 'yes', 'no');
        $op = in_array($op, $ops) ? $op : 'content';
        $uniacid = $_W['uniacid'];
        if ($op == 'content') {
            $pindex = max(1, intval($_GPC['page']));
            $psize = 20;//每页显示个数
            $keyword = $_GPC['keyword'];
            if ($keyword) {
                $products = pdo_fetchall_cj("select * from " . tablename_cj("choujiang_withdrawal") . " where uniacid=:uniacid and nickname like '%{$keyword}%' ORDER BY id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array("uniacid" => $uniacid));
                $total = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_withdrawal') . " where uniacid=:uniacid  and nickname like '%{$keyword}%'", array(':uniacid' => $uniacid));
            } else {
                $products = pdo_fetchall_cj("select * from " . tablename_cj("choujiang_withdrawal") . " where uniacid=:uniacid ORDER BY id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array("uniacid" => $uniacid));
                $total = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_withdrawal') . ' where uniacid=:uniacid ', array(':uniacid' => $uniacid));
            }
            foreach ($products as $key => $value) {
                $products[$key]['create_time'] = date('Y-m-d H:i', $value['create_time']);
                if ($value['status'] == 0) {
                    $products[$key]['status_name'] = '待受理';
                } else if ($value['status'] == 1) {
                    $products[$key]['status_name'] = '已提现';
                } else if ($value['status'] == -1) {
                    $products[$key]['status_name'] = '提现失败';
                }
            }

            $pager = pagination($total, $pindex, $psize);
        }
        if ($op == 'yes') {
            $id = intval($_GPC['id']);
            $products = pdo_fetch_cj("select * from " . tablename_cj("choujiang_withdrawal") . " where uniacid=:uniacid and id = :id and status = 0", array(":uniacid" => $uniacid, ":id" => $id));

            if (!empty($products)) {
                $tx = $this->doWebConfirm($products['money'], $products['openid'], $products['nickname']);
                if ($tx == 1) {
                    $str = pdo_update_cj('choujiang_withdrawal', array('status' => 1), array('id' => $id, 'uniacid' => $uniacid));
                }
            }
            if ($str) {
                message('信息 添加/修改 成功!', $this->createWeburl('choujiang_withdrawal', array('op' => 'display')), 'success');
            } else {
                message('信息 添加/修改 失败!', $this->createWeburl('choujiang_withdrawal', array('op' => 'display')), 'error');
            }
            // if($str){
            // 	$fmdata = array(
            // 		"success" => 1,
            // 		"data" => 1,
            // 	);
            // }else{
            // 	$fmdata = array(
            // 		"success" => 1,
            // 		"data" => -1,
            // 	);
            // }

            // echo json_encode($fmdata);
            // exit;
        }
        if ($op == 'no') {
            $id = intval($_GPC['id']);
            $products = pdo_fetch_cj("select * from " . tablename_cj("choujiang_withdrawal") . " where uniacid=:uniacid and id = :id and status = 0", array(":uniacid" => $uniacid, ":id" => $id));
            if (!empty($products)) {
                $member = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_user') . " where uniacid=:uniacid and openid = :openid", array(":uniacid" => $_W['uniacid'], ':openid' => $products['openid']));
                $remaining_sum = floatval($member['remaining_sum']);
                $new_remaining_sum = $remaining_sum + $products['total'];
                $strs = pdo_update_cj('choujiang_user', array('remaining_sum' => $new_remaining_sum), array('id' => $member['id']));
                $str = pdo_update_cj('choujiang_withdrawal', array('status' => -1), array('id' => $id, 'uniacid' => $uniacid));
            }
            if ($str && $strs) {
                message('信息 添加/修改 成功!', $this->createWeburl('choujiang_withdrawal', array('op' => 'display')), 'success');
            } else {
                message('信息 添加/修改 失败!', $this->createWeburl('choujiang_withdrawal', array('op' => 'display')), 'error');
            }
            // 	$fmdata = array(
            // 		"success" => 1,
            // 		"data" => 1,
            // 	);
            // }else{
            // 	$fmdata = array(
            // 		"success" => 1,
            // 		"data" => -1,
            // 	);
            // }
            // echo json_encode($fmdata);
            // exit;
        }
        include $this->template('choujiang_withdrawal');

    }

    // 提现方法
    public function doWebConfirm($total, $openid, $nickname)
    {
        global $_W, $_GPC;
        include 'wxtx.php';
        load()->func('tpl');
        $user_openid = $openid;
        $tx_cost = intval($total * 100);
        $uniacid = $_W['uniacid'];
        $u_name = $nickname;
        $key = pdo_fetch_cj("SELECT * FROM " . tablename_cj("choujiang_base") . " where uniacid=:uniacid", array(":uniacid" => $uniacid));
        // $appsecret =$key['appsecret'];
        $appid = $key['appid'];   //微信公众平台的appid
        $mch_id = $key['mch_id'];  //商户号id
        $openid = $user_openid;    //用户openid
        $amount = $tx_cost;  //提现金额$money_sj
        $desc = "提现";     //企业付款描述信息
        $appkey = $key['appkey'];   //商户号支付密钥
        $re_user_name = $u_name;   //收款用户姓名
        $Weixintx = new WeixinTx($appid, $mch_id, $openid, $amount, $desc, $appkey, $re_user_name);
        $notify_url = $Weixintx->Wxtx();
        if ($notify_url['return_code'] == "SUCCESS" && $notify_url['result_code'] == "SUCCESS") {
            $str = 1;
        } else {
            $str = -1;
        }
        return $str;

    }


    // 骗审管理
    public function doWebChoujiang_cheat()
    {
        global $_W, $_GPC;
        $op = $_GPC['op'];
        $ops = array('post', 'content', 'delete', 'index');
        $op = in_array($op, $ops) ? $op : 'content';
        $uniacid = $_W['uniacid'];
        if ($op == 'content') {
            $pindex = max(1, intval($_GPC['page']));
            $psize = 10;//每页显示个数

            $products = pdo_fetchall_cj("select * from " . tablename_cj("choujiang_cheat") . " where uniacid=:uniacid ORDER BY id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array("uniacid" => $uniacid));
            $total = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_cheat') . ' where uniacid=:uniacid ', array(':uniacid' => $uniacid));

            $pager = pagination($total, $pindex, $psize);
            $base = pdo_fetch_cj("SELECT * FROM " . tablename_cj("choujiang_base") . " where uniacid=:uniacid", array(":uniacid" => $uniacid));

        }
        if ($op == 'delete') {
            $id = intval($_GPC['id']);
            $row = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_cheat') . " WHERE id = :id and uniacid = :uniacid ", array(':id' => $id, ':uniacid' => $uniacid));
            if (empty($row)) {
                message('信息不存在或是已经被删除！');
            }
            pdo_delete_cj('choujiang_cheat', array('id' => $id, 'uniacid' => $uniacid));
            message('删除成功!', $this->createWeburl('choujiang_cheat', array('op' => 'content')), 'success');
        }
        //多删
        if (!empty($_GPC['deleteall'])) {
            for ($i = 0; $i < count($_GPC['deleteall']); $i++) {
                pdo_delete_cj('choujiang_cheat', array('id' => $_GPC['deleteall'][$i]));
            }
            message('删除成功!', $this->createWeburl('choujiang_cheat', array('op' => 'content')), 'success');
        }

        //骗审按钮
        if ($_GPC['teshu'] == 1) {
            pdo_update_cj('choujiang_base', array('cheat_status' => $_GPC['cheat_status']), array('uniacid' => $uniacid));
            message('操作成功', $this->createWeburl('choujiang_cheat', array('op' => 'content')), 'success');
        }

        if ($op == 'post') {

            $id = intval($_GPC['id']);
            if (!empty($id)) {
                $item = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_cheat') . " WHERE id = :id", array(':id' => $id));
                if (empty($item)) {
                    message('抱歉，信息不存在或是已经删除！', '', 'error');
                }

            }
            if (checksubmit('submit')) {
                if (empty($_GPC['cj']['title'])) {
                    message('标题不能为空，请输入标题！');
                }
                if (empty($id)) {
                    $_GPC['cj']['uniacid'] = $uniacid;
                    $str = pdo_insert_cj('choujiang_cheat', $_GPC['cj']);

                } else {
                    $str = pdo_update_cj('choujiang_cheat', $_GPC['cj'], array('id' => $id, 'uniacid' => $uniacid));
                }
                if (!empty($str)) {

                    message('信息 添加/修改 成功!', $this->createWeburl('choujiang_cheat', array('op' => 'content')), 'success');

                }
            }
        }
        include $this->template('choujiang_cheat');
    }

    // 骗审导航
    public function doWebChoujiang_cheat_nav()
    {
        global $_W, $_GPC;
        $op = $_GPC['op'];
        $ops = array('post', 'content', 'delete', 'index');
        $op = in_array($op, $ops) ? $op : 'content';
        $uniacid = $_W['uniacid'];
        if ($op == 'content') {
            $pindex = max(1, intval($_GPC['page']));
            $psize = 10;//每页显示个数

            $products = pdo_fetchall_cj("select * from " . tablename_cj("choujiang_cheat_nav") . " where uniacid=:uniacid ORDER BY id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array("uniacid" => $uniacid));
            $total = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_cheat_nav') . ' where uniacid=:uniacid ', array(':uniacid' => $uniacid));

            $pager = pagination($total, $pindex, $psize);
            $base = pdo_fetch_cj("SELECT * FROM " . tablename_cj("choujiang_base") . " where uniacid=:uniacid", array(":uniacid" => $uniacid));

        }
        if ($op == 'delete') {
            $id = intval($_GPC['id']);
            $row = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_cheat_nav') . " WHERE id = :id and uniacid = :uniacid ", array(':id' => $id, ':uniacid' => $uniacid));
            if (empty($row)) {
                message('信息不存在或是已经被删除！');
            }
            pdo_delete_cj('choujiang_cheat_nav', array('id' => $id, 'uniacid' => $uniacid));
            message('删除成功!', $this->createWeburl('choujiang_cheat_nav', array('op' => 'content')), 'success');
        }
        //多删
        if (!empty($_GPC['deleteall'])) {
            for ($i = 0; $i < count($_GPC['deleteall']); $i++) {
                pdo_delete_cj('choujiang_cheat_nav', array('id' => $_GPC['deleteall'][$i]));
            }
            message('删除成功!', $this->createWeburl('choujiang_cheat_nav', array('op' => 'content')), 'success');
        }

        //骗审按钮
        if ($_GPC['teshu'] == 1) {
            pdo_update_cj('choujiang_base', array('cheat_status' => $_GPC['cheat_status']), array('uniacid' => $uniacid));
            message('操作成功', $this->createWeburl('choujiang_cheat_nav', array('op' => 'content')), 'success');
        }

        if ($op == 'post') {

            $id = intval($_GPC['id']);
            if (!empty($id)) {
                $item = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_cheat_nav') . " WHERE id = :id", array(':id' => $id));
                if (empty($item)) {
                    message('抱歉，信息不存在或是已经删除！', '', 'error');
                }

            }
            if (checksubmit('submit')) {
                if (empty($_GPC['cj']['title'])) {
                    message('标题不能为空，请输入标题！');
                }
                if (empty($id)) {
                    $_GPC['cj']['uniacid'] = $uniacid;
                    $str = pdo_insert_cj('choujiang_cheat_nav', $_GPC['cj']);

                } else {
                    $str = pdo_update_cj('choujiang_cheat_nav', $_GPC['cj'], array('id' => $id, 'uniacid' => $uniacid));
                }
                if (!empty($str)) {

                    message('信息 添加/修改 成功!', $this->createWeburl('choujiang_cheat_nav', array('op' => 'content')), 'success');

                }
            }
        }
        include $this->template('choujiang_cheat_nav');
    }

    //返回图片url
    public function getImage($img)
    {
        global $_W;
        $item = $this->baseConfig;
        if(strstr($img,'https://') == false) {
            if ($item['type'] == 1) {  //aliyun osss
                if($item['cdn_speed'] && $item['cdn_url']){ //cdn加速开启 cdn域名和图片接口存在
                    $url = $item['cdn_url'].$img;
                }else{ ///oss
                    $url = $item['url'] . $img;
                }

                if ($item['img_api']) {//图片样式
                    $url = $url .'?x-oss-process=style/'.$item['img_api'];
                }

                return $url;
            } else { //本地存储
                $item['url'] = $_W['attachurl'];
                return $item['url'] . $img;
            }
        }else{
            return $img;
        }
    }

    /**
     * 上传到存储目录
     */
    public function alioss_buctkets($key, $secret)
    {
        load()->library('oss');
        $url = $this->baseConfig['location'];
        try {
            $ossClient = new \OSS\OssClient($key, $secret, $url);
            $ossClient->setConnectTimeout(300);
        } catch (\OSS\Core\OssException $e) {
            return error(1, $e->getMessage());
        }
        try {
            $bucketlistinfo = $ossClient->listBuckets();
        } catch (OSS\OSS_Exception $e) {
            return error(1, $e->getMessage());
        }
        $bucketlistinfo = $bucketlistinfo->getBucketList();
        $bucketlist = array();
        foreach ($bucketlistinfo as &$bucket) {
            $bucketlist[$bucket->getName()] = array('name' => $bucket->getName(), 'location' => $bucket->getLocation());
        }
        return $bucketlist;
    }

    /**
     * 图片传输到远程
     * @param $filename
     * @return array
     * @throws \OSS\Core\OssException
     */
    public function file_remote_upload($filename)
    {
        if ($this->baseConfig['type'] == 1) {//aliyun oss
            $bucket = explode('@@', $this->baseConfig['bucket']);
            load()->library('oss');
            load()->model('attachment');
            $bucketIndex = $bucket[0];
            $buckets = $this->alioss_buctkets($this->baseConfig['aliosskey'], $this->baseConfig['aliosssecret']);
            $host_name = $this->baseConfig['internal'] ? '-internal.aliyuncs.com' : '.aliyuncs.com';
            $endpoint = $this->baseConfig['location'];
            $file = ATTACHMENT_ROOT . $filename;

            try {
                $ossClient = new \OSS\OssClient($this->baseConfig['aliosskey'], $this->baseConfig['aliosssecret'], $endpoint);
                $ossClient->setConnectTimeout(300);
                $ossClient->uploadFile($bucketIndex, $filename, $file);

            } catch (\OSS\Core\OssException $e) {
                return error(1, $e->getMessage());
            }
        }
    }


}