<?php
/**
 * 旅游小程序接口定义
 *
 * @author wangbosichuang
 * @url
 */
defined('IN_IA') or exit('Access Denied');

require_once __DIR__."/config.php";
require_once __DIR__ . "/common.func.php";
///微信数据解码接口
require_once __DIR__ . "/class/wxDataDecode/wxBizDataCrypt.php";

pdo_run_cj("set names utf8mb4");

class Choujiang_pageModuleWxapp_v5 extends WeModuleWxapp
{
    protected $attachurl;

    /**
     * @var 基础信息配置
     */
    protected $baseConfig;
    /**
     * @var
     */
    private $secret = 'fghb45jtm89ob25b';

    public function __construct()
    {
        global $_W;

        $uniacid = $_W['uniacid'];
        $this->baseConfig = $item = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_base') . " WHERE uniacid = :uniacid", array(':uniacid' => $uniacid));
        if ($item['type']) {
            $this->attachurl = $item['url'];
        } else {
            $this->attachurl = $_W['attachurl'];
        }

    }

    /*
     * 查询奖品是否可编辑接口
     *
     *
     */
    public function doPageGoodsEditAble() {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];
        $goods_id = $_GPC['id'];
        $res = pdo_get_cj('choujiang_goods',array('id'=>$goods_id,'uniacid'=>$uniacid));
        if($res['canyunum']==0){
            return $this->result(0, "success", 1);
        }else{
            return $this->result(0, "success", 0);
        }
    }

    /**
     * 返回图片路径
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

    //获取所有中奖者收货地址
    public function doPageGetObtainRecordAddress(){
        global $_GPC, $_W;
        $goods_id = $_GPC['goods_id'];
        $uniacid = $_W['uniacid'];
        $list = pdo_getall_cj('choujiang_record',array('goods_id'=>$goods_id,'uniacid'=>$uniacid,'status !='=>0));
        $message = 'success';
        $errno = 0;
        return $this->result($errno, $message, $list);
    }

    //晒单列表
    public function doPageOrderList()
    {
        global $_GPC;
        $pindex = max(1, intval($_POST['page']));
        $psize = 10;//每页显示个数
        $list = pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_share_order') . " GROUP BY goods_id ORDER BY sort desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize);
        foreach ($list as $k => $v) {
            $goods = pdo_get_cj('choujiang_goods', array('id' => $v['goods_id']));

            $list[$k]['goods_num'] = $goods['goods_num'];
            if ($v['cover_img']) {  //晒单有设置封面
                $list[$k]['cover_img'] = $this->getImage($v['cover_img']);
            } else {                //晒单未设置封面，调用奖品图片
                $list[$k]['cover_img'] = $this->getImage($goods['goods_icon']);
            }
            //用户晒单评论审核通过
            $status = pdo_count_cj('choujiang_share_order', array('goods_id' => $v['goods_id'], 'status' => 2));
            if ($status) {
                $list[$k]['status'] = 1;
            }else{
                $list[$k]['status'] = 0;
            }
            //该晒单用户头像列表
            $sd_avatar =[];
            $record = pdo_getall_cj("choujiang_record", array('goods_id' => $v['goods_id'],'status'=>1));
            foreach ($record as $k1=>$v1){
                $sd_avatar[] = $v1['avatar'];
            }
            $list[$k]['avatarList'] = $sd_avatar;
        }
        $total = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_share_order') . ' where openid=:openid ', array(':openid' => $_GPC['openid']));
        $pager = pagination($total, $pindex, $psize);
        $message = 'success';
        $errno = 0;
        return $this->result($errno, $message, $list);
    }

    //晒单详情
    public function doPageShareOrderInfo()
    {
        global $_GPC;
        $pindex = max(1, intval($_POST['page']));
        $psize = 3;//每页显示个数
        $info = array();
        $openid = $_GPC['openId'];
        $goods_id = $_GPC['goods_id'];
        $goods = pdo_get_cj('choujiang_goods', array('id' => $goods_id));
        $status = pdo_get_cj('choujiang_share_order', array('goods_id' => $goods_id,'status'=>2));
        if ($status) {  // 已有用户晒单
            $sql = "SELECT * from" . tablename_cj('choujiang_share_order') . " where goods_id='{$goods_id}' and status=2 ORDER BY id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize;
            $info['list'] = pdo_fetchall_cj($sql);
            foreach ($info['list'] as $k => $v) {
                $img = json_decode($v['img']);
                foreach ($img as $k1 => $v1) {
                    $imgArray[] = $this->getImage($v1);
                }
                $info['list'][$k]['img'] = $imgArray;
            }
            $info['status'] = 1;
        } else {        // 未有用户晒单
            $order = pdo_get_cj('choujiang_share_order', array('openid' => $openid, 'goods_id' => $goods_id));
            if ($order) {  // 中奖者晒单查看
                $info['avatar'] = $order['avatar'];
            } else {       //非中奖者晒单查看 随机选取一个用户晒单头像
                $sql = "SELECT * from" . tablename_cj('choujiang_share_order') . " where goods_id='{$goods_id}' ORDER BY rand() LIMIT 1";
                $info['avatar'] = pdo_fetch_cj($sql)['avatar'];
            }
            $info['status'] = 0;
        }
        $info['goods_sponsorship'] = $goods['goods_sponsorship'];
        $info['goods_name'] = $goods['goods_name'];
        $info['goods_num'] = $goods['goods_num'];
        $info['goods_icon'] = $this->getImage($goods['goods_icon']);
        $message = 'success';
        $errno = 0;
        return $this->result($errno, $message, $info);
    }

    //用户晒单列表
    public function doPageShareOrderList()
    {
        global $_GPC;
        $pindex = max(1, intval($_POST['page']));
        $psize = 5;//每页显示个数
        $list = pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_share_order') . " where openid='{$_GPC['openId']}' and status=0 or status=-1 ORDER BY id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize);
        if($list){
            foreach ($list as $k => $v) {
                $record = pdo_get_cj('choujiang_record', array('goods_id' => $v['goods_id'], 'openid' => $_GPC['openId']));
                $goods = pdo_get_cj('choujiang_goods', array('id' => $v['goods_id']));
                $img = json_decode($v['img'], true);
                if (is_array($img)) {
                    foreach ($img as $k1 => $v1) {
                        $imglist[$k1] = $this->getImage($v1);
                    }
                }
                $list[$k]['img'] = $imglist;
                $list[$k]['goods_num'] = $goods['goods_num'];
                $list[$k]['goods_icon'] = $this->getImgArray($goods['goods_icon'])[0];
                $list[$k]['courier_name'] = $record['express_company'];
                $list[$k]['courier_number'] = $record['express_no'];
            }
        }
        $total = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_share_order') . ' where openid=:openid ', array(':openid' => $_GPC['openid']));
        $pager = pagination($total, $pindex, $psize);
        $message = 'success';
        $errno = 0;
        return $this->result($errno, $message, $list);
    }

    //用户晒单详情
    public function doPageUserShareOrderInfo()
    {
        global $_GPC;
        $id = $_GPC['id'];
        $info = pdo_get_cj('choujiang_share_order', array('id' => $id));
        $goods = pdo_get_cj('choujiang_goods', array('id' => $info['goods_id']));
        $info['goods_name'] = $goods['goods_name'];
        $info['goods_num'] = $goods['goods_num'];
        $info['goods_icon'] = $this->getImgArray($goods['goods_icon'])[0];
        $info['img'] = $this->getImgArray($info['img']);
        $message = 'success';
        $errno = 0;
        return $this->result($errno, $message, $info);
    }

    //提交晒单信息
    public function doPageAddShareOrder()
    {
        global $_GPC;
        $imgArray = explode(",", $_POST['img']);
        $img = array();
        $order = pdo_fetch_cj("select * from " . tablename_cj('choujiang_share_order') . " order by sort desc ")['sort'];
        foreach ($imgArray as $k => $v) {
            $start = strpos($v, ".com/") + 5;
            $length = strpos($v, "?") - $start;
            $img[] = strpos($v, "?") == false ? substr($v, $start) : substr($v, $start, $length);
        }
        if(!empty($img)){
            $img = json_encode($img);
        }
        $data = [
            'content' => $_GPC['content'],
            'img' => $img,
            'update_at' => date('Y-m-d H:i:s', time()),
            'status' => 1,
            'formid' => $_GPC['formid'],
            'sort' => $order+1
        ];
        $res = pdo_update_cj('choujiang_share_order', $data, array('id' => $_GPC['id']));
        if ($res) {
            $message = 'success';
            $errno = 0;
        } else {
            $message = 'error';
            $errno = 1;
        }
        return $this->result($errno, $message);
    }

    //用户填写物流单号、物流公司
    public function doPageAddExpressInfo()
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];
        $goods = pdo_get_cj('choujiang_goods', array('id' => $_GPC['goods_id']));
        $recoerd = [
            'express_company' => $_GPC['express_name'],
            'express_no' => $_GPC['express_no'],
            'ex_create_at'=> date('Y-m-d H:i:s')
        ];
        $order = pdo_fetch_cj("select * from " . tablename_cj('choujiang_share_order') . " order by sort desc ")['sort'];
        $ret = pdo_update_cj("choujiang_record", $recoerd, ['id'=>$_GPC['record_id']]);
        $user = pdo_get_cj('choujiang_user', array('openid' => $_GPC['openid']));
        $lottery_user = pdo_fetch_cj("select * from " . tablename_cj('choujiang_record') . " where id='{$_GPC['record_id']}'");
        $shareOrder = pdo_get_cj('choujiang_share_order', array('openid' => $_GPC['openid'], 'goods_id' => $_GPC['goods_id']));
        $data = [
            'goods_id' => $goods['id'],
            'goods_icon' => $goods['goods_icon'],
            'goods_name' => $goods['goods_name'],
            'openid' => $lottery_user['openid'],
            'nickname' => $lottery_user['nickname'],
            'avatar' => $lottery_user['avatar'],
            'create_at' => date('Y-m-d H:i:s'),
            'update_at' => date('Y-m-d H:i:s'),
            'status' => 0,
            'sort'=> $order+1
        ];
        if (!$shareOrder) {
            $res = pdo_insert_cj('choujiang_share_order', $data);
        }
        if ($ret) {
            $message = 'success';
            $errno = 0;
        } else {
            $message = 'error';
            $errno = 1;
        }
        return $this->result($errno, $message);
    }

    //物流公司
    public function doPageExpress()
    {
        $expressList = pdo_getall_cj("choujiang_express");
        $message = 'success';
        $errno = 0;
        return $this->result($errno, $message, $expressList);
    }

    /**
     *  品牌（我要上首页）
     */
    public function doPageBrand()
    {
        global $_GPC,$_W;
        $openid = $_REQUEST['openid'];

        $data = [
            'openid' => $openid,
            'real_name' => addslashes(trim($_GPC['real_name'])),
            'tel' => (int)$_GPC['tel'],
            'qq' => (int)$_GPC['qq'],
            'brand' => addslashes(trim($_GPC['brand'])),
            'form_id' => addslashes(trim($_GPC['form_id'])),
            'create_at' => date("Y-m-d H:i:s"),
            'update_at' => date("Y-m-d H:i:s"),
        ];

        $result = pdo_insert_cj("choujiang_brand", $data);
        if ($result) {
            return $this->result(0, "申请成功，客服将尽快为你审核");
        } else {
            return $this->result(-1, "申请失败，请联系客服");
        }
    }

    //获取用户信息
    private function httpGet($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_URL, $url);
        $res = curl_exec($curl);
        curl_close($curl);
        return $res;
    }

    public function doPageUserInfo()
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];

        if (!empty($_GPC['c_auth'])) {
            $authTmp = $_GPC['c_auth'];
            $auth = explode("|", $authTmp);
            $openid = $auth[0];
            $userId = $auth[3];
            $myAuth = md5($auth[1] . '|' . $this->secret . $auth[0]);
            if ($myAuth != $auth[2] || empty($userId)) {
                return $this->result(-4, '请重新登录！');
            }
        }

        if ($_GPC['op'] == 'addPhone') {///添加手机号
            $redis = connect_redis();
            $sessionRedisKey = sprintf("cj_user_session_key:%s:%s",$uniacid,$openid);
            $sessionKey = $redis->get($sessionRedisKey);

            $result = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_base') . " where `uniacid`='{$uniacid}'");
            $appid = trim($result['appid']);
            $encryptedData = $_GPC['encryptedData'];
            $iv = $_GPC['iv'];

            $pc = new WXBizDataCrypt($appid, $sessionKey);
            $errCode = $pc->decryptData($encryptedData, $iv, $data );
            $decodeData = json_decode($data, true);

            if ($errCode == 0) {
                $result = pdo_update_cj("choujiang_user", ['tel' => $decodeData['phoneNumber']], ['id' => $userId]);
                return $result ? $this->result(0, 'success'): $this->result(-2, '数据异常，请联系客服');
            } else {
                return $this->result(-3, '授权失败，请联系客服');
            }
        } else {
            $result = pdo_get_cj("choujiang_user", ['openid' => $openid]);
            if ($result['tel'] > 0) {
                return $this->result(0, 'success');
            }

        }

        return $this->result(-1, "error");
    }

    public function doPageGetUid()
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];

        ///已经登录过的，在一定时间内不用再次请求微信接口
        if (!empty($_GPC['c_auth'])) {
            $authTmp = $_GPC['c_auth'];
            $auth = explode("|", $authTmp);
            $userId = $auth[3];

            $myAuth = md5($auth[1] . '|' . $this->secret . $auth[0]);
            if (($myAuth == $auth[2] && time() - $auth[1] < 604800) && $userId > 0) {//一周后过期
                return $this->result(0, 'success', ['openid' => $auth[0], 'new' => 0, 'user_id' => $userId]);
            }

            if (! empty($auth[0]) ) { ///openid不为空
                $res = pdo_fetch_cj('SELECT `id` FROM ' . tablename_cj('choujiang_user') . " where `openid`='{$openid}' and `uniacid`='{$uniacid}'");
                $userId = $res['id'];

                if ($userId > 0) {
                    return $this->result($errno, $message, ['openid' => $auth[0], 'new' => 0, 'user_id' => $userId]);
                }
            }
        }

        $result = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_base') . " where `uniacid`='{$uniacid}'");
        $APPID = trim($result['appid']);
        $SECRET = trim($result['appsecret']);
        $code = trim($_GPC['code']);
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$APPID}&secret={$SECRET}&js_code={$code}&grant_type=authorization_code";

        $data['userinfo'] = json_decode($this->httpGet($url));

        $openid = $data['userinfo']->openid;
        $sessionKey = $data['userinfo']->session_key;
        $redis = connect_redis();
        $sessionRedisKey = sprintf("cj_user_session_key:%s:%s",$uniacid,$openid);
        $redis->set($sessionRedisKey, $sessionKey);

        $item['openid'] = $openid;
        $item['uniacid'] = $uniacid;

        if ($openid) {
            $res = pdo_fetch_cj('SELECT `id` FROM ' . tablename_cj('choujiang_user') . " where `openid`='{$openid}' and `uniacid`='{$uniacid}'");
            $userId = $res['id'];
            if (!$res['id']) {
                $base = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_base') . " where `uniacid`='{$uniacid}'");
                $item['mf_num'] = $base['join_num'];
                $item['smoke_num'] = $base['smoke_num'];
                $item['winning_num'] = $base['winning_num'];
                $item['create_time'] = time();
                $res = pdo_insert_cj('choujiang_user', $item);
                $userId = pdo_insertid_cj();
                $new = 1;

                ///统计
                if ($userId > 1) {
                    global $_W, $_GPC;

                    $this->_statNewUser(); //新增用户数

                    if ($_GPC['share_channel'] == -1) {
                        ///统计用户分享
                        $this->_statUserShare([
                            'user_id' => $userId
                        ]);
                    } else if ($_GPC['share_channel'] >= 1) {
                        //渠道统计
                        $this->_statChannelNew();
                        //渠道访问次数
                        $_GPC['channel'] = $_GPC['share_channel'];
                        $this->_statChannelUser($userId);
                    }
                    ///访问次数
                    $this->_statVisitUser($userId);
                }
            }
        }
        $data['openid'] = $openid;
        $message = 'success';
        $errno = 0;

        return $this->result($errno, $message, ['openid' => $openid, 'new' => $new, 'user_id' => $userId]);
    }

    public function doPageMember()
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];
        $openid = $_REQUEST['openid'];

        $member = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_user') . " where `uniacid`='{$uniacid}' and `openid`='{$openid}'");
        if (!empty($member)) {
            if (! empty($_GPC['nickName']) ) {
                $item['nickname'] = $_GPC['nickName'];
                $edit = 1;
            }
            if (! empty($_GPC['avatarUrl']) ) {
                $item['avatar'] = $_GPC['avatarUrl'];
                $edit = 1;

                ///如果用户头像没有缓存则进行缓存或者判断用户头像是否需要更新
                if( $this->baseConfig['type']  == 1 ) {
                    //$avatarSrc = $this->attachurl."/avatar/".$uniacid."/".$member['id'].".jpg";
                    $avatarSrc = "avatar/".$uniacid."/".$member['id'].".jpg";

                    $bucket = explode('@@', $this->baseConfig['bucket']);
                    load()->library('oss');
                    $bucketIndex = $bucket[0];
                    $endpoint = $this->baseConfig['location'];

                    try {
                        $ossClient = new \OSS\OssClient($this->baseConfig['aliosskey'], $this->baseConfig['aliosssecret'], $endpoint);
                        //$ossClient->uploadFile($bucketIndex, $filename, $file);
                        $avatarExist = $ossClient->doesObjectExist($bucketIndex, $avatarSrc);

                    } catch (\OSS\Core\OssException $e) {
                        return error(1, $e->getMessage());
                    }

                } else {
                    $avatarSrc = IA_ROOT."/attachment/choujiang_page/avatar/".$uniacid."/".$member['id'].".jpg";
                    $avatarExist = file_exists($avatarSrc);
                }
                if( !$avatarExist || $_GPC['avatarUrl'] != $member['avatar'] ){
                    $this->_cacheUserAvatar($member['id'],$_GPC['avatarUrl']);
                }
            }
            if ($edit) {
                $item['uniacid'] = $uniacid;
                $item['send_time'] = time();
                $res = pdo_update_cj('choujiang_user', $item, array('id' => $member['id']));
            }

            $time = time();
            $auth = md5($time . '|' . $this->secret . $openid);
            $cAuth = sprintf('%s|%s|%s|%s', $openid, $time, $auth, $member['id']);
            $item['c_auth'] = $cAuth;
            $item['nickname'] = empty($item['nickname'])? $member['nickname']: $item['nickname'];
            $item['avatar'] = empty($item['avatar'])? $member['avatar']: $item['avatar'];
        }
        $message = 'success';
        $errno = 0;
        return $this->result($errno, $message, $item);
    }

    /*
     * 标记更新用户头像到本地缓存
     *
     */
    public function _cacheUserAvatar($userId,$avatar)
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];
        $redis = connect_redis();
        $avatarKey = sprintf("cj_update_avatar:%s",$uniacid);
        $redis->hSet($avatarKey, $userId, $avatar);
    }

//    public function doPageMemberInfo()
//    {
//        global $_GPC, $_W;
//        $uniacid = $_W['uniacid'];
//        $openid = $_REQUEST['openid'];
//        $member = pdo_fetch_cj('SELECT yu_num,mf_num,openid,avatar,nickname FROM ' . tablename_cj('choujiang_user') . " where `uniacid`='{$uniacid}' and `openid`='{$openid}'");
//        $base = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_base') . " where uniacid=:uniacid", array(":uniacid" => $_W['uniacid']));
//        if ($base['share_num'] == 0) {
//            $member['share_num_status'] = 2;
//        } else {
//            $member['share_num_status'] = 1;
//        }
//        $member['num'] = $member['yu_num'] + $member['mf_num'];
//        return $this->result($errno, $message, $member);
//
//    }

    public function doPageBase()
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];
        $base = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_base') . " where uniacid=:uniacid", array(":uniacid" => $_W['uniacid']));
        return $this->result(0, 'success', $base);

    }

    // 抽奖列表 - 优化
    public function doPageIndexList()
    {
        global $_GPC, $_W;

        $uniacid = $_W['uniacid'];
        $openid = $_REQUEST['openid'];
        $pindex = max(1, intval($_REQUEST['page']));
        $psize = 5;//每页显示个数
        $condition = $_REQUEST['condition'];
        if ($condition == 1) {  //手动开奖
            $conditions = ' and smoke_set = 2';
        } else if ($condition == 2) { //现场开奖
            $conditions = ' and smoke_set = 3';
        } else {
            $conditions = ' and (smoke_set = 0 or smoke_set = 1 or smoke_set = 2)';
        }
        $ret = pdo_fetchall_cj("SELECT goods_name,goods_num,goods_icon,id,goods_sponsorship,is_pintuan,smoke_set,smoke_num,smoke_time,stick_time from" . tablename_cj('choujiang_goods') . "WHERE uniacid = :uniacid and status = 0 and is_del != -1 and audit_status = 1 " . $conditions . " ORDER BY stick_time desc,id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array(':uniacid' => $uniacid));

        $goodsIds = [];
        foreach ($ret as $key => $value) {
            $goods_icon = $this->getImgArray($value['goods_icon'])[0];
            $ret[$key]['goods_icon'] = $this->getImage($goods_icon);
            $goodsIds[] = $value['id'];
        }
        if (!empty($goodsIds)) {
            $join = pdo_getall_cj('choujiang_record', [
                'uniacid' => $uniacid,
                'openid' => $openid,
                'goods_id' => $goodsIds
            ], 'goods_id');
            if (!empty($join)) {
                foreach ($join as $val) {
                    $mine[] = $val['goods_id'];
                }

                foreach ($ret as $key => $value) {
                    if (!in_array($value['id'], $mine)) {
                        $str = -1;
                    } else {
                        $str = 1;
                    }
                    $ret[$key]['join'] = $str;
                }
            }
        }

        $total = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_goods') . ' where uniacid=:uniacid  and is_del != -1 ' . $conditions, array(':uniacid' => $uniacid));
        $pager = pagination($total, $pindex, $psize);
        return $this->result(0, 'success', $ret);
    }

//    // 抽奖列表
//    public function doPageIndexList()
//    {
//        global $_GPC, $_W;
//
//        $uniacid = $_W['uniacid'];
//        $openid = $_REQUEST['openid'];
//        $pindex = max(1, intval($_REQUEST['page']));
//        $psize = 5;//每页显示个数
//        $condition = $_REQUEST['condition'];
//        if ($condition == 1) {  //手动开奖
//            $conditions = ' and smoke_set = 2';
//        } else if ($condition == 2) { //现场开奖
//            $conditions = ' and smoke_set = 3';
//        } else {
//            $conditions = ' and (smoke_set = 0 or smoke_set = 1 or smoke_set = 2)';
//        }
//        $ret = pdo_fetchall_cj("SELECT goods_name,goods_num,goods_icon,id,goods_sponsorship,is_pintuan,smoke_set,smoke_num,smoke_time from" . tablename_cj('choujiang_goods') . "WHERE uniacid = :uniacid and status = 0 and is_del != -1 and audit_status = 1 " . $conditions . " ORDER BY id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array(':uniacid' => $uniacid));
//
//        foreach ($ret as $key => $value) {
//            $ret[$key]['goods_icon'] = $this->getImage($value['goods_icon']);
////            $join = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and openid = :openid", array(':uniacid' => $uniacid, ':id' => $value['id'], ':openid' => $openid));
//            if (empty($join)) {
//                $str = -1;
//            } else {
//                $str = 1;
//            }
//            $ret[$key]['join'] = $str;
//        }
//        $total = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_goods') . ' where uniacid=:uniacid  and is_del != -1 ' . $conditions, array(':uniacid' => $uniacid));
//        $pager = pagination($total, $pindex, $psize);
//        return $this->result(0, 'success', $ret);
//    }

    // 列表详情页
    public function doPageGoodsXq()
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];
        $id = $_REQUEST['id'];
        $openid = $_REQUEST['openid'];
        // $openid = 'oQQf_0KyaKENcRwM1kgeF6W4hH_Y';
        $join = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and openid = :openid", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));
        $ret = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_goods') . "WHERE uniacid = :uniacid and id = :id and is_del != -1", array(':uniacid' => $uniacid, ':id' => $id));

        $item = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_base') . " WHERE uniacid = :uniacid", array(':uniacid' => $uniacid));
        if (!$item['type']) {
            $item['url'] = $_W['attachurl'];
        }


        $ret['goods_icon'] = $this->getImage($ret['goods_icon']);


        if ($ret['smoke_set'] == 0) {
            $ret['open_time'] = strtotime($ret['smoke_time']);
            $time = $ret['smoke_time'];
            $year = substr($time, 0, 4);
            $month = substr($time, 5, 2);
            $day = substr($time, 8, 2);
            $hour = substr($time, 11, 2);
            $min = substr($time, 14, 2);
            if (substr($month, 0, 1) == 0) {
                $month = substr($month, 1, 1);
            }
            if (substr($day, 0, 1) == 0) {
                $day = substr($day, 1, 1);
            }
            if (substr($hour, 0, 1) == 0) {
                $hour = substr($hour, 1, 1);
            }
            $ret['The_time']['year'] = $year;
            $ret['The_time']['month'] = $month;
            $ret['The_time']['day'] = $day;
            $ret['The_time']['hour'] = $hour;
            $ret['The_time']['min'] = $min;
        }
        $user = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_user') . "WHERE uniacid = :uniacid and openid = :openid", array(':uniacid' => $uniacid, ':openid' => $ret['goods_openid']));
        $ret['avatar'] = $user['avatar'];
        $ret['nickname'] = $user['nickname'];
        //var_dump($_REQUEST['ntuan_id']);
        if (!empty($join)) {
            $ret['join_status'] = 1;

            if (isset($_REQUEST['ntuan_id'])) {
                $ret['pintuan_id'] = $_REQUEST['ntuan_id'];
                $join_num = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_record') . ' where uniacid=:uniacid and goods_id = :goods_id and pintuan_id = :pintuan_id', array(':uniacid' => $uniacid, ':goods_id' => $id, ':pintuan_id' => $_REQUEST['ntuan_id']));
                $ret['canjiaNum'] = $join_num;
                if (!empty($join['pintuan_id'])) {
                    if ($join['pintuan_id'] != $_REQUEST['ntuan_id']) {
                        $ret['other_tuan'] = 1;
                        $ret['is_tuan'] = 1;
                    } else {
                        $ret['other_tuan'] = 0;
                        $ret['is_tuan'] = 1;
                    }
                } else {
                    $ret['other_tuan'] = 0;
                    $ret['is_tuan'] = 0;
                }
                //$ret['other_tuan'] = 0;
                //$ret['join_tuan'] = 1;
            } else {
                //$ret['is_tuan'] = 0;
                //$ret['other_tuan'] = 1;

            }
            if ($join['pintuan_id']) {
                $ret['pintuan_id'] = $join['pintuan_id'];
                $join_num = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_record') . ' where uniacid=:uniacid and goods_id = :goods_id and pintuan_id = :pintuan_id', array(':uniacid' => $uniacid, ':goods_id' => $id, ':pintuan_id' => $join['pintuan_id']));
                $ret['canjiaNum'] = $join_num;
                if ($join['pintuan_id'] == $join['id']) {
                    $ret['pintuan_head'] = 1;
                } else {
                    $ret['pintuan_head'] = 0;
                }
            } else {
                $ret['pintuan_id'] = 0;
                $ret['canjiaNum'] = 0;
                $ret['pintuan_head'] = 0;
            }
        } else {
            $ret['join_status'] = 0;
            if (isset($_REQUEST['ntuan_id'])) {
                $join_num = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_record') . ' where uniacid=:uniacid and goods_id = :goods_id and pintuan_id = :pintuan_id', array(':uniacid' => $uniacid, ':goods_id' => $id, ':pintuan_id' => $_REQUEST['ntuan_id']));
                $ret['canjiaNum'] = $join_num;
                if ($join_num >= $ret['pintuan_maxnum']) {
                    $ret['is_full'] = 1;
                } else {
                    $ret['is_full'] = 0;
                }
            }
        }
        $images = unserialize($ret['goods_images']);
        if ($images) {
            foreach ($images as $key => $value) {
                if ($value == '') {
                    unset($images[$key]);
                }
            }

            foreach ($images as $key => $value) {
                if (strstr($value, 'http')) {
                    $images[$key] = $value;
                } else {
                    $images[$key] = $this->getImage($value);
                }
            }
        }

        $ret['goods_images'] = $images;
        if ($ret['goods_status'] == 1) {
            $ret['goods_name'] = '红包 ' . $ret['red_envelope'] . '元/人';
        } else if ($ret['goods_status'] == 2) {
            $ret['card_info'] = unserialize($ret['card_info']);
        }
        return $this->result(0, 'success', $ret);
    }

// 手续费
    public function doPagePoundage()
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];
        $base = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_base') . " where uniacid=:uniacid", array(":uniacid" => $_W['uniacid']));
        $ret['poundage'] = $base['poundage'];
        $ret['xcx_price'] = $base['xcx_price'];
        return $this->result(0, 'success', $ret);
    }


// 参与抽奖的用户列表
    public function doPageGoodsRecord()
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];
        $id = $_REQUEST['id'];
        $ret = pdo_fetchall_cj("SELECT avatar, nickname,pintuan_id,id from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id  ", array(':uniacid' => $uniacid, ':id' => $id));
        if (isset($_REQUEST['ztyq_id'])) {
            if (isset($_REQUEST['openid'])) {
                $pintuan_id = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and openid = :openid and goods_id = :id  ", array(':uniacid' => $uniacid, ':openid' => $_REQUEST['openid'], ':id' => $id));
                if ($pintuan_id) {
                    $ret = pdo_fetchall_cj("SELECT avatar, nickname,pintuan_id,id from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and pintuan_id = :pintuan_id and goods_id = :id ", array(':uniacid' => $uniacid, ':pintuan_id' => $pintuan_id['pintuan_id'], ':id' => $id));
                } else {
                    $ret = pdo_fetchall_cj("SELECT avatar, nickname,pintuan_id,id from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and pintuan_id = :pintuan_id and goods_id = :id ", array(':uniacid' => $uniacid, ':pintuan_id' => $_REQUEST['ztyq_id'], ':id' => $id));
                }
            } else {
                $ret = pdo_fetchall_cj("SELECT avatar, nickname,pintuan_id,id from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and pintuan_id = :pintuan_id and goods_id = :id ", array(':uniacid' => $uniacid, ':pintuan_id' => $_REQUEST['ztyq_id'], ':id' => $id));
            }
        }
        //数组重组
        foreach ($ret as $k => $v) {
            if ($v['id'] == $v['pintuan_id']) {
                $arr = array();
                $v['is_tz'] = 1;
                $arr[] = $v;
                unset($ret[$k]);
            }
        }
        if (!empty($arr)) {
            $ret = array_merge($arr, $ret);
        }
        return $this->result(0, 'success', $ret);
    }

// 全部参与抽奖的用户列表
    public function doPageAllGoodsRecord()
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];
        $id = $_REQUEST['id'];
        $openid = $_REQUEST['openid'];
//        $ret = pdo_fetchall_cj("SELECT avatar, nickname,pintuan_id,id from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and goods_id = :id  ", array(':uniacid' => $uniacid, ':id' => $id));
        $nowUser = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and openid = :openid", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));
        if($nowUser){//当前用户参与抽奖
            if($nowUser['pintuan_id']==0){//当前用户参与抽奖没有组团
                $ret = pdo_fetchall_cj("SELECT avatar, nickname,pintuan_id,id from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and goods_id = :id and openid != :openid ORDER BY codes_amount DESC limit 0,20", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));
                $tmpArr =  array();
                foreach($ret as $k => $v){
                    if($k==0) {
                        $tmpArr[0]['avatar'] = $nowUser['avatar'];
                        $tmpArr[0]['nickname'] = $nowUser['nickname'];
                        $tmpArr[0]['pintuan_id'] = $nowUser['pintuan_id'];
                        $tmpArr[0]['id'] = $nowUser['id'];
                    }
                    $tmpArr[]=$v;
                }
                $ret=$tmpArr;
            }else {//当前用户参与抽奖同时参与组团
                $ret = pdo_fetchall_cj("SELECT avatar, nickname,pintuan_id,id from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and goods_id = :id and pintuan_id != :pintuan_id ORDER BY codes_amount DESC limit 0,20", array(':uniacid' => $uniacid, ':id' => $id, ':pintuan_id' => $nowUser['pintuan_id']));
                $tmpArr =  array();
                foreach($ret as $k => $v){
                    if($k==0) {
                        $tmpArr[0]['avatar'] = $nowUser['avatar'];
                        $tmpArr[0]['nickname'] = $nowUser['nickname'];
                        $tmpArr[0]['pintuan_id'] = $nowUser['pintuan_id'];
                        $tmpArr[0]['id'] = $nowUser['id'];
                    }
                    $tmpArr[]=$v;
                }
                $ret=$tmpArr;
            }
        }else{//当前用户没有参与抽奖
            $ret = pdo_fetchall_cj("SELECT avatar, nickname,pintuan_id,id from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and goods_id = :id ORDER BY codes_amount DESC limit 0,20", array(':uniacid' => $uniacid, ':id' => $id));
        }
        return $this->result(0, 'success', $ret);
    }

// 全部参与抽奖的用户列表
    public function doPageGoodsRecordByPage()
    {
        global $_GPC, $_W;
        $maxNum = 65;
        $uniacid = $_W['uniacid'];
        $id = $_REQUEST['id'];
        $openid = $_REQUEST['openid'];
        $page = $_REQUEST['page'];
        $pageNum = $_REQUEST['num']>$maxNum ? $maxNum : (int)$_REQUEST['num'];
        $start = ($page-1)*$pageNum;
        //if($page!=1){$start = $start+1;}
        $nowUser = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and openid = :openid ", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));

        if($page==1){
            if($nowUser){//当前用户参与抽奖
                if($nowUser['pintuan_id']==0){//当前用户参与抽奖没有组团
                    $ret = pdo_fetchall_cj("SELECT avatar, nickname,pintuan_id,codes_amount,id,codes from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and goods_id = :id and openid != :openid ORDER BY codes_amount DESC limit {$start},{$pageNum}", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));
                    $tmpArr =  array();
                    foreach($ret as $k => $v){
                        if($k==0) {
                            $tmpArr[0]['avatar'] = $nowUser['avatar'];
                            $tmpArr[0]['nickname'] = $nowUser['nickname'];
                            $tmpArr[0]['pintuan_id'] = $nowUser['pintuan_id'];
                            $tmpArr[0]['id'] = $nowUser['id'];
                            $tmpArr[0]['codes_amount'] = $nowUser['codes_amount'];
                            $tmpArr[0]['codes'] = $nowUser['codes'];
                        }
                        $tmpArr[]=$v;
                    }
                    $ret=$tmpArr;
                }else {//当前用户参与抽奖同时参与组团
//                    $ret = pdo_fetchall_cj("SELECT avatar, nickname,pintuan_id,id from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and goods_id = :id and pintuan_id != :pintuan_id ORDER BY id DESC limit {$start},{$pageNum}", array(':uniacid' => $uniacid, ':id' => $id, ':pintuan_id' => $nowUser['pintuan_id']));
//                    $tmpArr =  array();
//                    foreach($ret as $k => $v){
//                        if($k==0) {
//                            $tmpArr[0]['avatar'] = $nowUser['avatar'];
//                            $tmpArr[0]['nickname'] = $nowUser['nickname'];
//                            $tmpArr[0]['pintuan_id'] = $nowUser['pintuan_id'];
//                            $tmpArr[0]['id'] = $nowUser['id'];
//                        }
//                        $tmpArr[]=$v;
//                    }
//                    $ret=$tmpArr;
                }
            }else{//当前用户没有参与抽奖
                $ret = pdo_fetchall_cj("SELECT avatar, nickname,pintuan_id,codes_amount,id,codes from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and goods_id = :id ORDER BY codes_amount DESC limit {$start},{$pageNum}", array(':uniacid' => $uniacid, ':id' => $id));
            }
        }else{//当前用户没有参与抽奖
            $ret = pdo_fetchall_cj("SELECT avatar, nickname,pintuan_id,codes_amount,id,codes from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and goods_id = :id and openid != :openid ORDER BY codes_amount DESC limit {$start},{$pageNum}", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));
        }
//        $ret = pdo_fetchall_cj("SELECT avatar, nickname,pintuan_id,id from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and goods_id = :id  ", array(':uniacid' => $uniacid, ':id' => $id));


        $goods = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_goods') . "WHERE uniacid = :uniacid and id = :id ORDER BY id DESC", array(':uniacid' => $uniacid, ':id' => $id));

        $codeAmount = $goods['max_cj_code'] - 10000000 + 1;

        ///获取code用户的头像
        if (! empty($ret) ) {
            foreach ($ret as $key => $val) {
                $ret[$key]['avatar'] = $this->getImage($val['avatar']);
                $codes = json_decode($val['codes'], true);
                if (! empty($codes)) {
                    $openIds = [];
                    foreach ($codes as $k => $v) {
                        if (! empty($v['openid']) ) {
                            $openIds[] =  $v['openid'];
                        }
                    }
                    if (! empty($openIds)) {
                        $userInfo = pdo_getall('choujiang_user',['openid in' => $openIds], ['openid', 'avatar'],'openid');
                    }

                    foreach ($codes as $k => $v) {
                        $avatar = $userInfo[$v['openid']]['avatar'];
                        $codes[$k]['avatar'] = $this->getImage($avatar);
                        unset($codes[$k]['openid']);
                    }
                }

                $ret[$key]['codes'] = json_encode($codes);
            }
        }
        $res["avatar"] = $ret;
        $res['countNum'] = $goods['canyunum'];
        $res['codeAmount'] = $codeAmount;

        return $this->result(0, 'success', $res);
    }

    // 中奖者用户地址个数
    public function doPageObtainRecordAddress()
    {
        global $_GPC, $_W;
        $id = $_REQUEST['id'];
        $uniacid = $_W['uniacid'];
        $ret = count(pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and status = 1 and user_name != ''", array(':uniacid' => $uniacid, ':id' => $id)));
        return $this->result(0, 'success', $ret);

    }

    // 中奖者用户地址信息
    public function doPageObtainRecordAddressIn()
    {
        global $_GPC, $_W;
        $id = $_REQUEST['id'];
        $uniacid = $_W['uniacid'];
        $ret = pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and status = 1", array(':uniacid' => $uniacid, ':id' => $id));
        return $this->result(0, 'success', $ret);

    }

    // 地址最迟填写时间
    public function doPageAddress_out_time()
    {
        global $_GPC, $_W;
        $record_id = $_REQUEST['id'];
        $uniacid = $_W['uniacid'];
        $record = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and id = :id", array(':uniacid' => $uniacid, ':id' => $record_id));

        $sql = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_goods') . "WHERE uniacid = :uniacid and id = :id", array(':uniacid' => $uniacid, ':id' => $record['goods_id']));
        $time = $sql['send_time'];
        $out_time = strtotime("+1days", $time);
        $ret = date('Y-m-d H:i', $out_time);
        return $this->result(0, 'success', $ret);
    }

    // 前台添加图片到服务器
    public function doPageImgUrl()
    {
        global $_W;
        $str['uniacid'] = $_W['uniacid'];
        $str['url'] = $_W['siteroot'];
        return $this->result(0, 'success', $str);
    }

    public function doPageUpload()
    {
        global $_W, $_GPC;

        $uniacid = $_W['uniacid'];
        $item = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_base') . " WHERE uniacid = :uniacid", array(':uniacid' => $uniacid));


//        if($_W['setting']['remote']['type']==3)  //七牛云开启
//        {
//            $qiniu = $_W['setting']['remote']['qiniu'];
//             require_once(IA_ROOT . '/framework/library/qiniu/autoload.php');
//             $accessKey=$qiniu['accesskey'];
//             $secretKey=$qiniu['secretkey'];
//             $bucket=$qiniu['bucket'];
//             //转码时使用的队列名称
//             //$pipeline = $qiniu['qn_queuename'];
//             //要进行转码的转码操作
//             $fops = "avthumb/mp4/ab/64k/ar/44100/acodec/libfaac";
//             $auth = new Qiniu\Auth($accessKey, $secretKey);
//
//            $filekey=$_FILES['upfile)']['name'];         //上传文件名
//            $filePath=$_FILES['upfile']['tmp_name'];    //上传文件的路径
//
//             //可以对转码后的文件进行使用saveas参数自定义命名，当然也可以不指定文件会默认命名并保存在当间
//             $savekey =  Qiniu\base64_urlSafeEncode($bucket.':'.$filekey.'_1');
//             $fops = $fops.'|saveas/'.$savekey;
//             $policy = array(
//                     'persistentOps' => $fops,
//                    // 'persistentPipeline' => $pipeline
//             );
//             $uptoken = $auth->uploadToken($bucket, null, 3600, $policy);    //上传凭证
//             //上传文件的本地路径
//             $uploadMgr = new Qiniu\Storage\UploadManager();
//             $ss = $uploadMgr->putFile($uptoken, $filekey, $filePath);
//             load()->func("logging");
//             $error=logging_run("qiniu:error".$err."成个");
//             if ($err !== null) {
//                 load()->func("logging");
//                 logging_run("qiniu:error");
//                 return false;
//             }
//             //$ffff 为七牛云路径
//            $fname=$qiniu['url'].'/'.$ss[0]['key'];
//            echo $fname;
//        }
        if ($item['type'] == 1)   //阿里云oss 开启
        {
            //将本地图片先上传到服务器
            load()->func('file');
            $file = $_FILES['upfile'];
            $filename = $file['tmp_name'];
            $destination_folder = '../attachment/images/' . $_W['uniacid'] . '/' . date('Y/m/') . '/';  //图片文件夹路径
            //创建存放图片的文件夹
            if (!is_dir($destination_folder)) {
                $res = mkdir($destination_folder, 0777, true);
            }
            if (!is_uploaded_file($_FILES['upfile']['tmp_name'])) {
                echo '图片不存在!';
                die;
            }

            $pinfo = pathinfo($file['name']);
            $ftype = $pinfo['extension'];
            $destination = $destination_folder . str_shuffle(time() . rand(111111, 999999)) . '.' . $ftype;
            if (file_exists($destination) && $overwrite != true) {
                echo '同名文件已经存在了';
                die;
            }
            if (!move_uploaded_file($filename, $destination)) {
                echo '移动文件出错';
                die;
            }
            $pinfo = pathinfo($destination);
            $filename = 'images/' . $_W['uniacid'] . '/' . date('Y/m/') . $pinfo['basename'];

            //将服务器上的图片转移到阿里云oss

            $remote = $item;
            $bucket = explode("@@", $remote['bucket']);
            require_once(IA_ROOT . '/framework/library/alioss/autoload.php');
            load()->model('attachment');
            $endpoint = $remote['location'];

            try {
                $ossClient = new \OSS\OssClient($remote['aliosskey'], $remote['aliosssecret'], $endpoint);
                $ossClient->uploadFile($bucket[0], $filename, ATTACHMENT_ROOT . $filename);
            } catch (\OSS\Core\OssException $e) {
                //echo  'error--->'.$e->getMessage();
                return error(1, $e->getMessage());

            }
            if ($auto_delete_local) {
                unlink($filename);
            }

            //删除服务器上的上传文件
            unlink(ATTACHMENT_ROOT . $filename);
            $fname = $remote['url'] . '/' . $filename;
            echo $fname;

        } else if ($item['type'] == 0)    //远程存储关闭
        {
            $uptypes = array('image/jpg', 'image/jpeg', 'image/png', 'image/pjpeg', 'image/gif', 'image/bmp', 'image/x-png');
            $max_file_size = 2000000;
            $destination_folder = '../attachment/choujiang_page/';  //图片文件夹路径
            //创建存放图片的文件夹
            if (!is_dir($destination_folder)) {
                $res = mkdir($destination_folder, 0777, true);
            }
            if (!is_uploaded_file($_FILES['upfile']['tmp_name'])) {
                echo '图片不存在!';
                die;
            }
            $file = $_FILES['upfile'];
            if ($max_file_size < $file['size']) {
                echo '文件太大!';
                die;
            }
            if (!in_array($file['type'], $uptypes)) {
                echo '文件类型不符!' . $file['type'];
                die;
            }
            $filename = $file['tmp_name'];
            $pinfo = pathinfo($file['name']);
            $ftype = $pinfo['extension'];
            $destination = $destination_folder . str_shuffle(time() . rand(111111, 999999)) . '.' . $ftype;
            if (file_exists($destination) && $overwrite != true) {
                echo '同名文件已经存在了';
                die;
            }
            if (!move_uploaded_file($filename, $destination)) {
                echo '移动文件出错';
                die;
            }
            $pinfo = pathinfo($destination);
            $fname = $_W['attachurl'] . 'choujiang_page/' . $pinfo['basename'];
            echo $fname;
        }
    }

    // 前台添加奖品
    public function doPageGoodsInto()
    {
        global $_W, $_GPC;
//        var_dump($_REQUEST);
        $current = $_REQUEST['current'];
//        var_dump($_REQUEST);
//        exit;
        $pay_id = $_REQUEST['pay_id'];
        if ($current == 0) {  //实物
            $data['goods_status'] = 0;
        } else if ($current == 1) {  //红包
            $data['red_envelope'] = $_REQUEST['hbname'];  //红包金额
            $data['goods_status'] = 1;
        } else if ($current == 2) {    //电子卡
            $dianzika = $_REQUEST['dainzika'];
            $picPath = str_replace('"', "", str_replace("}]", "", str_replace("[{", "", $dianzika)));
            $newarr = array();
            $newarrs = array();
            $arr = explode("},{", $picPath);
            foreach ($arr as $key => $value) {
                $arr1 = explode(",", $value);
                foreach ($arr1 as $k => $v) {
                    $arr2 = explode(":", $v);
                    $ke = $arr2[0];
                    $va = $arr2[1];
                    $newarr1[$ke] = $va;
                }
                array_push($newarr, $newarr1);
            }
            foreach ($newarr as $key => $value) {
                $k = $value['keys'];
                $v = $value['vals'];
                $newarrs[$k] = $v;
            }
            $data['card_info'] = serialize($newarrs);
            $_REQUEST['jpnum1'] = count($newarrs);
            $data['goods_status'] = 2;
        }
        $id = $_REQUEST['id'];
        $uniacid = $_W['uniacid'];
        $status = $_REQUEST['index'];
        $data['mouth_command'] = $_REQUEST['jpkouling'];   //口令

        $price = $_REQUEST['fufeije']; //付费参与金额
        if ($price > 0) {
            $data['price'] = $_REQUEST['fufeije']; //付费参与金额
        } else {
            $data['price'] = 0; //付费参与金额
        }
        $data['join_conditions'] = $_REQUEST['join_conditions'];
        $data['content'] = $_REQUEST['jpjjval'];  //奖品简介
        $data['goods_sponsorship'] = $_REQUEST['zanzhusval'];  //赞助商
        $data['sponsorship_text'] = $_REQUEST['zanzhusjjval']; //赞助商标题
        $data['sponsorship_appid'] = $_REQUEST['tiaozhuan'];   //小程序跳转input
        $data['sponsorship_content'] = $_REQUEST['zanzhusjsval'];  //赞助商介绍
        $data['sponsorship_url'] = $_REQUEST['sponsorship_url'];  //赞助商小程序跳转链接
        $picPath = $_REQUEST['picPath'];
        if ($picPath != '') {
            $picPath = str_replace('"', "", str_replace("]", "", str_replace("[", "", $picPath)));
            $arr = explode(",", $picPath);
            $data['goods_images'] = serialize($arr);
        } else {
            $data['goods_images'] = '';
        }

        if (isset($_REQUEST['types']) && $_REQUEST['types'] == "more") {
            $data['is_zq'] = 1;
            $data['draw_message'] = $_REQUEST['message'];
        } else {
            $data['is_zq'] = 0;
            $data['draw_message'] = "";
        }

//        $item = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_base') . " WHERE uniacid = :uniacid", array(':uniacid' => $uniacid));
//        if (!$item['type']) {
//            $item['url'] = $_W['attachurl'];
//        }

//        $data['goods_icon'] = str_replace($this->attachurl, '', $_REQUEST['icon']);
        $data['goods_icon'] = $this->getImgPath($_REQUEST['icon']);
        $data['goods_name'] = $_REQUEST['jpname1'];
        if (isset($_REQUEST['types']) && $_REQUEST['types'] == "more") {
            if ($_REQUEST['jpnum1'] > 100000) {
                return $this->result(1, '奖品数量超过上限', "奖品数量超过上限");
            }
        } else {
            if ($_REQUEST['jpnum1'] > 100) {
                return $this->result(1, '奖品数量超过上限', "奖品数量超过上限");
            }
        }
        $data['goods_num'] = $_REQUEST['jpnum1'];
        if ($status == 0) {
            $year = $_REQUEST['year'];
            $month = $_REQUEST['month'];
            $day = $_REQUEST['day'];
            $hour = $_REQUEST['hour'];
            $min = $_REQUEST['min'];
            if (strlen($month) == 1) {
                $month = '0' . $month;
            }
            if (strlen($day) == 1) {
                $day = '0' . $day;
            }
            if (strlen($hour) == 1) {
                $hour = '0' . $hour;
            }
            if (strlen($min) == 1) {
                $min = '0' . $min;
            }
            $data['smoke_time'] = $year . '-' . $month . '-' . $day . ' ' . $hour . ':' . $min;
//            if (isset($_REQUEST['types']) && $_REQUEST['types'] == "more") {
//                if ($_REQUEST['pintuan'] == "true") {
//                    $data['is_pintuan'] = 1;
//                    $data['pintuan_maxnum'] = $_REQUEST['pintuannum'];
//                } else {
//                    $data['is_pintuan'] = 0;
//                    $data['pintuan_maxnum'] = 0;
//                }
//            } else {
//                $data['is_pintuan'] = 0;
//                $data['pintuan_maxnum'] = 0;
//            }

            $data['smoke_time'] = $year . '-' . $month . '-' . $day . ' ' . $hour . ':' . $min;
        } else if ($status == 1) {
            if (isset($_REQUEST['types']) && $_REQUEST['types'] == "more") {
                if ($_REQUEST['kjPeonum'] > 100000) {
                    return $this->result(1, '超过上限人数', "超过上限人数");
                }
            } else {
                if ($_REQUEST['kjPeonum'] > 1024) {
                    return $this->result(1, '超过上限人数', "超过上限人数");
                }
            }

            $data['smoke_num'] = $_REQUEST['kjPeonum'];
        }
        $data['goods_openid'] = $_REQUEST['openid'];
        $data['smoke_set'] = $status;
        foreach ($data as $key => $value) {
            if ($value == undefined) {
                $data[$key] = '';
            }
        }

        if (empty($id)) {
            $data['uniacid'] = $uniacid;
            $sql = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_user') . " WHERE openid = :openid and uniacid = :uniacid", array(':uniacid' => $uniacid, ':openid' => $_REQUEST['openid']));
            if (isset($_REQUEST['types']) && $_REQUEST['types'] == "more") {
                if ($sql['extensions_num'] == 0) {
                    $strs = -1;
                } else {
                    $pata['extensions_num'] = 0;
                    $strs = pdo_update_cj('choujiang_user', $pata, array('id' => $sql['id'], 'uniacid' => $uniacid));
                }
            } else {
                if ($sql['mf_num'] <= 0 && $sql['yu_num'] <= 0) {

                    $strs = -1;

                } else if ($sql['mf_num'] > 0) {

                    $pata['mf_num'] = $sql['mf_num'] - 1;
                    $strs = pdo_update_cj('choujiang_user', $pata, array('id' => $sql['id'], 'uniacid' => $uniacid));

                } else if ($sql['yu_num'] > 0) {
                    $pata['yu_num'] = $sql['yu_num'] - 1;
                    $strs = pdo_update_cj('choujiang_user', $pata, array('id' => $sql['id'], 'uniacid' => $uniacid));

                }
            }
            if ($strs != -1) {
//                var_dump($data);
                $data['create_time'] = date('Y-m-d H:i', time());
                $data['formid'] = $_REQUEST['formid'];
                $str = pdo_insert_cj('choujiang_goods', $data);
            }
            if (!empty($str)) {
                $uid = pdo_insertid_cj();
                $status = 1;
                $this->doWebInvitation($uid);
                //$this->doWebGroupsInvitation($uid);
            } else {
                $status = -1;
            }
        } else {
            $str = pdo_update_cj('choujiang_goods', $data, array('id' => $id, 'uniacid' => $uniacid));
            $uid = $id;
            $status = 2;
        }

        $ret['status'] = $status;
        $ret['uid'] = $uid;
        $ret['data'] = $data;
        if (!empty($pay_id)) {
            pdo_update_cj('choujiang_pay_record', array('goods_id' => $uid), array('id' => $pay_id, 'uniacid' => $uniacid));
        }
        return $this->result(0, 'success', $ret);
    }


    // 个人中心
    // 待开奖
    public function doPageGoodsStart()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $openid = $_REQUEST['openid'];
        $status = $_REQUEST['status'];
        $end = $_REQUEST['end'] * 1;
        $churl = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_base') . " WHERE uniacid = :uniacid", array(':uniacid' => $uniacid));
        if (!$churl['type']) {
            $churl['url'] = $_W['attachurl'];
        }
        if ($status == 1) {
            $ret = pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_goods') . "WHERE uniacid = :uniacid and goods_openid = :openid and status = 0  and is_del = 1 ORDER BY id desc limit 0,{$end}", array(':uniacid' => $uniacid, ':openid' => $openid));
            foreach ($ret as $key => $value) {
                $ret[$key]['goods_icons'] = $this->getImgArray($value['goods_icon'])[0];
                $ret[$key]['goods_id'] = $value['id'];
            }
        } else if ($status == 2) {
            $ret = pdo_fetchall_cj("SELECT *, " . tablename_cj('choujiang_record') . ".id as _id from" . tablename_cj('choujiang_record') . "left join" . tablename_cj('choujiang_goods') . "on" . tablename_cj('choujiang_record') . ".goods_id=" . tablename_cj('choujiang_goods') . ".id WHERE " . tablename_cj('choujiang_record') . ".uniacid = :uniacid and " . tablename_cj('choujiang_record') . ".del=0  and " . tablename_cj('choujiang_record') . ".openid = :openid and " . tablename_cj('choujiang_goods') . ".status=0 ORDER BY " . tablename_cj('choujiang_record') . ".id desc limit 0,{$end}", array(':uniacid' => $uniacid, ':openid' => $openid));

            foreach ($ret as $k => $v) {
                unset($ret[$k]['id']);
                $ret[$k]['id'] = $v['_id'];
                $ret[$k]['goods_icon'] = $this->getImgArray($v['goods_icon'])[0];
                $ret[$k]['goods_icons'] =$this->getImgArray($v['goods_icon'])[0];
                $ret[$k]['create_time'] = date('Y-m-d H:i', $v['create_time']);
            }
        }
        return $this->result(0, 'success', $ret);
    }

    // 已开奖
    public function doPageGoodsStart1()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $status = $_REQUEST['status'];
        $openid = $_REQUEST['openid'];
        $end = $_REQUEST['end'] * 1;
        $time = strtotime("-1 day");
        if ($status == 1) {
            $ret = pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_goods') . "WHERE uniacid = :uniacid and goods_openid = :openid and status !=0 and is_del = 1  ORDER BY send_time desc limit 0,{$end}", array(':uniacid' => $uniacid, ':openid' => $openid));
            foreach ($ret as $key => $value) {
                $ret[$key]['goods_id'] = $value['id'];
                $ret[$key]['time'] = date('m-d H:i', $value['send_time']);
                $ret[$key]['smoke_set'] = $value['smoke_set'];
                $ret[$key]['goods_icon'] = $this->getImgArray($value['goods_icon'])[0];
                $ret[$key]['total'] = pdo_fetch_cj("select count(*) as count from" . tablename_cj('choujiang_record') ." where goods_id={$value['id']} and status!=0 and uniacid={$uniacid}")['count'];
                $ret[$key]['write_num'] = pdo_fetch_cj("select count(*) as count from" . tablename_cj('choujiang_record') ." where goods_id={$value['id']} and status=1 and user_address!='' and uniacid={$uniacid}")['count'];
            }
        } else if ($status == 2) {
            $ret = pdo_fetchall_cj("SELECT *," . tablename_cj('choujiang_record') . ".id as _id ," . tablename_cj('choujiang_record') . ".status as _status from" . tablename_cj('choujiang_record') . "left join" . tablename_cj('choujiang_goods') . "on" . tablename_cj('choujiang_record') . ".goods_id=" . tablename_cj('choujiang_goods') . ".id WHERE " . tablename_cj('choujiang_record') . ".uniacid = :uniacid and " . tablename_cj('choujiang_record') . ".del=0  and " . tablename_cj('choujiang_record') . ".openid = :openid and " . tablename_cj('choujiang_goods') . ".status!=0 ORDER BY " . tablename_cj('choujiang_record') . ".id desc limit 0,{$end}", array(':uniacid' => $uniacid, ':openid' => $openid));
            foreach ($ret as $k => $v) {
                unset($ret[$k]['id']);
                $ret[$k]['id'] = $v['_id'];
                $ret[$k]['goods_icon'] = $this->getImgArray($v['goods_icon'])[0];
                $ret[$k]['goods_icons'] = $this->getImgArray($v['goods_icon'])[0];
                $ret[$k]['time'] = date('Y-m-d H:i', $v['finish_time']);
                if ($time >= $v['finish_time'] && $v['user_name'] == '' && $v['status'] == 1) {
                    pdo_update_cj('choujiang_record', array('status' => -1), array('id' => $v['id']));
                }
                if ($v['smoke_set'] == 3) {
                    $list = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_exchange') . " where uniacid=:uniacid and goods_id = :goods_id and openid = :openid", array(":goods_id" => $v['goods_id'], ":uniacid" => $uniacid, ":openid" => $openid));
                    if ($list['status'] == 1) {
                        $ret[$k]['hex_status'] = -1;
                    } else {
                        $ret[$k]['hex_status'] = 1;
                    }
                }
            }
        } else {
            $ret = pdo_fetchall_cj("SELECT R.*, S.id AS share_order_id, S.status AS lottery_status from " . tablename_cj('choujiang_record') . " AS R LEFT JOIN " . tablename_cj('choujiang_share_order') . " AS S ON S.goods_id=R.goods_id AND S.openid=R.openid WHERE R.uniacid = :uniacid and R.openid = :openid and R.status != 0 and R.del=0 ORDER BY R.id desc limit 0,{$end}", array(':uniacid' => $uniacid, ':openid' => $openid));

            foreach ($ret as $key => $value) {
                $goods_id = $value['goods_id'];
                $good = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_goods') . "WHERE uniacid = :uniacid and id = :id and status = 1", array(':uniacid' => $uniacid, ':id' => $goods_id));
                if (empty($good)) {
                    $ret[$key]['goods_id'] = 0;
                } else {
                    $ret[$key]['goods_id'] = $good['id'];
                }
                $ret[$key]['goods_num'] = $good['goods_num'];
                if ($time >= $value['finish_time'] && $value['user_name'] == '' && $value['status'] == 1) {
                    pdo_update_cj('choujiang_record', array('status' => -1), array('id' => $value['id']));
                }
                $ret[$key]['time'] = date('m-d H:i', $value['finish_time']);
                $ret[$key]['smoke_set'] = $good['smoke_set'];
                $ret[$key]['is_del'] = $good['is_del'];
                if ($good['smoke_set'] == 3) {
                    $list = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_exchange') . " where uniacid=:uniacid and goods_id = :goods_id and openid = :openid", array(":goods_id" => $goods_id, ":uniacid" => $uniacid, ":openid" => $openid));
                    if ($list['status'] == 1) {
                        $ret[$key]['hex_status'] = -1;
                    } else {
                        $ret[$key]['hex_status'] = 1;
                    }
                }
                $ret[$key]['_status'] = $value['status'];
                $img = json_decode($good['goods_icon']);
                $ret[$key]['goods_icon'] = $this->getImgArray($good['goods_icon'])[0];
            }
        }
        return $this->result(0, 'success', $ret);
    }

    // 发起抽奖数量
    public function doPageGoodsStartNum()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $openid = $_REQUEST['openid'];
        $ret['start'] = count(pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_goods') . "WHERE uniacid = :uniacid and goods_openid = :openid and is_del = 1", array(':uniacid' => $uniacid, ':openid' => $openid)));
        $ret['join'] = count(pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and openid = :openid and del=0", array(':uniacid' => $uniacid, ':openid' => $openid)));
        $ret['obtain'] = count(pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and openid = :openid and status != 0 and del=0", array(':uniacid' => $uniacid, ':openid' => $openid)));
        return $this->result(0, 'success', $ret);
    }


    // 参与抽奖
    public function doPageParticipate()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $openid = $_REQUEST['openid'];
        $id = $_REQUEST['id'];
        $payfu = $_REQUEST['payfu'];
        $sy_num = 0;
        ///抽奖码发放锁
        $redis = connect_redis();
        $lockKey = sprintf('cj_lottery_code:%s', $id);
        if ($redis->get($lockKey)) {//锁未释放
            $str['status'] = -6;
            return $this->result(0, 'success', $str);
        }

        $base = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_base') . "WHERE uniacid = :uniacid", array(':uniacid' => $uniacid));
        $user = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_user') . "WHERE uniacid = :uniacid and openid = :openid", array(':uniacid' => $uniacid, ':openid' => $openid));
        if(empty($user['openid'])||empty($user['nickname'])){
            $str['status'] = -5;
            return $this->result(0, 'success', $str);
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
            $join = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and openid = :openid", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));
            if (empty($join)) {
                $ret = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_goods') . "WHERE uniacid = :uniacid and id = :id", array(':uniacid' => $uniacid, ':id' => $id));

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
                $record = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_record') . ' where uniacid=:uniacid and goods_id = :goods_id and openid = :openid', array(':uniacid' => $uniacid, ':goods_id' => $id, ':openid' => $openid));
                $data['formid'] = $_REQUEST['formid'];
                $data['create_time'] = time();
                $data['avatar'] = $user['avatar'];
                if($ret['smoke_set'] == 0){///按时间开奖
                    if(strtotime($ret['smoke_time'])<time()){///已开奖
                        if($ret['canyunum']>0){ ///有人参与
                            $str['status'] = -3;
                        }else{ ///无人参与
                            $str['status'] = -4;
                        }

                    }else{///未开奖
                        $str['status'] = $this->_doPageAddRecord($data, $record, $openid, $ret);
                    }
                }else if ($ret['smoke_set'] == 1 ) {///按人数开奖
                    if ($ret['smoke_num'] > $ret['canyunum'] || strtotime($ret['create_time']) < time()-86400*3) {///未开奖 - 人数未满或奖品日期未到3天
                        $str['status'] = $this->_doPageAddRecord($data, $record, $openid, $ret);
                    } else {///已开奖
                        if($ret['canyunum']>0){
                            $str['status'] = -3;
                        }else {
                            $str['status'] = -4;
                        }
                    }
                } else {///手动开奖
                    if(strtotime($ret['create_time']) > time()-86400*3){///未开奖 - 3天未手动开奖，则自动开奖
                        //redis 缓存手动开奖锁
                        $goodsOpen = cache_load('goodsopen'.$id);
                        if($goodsOpen){
                            //手动开奖中
                            $str['status'] = -3;
                        }else {
                            $str['status'] = $this->_doPageAddRecord($data, $record, $openid, $ret);
                        }
                    }else{
                        if($ret['canyunum']>0){
                            $str['status'] = -3;
                        }else{
                            $str['status'] = -4;
                        }
                    }

                }

                if ($str['status'] == 1 && $base['smoke_num'] > 0 && $payfu != 1) {
                    pdo_update_cj('choujiang_user', $pata, array('id' => $user['id']));
                }
                $str['avatar'] = $user['avatar'];
            } else {
                $str['status'] = -1;
            }
        } else {
            $str['status'] = -2;
        }


        return $this->result(0, 'success', $str);
    }

    //添加参与抽奖记录
    private function _doPageAddRecord($data, $record, $openid, $ret)
    {
        global $_GPC;
        if ($record < 1) {
            if ($openid) {
                ///抽奖码发放锁
                $redis = connect_redis();
                $lockKey = sprintf('cj_lottery_code:%s', $ret['id']);
                $redis->set($lockKey, 1);

                $code = $ret['max_cj_code'] == 0 ? 10000000: $ret['max_cj_code'] + 1;

                $data['codes'] = json_encode([
                    $code => [
                        'type' => 1,
                        'openid' => $openid
                    ]
                ]);
                $data['codes_amount'] = 1;
                $status = pdo_insert_cj('choujiang_record', $data);
                $canyunum = $ret['canyunum'] + 1;
                if($status) {
                    $addNum = [
                        'canyunum' => $canyunum,
                        'max_cj_code' => $code
                    ];
                    pdo_update_cj('choujiang_goods', $addNum, array('id' => $ret['id']));

                    ///通过别人分享进来的 - 分享人增加抽奖码
                    if ($_GPC['cj_share_c'] == -1 && $_GPC['cj_share_u'] > 0 && $_GPC['cj_share_id'] == $ret['id']) {
                        $shareUserInfo = pdo_get_cj('choujiang_user', [
                            'id' => $_GPC['cj_share_u']
                        ]);

                        //奖品发起者只能有一个抽奖码
                        if ($ret['goods_openid'] != $shareUserInfo['openid']) {
                            $shareUserRecord = pdo_get_cj('choujiang_record', [
                                'goods_id' => $ret['id'],
                                'openid' => $shareUserInfo['openid']
                            ]);

                            $shareUserCodes = json_decode($shareUserRecord['codes'], true);
                            $nextCode = $code + 1;
                            $shareUserCodes[$nextCode] = [
                                'type' => 2,
                                'openid' => $openid
                            ];
                            $result = pdo_update_cj('choujiang_record', [
                                'codes' => json_encode($shareUserCodes),
                                'codes_amount' => $shareUserRecord['codes_amount'] + 1
                            ], ['id' => $shareUserRecord['id']]);
                            if($result) {
                                $addNum = [
                                    'max_cj_code' => $nextCode,
                                ];
                                pdo_update_cj('choujiang_goods', $addNum, array('id' => $ret['id']));
                            }
                        }
                    }
                }

                $redis->del($lockKey);
                return $status;
            }
        }

        return false;
    }

    // 参与组团
    public function doPageParticipateGroups()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $openid = $_REQUEST['openid'];
        $id = $_REQUEST['id'];
        $payfu = $_REQUEST['payfu'];
        $sy_num = 0;
        $base = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_base') . "WHERE uniacid = :uniacid", array(':uniacid' => $uniacid));
        $user = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_user') . "WHERE uniacid = :uniacid and openid = :openid", array(':uniacid' => $uniacid, ':openid' => $openid));
        if(empty($user['openid'])||empty($user['nickname'])){
            $str['status'] = -5;
            return $this->result(0, 'success', $str);
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
            $join = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and openid = :openid", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));
            if (empty($join)) {
                $ret = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_goods') . "WHERE uniacid = :uniacid and id = :id", array(':uniacid' => $uniacid, ':id' => $id));

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
                $data['formid'] = $_REQUEST['formid'];
                $data['create_time'] = time();
                $data['avatar'] = $user['avatar'];
                $data['pintuan_id'] = $_REQUEST['newtuan_id'];
                $data['is_group_member'] = 1;
                $str['status'] = pdo_insert_cj('choujiang_record', $data);
                if ($str['status'] == 1 && $base['smoke_num'] > 0 && $payfu != 1) {
                    pdo_update_cj('choujiang_user', $pata, array('id' => $user['id']));
                }
                $str['pintuan_id'] = $_REQUEST['newtuan_id'];
                $str['avatar'] = $user['avatar'];
                $str['nickname'] = $user['nickname'];
            } else {
                $str['status'] = -1;
            }
        } else {
            $str['status'] = -2;
        }
        return $this->result(0, 'success', $str);
    }

/*******************************自动开奖 start ********************************/
    // 定时开奖
    public function doPageGoodsOpenSetTime($id)
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        // $id = 629;
        $ret = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_goods') . "WHERE uniacid = :uniacid and id = :id", array(':uniacid' => $uniacid, ':id' => $id));

        $result = pdo_update_cj('choujiang_record', array('finish_time' => time()), array('goods_id' => $id));
        pdo_update_cj('choujiang_goods', array('status' => 1, 'send_time' => time()), array('id' => $id));

        if($ret['canyunum']>0){
            // redis 添加手动开奖锁
            cache_write('goodsopen'.$id, 1);
        }
        if ($ret['status'] == 0) {
            if ($ret['The_winning'] == 1) {    //指定中奖人
                $str = pdo_update_cj('choujiang_goods', array('status' => 1, 'send_time' => time()), array('id' => $id));
                $openid_arr = $ret['openid_arr'];
                $winning_openid = unserialize($openid_arr);
                $surplusGoodsNum = 0;//剩余总的奖品数量
                $surplus = 0;//内定人员参与失败人数 -> 将进行随机抽奖
                if (count($winning_openid) == $ret['goods_num']) {
                    foreach ($winning_openid as $key => $value) {
                        ///中奖号码
                        $winningCode = $this->_getWinningCode(['goods_id' => $id, 'openid' => $value]);
                        if (! $winningCode) {
                            continue;
                        }

                        $result_zd = pdo_update_cj('choujiang_record', array('status' => 1, 'winning_code' => $winningCode), array('openid' => $value, 'goods_id' => $id));
                        if (!$result_zd) {
                            $surplus++;
                        } else {
//                            if ($ret['is_pintuan']) {
//                                $oneRecord = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE openid = :openid and goods_id = :goods_id", array(':openid' => $value, ':goods_id' => $id));
//                                if ($oneRecord['pintuan_id']) {
//                                    $result_zd = pdo_update_cj('choujiang_record', array('status' => 1), array('pintuan_id' => $oneRecord['pintuan_id'], 'goods_id' => $id));
//                                }
//                            }
                        }
                    }
                } elseif (count($winning_openid) < $ret['goods_num']) {
                    foreach ($winning_openid as $key => $value) {
                        ///中奖号码
                        $winningCode = $this->_getWinningCode(['goods_id' => $id, 'openid' => $value]);
                        if (! $winningCode) {
                            continue;
                        }

                        $result_zd = pdo_update_cj('choujiang_record', array('status' => 1, 'winning_code' => $winningCode), array('openid' => $value, 'goods_id' => $id));
                        if (!$result_zd) {
                            $surplus++;
                        } else {
//                            if ($ret['is_pintuan']) {
//                                $oneRecord = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE openid = :openid and goods_id = :goods_id", array(':openid' => $value, ':goods_id' => $id));
//                                if ($oneRecord['pintuan_id']) {
//                                    $result_zd = pdo_update_cj('choujiang_record', array('status' => 1), array('pintuan_id' => $oneRecord['pintuan_id'], 'goods_id' => $id));
//                                }
//                            }
                        }
                    }
                    ///剩余总的奖品数量
                    $surplusGoodsNum = $ret['goods_num'] - (count($winning_openid) - $surplus);
                }
                $str = $this->doWebRandLottery($surplusGoodsNum, $id, time());
            } else {
                $str = $this->doWebRandLottery($ret['goods_num'], $id, time());
            }

            if (!empty($str)) {
                $res['status'] = 1;
                $res['goods_status'] = $ret['goods_status'];
                //$res['ddddd'] = $str ;
                file_put_contents(IA_ROOT . '/addons/choujiang_page/uuuu.log', "已经准备开奖111" . date('Y-m-d h:i:s', time()) . "\n", FILE_APPEND);
                $this->doPageInform($id);
            } else {
                $res['status'] = -1;
            }

        } else {
            $res['status'] = -1;
        }

    }

    // 随机抽奖调用
    /*
     * $id 抽奖商品Id
     * $num 抽奖人数
     * */
    public function doWebRandLottery($num, $id, $finishTime=0)
    {
        if ($num <=0 || time() - $finishTime > 120) {
            cache_delete('goodsopen'.$id);
            return true;
        }

        global $_W;
        $uniacid = $_W['uniacid'];

        $ret = pdo_get_cj("choujiang_goods", ['uniacid' => $uniacid, 'id' => $id]);
        $winningCode = mt_rand(10000000, $ret['max_cj_code']);

        ///号码是否已中过
        $result1 = pdo_get_cj("choujiang_record", ['goods_id' => $id, 'winning_code' => $winningCode]);

        ///机器人中奖限制
        $result2 = 0;
        if (! $ret['machine_canyu']) {//机器人不会中奖
            $result2 = pdo_get_cj("choujiang_record", ['goods_id' => $id, 'is_machine' => 1, 'codes like' => $winningCode]);
        }

        $recordInfo = pdo_get_cj("choujiang_record", ['goods_id' => $id, 'codes like' => "%{$winningCode}%"]);
        $userInfo = pdo_get_cj("choujiang_user", ['openid' => $recordInfo['openid']]);

        ///中奖次数限制
        $result3 = 0;
        $base = pdo_get_cj("choujiang_base", ["uniacid" => $uniacid]);
        $winning_num = $base['winning_num'];
        if ($winning_num > 0) {   //开启 - 限制中奖次数
            if (empty ($recordInfo) ) {
                $result3 = 1;
            } else {
                if ($userInfo['winning_num'] <= 0) { ///本月中奖次数已用完
                    $result3 = 1;
                }

            }
        }

        $str = pdo_update_cj('choujiang_goods', array('status' => 1, 'send_time' => time()), array('id' => $id));
        $max_num = $num;

        if ($result1 || $result2 || $result3) { ///本次号码无效
            return $this->doWebRandLottery($num, $id, $finishTime);
        } else { ///本次号码有效
            $winningNum = $userInfo['winning_num'] - 1;
            pdo_update_cj('choujiang_user', ['winning_num' => $winningNum], ['id' => $userInfo['id']]);
            $result = pdo_update_cj('choujiang_record', ['status' => 1, 'winning_code' => $winningCode], ['goods_id' => $id, 'openid' => $recordInfo['openid']]);

            file_put_contents(IA_ROOT . '/addons/choujiang_page/uuuu.log', "已经准备开奖:抽奖码{$winningCode}" . date('Y-m-d h:i:s', time()) . "\n", FILE_APPEND);
            if ($result) {
                $num = $num - 1;
            }

            return $this->doWebRandLottery($num, $id, $finishTime);
        }

        return $str;
    }

    /**
     * 获取中奖号码
     * @param array $params
     * @return bool
     */
    private function _getWinningCode($params=[])
    {
        if ($params['goods_id']) {
            return false;
        }

        ///中奖信息
        $recordInfo = pdo_get_cj('choujiang_record', ['goods_id' => $params['goods_id'], 'openid' => $params['openid']], ['codes','status']);
        if ($recordInfo['status'] == 1) { ///每个人最多中一次
            return false;
        }
        $codesArr = json_decode($recordInfo['codes'], true);
        $codesAmount = count($codesArr);
        $winningCodeIndex = mt_rand(0, $codesAmount-1);
        $winningCode = $codesArr[$winningCodeIndex];///中奖号码

        ///
        return $winningCode;
    }

    /*******************************自动开奖 end ********************************/

    // 开奖
    public function doPageGoodsOpen()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $id = $_REQUEST['id'];
        $xccj = $_REQUEST['xccj'];

        // redis 添加手动开奖锁
//        cache_write('goodsopen', 1);
        $ret = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_goods') . "WHERE uniacid = :uniacid and id = :id", array(':uniacid' => $uniacid, ':id' => $id));
        if($ret['canyunum']>0){
            // redis 添加手动开奖锁
            cache_write('goodsopen'.$id, 1);
        }
        if ($ret['status'] == 0) {
            $result = pdo_update_cj('choujiang_record', array('finish_time' => time()), array('goods_id' => $id));
            pdo_update_cj('choujiang_goods', array('status' => 1, 'send_time' => time()), array('id' => $id));
            if ($ret['The_winning'] == 1) {    //指定中奖人


                $str = pdo_update_cj('choujiang_goods', array('status' => 1, 'send_time' => time()), array('id' => $id));
                $openid_arr = $ret['openid_arr'];
                $winning_openid = unserialize($openid_arr);
                $surplusGoodsNum = 0;//剩余总的奖品数量
                $surplus = 0;//内定人员参与失败人数 -> 将进行随机抽奖
                if (count($winning_openid) == $ret['goods_num']) {///指定中奖人数和奖品数量一致
                    foreach ($winning_openid as $key => $value) {
                        ///中奖号码
                        $winningCode = $this->_getWinningCode(['goods_id' => $id, 'openid' => $value]);
                        if (! $winningCode) {
                            continue;
                        }

                        $result_zd = pdo_update_cj('choujiang_record', array('status' => 1, 'winning_code' => $winningCode), array('openid' => $value, 'goods_id' => $id));
                        if (!$result_zd) {
                            $surplus++;
                        } else {
                            if ($ret['is_pintuan']) {
                                $oneRecord = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE openid = :openid and goods_id = :goods_id", array(':openid' => $value, ':goods_id' => $id));
                                if ($oneRecord['pintuan_id']) {
                                    $result_zd = pdo_update_cj('choujiang_record', array('status' => 1), array('pintuan_id' => $oneRecord['pintuan_id'], 'goods_id' => $id));
                                    // redis 删除手动开奖锁
                                    cache_delete('goodsopen'.$id);
                                }
                            }
                        }
                    }
                } elseif (count($winning_openid) < $ret['goods_num']) { //指定中奖人比奖品数量少
                    foreach ($winning_openid as $key => $value) {
                        ///中奖号码
                        $winningCode = $this->_getWinningCode(['goods_id' => $id, 'openid' => $value]);
                        if (! $winningCode) {
                            continue;
                        }

                        $result_zd = pdo_update_cj('choujiang_record', array('status' => 1, 'winning_code' => $winningCode), array('openid' => $value, 'goods_id' => $id));
                        if (!$result_zd) {
                            $surplus++;
                        } else {
                            if ($ret['is_pintuan']) {
                                $oneRecord = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE openid = :openid and goods_id = :goods_id", array(':openid' => $value, ':goods_id' => $id));
                                if ($oneRecord['pintuan_id']) {
                                    $result_zd = pdo_update_cj('choujiang_record', array('status' => 1), array('pintuan_id' => $oneRecord['pintuan_id'], 'goods_id' => $id));
                                    // redis 删除手动开奖锁
                                    cache_delete('goodsopen'.$id);
                                }
                            }
                        }
                    }
                    ///剩余总的奖品数量
                    $surplusGoodsNum = $ret['goods_num'] - (count($winning_openid) - $surplus);
                }
                $str = $this->doWebRandLottery($surplusGoodsNum, $id, time());


//                foreach ($winning_openid as $key => $value) {
//                    $result_zd = pdo_update_cj('choujiang_record', array('status' => 1), array('openid' => $value, 'goods_id' => $id));
//                    if($result_zd){
//
//                    }
//                }
            } else {


                $str = $this->doWebRandLottery($ret['goods_num'], $id, time());


//                $join = pdo_fetchall_cj("SELECT id,openid from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id", array(':uniacid' => $uniacid, ':id' => $id));
//                foreach ($join as $key => $value) {
//                    pdo_update_cj('choujiang_record', array('finish_time' => time()), array('id' => $value['id']));
//                }
//                $base = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_base') . "WHERE uniacid = :uniacid", array(':uniacid' => $uniacid));
//                $winning_num = $base['winning_num'];
//                if ($winning_num > 0) {   //限制中奖次数
//                    foreach ($join as $key => $value) {
//                        $user = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_user') . "WHERE uniacid = :uniacid and openid = :openid", array(':uniacid' => $uniacid, ':openid' => $value['openid']));
//                        $send_time = $user['send_time'];
//                        $finish_time = strtotime("+1 months", $send_time);
//                        if ($finish_time <= time()) {
//                            pdo_update_cj('choujiang_user', array('send_time' => time(), 'winning_num' => $winning_num), array('id' => $user['id']));
//                        } else if ($send_time == '' || $send_time == null) {
//                            pdo_update_cj('choujiang_user', array('send_time' => time(), 'winning_num' => $winning_num), array('id' => $user['id']));
//                        } else {
//                            if ($user['winning_num'] <= 0) {
//                                unset($join[$key]);
//                            }
//                        }
//
//                    }
//                }
//                if ($xccj == 1) {   //现场抽奖
//                    $join = $join;   //数组中有的人 - 不在现场的人
//                }
//                $str = pdo_update_cj('choujiang_goods', array('status' => 1, 'send_time' => time()), array('id' => $id));
//                $max_num = $ret['goods_num'];
//
//
//                $join_count = count($join);
//                if ($join_count < $max_num) {
//                    $max_num = $join_count;
//                }
//                $arr = array();
//                if ($max_num == 1) {
//                    $arr[] = array_rand($join, $max_num);
//                } else {
//                    $arr = array_rand($join, $max_num);
//                }
//                foreach ($arr as $k => $val) {
//                    $obtain[] = $join[$val];
//                }
//                if ($ret['goods_status'] == 1) {   //红包
//                    $money = $ret['red_envelope'];
//                    foreach ($obtain as $key => $value) {
//                        $user = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_user') . "WHERE uniacid = :uniacid and openid = :openid", array(':uniacid' => $uniacid, ':openid' => $value['openid']));
//                        $earnings = $user['earnings'];
//                        $remaining_sum = $user['remaining_sum'];
//                        $pata['earnings'] = $earnings + $money;
//                        $pata['remaining_sum'] = $remaining_sum + $money;
//                        $data['uniacid'] = $uniacid;
//                        $data['openid'] = $value['openid'];
//                        $data['create_time'] = time();
//                        $data['money'] = $money;
//                        pdo_insert_cj('choujiang_earnings', $data);
//                        pdo_update_cj('choujiang_user', $pata, array('id' => $user['id']));
//                    }
//                }
//                if ($ret['goods_status'] == 2) {
//                    $cards = unserialize($ret['card_info']);
//                    $card_arr = array();
//                    $i = 0;
//                    foreach ($cards as $k => $v) {
//                        $card_arr[$i]['card_num'] = $k;
//                        $card_arr[$i]['card_password'] = $v;
//                        $i++;
//                    }
//                }
//
//                $i = 0;
//                foreach ($obtain as $key => $value) {
//                    if ($ret['smoke_set'] == 3) {  //现场开奖 生成核销码
//                        $this->doWebExchange($id, $value['openid']);
//                    }
//                    $users = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_user') . "WHERE uniacid = :uniacid and openid = :openid", array(':uniacid' => $uniacid, ':openid' => $value['openid']));
//                    if ($base['winning_num'] == 0) {
//                        $user_winning_num = $users['winning_num'];
//                    } else {
//                        $user_winning_num = $users['winning_num'] - 1;
//                    }
//                    pdo_update_cj('choujiang_user', array('winning_num' => $user_winning_num), array('id' => $users['id']));
//                    if ($ret['goods_status'] == 2) {   //电子卡
//                        $stat['status'] = 1;
//                        $stat['card_num'] = $card_arr[$i]['card_num'];
//                        $stat['card_password'] = $card_arr[$i]['card_password'];
//                        pdo_update_cj('choujiang_record', $stat, array('id' => $value['id']));
//                    } else {
//                        pdo_update_cj('choujiang_record', array('status' => 1), array('id' => $value['id']));
//                    }
//                    $i++;
//                }
            }

            if (!empty($str)) {
                $res['status'] = 1;
                $res['goods_status'] = $ret['goods_status'];
                //$res['ddddd'] = $str ;
                file_put_contents(IA_ROOT . '/addons/choujiang_page/uuuu.log', "已经准备开奖fdsfdsfas" . date('Y-m-d h:i:s', time()) . "\n", FILE_APPEND);
                $this->doPageInform($id);
            } else {
                $res['status'] = -1;
            }

        } else {
            $res['status'] = -1;
        }

        return $this->result(0, 'success', $res);


    }

    // 创建拼团
    public function doPageCreateGroups()
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];
        $id = $_REQUEST['id'];
        $openid = $_REQUEST['openid'];
        $pintuan = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_record') . "where uniacid = :uniacid and openid = :openid and goods_id = :id", array(':uniacid' => $uniacid, ':openid' => $openid, ':id' => $id));
        $ret = pdo_update_cj('choujiang_record', array('pintuan_id' => $pintuan['id']), array('uniacid' => $uniacid, 'openid' => $openid, 'goods_id' => $id));
        $this->doWebGroupsInvitation($id,$pintuan['id']);
        $result['status'] = $ret;
        $result['avatar'] = $pintuan['avatar'];
        $result['pintuan_id'] = $pintuan['id'];
        if ($ret) {
            return $this->result(0, 'success', $result);
        } else {
            return $this->result(1, 'fail', $result);
        }
    }

    // 电子卡中奖 获得卡号密码查询
    public function doPageMyOneRecord()
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];
        $id = $_REQUEST['id'];
        $openid = $_REQUEST['openid'];
        $cards = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_record') . "where uniacid = :uniacid and openid = :openid and goods_id = :id", array(':uniacid' => $uniacid, ':openid' => $openid, ':id' => $id));
        $ret['card_num'] = $cards['card_num'];
        $ret['card_password'] = $cards['card_password'];
        return $this->result(0, 'success', $ret);

    }

    //开奖成功 模板通知
    public function doPageInform($id)
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];
        $base = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_base') . "where `uniacid`='{$uniacid}' ");
        $template_id = $base['template_id'];
//        $appid = $base['appid'];
//        $appsecret = $base['appsecret'];
//        $tokenUrl = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}";
//        $getArr = array();
//        $tokenArr = json_decode($this->send_post($tokenUrl, $getArr, "GET"));
//        $access_token = $tokenArr->access_token;
        $wxapp = WeAccount::create();
        $access_token = $wxapp->getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=' . $access_token;
        $dd = array();
        $sql = pdo_fetchall_cj("SELECT * FROM " . tablename_cj('choujiang_record') . " where `uniacid`='{$uniacid}' and `goods_id`='{$id}'");
        $goods = pdo_fetch_cj("SELECT goods_openid,formid,goods_name,canyunum FROM " . tablename_cj('choujiang_goods') . " where `uniacid`='{$uniacid}' and `id`='{$id}'");
        $count = count($sql);
        for ($i = 0; $i < $count + 1; $i++) {
            $value = $sql[$i];
            if ($i == $count) {
                $dd['form_id'] = $goods['formid'];
                $dd['touser'] = $goods['goods_openid'];
                $content = array(
                    "keyword1" => array(
                        "value" => $goods['goods_name'],
                        "color" => "#4a4a4a"
                    ),
                    "keyword2" => array(
                        "value" => '您发起的 "' . $goods['goods_name'] . '" 活动现在开奖啦,点击查看中奖名单',
                        "color" => "#9b9b9b"
                    ),
                );
            } else {
                $dd['form_id'] = $value['formid'];
                $dd['touser'] = $value['openid'];
                $content = array(
                    "keyword1" => array(
                        "value" => $value['goods_name'],
                        "color" => "#4a4a4a"
                    ),
                    "keyword2" => array(
                        "value" => $value['nickname'] . ',您参与的 "' . $value['goods_name'] . '" 活动现在开奖啦,点击查看中奖名单',
                        "color" => "#9b9b9b"
                    ),

                );
            }


            $dd['template_id'] = $template_id;
            $dd['page'] = 'choujiang_page/fuli_xq/fuli_xq?id=' . $id;  //点击模板卡片后的跳转页面，仅限本小程序内的页面。支持带参数,该字段不填则模板无跳转。
            // $dd['page']='/choujiang_page/fuli_xq/fuli_xq?id='.$id;  //点击模板卡片后的跳转页面，仅限本小程序内的页面。支持带参数,该字段不填则模板无跳转。
            $dd['data'] = $content;                        //模板内容，不填则下发空模板
            $dd['color'] = '';                        //模板内容字体的颜色，不填默认黑色
            $dd['emphasis_keyword'] = '';    //模板需要放大的关键词，不填则默认无放大
            $result = $this->https_curl_json($url, $dd, 'json');
        }
        return $result;
    }


    //未开奖 模板通知
    public function doPageInform1($id)
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];
        $base = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_base') . "where `uniacid`='{$uniacid}' ");
        $template_id = $base['template_id'];
//        $appid = $base['appid'];
//        $appsecret = $base['appsecret'];
//        $tokenUrl = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}";
//        $getArr = array();
//        $tokenArr = json_decode($this->send_post($tokenUrl, $getArr, "GET"));
//        $access_token = $tokenArr->access_token;
        $wxapp = WeAccount::create();
        $access_token = $wxapp->getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=' . $access_token;
        $dd = array();
        $goods = pdo_fetch_cj("SELECT goods_openid,formid,goods_name,canyunum FROM " . tablename_cj('choujiang_goods') . " where `uniacid`='{$uniacid}' and `id`='{$id}'");
        $dd['form_id'] = $goods['formid'];
        $dd['touser'] = $goods['goods_openid'];
        $content = array(
            "keyword1" => array(
                "value" => $goods['goods_name'],
                "color" => "#4a4a4a"
            ),
            "keyword2" => array(
                "value" => '您发起的抽奖未开奖，因参与人数为0',
                "color" => "#9b9b9b"
            ),
        );
        $dd['template_id'] = $template_id;
        $dd['page'] = 'choujiang_page/fuli_xq/fuli_xq?id=' . $id;  //点击模板卡片后的跳转页面，仅限本小程序内的页面。支持带参数,该字段不填则模板无跳转
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

    // 中奖状态
    public function doPageObtainRecord()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $id = $_REQUEST['id'];
        $openid = $_REQUEST['openid'];
        $res = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and openid = :openid", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));
        return $this->result(0, 'success', $res);

    }

    // 中奖人员
    public function doPageObtainRecordUser()
    {
        if (isset($_GPC['version']) && ($_GPC['version'] > $this->clientVersion)){
            $this->doPageObtainRecordUser5();
            exit;
        }
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $id = $_REQUEST['id'];
        $openid = $_REQUEST['openid'];
        $res = pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and status = 1 ORDER BY id DESC", array(':uniacid' => $uniacid, ':id' => $id));
        return $this->result(0, 'success', $res);

    }

    // 中奖人员
    public function doPageObtainRecordUser5()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $id = $_REQUEST['id'];
        $openid = $_REQUEST['openid'];

        $nowUser = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and status = 1 and openid = :openid ORDER BY id DESC", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));
        if($nowUser){//当前用户参与抽奖
            if($nowUser['pintuan_id']==0){//当前用户参与抽奖没有组团
                $ret = pdo_fetchall_cj("SELECT avatar, nickname,pintuan_id,id,openid from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and status = 1 and goods_id = :id and openid != :openid ORDER BY id DESC limit 0,14", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));
                $tmpArr =  array();
                foreach($ret as $k => $v){
                    if($k==0) {
                        $tmpArr[0]['avatar'] = $nowUser['avatar'];
                        $tmpArr[0]['nickname'] = $nowUser['nickname'];
                        $tmpArr[0]['pintuan_id'] = $nowUser['pintuan_id'];
                        $tmpArr[0]['id'] = $nowUser['id'];
                    }
                    $tmpArr[]=$v;
                }
                $ret=$tmpArr;
            }else {//当前用户参与抽奖同时参与组团
                $ret = pdo_fetchall_cj("SELECT avatar, nickname,pintuan_id,id,openid from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and status = 1 and goods_id = :id and pintuan_id != :pintuan_id ORDER BY id DESC limit 0,14", array(':uniacid' => $uniacid, ':id' => $id, ':pintuan_id' => $nowUser['pintuan_id']));
                $tmpArr =  array();
                foreach($ret as $k => $v){
                    if($k==0) {
                        $tmpArr[0]['avatar'] = $nowUser['avatar'];
                        $tmpArr[0]['nickname'] = $nowUser['nickname'];
                        $tmpArr[0]['pintuan_id'] = $nowUser['pintuan_id'];
                        $tmpArr[0]['id'] = $nowUser['id'];
                    }
                    $tmpArr[]=$v;
                }
                $ret=$tmpArr;
            }
        }else{//当前用户没有参与抽奖
            $ret = pdo_fetchall_cj("SELECT avatar, nickname,pintuan_id,id,openid from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and goods_id = :id ORDER BY id DESC limit 0,15", array(':uniacid' => $uniacid, ':id' => $id));
        }
        $res = array();
        foreach($ret as $key => $val){
            if($val['pintuan_id']!=0){
                $tuanMenber = pdo_fetchall_cj("SELECT avatar, nickname,pintuan_id,id,openid from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and status = 1 and goods_id = :id ORDER BY id DESC", array(':uniacid' => $uniacid, ':id' => $id, ':pintuan_id' => $val['pintuan_id']));
                if($tuanMenber){
                    foreach($tuanMenber as $key1 => $val1){
                        if($key1 == 0){
                            $val['tuanZhanng'] = 1;
                            $res[$key][$val['openid']]=$val;
                        }
                        $val1['tuanZhanng'] = 0;
                        $res[$key][$val1['openid']]=$val1;
                    }
                }else{
                    $val['tuanZhanng'] = 1;
                    $res[$key][$val['openid']]=$val;
                }
            }else{
                $res[$key][$val['openid']]=$val;
            }
        }



//        $resTemp = pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and goods_id = :id and status = 1 ORDER BY id DESC limit 0,15", array(':uniacid' => $uniacid, ':id' => $id));
//        $res = array();
//        foreach($resTemp as $k => $v){
//            if($v['pintuan_id'] != 0){
//
//            }else{
//
//            }
//        }
        return $this->result(0, 'success', $res);
    }

    // 选择保存地址
    public function doPageUpdAdd()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $id = $_REQUEST['record_id'];
        $openid = $_REQUEST['openid'];
        if(empty($_REQUEST['user_tel'])|| !preg_match('/^1[34578][0-9]{9}$/', $_REQUEST['user_tel'])){
            $res = -1;
            return $this->result(0, 'success', $res);
        }else{
            $data['user_tel'] = $_REQUEST['user_tel'];
        }
        $data['user_zip'] = $_REQUEST['user_zip'];
        $data['user_address'] = $_REQUEST['user_address'];
        $data['user_name'] = $_REQUEST['user_name'];
        $str = pdo_update_cj('choujiang_record', $data, array('id' => $id, 'openid' => $openid, 'uniacid' => $uniacid));
        if (!empty($str)) {
            $res = 1;
        } else {
            $res = -2;
        }
        return $this->result(0, 'success', $res);

    }


    // 常见问题
    public function doPageProblems()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $res = pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_problems') . "WHERE uniacid = :uniacid and status=1 ORDER BY sort asc", array(':uniacid' => $uniacid));
        return $this->result(0, 'success', $res);
    }

    // 小程序推荐
    public function doPageUrlXcx()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $res = pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_xcx') . "WHERE uniacid = :uniacid and status = 1", array(':uniacid' => $uniacid));
        foreach ($res as $key => $value) {
            $res[$key]['icon'] = $_W['attachurl'] . $value['icon'];
        }
        return $this->result(0, 'success', $res);
    }


    // 删除
    public function doPageHomeDelete()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $id = $_REQUEST['id'];
        $openid = $_REQUEST['openid'];
        $status = $_REQUEST['status'];
        $record_id = $_REQUEST['record_id'];

        if ($status == 1) {  //发起的
            $goods = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_goods') . "WHERE uniacid = :uniacid and id = :id and goods_openid = :goods_openid and status=0", array(':id' => $id, ':goods_openid' => $openid, ':uniacid' => $uniacid));
            if ((int)$goods['canyunum'] < 1) {
                $str = pdo_update_cj('choujiang_goods', array('is_del' => -1), array('id' => $id, 'goods_openid' => $openid, 'uniacid' => $uniacid));
            } else {
                $str = 2;
            }
        } else {
            $str = pdo_update_cj('choujiang_record', array('del' => 1), array('id' => $record_id));
        }

        if ($str == 1) {
            $ret = 1;
        } elseif ($str == 2) {
            $ret = 2;
        } else {
            $ret = -1;
        }
//        $ret=$record_id;
        return $this->result(0, 'success', $ret);

    }

    public function doPageMemberHxM()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $goods_id = $_REQUEST['goods_id'];
        $openid = $_REQUEST['openid'];
        $sql = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_exchange') . " where uniacid=:uniacid and goods_id = :id", array(":id" => $goods_id, ":uniacid" => $uniacid));
        if ($_W['setting']['remote']['type'] != 0) {   //当开启远程存储
            $in = 'https';
            $url = $_W['setting']['site']["url"];
            $sub = substr($url, 0, strpos($url, ':'));
            if ($sub == $in) {
                $new_url = $url;
            } else {
                $new_url = $sub . 's:' . substr($url, strpos($url, ':') + 1);
            }
            $ret['verification'] = $new_url . '/attachment/choujiang_page/' . $sql['verification'];
            $ret['status'] = $sql['status'];
        } else {
            $ret['verification'] = $_W['attachurl'] . 'choujiang_page/' . $sql['verification'];
            $ret['status'] = $sql['status'];
        }
        return $this->result(0, 'success', $ret);
    }

    // 核销
    // 核销判断
    public function doPageHexiaoIf()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $text_ewm = $_REQUEST['text_ewm'];
        $openid = $_REQUEST['openid'];
        $list = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_exchange') . " where uniacid=:uniacid and orders = :text_ewm", array(":text_ewm" => $text_ewm, ":uniacid" => $uniacid));
        if (empty($list)) {
            // $str = '该订单不存在';
            $state = -2;
        } else {
            $goods = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_goods') . " where uniacid=:uniacid and id = :id", array(":id" => $list['goods_id'], ":uniacid" => $uniacid));
            if ($list['status'] == 1) {
                // $str = '该订单已核销';
                $state = -1;
            } else if ($goods['goods_openid'] == $openid) {
                $state = 1;
            } else {
                $state = -3;
            }
        }
        $ret['status'] = $state;
        // $ret['con'] = $str;
        return $this->result(0, '成功'/*'成功'*/, $ret);

    }

// 核销
    public function doPageHexiaoIfIn()
    {

        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $text_ewm = $_REQUEST['text_ewm'];
        $list = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_exchange') . " where uniacid=:uniacid and orders = :text_ewm and status = 0", array(":text_ewm" => $text_ewm, ":uniacid" => $uniacid));
        if (!empty($list)) {
            $rets = pdo_update_cj("choujiang_exchange", array('status' => 1), array('id' => $list['id'], 'uniacid' => $uniacid));
            if ($rets) {
                $str = 1;
            } else {
                $str = -1;
            }
        } else {
            $str = -1;
        }

        return $this->result(0, '成功'/*'成功'*/, $str);


    }

    public function doPageFriendEwm()
    {

        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $id = $_REQUEST['id'];
        // $id = 498;
        // $id = 497;
        $sql = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_verification') . " where uniacid=:uniacid and goods_id = :id", array(":id" => $id, ":uniacid" => $uniacid));
        if ($sql['haibao'] == '') {
            $goods = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_goods') . " where uniacid=:uniacid and id = :id", array(":id" => $id, ":uniacid" => $uniacid));
            if ($_W['setting']['remote']['type'] != 0) {   //当开启远程存储
                $in = 'https';
                $url = $_W['setting']['site']["url"];
                $sub = substr($url, 0, strpos($url, ':'));
                if ($sub == $in) {
                    $new_url = $url;
                } else {
                    $new_url = $sub . 's:' . substr($url, strpos($url, ':') + 1);
                }
                $url1 = $_W['attachurl'];
                $sub1 = substr($url1, 0, strpos($url1, ':'));
                if ($sub1 == $in) {
                    $new_url1 = $url1;
                } else {
                    $new_url1 = $sub1 . 's:' . substr($url1, strpos($url1, ':') + 1);
                }
                $ret['verification'] = $new_url . '/attachment/choujiang_page/' . $sql['verification'];
                $ret['goods_icon'] = $new_url1 . $goods['goods_icon'];
            } else {
                $ret['verification'] = $_W['attachurl'] . 'choujiang_page/' . $sql['verification'];
                $ret['goods_icon'] = $_W['attachurl'] . $goods['goods_icon'];
            }


//            $item = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_base') . " WHERE uniacid = :uniacid", array(':uniacid' => $uniacid));
//            if (!$item['type']) {
//                $item['url'] = $_W['attachurl'];
//            }
//            $ret['goods_icon'] = $item['url'] . $goods['goods_icon'];
//            $ret['goods_icon'] = $_W['attachurl'] . $goods['goods_icon'];
            $ret['goods_icon'] = $this->getImage($goods['goods_icon']);

            // 生成海报
            $pic_list = array(
                $ret['verification'],
                $ret['goods_icon'],
            );

            $suofan = 2;
            $bg_w = 400 * $suofan; // 背景图片宽度
            $bg_h = 700 * $suofan; // 背景图片高度

            $background = imagecreatetruecolor($bg_w, $bg_h); // 背景图片
            // $color = imagecolorallocate($background, 204, 96, 83); // 为真彩色画布创建白色背景，再设置为透明
            // $black = imagecolorallocate($background, 0, 0, 0); //设置一个颜色变量为黑色
            $white = imagecolorallocate($background, 255, 255, 255); //设置一个颜色变量为黑色
            imagefill($background, 0, 0, $white);

            // header("Content-type:  charset=utf-8");
            // imagettftext($background, 20, 0, 130, 350, $wasd, "./stxingka.ttf", "你好啊啊");//向画布上写字
            // imagettftext($background, 20, 0, 80, 400, $wasd, "./stxingka.ttf", "你好啊啊三生三世");//向画布上写字

            foreach ($pic_list as $k => $pic_path) {
                $pathInfo = pathinfo($pic_path);
                switch (strtolower($pathInfo['extension'])) {
                    case 'jpg':
                    case 'jpeg':
                        $imagecreatefromjpeg = 'imagecreatefromjpeg';
                        break;
                    case 'png':
                        $imagecreatefromjpeg = 'imagecreatefrompng';
                        break;
                    case 'gif':
                    default:
                        $imagecreatefromjpeg = 'imagecreatefromstring';
                        $pic_path = file_get_contents($pic_path);
                        break;
                }
                $resource = $imagecreatefromjpeg($pic_path);
                if ($k == 1) {
                    $start_x = intval(15 * $suofan); // 开始位置X
                    $start_y = intval($bg_h / 10) - 35 * $suofan; // 开始位置Y
                    $pic_w = intval($bg_w / 2) + 170 * $suofan; // 宽度
                    $pic_h = ($pic_w / 1.875); // 高度
                    //imagecopyresized($background, $resource, $start_x, $start_y, 0, 0, $pic_w, $pic_h, imagesx($resource), imagesy($resource));
                    list($width, $height) = getimagesize($pic_path);
                    $image_p1 = imagecreatetruecolor($pic_w, $pic_h);
                    $image1 = $imagecreatefromjpeg($pic_path);
                    imagecopyresampled($image_p1, $image1, 0, 0, 0, 0, $pic_w, $pic_h, $width, $height);
                    imagecopymerge($background, $image_p1, $start_x, $start_y, 0, 0, $pic_w, $pic_h, 100);
                } else {
                    $start_x = intval($bg_w / 4) + 10 * $suofan; // 开始位置X
                    $start_y = intval($bg_h / 2) + 40 * $suofan; // 开始位置Y
                    //$pic_w = 176 * $suofan; // 宽度
                    $pic_w = 200 * $suofan; // 宽度
                    $pic_h = 200 * $suofan; // 高度
                    //imagecopyresized($background, $resource, $start_x, $start_y, 0, 0, $pic_w, $pic_h, imagesx($resource), imagesy($resource));
                    list($width, $height) = getimagesize($pic_path);
                    $image_p = imagecreatetruecolor($pic_w, $pic_h);
                    $image = $imagecreatefromjpeg($pic_path);
                    imagecopyresampled($image_p, $image, 0, 0, 0, 0, $pic_w, $pic_h, $width, $height);
                    imagecopymerge($background, $image_p, $start_x, $start_y, 0, 0, $pic_w, $pic_h, 100);
                }
            }
            $image_name = md5(uniqid(rand())) . ".jpg";
            $filepath = "../attachment/choujiang_page/{$image_name}";
            pdo_update_cj("choujiang_verification", array('haibao' => $image_name), array('goods_id' => $id, 'uniacid' => $uniacid));

            header("Content-type: image/jpg");
            imagejpeg($background);

            $img = imagegif($background, $filepath);
            imagedestroy($image_p);
            imagedestroy($image);
            imagedestroy($image_p1);
            imagedestroy($image1);
            imagedestroy($background);

            return $this->result(0, 'success', 123);
        } else {
            return $this->result(0, 'success', 456);
        }

    }

    public function doPageHaiBao()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $id = $_REQUEST['id'];
        // $id = 497;
        $ret = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_verification') . " where uniacid=:uniacid and goods_id = :id", array(":id" => $id, ":uniacid" => $uniacid));
        if ($_W['setting']['remote']['type'] != 0) {   //当开启远程存储
            $in = 'https';
            $url = $_W['setting']['site']["url"];
            $sub = substr($url, 0, strpos($url, ':'));
            if ($sub == $in) {
                $new_url = $url;
            } else {
                $new_url = $sub . 's:' . substr($url, strpos($url, ':') + 1);
            }
            $ret['haibao'] = $new_url . '/attachment/choujiang_page/' . $ret['haibao'];
        } else {
            $ret['haibao'] = $_W['attachurl'] . 'choujiang_page/' . $ret['haibao'];
        }
        $goods = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_goods') . " where uniacid=:uniacid and id = :id", array(":id" => $id, ":uniacid" => $uniacid));
        if ($goods['smoke_set'] == 0) {
            $str = $goods['smoke_time'] . ' 自动开奖';
        } else if ($goods['smoke_set'] == 1) {
            $str = '参与人数达到 ' . $goods['smoke_num'] . ' 人 自动开奖';
        } else if ($goods['smoke_set'] == 2) {
            $str = '由发起人手动开奖';
        }
        if ($goods['goods_status'] == 1) {
            $ret['goods_name'] = '红包' . $goods['red_envelope'];
        } else {
            $ret['goods_name'] = '奖品:' . $goods['goods_name'];
        }

        $ret['goods_num'] = $goods['goods_num'];
        $ret['goods_set'] = $str;
        return $this->result(0, 'success', $ret);

    }

    public function doPageGroupFriendEwm()
    {

        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $id = $_REQUEST['id'];
        // $id = 498;
        // $id = 497;
        $sql = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_verification') . " where uniacid=:uniacid and goods_id = :id", array(":id" => $id, ":uniacid" => $uniacid));
        if ($sql['group_haibao'] == '') {
            $goods = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_goods') . " where uniacid=:uniacid and id = :id", array(":id" => $id, ":uniacid" => $uniacid));
            if ($_W['setting']['remote']['type'] != 0) {   //当开启远程存储
                $in = 'https';
                $url = $_W['setting']['site']["url"];
                $sub = substr($url, 0, strpos($url, ':'));
                if ($sub == $in) {
                    $new_url = $url;
                } else {
                    $new_url = $sub . 's:' . substr($url, strpos($url, ':') + 1);
                }
                $url1 = $_W['attachurl'];
                $sub1 = substr($url1, 0, strpos($url1, ':'));
                if ($sub1 == $in) {
                    $new_url1 = $url1;
                } else {
                    $new_url1 = $sub1 . 's:' . substr($url1, strpos($url1, ':') + 1);
                }
                $ret['verification'] = $new_url . '/attachment/choujiang_page/' . $sql['group_verification'];
                $ret['goods_icon'] = $new_url1 . $goods['goods_icon'];
            } else {
                $ret['verification'] = $_W['attachurl'] . 'choujiang_page/' . $sql['group_verification'];
                $ret['goods_icon'] = $_W['attachurl'] . $goods['goods_icon'];
            }


            $ret['goods_icon'] = $this->getImage($goods['goods_icon']);

            // 生成海报
            $pic_list = array(
                $ret['verification'],
                $ret['goods_icon'],
            );

            $suofan = 2;
            $bg_w = 400 * $suofan; // 背景图片宽度
            $bg_h = 700 * $suofan; // 背景图片高度

            $background = imagecreatetruecolor($bg_w, $bg_h); // 背景图片
            // $color = imagecolorallocate($background, 204, 96, 83); // 为真彩色画布创建白色背景，再设置为透明
            // $black = imagecolorallocate($background, 0, 0, 0); //设置一个颜色变量为黑色
            $white = imagecolorallocate($background, 255, 255, 255); //设置一个颜色变量为黑色
            imagefill($background, 0, 0, $white);

            // header("Content-type:  charset=utf-8");
            // imagettftext($background, 20, 0, 130, 350, $wasd, "./stxingka.ttf", "你好啊啊");//向画布上写字
            // imagettftext($background, 20, 0, 80, 400, $wasd, "./stxingka.ttf", "你好啊啊三生三世");//向画布上写字

            foreach ($pic_list as $k => $pic_path) {
                $pathInfo = pathinfo($pic_path);
                switch (strtolower($pathInfo['extension'])) {
                    case 'jpg':
                    case 'jpeg':
                        $imagecreatefromjpeg = 'imagecreatefromjpeg';
                        break;
                    case 'png':
                        $imagecreatefromjpeg = 'imagecreatefrompng';
                        break;
                    case 'gif':
                    default:
                        $imagecreatefromjpeg = 'imagecreatefromstring';
                        $pic_path = file_get_contents($pic_path);
                        break;
                }
                $resource = $imagecreatefromjpeg($pic_path);
                if ($k == 1) {
                    $start_x = intval(15 * $suofan); // 开始位置X
                    $start_y = intval($bg_h / 10) - 35 * $suofan; // 开始位置Y
                    $pic_w = intval($bg_w / 2) + 170 * $suofan; // 宽度
                    $pic_h = intval($bg_w / 2) + 40 * $suofan; // 高度
                    //imagecopyresized($background, $resource, $start_x, $start_y, 0, 0, $pic_w, $pic_h, imagesx($resource), imagesy($resource));
                    list($width, $height) = getimagesize($pic_path);
                    $image_p1 = imagecreatetruecolor($pic_w, $pic_h);
                    $image1 = $imagecreatefromjpeg($pic_path);
                    imagecopyresampled($image_p1, $image1, 0, 0, 0, 0, $pic_w, $pic_h, $width, $height);
                    imagecopymerge($background, $image_p1, $start_x, $start_y, 0, 0, $pic_w, $pic_h, 100);
                } else {
                    $start_x = intval($bg_w / 4) + 10 * $suofan; // 开始位置X
                    $start_y = intval($bg_h / 2) + 70 * $suofan; // 开始位置Y
                    //$pic_w = 176 * $suofan; // 宽度
                    $pic_w = 200 * $suofan; // 宽度
                    $pic_h = 200 * $suofan; // 高度
                    //imagecopyresized($background, $resource, $start_x, $start_y, 0, 0, $pic_w, $pic_h, imagesx($resource), imagesy($resource));
                    list($width, $height) = getimagesize($pic_path);
                    $image_p = imagecreatetruecolor($pic_w, $pic_h);
                    $image = $imagecreatefromjpeg($pic_path);
                    imagecopyresampled($image_p, $image, 0, 0, 0, 0, $pic_w, $pic_h, $width, $height);
                    imagecopymerge($background, $image_p, $start_x, $start_y, 0, 0, $pic_w, $pic_h, 100);
                }
            }
            $image_name = md5(uniqid(rand())) . ".jpg";
            $filepath = "../attachment/choujiang_page/{$image_name}";
            pdo_update_cj("choujiang_verification", array('group_haibao' => $image_name), array('goods_id' => $id, 'uniacid' => $uniacid));

            header("Content-type: image/jpg");
            imagejpeg($background);

            $img = imagegif($background, $filepath);
            imagedestroy($image_p);
            imagedestroy($image);
            imagedestroy($image_p1);
            imagedestroy($image1);
            imagedestroy($background);

            return $this->result(0, 'success', 123);
        } else {
            return $this->result(0, 'success', 456);
        }

    }

    public function doPageGroupHaiBao()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $id = $_REQUEST['id'];
        // $id = 497;
        $ret = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_verification') . " where uniacid=:uniacid and goods_id = :id", array(":id" => $id, ":uniacid" => $uniacid));
        if ($_W['setting']['remote']['type'] != 0) {   //当开启远程存储
            $in = 'https';
            $url = $_W['setting']['site']["url"];
            $sub = substr($url, 0, strpos($url, ':'));
            if ($sub == $in) {
                $new_url = $url;
            } else {
                $new_url = $sub . 's:' . substr($url, strpos($url, ':') + 1);
            }
            $ret['haibao'] = $new_url . '/attachment/choujiang_page/' . $ret['group_haibao'];
        } else {
            $ret['haibao'] = $_W['attachurl'] . 'choujiang_page/' . $ret['group_haibao'];
        }
        $goods = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_goods') . " where uniacid=:uniacid and id = :id", array(":id" => $id, ":uniacid" => $uniacid));
        if ($goods['smoke_set'] == 0) {
            $str = $goods['smoke_time'] . ' 自动开奖';
        } else if ($goods['smoke_set'] == 1) {
            $str = '参与人数达到 ' . $goods['smoke_num'] . ' 人 自动开奖';
        } else if ($goods['smoke_set'] == 2) {
            $str = '由发起人手动开奖';
        }
        if ($goods['goods_status'] == 1) {
            $ret['goods_name'] = '红包' . $goods['red_envelope'];
        } else {
            $ret['goods_name'] = '奖品:' . $goods['goods_name'];
        }

        $ret['goods_num'] = $goods['goods_num'];
        $ret['goods_set'] = $str;
        return $this->result(0, 'success', $ret);
    }

    public function doPageNewGroupHaiBao()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $id = $_REQUEST['id'];
        $openId = $_REQUEST['openid'];
        $base = pdo_fetch_cj('SELECT `index_title`, `app_icon` FROM ' . tablename_cj('choujiang_base') . " where uniacid=:uniacid", array(":uniacid" => $uniacid));

        if($_REQUEST['zt']){
            $ret = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_record') . " where uniacid=:uniacid and goods_id = :id and openid=:openid", array(":id" => $id, ":uniacid" => $uniacid, ":openid" => $openId));
            $verification = $ret['group_verification'];
        }else{
            if(isset($_REQUEST['version']) && $_REQUEST['version'] == 4){

                $userInfo = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_user') . " where uniacid=:uniacid and openid=:openid", [":uniacid" => $uniacid, ":openid" => $openId]);
                $key = sprintf("cj_share_code:%s:%s:%s", $uniacid, $id, $userInfo['id']);
                $redis = connect_redis();
                $imgSrc = $redis->hGet($key, 'src');
//            $imgSrc = null;
                if (empty($imgSrc)) {
                    $redis->hSet($key, 'src', "1");
                    $noncestr = "/choujiang_page/fuli_xq/fuli_xq?g=" . $id . "&c=-1&u=" . $userInfo['id'];
                    $scene = "g=" . $id . "&c=-1&u=" . $userInfo['id'];
                    $page = "choujiang_page/fuli_xq/fuli_xq";
                    $codeWidth = 430;

//                $result = $this->getWxInvitation($noncestr,'',$codeWidth);//A方式获取二维码
                    $result = $this->getWxInvitation($scene,$page,$codeWidth);//B方式获取二维码
                    if($result == 'getCodeFail'){
                        $redis->hDel($key, 'src');
                        return $this->result(1, 'fail', []);
                    }else{

                        $filename = 'cj/share/'.$uniacid.date('/Y/m/d/').md5(date('YmdHis').rand(111111, 999999)).'.jpg';
                        $goods = pdo_fetch_cj("SELECT G.*, U.nickname, U.avatar FROM " . tablename_cj('choujiang_goods') . " as G left join " . tablename_cj('choujiang_user') . " as U on G.goods_openid = U.openid where G.uniacid=:uniacid and G.id = :id", array(":id" => $id, ":uniacid" => $uniacid));

//                    if ($_W['setting']['remote']['type'] != 0) {   //当开启远程存储
//                        $in = 'https';
//                        $url = $_W['setting']['site']["url"];
//                        $sub = substr($url, 0, strpos($url, ':'));
//                        if ($sub == $in) {
//                            $new_url = $url;
//                        } else {
//                            $new_url = $sub . 's:' . substr($url, strpos($url, ':') + 1);
//                        }
//                        $url1 = $_W['attachurl'];
//                        $sub1 = substr($url1, 0, strpos($url1, ':'));
//                        if ($sub1 == $in) {
//                            $new_url1 = $url1;
//                        } else {
//                            $new_url1 = $sub1 . 's:' . substr($url1, strpos($url1, ':') + 1);
//                        }
//                        $ret['verification'] = $new_url . '/attachment/choujiang_page/' . $sql['group_verification'];
//                        $ret['goods_icon'] = $new_url1 . $goods['goods_icon'];
//                    } else {
//                        $ret['verification'] = $_W['attachurl'] . 'choujiang_page/' . $sql['group_verification'];
//                        $ret['goods_icon'] = $_W['attachurl'] . $goods['goods_icon'];
//                    }
                        $ret['goods_icon'] = $goods['goods_icon'];
                        $ret['goods_icon'] = $this->getImgArray($goods['goods_icon'])[0];
                        // 生成海报
                        $pic_list = array(
                            $ret['verification'],
                            $ret['goods_icon'],
                        );
                        $suofan = 2;
                        $bg_w = 375; // 背景图片宽度
                        $bg_h = 657; // 背景图片高度
                        $rectangleMargin = 10;
                        $rectanglePadding = 25;
                        $font = __DIR__.'/resource/fonts/msyh.ttf';//微软雅黑字体

                        $background = imagecreatetruecolor($bg_w * $suofan, $bg_h * $suofan); // 背景图片
                        $color = imagecolorallocate($background, 255, 64, 72); // 为真彩色画布创建白色背景，再设置为透明
                        $orange = imagecolorallocate($background, 236, 73, 67); // 背景颜色
                        $black = imagecolorallocate($background, 0, 0, 0); //设置一个颜色变量为黑色
                        $gray = imagecolorallocate($background, 153, 153, 153); //设置一个颜色变量为红色
                        $white = imagecolorallocate($background, 255, 255, 255); //设置一个颜色变量为白色
//                    imagefill($background, 0, 0, $white); //填充白色背景
                        imagefill($background, 0, 0, $orange); //填充背景颜色
                        $image_p = imagecreatetruecolor( ($bg_w - 20) * $suofan, ($bg_h - 20) * $suofan);
                        imagefill($image_p, 0, 0, $white); //填充白色背景
                        imagecopymerge($background, $image_p, $rectangleMargin * $suofan, $rectangleMargin * $suofan, 0, 0, ($bg_w - $rectangleMargin*2) * $suofan, ($bg_h - $rectangleMargin*2) * $suofan, 100);
                        imagedestroy($image_p);

                        if( $this->baseConfig['type'] == 1){
                            $shareUserAvatar = $this->attachurl."/"."cj/avatar/".$uniacid."/".$userInfo['id'].".jpg";

                        }else{
                            $shareUserAvatar = $this->attachurl."/attachment/choujiang_page/cj/avatar/".$uniacid."/".$userInfo['id'].".jpg";
                        }
                        $touxiang = $this->circularImg($shareUserAvatar);


                        $pic_list = [
                            [//头像
                                'imageData' => $touxiang,
//                            'picPath' => $goods['avatar'],
                                'picPath' => $shareUserAvatar,
//                            'start_x' => intval(141 * $suofan),
                                'start_x' => intval(155 * $suofan),
//                            'start_y' => intval(27 * $suofan),
                                'start_y' => intval(41 * $suofan),
//                            'pic_w' => 93 * $suofan,
//                            'pic_h' => 93 * $suofan,
                                'pic_w' => 66 * $suofan,
                                'pic_h' => 66 * $suofan,
                            ],
                            [//奖品图片
                                'picPath' => $ret['goods_icon'],
                                'start_x' => intval($rectanglePadding * $suofan),
                                'start_y' => intval(194* $suofan),
                                'pic_w' => intval(($bg_w -$rectanglePadding*2) * $suofan),
                                'pic_h' => intval((($bg_w -$rectanglePadding*2) * $suofan) / 1.875),
                            ],
                            [//二维码
                                'imageData' => $result,
                                'start_x' => intval(($bg_w-124)/2 * $suofan),
                                'start_y' => intval(451 * $suofan),
                                'pic_w' => 124 * $suofan,
                                'pic_h' => 124 * $suofan,
                            ],
                            [//分享文案
                                'picPath' => __DIR__.'/resource/image/share.png',
                                'start_x' => 65*$suofan,
                                'start_y' => 138*$suofan,
                                'pic_w' => ($bg_w* $suofan-65* $suofan*2),
                                'pic_h' => 38 * $suofan,
                            ],
                        ];
//                    var_dump( $pic_list);
                        foreach ($pic_list as $k => $picVal) {
//                        if(isset($picVal['picPath'])){
                            if($k==1||$k==3){
                                $pic_path = $picVal['picPath'];
                                //$pathInfo = pathinfo($pic_path);
                                $pathInfo = getimagesize($pic_path);
                                list($width, $height) =$pathInfo;
                                switch (strtolower($pathInfo['mime'])) {
                                    case 'image/jpg':
                                    case 'image/jpeg':
                                        $imagecreatefromjpeg = 'imagecreatefromjpeg';
                                        break;
                                    case 'image/png':
                                        $imagecreatefromjpeg = 'imagecreatefrompng';
                                        break;
                                    case 'image/gif':
                                    default:
                                        $imagecreatefromjpeg = 'imagecreatefromstring';
                                        $pic_path = file_get_contents($pic_path);
                                        break;
                                }
                                $image_p = imagecreatetruecolor( $picVal['pic_w'], $picVal['pic_h']);
                                if($k==3){
                                    imagefill($image_p, 0, 0, $white); //填充白色背景
                                }
                                $image = $imagecreatefromjpeg($pic_path);
                                imagecopyresampled($image_p, $image, 0, 0, 0, 0,  $picVal['pic_w'], $picVal['pic_h'], $width, $height);
                            }elseif($k==2){
                                //$image_p = $picVal['imageData'];
                                $pic_path = $picVal['imageData'];
                                $imagecreatefromjpeg = 'imagecreatefromstring';
//                            $pic_path = file_get_contents($pic_path);
                                $image_p = imagecreatetruecolor( $picVal['pic_w'], $picVal['pic_h']);
                                $image = $imagecreatefromjpeg($pic_path);
                                imagecopyresampled($image_p, $image, 0, 0, 0, 0,  $picVal['pic_w'], $picVal['pic_h'], $codeWidth, $codeWidth);
                            }elseif($k==0){
                                list($width, $height) = getimagesize($picVal['picPath']);
                                $image_p = imagecreatetruecolor( $picVal['pic_w'], $picVal['pic_h']);
                                imagefill($image_p, 0, 0, $white); //填充白色背景
                                $image = $picVal['imageData'];
                                imagecopyresampled($image_p, $image, 0, 0, 0, 0,  $picVal['pic_w'], $picVal['pic_h'], $width, $height);
                            }
                            imagecopymerge($background, $image_p, $picVal['start_x'], $picVal['start_y'], 0, 0, $picVal['pic_w'], $picVal['pic_h'], 100);
                            if(isset($picVal['picPath'])) {
                                imagedestroy($image_p);
                                imagedestroy($image);
                            }
                        }

//                    if ($goods['smoke_set'] == 0) {
//                        $str = $goods['smoke_time'] . ' 自动开奖';
//                    } else if ($goods['smoke_set'] == 1) {
//                        $str = '参与人数达到 ' . $goods['smoke_num'] . ' 人 自动开奖';
//                    } else if ($goods['smoke_set'] == 2) {
//                        $str = '由发起人手动开奖';
//                    }
//                    if($userInfo['nickname'] == $goods['nickname']){
//                        $shareText = "发起了一个免费抽奖，邀您参加";
//                    }else{
//                        $shareText = "分享了一个免费抽奖，邀您参加";
//                    }

                        $text_list = [
//                        [
//                            'text' => $shareText,
//                            'font' => $font,
//                            'fontSize' => 12*$suofan,
//                            'color' => $orange,
//                            'xSize' => 'center',
//                            'ySize' => 163*$suofan,
//                            'lineTop' => 138*$suofan,
//                            'lineBottom' => 176*$suofan,
//                        ],
                            [
                                'text' => "奖品：".$goods['goods_name']." x".$goods['goods_num'],
                                'font' => $font,
                                'fontSize' => 11*$suofan,
                                'color' => $black,
                                'xSize' => 30*$suofan,
                                'ySize' => 402*$suofan,
                            ],
//                        [
//                            'text' => $str,
//                            'font' => $font,
//                            'fontSize' => 10*$suofan,
//                            'color' => $gray,
//                            'xSize' => 'center',
//                            'ySize' => 430*$suofan,
//                        ],
                            [
                                'text' => "长按识别小程序，免费参与抽奖",
                                'font' => $font,
                                'fontSize' => 10*$suofan,
                                'color' => $gray,
                                'xSize' => 'center',
                                'ySize' => 610*$suofan,
                            ],
                        ];

                        foreach($text_list as $textKey=>$textVal){
                            if($textVal['xSize'] == 'center'){
                                $fontBox  = imagettfbbox($textVal['fontSize'], 0, $textVal['font'], $textVal['text']);//文字水平居中实质
                                $xSize = ceil(($bg_w * $suofan - $fontBox[2]) / 2);
                            }else{
                                $xSize = $textVal['xSize'];
                            }
                            if(isset($textVal['lineTop'])){
                                imageline( $background, $xSize, $textVal['lineTop'], $xSize+$fontBox[2], $textVal['lineTop'], $orange );  //绘制线条
                            }
                            if(isset($textVal['lineBottom'])){
                                imageline( $background, $xSize, $textVal['lineBottom'], $xSize+$fontBox[2], $textVal['lineBottom'], $orange );  //绘制线条
                            }
                            imagettftext($background, $textVal['fontSize'], 0, $xSize, $textVal['ySize'], $textVal['color'], $textVal['font'], $textVal['text']);//向画布上写字
                        }

                        imageline( $background, intval($rectanglePadding * $suofan), 426*$suofan, intval(($bg_w - $rectanglePadding) * $suofan), 426*$suofan, $gray );  //绘制线条
//                    imagerectangle($background, $rectangleMargin * $suofan, $rectangleMargin * $suofan, ($bg_w-$rectangleMargin) * $suofan, ($bg_h-$rectangleMargin) * $suofan, $color); //绘制矩形框

                        $image_name = md5(uniqid(rand())) . ".jpg";
                        $filepath = "../attachment/choujiang_page/{$image_name}";
                        imagejpeg($background,$filepath,100);
                        imagedestroy($background);

                        $imgSrc = $this->upLoadShareImage($filename,file_get_contents($filepath));
                        @unlink($filepath);
//                    imagedestroy($background);
                        $redis->hSet($key, src, $imgSrc);
                        $redis->expire($key, 3600*24*7);
//                    cache_write($key, $imgSrc, 3600*24*7);
                    }
                }
                return $this->result(0, 'success', $this->getImage($imgSrc));

            }else{
                $ret = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_verification') . " where uniacid=:uniacid and goods_id = :id", array(":id" => $id, ":uniacid" => $uniacid));
                $verification = $ret['verification'];
            }
        }
        if ($_W['setting']['remote']['type'] != 0) {   //当开启远程存储
            $in = 'https';
            $url = $_W['setting']['site']["url"];
            $sub = substr($url, 0, strpos($url, ':'));
            if ($sub == $in) {
                $new_url = $url;
            } else {
                $new_url = $sub . 's:' . substr($url, strpos($url, ':') + 1);
            }
//            $ret['haibao'] = $new_url . '/attachment/choujiang_page/' . $ret['group_haibao'];
            $ret['erweima'] = $new_url . '/attachment/choujiang_page/' . $verification;
        } else {
//            $ret['haibao'] = $_W['attachurl'] . 'choujiang_page/' . $ret['group_haibao'];
            $ret['erweima'] = $_W['attachurl'] . 'choujiang_page/' . $verification;
        }
        $goods = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_goods') . " where uniacid=:uniacid and id = :id", array(":id" => $id, ":uniacid" => $uniacid));
        if ($goods['smoke_set'] == 0) {
            $str = $goods['smoke_time'] . ' 自动开奖';
        } else if ($goods['smoke_set'] == 1) {
            $str = '参与人数达到 ' . $goods['smoke_num'] . ' 人 自动开奖';
        } else if ($goods['smoke_set'] == 2) {
            $str = '由发起人手动开奖';
        }
        if ($goods['goods_status'] == 1) {
            $ret['goods_name'] = '红包' . $goods['red_envelope'];
        } else {
            $ret['goods_name'] = '奖品:' . $goods['goods_name'];
        }
        $goods_pic = $this->getImage($goods['goods_icon']);
        $ret['goods_num'] = $goods['goods_num'];
        $ret['goods_set'] = $str;
        $ret['goods_pic'] = $goods_pic;
        $ret['xcxName'] =  $base['index_title'];
        $app_icon = $this->getImage( $base['app_icon']);
        $ret['app_pic'] =  $app_icon;
        return $this->result(0, 'success', $ret);
    }

    /*
     * 生成圆形头像专用图片
     */
    public function circularImg($imgpath) {
        $runTime = '';
        $ext     = pathinfo($imgpath);
        $src_img = null;
        if(!isset($ext['extension'])){
            $ext['extension'] = 'jpg';
        }

        switch ($ext['extension']) {
            case 'jpg':
                $src_img = imagecreatefromjpeg($imgpath);
                break;
            case 'png':
                $src_img = imagecreatefrompng($imgpath);
                break;
        }
        $wh  = getimagesize($imgpath);
        $w   = $wh[0];
        $h   = $wh[1];
        $w   = min($w, $h);
        $h   = $w;
        $img = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($img, 255, 255, 255); //设置一个颜色变量为白色
        imagefill($img, 0, 0, $white); //填充白色背景
        //这一句一定要有
        //imagesavealpha($img, true);
        //拾取一个完全透明的颜色,最后一个参数127为全透明
        $bg = imagecolorallocatealpha($img, 255, 255, 255, 127);
        imagefill($img, 0, 0, $bg);

        $r   = $w / 2; //圆半径
        $y_x = $r; //圆心X坐标
        $y_y = $r; //圆心Y坐标
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $rgbColor = imagecolorat($src_img, $x, $y);
                if (((($x - $r) * ($x - $r) + ($y - $r) * ($y - $r)) < ($r * $r))) {
                    imagesetpixel($img, $x, $y, $rgbColor);
                }
            }
        }
        return $img;
    }

    /*
     *分享图片上传
     *
     */

    public function upLoadShareImage($filename,$data)
    {
        global $_W, $_GPC;

        $uniacid = $_W['uniacid'];
        $item = $this->baseConfig;
        if ($item['type'] == 1)   //阿里云oss 开启
        {
//            $pinfo = $filename;
//            $filename = 'images/' . $_W['uniacid'] . '/' . date('Y/') . $goods_id . '/' . $pinfo['basename'];

            //将服务器上的图片转移到阿里云oss
            $remote = $item;
            $bucket = explode("@@", $remote['bucket']);
            require_once(IA_ROOT . '/framework/library/alioss/autoload.php');
            load()->model('attachment');
            $endpoint = $remote['location'];

            try {
                $ossClient = new \OSS\OssClient($remote['aliosskey'], $remote['aliosssecret'], $endpoint);
//                $ossClient->uploadFile($bucket[0], $filename, ATTACHMENT_ROOT . $filename);
                $ossClient->putObject($bucket[0], $filename, $data);//上传内存数据
            } catch (\OSS\Core\OssException $e) {
                //echo  'error--->'.$e->getMessage();
                return error(1, $e->getMessage());

            }

            //$fname = $remote['url'] . $filename;
            $fname = $filename;
            return $fname;

        } else if ($item['type'] == 0)    //远程存储关闭
        {
            $uptypes = array('image/jpg', 'image/jpeg', 'image/png', 'image/pjpeg', 'image/gif', 'image/bmp', 'image/x-png');
            $max_file_size = 2000000;
            $destination_folder = '../attachment/choujiang_page/';  //图片文件夹路径
            //创建存放图片的文件夹
            if (!is_dir($destination_folder)) {
                $res = mkdir($destination_folder, 0777, true);
            }
            if (!is_uploaded_file($_FILES['upfile']['tmp_name'])) {
                echo '图片不存在!';
                die;
            }
            $file = $_FILES['upfile'];
            if ($max_file_size < $file['size']) {
                echo '文件太大!';
                die;
            }
            if (!in_array($file['type'], $uptypes)) {
                echo '文件类型不符!' . $file['type'];
                die;
            }
            $filename = $file['tmp_name'];
            $pinfo = pathinfo($file['name']);
            $ftype = $pinfo['extension'];
            $destination = $destination_folder . str_shuffle(time() . rand(111111, 999999)) . '.' . $ftype;
            if (file_exists($destination) && $overwrite != true) {
                echo '同名文件已经存在了';
                die;
            }
            if (!move_uploaded_file($filename, $destination)) {
                echo '移动文件出错';
                die;
            }
            $pinfo = pathinfo($destination);
            $fname = $_W['attachurl'] . 'choujiang_page/' . $pinfo['basename'];
            return $fname;
        }
    }

    /*
     *向微信接口请求二维码
     *
     */
    public function getWxInvitation($noncestr, $page='', $width='430')
    {

        $repeatTime = 2;
        $wxapp = WeAccount::create();
        $access_token = $wxapp->getAccessToken();
        $post_data = '{"path":"' . $noncestr . '","width":' . $width . '}';
        $url = 'https://api.weixin.qq.com/wxa/getwxacode?access_token=' . $access_token;
        if(!empty($page)){
            $post_data = json_encode([
                'width' => $width,
                'scene' => $noncestr,
                'page' => $page,
            ]);
            $url = sprintf("https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=%s", $access_token);
        }
        $result = $this->api_notice_increment($url, $post_data);

        $jsonResult = @json_decode($result,true);
        $l =0;
        if(!is_null($jsonResult)){
            for($i=0;$i<$repeatTime;$i++){
                $cacheKey = "accesstoken:{$this->baseConfig['appid']}";
                cache_delete($cacheKey);
                $result = $this->getWxInvitation($noncestr);
                $jsonResult = @json_decode($result,true);
                if(is_null($jsonResult)){
                    break;
                }
                $l++;
            }
        }
        if($l == $repeatTime){
            return 'getCodeFail';
        }else{
            return $result;
        }
    }


    // 新增奖品生成二维码图片
    public function doWebInvitation($id)
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
        $wxapp = WeAccount::create();
        $access_token = $wxapp->getAccessToken();
        $noncestr = 'choujiang_page/fuli_xq/fuli_xq?id=' . $goods_id;
        $width = 430;
        $post_data = '{"path":"' . $noncestr . '","width":' . $width . '}';
        // $url="https://api.weixin.qq.com/cgi-bin/wxaapp/createwxaqrcode?access_token=".$access_token;
        $url = 'https://api.weixin.qq.com/wxa/getwxacode?access_token=' . $access_token;
        $result = $this->api_notice_increment($url, $post_data);

        $image_name = md5(uniqid(rand())) . ".jpg";
        $filepath = "../attachment/choujiang_page/{$image_name}";
        $file_put = file_put_contents($filepath, $result);
        if ($file_put) {
            $sql = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_verification') . " where uniacid=:uniacid and goods_id = :id", array(":uniacid" => $uniacid, ':id' => $goods_id));
            if (empty($sql)) {
                $datas = array('verification' => $image_name, 'uniacid' => $uniacid, 'goods_id' => $goods_id);
                pdo_insert_cj("choujiang_verification", $datas);
            } else {
                $datas = array('verification' => $image_name);
                pdo_update_cj("choujiang_verification", $datas, array('goods_id' => $goods_id, 'uniacid' => $uniacid));
            }
        } else {
            $filepath = "attachment/choujiang_page/{$image_name}";
        }
        return $filepath;
    }


    // 新增奖品生成组团二维码图片
    public function doWebGroupsInvitation($id,$tId)
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $goods_id = $id;
//        $result = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_base') . " where uniacid=:uniacid", array(":uniacid" => $uniacid));
//        $APPID = $result['appid'];
//        $SECRET = $result['appsecret'];
//        $tokenUrl = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$APPID}&secret={$SECRET}";
//        $getArr = array();
//        $tokenArr = json_decode($this->send_post($tokenUrl, $getArr, "GET"));
//        $access_token = $tokenArr->access_token;

        $wxapp = WeAccount::create();
        $access_token = $wxapp->getAccessToken();

        $noncestr = 'choujiang_page/groupsinvitation/groupsinvitation?id=' . $goods_id . "&tid=" . $tId;
        $width = 430;
        $post_data = '{"path":"' . $noncestr . '","width":' . $width . '}';
        // $url="https://api.weixin.qq.com/cgi-bin/wxaapp/createwxaqrcode?access_token=".$access_token;
        $url = 'https://api.weixin.qq.com/wxa/getwxacode?access_token=' . $access_token;
        $result = $this->api_notice_increment($url, $post_data);

        $image_name = md5(uniqid(rand())) . ".jpg";
        $filepath = "../attachment/choujiang_page/{$image_name}";
        $file_put = file_put_contents($filepath, $result);
        if ($file_put) {
            $datas = array('group_verification' => $image_name);
            pdo_update_cj("choujiang_record", $datas, array('id' => $tId, 'uniacid' => $uniacid));
        }
        return $filepath;
    }


    // 现场抽奖中奖生成二维码
    public function doWebExchange($id, $openid)
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $goods_id = $id;
        // $goods_id = 353;
//        $result = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_base') . " where uniacid=:uniacid", array(":uniacid" => $uniacid));
//        $APPID = $result['appid'];
//        $SECRET = $result['appsecret'];
//        $tokenUrl = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$APPID}&secret={$SECRET}";
//        $getArr = array();
//        $tokenArr = json_decode($this->send_post($tokenUrl, $getArr, "GET"));
//        $access_token = $tokenArr->access_token;

        $wxapp = WeAccount::create();
        $access_token = $wxapp->getAccessToken();

        $noncestr = date('YmdHis') . rand(10000000, 99999999);
        $width = 430;
        $post_data = '{"path":"' . $noncestr . '","width":' . $width . '}';
        $url = 'https://api.weixin.qq.com/wxa/getwxacode?access_token=' . $access_token;

        $result = $this->api_notice_increment($url, $post_data);

        $image_name = md5(uniqid(rand())) . ".jpg";
        $filepath = "../attachment/choujiang_page/{$image_name}";
        $file_put = file_put_contents($filepath, $result);

        if ($file_put) {
            $sql = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_exchange') . " where uniacid=:uniacid and goods_id = :id and openid = :openid", array(":uniacid" => $uniacid, ':id' => $goods_id, ':openid' => $openid));
            if (empty($sql)) {
                $datas = array('verification' => $image_name, 'uniacid' => $uniacid, 'goods_id' => $goods_id, 'openid' => $openid, 'status' => 0, 'create_time' => time(), 'orders' => $noncestr);
                pdo_insert_cj("choujiang_exchange", $datas);
            }
        }
        return $image_name;

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
        // $header = "Accept-Charset: utf-8";
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

    // 次数购买
    public function doPageVio_Num()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $ret = pdo_fetchall_cj('SELECT * FROM ' . tablename_cj('choujiang_vip_num') . " where uniacid=:uniacid", array(":uniacid" => $uniacid));
        return $this->result(0, 'success', $ret);
    }

    // 判断次数是否剩余
    public function doPageSurplus_Num()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $ret = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_user') . " where uniacid=:uniacid and openid =:openid", array(":uniacid" => $uniacid, ":openid" => $_GPC["id"]));
        if (isset($_REQUEST['types']) && $_REQUEST['types'] == "more") {
            if ($ret['extensions_num']) {
                $free_num = 1;
            } else {
                $free_num = 0;
            }
        } else {
            if ($ret['mf_num'] == 0 && $ret['yu_num'] == 0) {
                $free_num = 0;
            } else {
                $free_num = 1;
            }
        }

        return $this->result(0, 'success', $free_num);
    }

    // 付费版增强功能
    public function doPageExtensions_Num()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $ret = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_base') . " where uniacid=:uniacid", array(":uniacid" => $uniacid));
        $res_price = $ret['extensions_price'];
        return $this->result(0, 'success', $res_price);
    }

// 订单支付
    public function doPagePay()
    {
        global $_GPC, $_W;
        include 'wxpay.php';
        $res = pdo_get_cj('choujiang_base', array('uniacid' => $_W['uniacid']));
        $appid = $res['appid'];
        $openid = $_REQUEST['openid'];
        $mch_id = $res['mch_id'];
        $key = $res['appkey'];
        $out_trade_no = $mch_id . time();
        $total_fee = $_REQUEST['total'];
        if (empty($total_fee)) {
            $body = '订单付款';
            $total_fee = floatval(0.01 * 100);
        } else {
            $body = '订单付款';
            $total_fee = floatval($total_fee * 100);
        }
        $weixinpay = new WeixinPay($appid, $openid, $mch_id, $key, $out_trade_no, $body, $total_fee);
        $return = $weixinpay->pay();
        echo json_encode($return);
    }

    public function doPageXcxPayRecord()
    {
        global $_GPC, $_W;
        $data['uniacid'] = $_W['uniacid'];
        $data['openid'] = $_REQUEST['openid'];
        $member = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_user') . " where uniacid=:uniacid and openid = :openid", array(":uniacid" => $_W['uniacid'], ':openid' => $_REQUEST['openid']));
        $data['total'] = $_REQUEST['total'];
        $poundage = $_REQUEST['poundage'];
        if ($poundage == 0) {
            $data['y_total'] = 0;
        } else {
            $data['y_total'] = $poundage;
        }
        $data['y_total'] = $_REQUEST['xcx_price'];
        $data['nickname'] = $member['nickname'];
        $data['avatar'] = $member['avatar'];
        $data['goods_id'] = $_REQUEST['id'];
        $data['status'] = 6;
        $data['create_time'] = time();
        $res = pdo_insert_cj('choujiang_pay_record', $data);
        $pay_id = pdo_insertid_cj();
        if (empty($res)) {
            $ret['status'] = -1;
        } else {
            $ret['status'] = 1;
            $ret['pay_id'] = $pay_id;
        }
        return $this->result(0, 'success', $ret);
    }

    // 付费抽奖
    public function doPageJoinPayRecord()
    {
        global $_GPC, $_W;
        $data['uniacid'] = $_W['uniacid'];
        $data['openid'] = $_REQUEST['openid'];
        $member = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_user') . " where uniacid=:uniacid and openid = :openid", array(":uniacid" => $_W['uniacid'], ':openid' => $_REQUEST['openid']));
        $data['total'] = $_REQUEST['total'];
        $data['nickname'] = $member['nickname'];
        $data['avatar'] = $member['avatar'];
        $data['goods_id'] = $_REQUEST['id'];
        $data['status'] = 3;
        $data['create_time'] = time();
        $res = pdo_insert_cj('choujiang_pay_record', $data);
        $pay_id = pdo_insertid_cj();
        if (empty($res)) {
            $ret['status'] = -1;
        } else {
            $ret['status'] = 1;
        }
        return $this->result(0, 'success', $ret);
    }

    public function doPagePayorder()
    {
        global $_GPC, $_W;
        $data['uniacid'] = $_W['uniacid'];
        $data['openid'] = $_REQUEST['openid'];
        $member = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_user') . " where uniacid=:uniacid and openid = :openid", array(":uniacid" => $_W['uniacid'], ':openid' => $_REQUEST['openid']));

        if (isset($_REQUEST['types']) && $_REQUEST['types'] == "more") {
            $data['num'] = 1;
            $data['total'] = $_REQUEST['total'];
            $data['y_total'] = $_REQUEST['total'];
            $data['poundage'] = 0;
            $data['nickname'] = $member['nickname'];
            $data['avatar'] = $member['avatar'];
            $data['create_time'] = time();
            $data['status'] = 7;
            $res = pdo_insert_cj('choujiang_pay_record', $data);
            if (empty($res)) {
                $str = -1;
            } else {
                $now_num = 1;
                $ret = pdo_update_cj('choujiang_user', array('extensions_num' => $now_num), array('id' => $member['id']));
                if (!empty($ret)) {
                    $str = 1;
                }
            }
        } else {
            $data['vip_id'] = $_REQUEST['id'];
            $data['num'] = $_REQUEST['num'];
            $data['total'] = $_REQUEST['total'];
            $data['y_total'] = $_REQUEST['total'];
            $data['poundage'] = 0;
            $data['nickname'] = $member['nickname'];
            $data['avatar'] = $member['avatar'];
            $data['create_time'] = time();
            $data['status'] = 1;
            $res = pdo_insert_cj('choujiang_pay_record', $data);
            if (empty($res)) {
                $str = -1;
            } else {
                $now_num = $member['yu_num'] + $_REQUEST['num'];
                $ret = pdo_update_cj('choujiang_user', array('yu_num' => $now_num), array('id' => $member['id']));
                if (!empty($ret)) {
                    $str = 1;
                }
            }
        }
        return $this->result(0, 'success', $str);
    }

    // 红包发起抽奖

    public function doPagePay1()
    {
        global $_GPC, $_W;
        include 'wxpay.php';
        $res = pdo_get_cj('choujiang_base', array('uniacid' => $_W['uniacid']));
        $appid = $res['appid'];
        $poundage = intval($res['poundage']) / 100;
        $openid = $_REQUEST['openid'];
        $mch_id = $res['mch_id'];
        $key = $res['appkey'];
        $out_trade_no = $mch_id . time();
        $total_fee = $_REQUEST['total'];
        // $total_fee = 0.01;
        if (empty($total_fee)) {
            $body = '订单付款';
            $total_fee = floatval(0.01 * 100);
        } else {
            $body = '订单付款';
            $total_fee = floatval($total_fee * 100);
        }
        if ($total_fee >= 100) {
            $total_fee = round(floatval($total_fee + $total_fee * $poundage), 2);
        }
        $weixinpay = new WeixinPay($appid, $openid, $mch_id, $key, $out_trade_no, $body, $total_fee);
        $return = $weixinpay->pay();
        echo json_encode($return);
    }

    public function doPagePayGoods()
    {
        global $_GPC, $_W;
        $data['uniacid'] = $_W['uniacid'];
        $data['openid'] = $_REQUEST['openid'];
        $total_fee = $_REQUEST['total'];

        $member = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_user') . " where uniacid=:uniacid and openid = :openid", array(":uniacid" => $_W['uniacid'], ':openid' => $_REQUEST['openid']));
        $base = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_base') . " where uniacid=:uniacid", array(":uniacid" => $_W['uniacid']));
        $poundage = $base['poundage'] / 100;  //手续费百分比
        $total_fee = round(floatval($total_fee + $total_fee * $poundage), 2);  //支付总金额
        $data['poundage'] = round(floatval($total_fee * $poundage), 2);  //手续费价格
        $data['y_total'] = $_REQUEST['total'];   //不算手续费的总价
        $data['total'] = $total_fee;
        $data['nickname'] = $member['nickname'];
        $data['avatar'] = $member['avatar'];
        $data['num'] = 0;
        $data['vip_id'] = 0;
        $data['create_time'] = time();
        $data['status'] = 2;
        $res = pdo_insert_cj('choujiang_pay_record', $data);
        $pay_id = pdo_insertid_cj();
        if (empty($res)) {
            $ret['status'] = -1;
        } else {
            $ret['status'] = 1;
            $ret['pay_id'] = $pay_id;
        }
        return $this->result(0, 'success', $ret);

    }

    // // 提现
    // public function doPageConfirm()
    // {
    //     global $_W, $_GPC;
    //     include 'wxtx.php';
    //     $openid = "oQQf_0KyaKENcRwM1kgeF6W4hH_Y";
    //     $u_name = "Lj";
    //     $total = intval(1*100);
    //     // $openid = $_REQUEST['openid'];
    //     // $total = intval($_REQUEST['total'] * 100);  //提现金额
    //     //var_dump($tx_cost);exit();
    //     $uniacid = $_W['uniacid'];
    //     // $u_name = $_REQUEST['u_name'];   //提现昵称
    //     $key = pdo_fetch_cj("SELECT * FROM ".tablename_cj("choujiang_base")." where uniacid=:uniacid",array(":uniacid"=>$uniacid));
    //        // $appsecret =$key['appsecret'];
    //     $appid = $key['appid'];   //微信公众平台的appid
    //     $mch_id = $key['mch_id'];  //商户号id
    //     $openid = $openid;    //用户openid
    //     $amount = $total;  //提现金额$money_sj
    //     $desc = "提现";     //企业付款描述信息
    //     $appkey = $key['appkey'];   //商户号支付密钥
    //     $re_user_name = $u_name;   //收款用户姓名

    //     $Weixintx = new WeixinTx($appid,$mch_id,$openid,$amount,$desc,$appkey,$re_user_name);
    //     $notify_url = $Weixintx->Wxtx();
    //     var_dump($notify_url);
    //     if($notify_url['return_code']=="SUCCESS" && $notify_url['result_code']=="SUCCESS"){
    //         $member = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_user') . " where uniacid=:uniacid and openid = :openid", array(":uniacid" => $_W['uniacid'],':openid' => $_REQUEST['openid']));
    //         $remaining_sum = $member['remaining_sum'] - $total;
    //         $result = pdo_update_cj('choujiang_user', array('remaining_sum' => $remaining_sum), array('id' => $member['id']));
    //         if ($result) {
    //             $str = 1;
    //         } else {
    //             $str = -1;
    //         }
    //     }
    // // $ret['openid'] = $_REQUEST['openid'];
    // //     $ret['u_name'] = $_REQUEST['u_name'];
    // //     $ret['appid'] = $appid;
    // //     $ret['mch_id'] = $mch_id;
    // //     $ret['appkey'] = $appkey;
    // //     $ret['total'] = $total;
    //     // $ret['notify_url'] = $notify_url;

    //     return $this->result(0,'success', $ret);

    // }
    public function doPageWithdrawal()
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];
        $total = floatval($_REQUEST['total']);
        if ($total > 0) {
            $member = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_user') . " where uniacid=:uniacid and openid = :openid", array(":uniacid" => $_W['uniacid'], ':openid' => $_REQUEST['openid']));
            $remaining_sum = floatval($member['remaining_sum']);
            $new_remaining_sum = $remaining_sum - $total;
            $base = pdo_get_cj('choujiang_base', array('uniacid' => $uniacid));
            $poundage = intval($base['poundage']) / 100;
            $money = round(floatval($total - $total * $poundage), 2);
            $data['uniacid'] = $uniacid;
            $data['openid'] = $_REQUEST['openid'];
            $data['total'] = $total;  //原价
            $data['money'] = $money;  //实际提现
            $data['poundage'] = round(floatval($total * $poundage), 2);  //手续费
            $data['nickname'] = $member['nickname'];
            $data['avatar'] = $member['avatar'];
            $data['create_time'] = time();

            $rets = pdo_insert_cj('choujiang_withdrawal', $data);
            $ret = pdo_update_cj('choujiang_user', array('remaining_sum' => $new_remaining_sum), array('id' => $member['id']));
            if ($ret && $ret) {
                $str['status'] = 1;
                $str['remaining_sum'] = $new_remaining_sum;
            } else {
                $str['status'] = -1;
            }
        }
        return $this->result(0, 'success', $str);
    }

    // 我的钱包
    public function doPageMyMoney()
    {
        global $_GPC, $_W;
        $data['uniacid'] = $_W['uniacid'];
        $member = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_user') . " where uniacid=:uniacid and openid = :openid", array(":uniacid" => $_W['uniacid'], ':openid' => $_REQUEST['openid']));
        if ($member['remaining_sum'] == '') {
            $member['remaining_sum'] = 0;
        }
        if ($member['earnings'] == '') {
            $member['earnings'] = 0;
        }
        $ret['remaining_sum'] = $member['remaining_sum'];
        $ret['earnings'] = $member['earnings'];
        return $this->result(0, 'success', $ret);
    }

    // 我的交易记录
    public function doPageMyPayRecord()
    {
        global $_GPC, $_W;
        $_REQUEST['openid'] = 'oQQf_0KyaKENcRwM1kgeF6W4hH_Y';
        $arr = array();
        $withdrawal = pdo_fetchall_cj('SELECT * FROM ' . tablename_cj('choujiang_withdrawal') . " where uniacid=:uniacid and openid = :openid order by create_time desc", array(":uniacid" => $_W['uniacid'], ':openid' => $_REQUEST['openid']));
        $earnings = pdo_fetchall_cj('SELECT * FROM ' . tablename_cj('choujiang_earnings') . " where uniacid=:uniacid and openid = :openid order by create_time desc", array(":uniacid" => $_W['uniacid'], ':openid' => $_REQUEST['openid']));
        foreach ($withdrawal as $key => $value) {
            $withdrawal[$key]['record_status'] = 1;
            if ($value['status'] == 0) {
                $withdrawal[$key]['status_name'] = '提现中';
                $withdrawal[$key]['now_money'] = '-' . $value['total'];
            } else if ($value['status'] == 1) {
                $withdrawal[$key]['status_name'] = '提现成功';
                $withdrawal[$key]['now_money'] = '-' . $value['total'];
            } else if ($value['status'] == -1) {
                $withdrawal[$key]['status_name'] = '提现失败(已退回)';
                $withdrawal[$key]['now_money'] = '+' . $value['total'];
            }
        }
        foreach ($earnings as $key => $value) {
            $earnings[$key]['record_status'] = 2;
            $earnings[$key]['now_money'] = '+' . $value['money'];
        }
        foreach ($withdrawal as $key => $value) {
            array_push($arr, $value);
        }
        foreach ($earnings as $key => $value) {
            array_push($arr, $value);
        }
        $res = array();
        foreach ($arr as $v) {
            $res[] = $v['create_time'];
        }
        array_multisort($res, SORT_ASC, $arr);
        foreach ($arr as $key => $value) {
            if ($value['record_status'] == 1) {
                $arr[$key]['record_name'] = $value['status_name'];
            } else if ($value['record_status'] == 2) {
                $arr[$key]['record_name'] = '中奖收益';
            }
            $arr[$key]['create_time'] = date('Y-m-d H:i', $value['create_time']);
        }
        return $this->result(0, 'success', $arr);

    }


    // 每日分享获得抽奖次数
    public function doPageShareNumMy()
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];
        $openid = $_REQUEST['openid'];
        $base = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_base') . " where uniacid=:uniacid", array(":uniacid" => $uniacid));
        $user = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_user') . " where uniacid=:uniacid and openid = :openid", array(":uniacid" => $uniacid, ':openid' => $_REQUEST['openid']));
        $share_num = $base['share_num'];
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        $finish_time = strtotime(date($year . '-' . $month . '-' . $day . ' ' . '23:59:59'));  //每日更新最迟时间
        $start_time = strtotime(date($year . '-' . $month . '-' . $day . ' ' . '00:00:00'));  //每日更新最迟时间
        $now_time = time();
        if ($now_time <= $finish_time && ($user['share_num_time'] < $start_time || $user['share_num_time'] == '')) {
            $rets = pdo_update_cj('choujiang_user', array('share_num' => $share_num, 'share_num_time' => time()), array('id' => $user['id']));
            if ($rets) {
                $str = 1;
            } else {
                $str = -1;
            }
        } else {
            $str = -1;
        }
        return $this->result(0, 'success', $str);


    }

    // 分享好友
    public function doPageShareAddMy()
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];
        $openid = $_REQUEST['openid'];
        $base = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_base') . " where uniacid=:uniacid", array(":uniacid" => $uniacid));
        $share_num = $base['share_num'];
        if ($share_num > 0) {
            $user = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_user') . " where uniacid=:uniacid and openid = :openid", array(":uniacid" => $uniacid, ':openid' => $_REQUEST['openid']));
            if ($user['share_num'] > 0) {
                $data['share_num'] = $user['share_num'] - 1;
                $data['smoke_share_num'] = $user['smoke_share_num'] + 1;
                $rets = pdo_update_cj('choujiang_user', $data, array('id' => $user['id']));
                if ($rets) {
                    $str = 1;
                } else {
                    $str = -1;
                }
            } else {
                $str = -1;
            }
        } else {
            $str = 2;
        }
        return $this->result(0, 'success', $str);
    }


// 骗审导航
    public function doPagePiandaohang()
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];
        $cheat_nav = pdo_fetchall_cj('SELECT * FROM ' . tablename_cj('choujiang_cheat_nav') . " where uniacid=:uniacid", array(":uniacid" => $uniacid));
        foreach ($cheat_nav as $key => $value) {
            $cheat_nav[$key]['icon'] = $_W['attachurl'] . $value['icon'];
        }
        return $this->result(0, 'success', $cheat_nav);
    }

    // 骗审列表
    public function doPageCheatList()
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];
        $cheat = pdo_fetchall_cj('SELECT * FROM ' . tablename_cj('choujiang_cheat') . " where uniacid=:uniacid", array(":uniacid" => $uniacid));
        foreach ($cheat as $key => $value) {
            $cheat[$key]['icon'] = $_W['attachurl'] . $value['icon'];
        }
        return $this->result(0, 'success', $cheat);
    }

    // 骗审列表详情
    public function doPageCheatListIn()
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];
        $id = $_REQUEST['id'];
        $cheat = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_cheat') . " where uniacid=:uniacid and id = :id", array(":uniacid" => $uniacid, ":id" => $id));
        $cheat['icon'] = $_W['attachurl'] . $cheat['icon'];
        return $this->result(0, 'success', $cheat);
    }

    //服务器图片是否存在
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
     * 收集用户formid用于推送消息
     * @return bool
     */
    public function doPageMessage()
    {
        global $_GPC, $_W;
        // 收集formid - start
        $openId = $_REQUEST['openId'];
        $formId = $_REQUEST['formId'];
        $id = $_REQUEST['id'];

        $listKey = sprintf("cj_message_user_list:%s", $_W['uniacid']);
        $itemKey = sprintf("cj_message_user_item:%s", $openId);

        if (! empty($openId) && ! empty($formId)) {
            $redis = connect_redis();
            ///当前用户已经拥有的form_id
            if ($redis->exists($itemKey)) {
                $myFormIds = $redis->hGetAll($itemKey);
                $formIds = json_decode($myFormIds['form_id'], true);
                $first = $formIds[0]['date'];
                $firstDay = date('Ymd', strtotime($first));
            } else {
                $formIds = [];
                $firstDay = 0;
            }

            $now = date("Ymd");
            if (count($formIds) >= 7 && $firstDay >= $now) { //今日已经收集了7个form_id,小程序form_id有效期7天
                return true;
            } else {
                if (count($formIds) >= 7 && $firstDay < $now) {
                    unset($formIds[0]);
                }

                if ($myFormIds['id'] >= $id) { //当前用户已浏览过详情的最新奖品id
                    $id = $myFormIds['id'];
                }

                $formIds[] = [
                    'form_id' => $formId,
                    'date' => date('Y-m-d H:i:s'),
                ];

                $formIds = array_values($formIds);
                $myFormId = [
                    'open_id' => $openId,
                    'id' => $id,
                    'form_id' => json_encode($formIds)
                ];

                $redis->zAdd($listKey, 1, $openId);
                $redis->hMset($itemKey, $myFormId);
                //
                return true;
            }
        }
        // 收集formid - end
    }

    public function doPageGetInfo()
    {
        //ObtainRecordAddress  // 中奖者用户地址信息
        $ret = array();
        global $_GPC, $_W;
        $id = $_REQUEST['id'];
        $uniacid = $_W['uniacid'];
        $openid = $_REQUEST['openid'];
        //获取当前用户信息
        $ret['userInfos'] = pdo_fetch_cj("SELECT nickname as nickName,avatar as avatarUrl,openid from" . tablename_cj('choujiang_user') . "WHERE uniacid = :uniacid and openid = :openid", array(':uniacid' => $uniacid, ':openid' => $openid));
        $ret['ObtainRecordAddress'] = count(pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and status = 1 and user_name != ''", array(':uniacid' => $uniacid, ':id' => $id)));
        // 参与抽奖的用户列表
        //当前用户是否参与抽奖
//        $ret['Participate_avatar'] = pdo_fetchall_cj("SELECT avatar from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and goods_id = :id ORDER BY id DESC limit 0,20", array(':uniacid' => $uniacid, ':id' => $id));
        $nowUser = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and openid = :openid", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));
        if ($nowUser) {//当前用户参与抽奖
//            if ($nowUser['pintuan_id'] == 0) {//当前用户参与抽奖没有组团
            $ret['Participate_avatar'] = pdo_fetchall_cj("SELECT avatar,codes,codes_amount from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and goods_id = :id and openid != :openid ORDER BY codes_amount DESC limit 0,20", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));
            $tmpArr = array();
            if ($ret['Participate_avatar']) {
                foreach ($ret['Participate_avatar'] as $k => $v) {
                    if ($k == 0) {
                        $tmpArr[] = [
                            'avatar' => $nowUser['avatar'],
                            'codes_amount' => $nowUser['codes_amount'],
                            'codes' => empty($nowUser['codes']) ? [] : $nowUser['codes']
                        ];
                    }
                    $tmpArr[] = [
                        'avatar' => $v['avatar'],
                        'codes_amount' => $v['codes_amount'],
                        'codes' => empty($v['codes']) ? [] : $v['codes']
                    ];
                }
            } else {
                $tmpArr[] = [
                    'avatar' => $nowUser['avatar'],
                    'codes_amount' => $nowUser['codes_amount'],
                    'codes' => empty($nowUser['codes']) ? [] : $nowUser['codes']
                ];
            }

            $ret['Participate_avatar'] = $tmpArr;
//            } else {//当前用户参与抽奖同时参与组团
//                $ret['Participate_avatar'] = pdo_fetchall_cj("SELECT avatar,codes from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and goods_id = :id and pintuan_id != :pintuan_id ORDER BY id DESC limit 0,20", array(':uniacid' => $uniacid, ':id' => $id, ':pintuan_id' => $nowUser['pintuan_id']));
//                $tmpArr =  array();
//                if($ret['Participate_avatar']) {
//                    foreach ($ret['Participate_avatar'] as $k => $v) {
//                        if ($k == 0) {
//                            $tmpArr[]['avatar'] = $nowUser['avatar'];
//                        }
//                        $tmpArr[] = $v;
//                    }
//                }else{
//                    $tmpArr[]['avatar'] = $nowUser['avatar'];
//                }
//                $ret['Participate_avatar']=$tmpArr;
//            }
        } else {//当前用户没有参与抽奖
            $ret['Participate_avatar'] = pdo_fetchall_cj("SELECT avatar,codes,codes_amount from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and goods_id = :id ORDER BY codes_amount DESC limit 0,20", array(':uniacid' => $uniacid, ':id' => $id));
            if ($ret['Participate_avatar']) {
                foreach ($ret['Participate_avatar'] as $k => $v) {
                    $tmpArr[] = $v;
                }
            }
            $ret['Participate_avatar'] = $tmpArr;
        }
        /*if (isset($_REQUEST['ztyq_id'])) {
            if (isset($openid)) {
                $pintuan_id = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and openid = :openid and goods_id = :id ORDER BY id DESC", array(':uniacid' => $uniacid, ':openid' => $openid, ':id' => $id));
                $ret['Participate_avatar'] = pdo_fetchall_cj("SELECT avatar from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and pintuan_id = :pintuan_id and goods_id = :id ORDER BY id DESC", array(':uniacid' => $uniacid, ':pintuan_id' => $pintuan_id['pintuan_id'], ':id' => $id));
            } else {
                $ret['Participate_avatar'] = pdo_fetchall_cj("SELECT avatar from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and pintuan_id = :pintuan_id and goods_id = :id ORDER BY id DESC", array(':uniacid' => $uniacid, ':pintuan_id' => $_REQUEST['ztyq_id'], ':id' => $id));
            }
        }*/
        //ObtainRecord  // 中奖状态
        $ret['ObtainRecord'] = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and openid = :openid", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));
        //ObtainRecordUser  // 中奖人员
//        $ret['ObtainRecordUser'] = pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and status = 1 ORDER BY id DESC", array(':uniacid' => $uniacid, ':id' => $id));
        $nowUserRecor = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and status !=0 and openid = :openid", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));
        if ($nowUserRecor) {//当前用户参与抽奖
//            if ($nowUserRecor['pintuan_id'] == 0) {//当前用户参与抽奖没有组团
            $retUs = pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and status != 0 and goods_id = :id and openid != :openid ORDER BY codes_amount DESC limit 0,14", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));
            $tmpArr = array();
            if ($retUs) {
                foreach ($retUs as $k => $v) {
                    if ($k == 0) {
                        $tmpArr[] = $nowUserRecor;
                    }
                    $tmpArr[] = $v;
                }
            } else {
                $tmpArr[] = $nowUserRecor;
            }

            $retUs = $tmpArr;
//            } else {//当前用户参与抽奖同时参与组团
//                $retUs = pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and status != 0 and goods_id = :id and pintuan_id != :pintuan_id ORDER BY id DESC limit 0,14", array(':uniacid' => $uniacid, ':id' => $id, ':pintuan_id' => $nowUser['pintuan_id']));
//                $tmpArr = array();
//                if ($retUs) {
//                    foreach ($retUs as $k => $v) {
//                        if ($k == 0) {
//                            $tmpArr[] = $nowUserRecor;
//                        }
//                        $tmpArr[] = $v;
//                    }
//                } else {
//                    $tmpArr[] = $nowUserRecor;
//                }
//                $retUs = $tmpArr;
//            }
        } else {//当前用户没有参与抽奖
            $retUs = pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and status != 0 and is_group_member = 0 and goods_id = :id ORDER BY codes_amount DESC limit 0,15", array(':uniacid' => $uniacid, ':id' => $id));
        }
        $res = array();
        foreach ($retUs as $key => $val) {

//            if ($val['pintuan_id'] != 0) {
//                $tuanMenber = pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and status != 0 and pintuan_id = :pintuan_id and goods_id = :id  and id != :keyid ORDER BY id DESC", array(':uniacid' => $uniacid, ':id' => $id, ':pintuan_id' => $val['pintuan_id'], ':keyid' => $val['id']));
//                if ($tuanMenber) {
//                    foreach ($tuanMenber as $key1 => $val1) {
//                        if ($key1 == 0) {
//                            if ($val['id'] == $val['pintuan_id']) {
//                                $val['tuanZhanng'] = 1;
//                            } else {
//                                $val['tuanZhanng'] = 0;
//                            }
//                            //$res[$key][$val['openid']]=$val;
//                            $res[$key][] = $val;
//
//                        }
//                        if ($val1['id'] == $val1['pintuan_id']) {
//                            $val1['tuanZhanng'] = 1;
//                        } else {
//                            $val1['tuanZhanng'] = 0;
//                        }
//                        //$res[$key][$val1['openid']]=$val1;
//                        $res[$key][] = $val1;
//                    }
//                } else {
//                    $val['tuanZhanng'] = 1;
//                    //$res[$key][$val['openid']]=$val;
//                    $res[$key][] = $val;
//                }
//            } else {
            //$res[$key][$val['openid']]=$val;
            $res[$key][] = $val;
//            }
        }
        $ret['ObtainRecordUser'] = $res;
        $ret['ObtainRecordNum'] = count(pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and status != 0", array(':uniacid' => $uniacid, ':id' => $id)));

        //GoodsXq
        $join = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and openid = :openid", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));
        $result = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_goods') . "WHERE uniacid = :uniacid and id = :id and is_del != -1", array(':uniacid' => $uniacid, ':id' => $id));
        if (!$result) {
            return $this->result(0, 'success', "fail");
        }
        $item = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_base') . " WHERE uniacid = :uniacid", array(':uniacid' => $uniacid));
        if (!$item['type']) {
            $item['url'] = $_W['attachurl'];
        }
        $goods_icon = $this->getImgArray($result['goods_icon']);
        $result['goods_icon'] = $goods_icon;
        if ($result['smoke_set'] == 0) {
            $result['open_time'] = strtotime($result['smoke_time']);
            $time = $result['smoke_time'];
            $year = substr($time, 0, 4);
            $month = substr($time, 5, 2);
            $day = substr($time, 8, 2);
            $hour = substr($time, 11, 2);
            $min = substr($time, 14, 2);
            if (substr($month, 0, 1) == 0) {
                $month = substr($month, 1, 1);
            }
            if (substr($day, 0, 1) == 0) {
                $day = substr($day, 1, 1);
            }
            if (substr($hour, 0, 1) == 0) {
                $hour = substr($hour, 1, 1);
            }
            $result['The_time']['year'] = $year;
            $result['The_time']['month'] = $month;
            $result['The_time']['day'] = $day;
            $result['The_time']['hour'] = $hour;
            $result['The_time']['min'] = $min;
        }
        $user = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_user') . "WHERE uniacid = :uniacid and openid = :openid", array(':uniacid' => $uniacid, ':openid' => $result['goods_openid']));
        $result['avatar'] = $user['avatar'];
        $result['nickname'] = $user['nickname'];
        if (!empty($join)) {
            $result['join_status'] = 1;

//            if (isset($_REQUEST['ntuan_id'])) {
//                $result['pintuan_id'] = $_REQUEST['ntuan_id'];
//                $join_num = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_record') . ' where uniacid=:uniacid and goods_id = :goods_id and pintuan_id = :pintuan_id', array(':uniacid' => $uniacid, ':goods_id' => $id, ':pintuan_id' => $_REQUEST['ntuan_id']));
//                $result['canjiaNum'] = $join_num;
//                if (!empty($join['pintuan_id'])) {
//                    if ($join['pintuan_id'] != $_REQUEST['ntuan_id']) {
//                        $result['other_tuan'] = 1;
//                        $result['is_tuan'] = 1;
//                    } else {
//                        $result['other_tuan'] = 0;
//                        $result['is_tuan'] = 1;
//                    }
//                } else {
//                    $result['other_tuan'] = 0;
//                    $result['is_tuan'] = 0;
//                }
//                //$result['other_tuan'] = 0;
//                //$result['join_tuan'] = 1;
//            } else {
//                //$result['is_tuan'] = 0;
//                //$result['other_tuan'] = 1;
//
//            }
//            if ($join['pintuan_id']) {
//                $result['pintuan_id'] = $join['pintuan_id'];
//                $join_num = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_record') . ' where uniacid=:uniacid and goods_id = :goods_id and pintuan_id = :pintuan_id', array(':uniacid' => $uniacid, ':goods_id' => $id, ':pintuan_id' => $join['pintuan_id']));
//                $result['canjiaNum'] = $join_num;
//                if ($join['pintuan_id'] == $join['id']) {
//                    $result['pintuan_head'] = 1;
//                } else {
//                    $result['pintuan_head'] = 0;
//                }
//            } else {
//                $result['pintuan_id'] = 0;
//                $result['canjiaNum'] = 0;
//                $result['pintuan_head'] = 0;
//            }
        } else {
            $result['join_status'] = 0;
//            $result['canjiaNum'] = 0;
//            if (isset($_REQUEST['ntuan_id'])) {
//                $join_num = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_record') . ' where uniacid=:uniacid and goods_id = :goods_id and pintuan_id = :pintuan_id', array(':uniacid' => $uniacid, ':goods_id' => $id, ':pintuan_id' => $_REQUEST['ntuan_id']));
//                $result['canjiaNum'] = $join_num;
//                if ($join_num >= $result['pintuan_maxnum']) {
//                    $result['is_full'] = 1;
//                } else {
//                    $result['is_full'] = 0;
//                }
//            }
        }
        $images = unserialize($result['goods_images']);
        if ($images) {
            foreach ($images as $key => $value) {
                if ($value == '') {
                    unset($images[$key]);
                }
            }

            foreach ($images as $key => $value) {
                if (strstr($value, 'http')) {
                    $images[$key] = $value;
                } else {
                    $images[$key] = $this->getImage($value);
                }
            }
        }

        $result['goods_images'] = $images;
        if ($result['goods_status'] == 1) {
            $result['goods_name'] = '红包 ' . $result['red_envelope'] . '元/人';
        } else if ($result['goods_status'] == 2) {
            $result['card_info'] = unserialize($result['card_info']);
        }
        $ret['GoodsXq'] = $result;

        ///去除用户的openid
        $participateAvatar = $ret['Participate_avatar'];
        if (! empty($participateAvatar) ) {
            foreach ($participateAvatar as $key => $val) {
                $participateAvatar[$key]['avatar'] = $this->getImage($val['avatar']);
                if (! empty($val['codes'])) {
                    $codes = json_decode($val['codes'], true);
                    if (! empty($codes)) {
                        foreach ($codes as $k => $v) {
                            unset($codes[$k]['openid']);
                        }
                    }
                }

                $participateAvatar[$key]['codes'] = json_encode($codes);
            }
        }
        $ret['Participate_avatar'] = $participateAvatar;

        return $this->result(0, 'success', $ret);


    }

//    public function doPageYQGetInfo()
//    {
//
//        //ObtainRecordAddress  // 中奖者用户地址信息
//        $ret = array();
//        global $_GPC, $_W;
//        $id = $_REQUEST['id'];
//        $uniacid = $_W['uniacid'];
//        $openid = $_REQUEST['openid'];
//        $ret['ObtainRecordAddress'] = count(pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and status = 1 and user_name != ''", array(':uniacid' => $uniacid, ':id' => $id)));
//
//        //参与团用户列表
//        $ret['Participate_tuan_avatar'] = pdo_fetchall_cj("SELECT avatar,nickname from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and pintuan_id = :pintuan_id and goods_id = :id ORDER BY id ASC", array(':uniacid' => $uniacid, ':pintuan_id' => $_REQUEST['ntuan_id'], ':id' => $id));
//
//        // 参与抽奖的用户列表
//        /*$ret['Participate_avatar'] = pdo_fetchall_cj("SELECT avatar from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id ORDER BY id DESC", array(':uniacid' => $uniacid, ':id' => $id));
//        if (isset($_REQUEST['ztyq_id'])) {
//            if (isset($openid)) {
//                $pintuan_id = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and openid = :openid and goods_id = :id ORDER BY id DESC", array(':uniacid' => $uniacid, ':openid' => $openid, ':id' => $id));
//                $ret['Participate_avatar'] = pdo_fetchall_cj("SELECT avatar from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and pintuan_id = :pintuan_id and goods_id = :id ORDER BY id DESC", array(':uniacid' => $uniacid, ':pintuan_id' => $pintuan_id['pintuan_id'], ':id' => $id));
//            } else {
//                $ret['Participate_avatar'] = pdo_fetchall_cj("SELECT avatar from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and pintuan_id = :pintuan_id and goods_id = :id ORDER BY id DESC", array(':uniacid' => $uniacid, ':pintuan_id' => $_REQUEST['ztyq_id'], ':id' => $id));
//            }
//        }*/
//        $nowUser = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and openid = :openid ORDER BY id DESC", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));
//        if($nowUser){//当前用户参与抽奖
//            if($nowUser['pintuan_id']==0){//当前用户参与抽奖没有组团
//                $ret['Participate_avatar'] = pdo_fetchall_cj("SELECT avatar from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and goods_id = :id and openid != :openid ORDER BY id DESC limit 0,20", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));
//                $tmpArr =  array();
//                if($ret['Participate_avatar']){
//                    foreach($ret['Participate_avatar'] as $k => $v){
//                        if($k==0) {
//                            $tmpArr[]['avatar'] = $nowUser['avatar'];
//                        }
//                        $tmpArr[]=$v;
//                    }
//                }else{
//                    $tmpArr[]['avatar'] = $nowUser['avatar'];
//                }
//
//                $ret['Participate_avatar']=$tmpArr;
//            }else {//当前用户参与抽奖同时参与组团
//                $ret['Participate_avatar'] = pdo_fetchall_cj("SELECT avatar from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and goods_id = :id and pintuan_id != :pintuan_id ORDER BY id DESC limit 0,20", array(':uniacid' => $uniacid, ':id' => $id, ':pintuan_id' => $nowUser['pintuan_id']));
//                $tmpArr =  array();
//                if($ret['Participate_avatar']) {
//                    foreach ($ret['Participate_avatar'] as $k => $v) {
//                        if ($k == 0) {
//                            $tmpArr[]['avatar'] = $nowUser['avatar'];
//                        }
//                        $tmpArr[] = $v;
//                    }
//                }else{
//                    $tmpArr[]['avatar'] = $nowUser['avatar'];
//                }
//                $ret['Participate_avatar']=$tmpArr;
//            }
//        }else{//当前用户没有参与抽奖
//            $ret['Participate_avatar'] = pdo_fetchall_cj("SELECT avatar from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and goods_id = :id ORDER BY id DESC limit 0,20", array(':uniacid' => $uniacid, ':id' => $id));
//        }
//
//        //ObtainRecord  // 中奖状态
//        $ret['ObtainRecord'] = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and openid = :openid", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));
//        //ObtainRecordUser  // 中奖人员
//        //$ret['ObtainRecordUser'] = pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and status = 1 ORDER BY id DESC", array(':uniacid' => $uniacid, ':id' => $id));
//        $nowUserRecor = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and status = 1 and openid = :openid ORDER BY id DESC", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));
//        if($nowUserRecor){//当前用户参与抽奖
//            if($nowUserRecor['pintuan_id']==0){//当前用户参与抽奖没有组团
//                $retUs = pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and status = 1 and goods_id = :id and openid != :openid ORDER BY id DESC limit 0,14", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));
//                $tmpArr =  array();
//                if($retUs){
//                    foreach($retUs as $k => $v){
//                        if($k==0) {
//                            $tmpArr[] = $nowUserRecor;
//                        }
//                        $tmpArr[]=$v;
//                    }
//                }else{
//                    $tmpArr[] = $nowUserRecor;
//                }
//
//                $retUs=$tmpArr;
//            }else {//当前用户参与抽奖同时参与组团
//                $retUs = pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and status = 1 and goods_id = :id and pintuan_id != :pintuan_id ORDER BY id DESC limit 0,14", array(':uniacid' => $uniacid, ':id' => $id, ':pintuan_id' => $nowUser['pintuan_id']));
//                $tmpArr =  array();
//                if($retUs) {
//                    foreach ($retUs as $k => $v) {
//                        if ($k == 0) {
//                            $tmpArr[] = $nowUserRecor;
//                        }
//                        $tmpArr[] = $v;
//                    }
//                }else{
//                    $tmpArr[] = $nowUserRecor;
//                }
//                $retUs=$tmpArr;
//            }
//        }else{//当前用户没有参与抽奖
//            $retUs = pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and is_group_member = 0 and goods_id = :id ORDER BY id DESC limit 0,15", array(':uniacid' => $uniacid, ':id' => $id));
//        }
//        $res = array();
//        foreach($retUs as $key => $val){
//
//            if($val['pintuan_id']!=0){
//                $tuanMenber = pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and status = 1 and pintuan_id = :pintuan_id and goods_id = :id  and id != :keyid ORDER BY id DESC", array(':uniacid' => $uniacid, ':id' => $id, ':pintuan_id' => $val['pintuan_id'], ':keyid'=> $val['id']));
//                if($tuanMenber){
//                    foreach($tuanMenber as $key1 => $val1){
//                        if($key1 == 0){
//                            if($val['id']==$val['pintuan_id']){
//                                $val['tuanZhanng'] = 1;
//                            }else{
//                                $val['tuanZhanng'] = 0;
//                            }
//                            //$res[$key][$val['openid']]=$val;
//                            $res[$key][]=$val;
//
//                        }
//                        if($val1['id']==$val1['pintuan_id']) {
//                            $val1['tuanZhanng'] = 1;
//                        }else{
//                            $val1['tuanZhanng'] = 0;
//                        }
//                        //$res[$key][$val1['openid']]=$val1;
//                        $res[$key][]=$val1;
//                    }
//                }else{
//                    $val['tuanZhanng'] = 1;
//                    //$res[$key][$val['openid']]=$val;
//                    $res[$key][]=$val;
//                }
//            }else{
//                //$res[$key][$val['openid']]=$val;
//                $res[$key][]=$val;
//            }
//        }
//        $ret['ObtainRecordUser']=$res;
//        //GoodsXq
//        $join = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and goods_id = :id and openid = :openid", array(':uniacid' => $uniacid, ':id' => $id, ':openid' => $openid));
//        $result = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_goods') . "WHERE uniacid = :uniacid and id = :id and is_del != -1", array(':uniacid' => $uniacid, ':id' => $id));
//        if (!$result) {
//            return $this->result(0, 'success', "fail");
//        }
//        $item = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_base') . " WHERE uniacid = :uniacid", array(':uniacid' => $uniacid));
//        if (!$item['type']) {
//            $item['url'] = $_W['attachurl'];
//        }
//        $result['goods_icon'] = $this->getImage($result['goods_icon']);
//        if ($result['smoke_set'] == 0) {
//            $result['open_time'] = strtotime($result['smoke_time']);
//            $time = $result['smoke_time'];
//            $year = substr($time, 0, 4);
//            $month = substr($time, 5, 2);
//            $day = substr($time, 8, 2);
//            $hour = substr($time, 11, 2);
//            $min = substr($time, 14, 2);
//            if (substr($month, 0, 1) == 0) {
//                $month = substr($month, 1, 1);
//            }
//            if (substr($day, 0, 1) == 0) {
//                $day = substr($day, 1, 1);
//            }
//            if (substr($hour, 0, 1) == 0) {
//                $hour = substr($hour, 1, 1);
//            }
//            $result['The_time']['year'] = $year;
//            $result['The_time']['month'] = $month;
//            $result['The_time']['day'] = $day;
//            $result['The_time']['hour'] = $hour;
//            $result['The_time']['min'] = $min;
//        }
//        $user = pdo_fetch_cj("SELECT * from" . tablename_cj('choujiang_user') . "WHERE uniacid = :uniacid and openid = :openid", array(':uniacid' => $uniacid, ':openid' => $result['goods_openid']));
//        $result['avatar'] = $user['avatar'];
//        $result['nickname'] = $user['nickname'];
//        if (!empty($join)) {
//            $result['join_status'] = 1;
//
//            if (isset($_REQUEST['ntuan_id'])) {
//                $result['pintuan_id'] = $_REQUEST['ntuan_id'];
//                $join_num = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_record') . ' where uniacid=:uniacid and goods_id = :goods_id and pintuan_id = :pintuan_id', array(':uniacid' => $uniacid, ':goods_id' => $id, ':pintuan_id' => $_REQUEST['ntuan_id']));
//                $result['canjiaNum'] = $join_num;
//                if (!empty($join['pintuan_id'])) {
//                    if ($join['pintuan_id'] != $_REQUEST['ntuan_id']) {
//                        $result['other_tuan'] = 1;
//                        $result['is_tuan'] = 1;
//                    } else {
//                        $result['other_tuan'] = 0;
//                        $result['is_tuan'] = 1;
//                    }
//                } else {
//                    $result['other_tuan'] = 0;
//                    $result['is_tuan'] = 0;
//                }
//                //$result['other_tuan'] = 0;
//                //$result['join_tuan'] = 1;
//            } else {
//                //$result['is_tuan'] = 0;
//                //$result['other_tuan'] = 1;
//
//            }
//            if ($join['pintuan_id']) {
//                $result['pintuan_id'] = $join['pintuan_id'];
//                $join_num = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_record') . ' where uniacid=:uniacid and goods_id = :goods_id and pintuan_id = :pintuan_id', array(':uniacid' => $uniacid, ':goods_id' => $id, ':pintuan_id' => $join['pintuan_id']));
//                $result['canjiaNum'] = $join_num;
//                if ($join['pintuan_id'] == $join['id']) {
//                    $result['pintuan_head'] = 1;
//                } else {
//                    $result['pintuan_head'] = 0;
//                }
//            } else {
//                $result['pintuan_id'] = 0;
//                $result['canjiaNum'] = 0;
//                $result['pintuan_head'] = 0;
//            }
//        } else {
//            $result['join_status'] = 0;
//            $result['canjiaNum'] = 0;
//            if (isset($_REQUEST['ntuan_id'])) {
//                $join_num = pdo_fetchcolumn_cj("SELECT COUNT(*) FROM " . tablename_cj('choujiang_record') . ' where uniacid=:uniacid and goods_id = :goods_id and pintuan_id = :pintuan_id', array(':uniacid' => $uniacid, ':goods_id' => $id, ':pintuan_id' => $_REQUEST['ntuan_id']));
//                $result['canjiaNum'] = $join_num;
//                if ($join_num >= $result['pintuan_maxnum']) {
//                    $result['is_full'] = 1;
//                } else {
//                    $result['is_full'] = 0;
//                }
//            }
//        }
//        $images = unserialize($result['goods_images']);
//        if ($images) {
//            foreach ($images as $key => $value) {
//                if ($value == '') {
//                    unset($images[$key]);
//                }
//            }
//
//            foreach ($images as $key => $value) {
//                if (strstr($value, 'http')) {
//                    $images[$key] = $value;
//                } else {
//                    $images[$key] = $this->getImage($value);
//                }
//            }
//        }
//
//        $result['goods_images'] = $images;
//        if ($result['goods_status'] == 1) {
//            $result['goods_name'] = '红包 ' . $result['red_envelope'] . '元/人';
//        } else if ($result['goods_status'] == 2) {
//            $result['card_info'] = unserialize($result['card_info']);
//        }
//        $ret['GoodsXq'] = $result;
//
//        return $this->result(0, 'success', $ret);
//
//
//    }

    public function doPageMemberInfo()
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];
        $openid = $_REQUEST['openid'];
        $ret = array();
        //用户信息
        $ret['memberInfo'] = pdo_fetch_cj('SELECT yu_num,mf_num,openid,avatar,nickname FROM ' . tablename_cj('choujiang_user') . " where `uniacid`='{$uniacid}' and `openid`='{$openid}'");
        $base = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_base') . " where uniacid=:uniacid", array(":uniacid" => $_W['uniacid']));
        if ($base['share_num'] == 0) {
            $ret['memberInfo']['share_num_status'] = 2;
        } else {
            $ret['memberInfo']['share_num_status'] = 1;
        }
        $ret['memberInfo']['num'] = $ret['memberInfo']['yu_num'] + $ret['memberInfo']['mf_num'];
        //发起抽奖数量
        $ret['start'] = count(pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_goods') . "WHERE uniacid = :uniacid and goods_openid = :openid and is_del = 1", array(':uniacid' => $uniacid, ':openid' => $openid)));
        $ret['join'] = count(pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and openid = :openid and del=0", array(':uniacid' => $uniacid, ':openid' => $openid)));
        $ret['obtain'] = count(pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_record') . "WHERE uniacid = :uniacid and openid = :openid and status != 0 and del=0", array(':uniacid' => $uniacid, ':openid' => $openid)));
        $ret['sunburn'] = count(pdo_fetchall_cj("SELECT * from" . tablename_cj('choujiang_share_order') . "WHERE openid = :openid and status=0 or status=-1 ", array(':openid' => $openid)));
        return $this->result(0, 'success', $ret);
    }

    public function doPageObtainMe()
    {
        global $_W;
        $uniacid = $_W['uniacid'];
        $openid = $_REQUEST['openid'];
        $ret = array();
        //中奖情况
        $ret['ObtainMeInfo'] = pdo_fetchall_cj("select goods_id from " .tablename_cj("choujiang_record")."where `uniacid`='{$uniacid}'  and `status`=1 and `openid`='{$openid}' and `user_name` is null and `finish_time` > UNIX_TIMESTAMP(date_sub(now(),interval 1 day))");
        return $this->result(0, 'success', $ret);
    }

    // 手动生成二维码图片
    public function doWebDoInvitation($id)
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $goods_id = $id;
        $wxapp = WeAccount::create();
        $access_token = $wxapp->getAccessToken();
        $noncestr = '/choujiang_page/fuli_xq/fuli_xq?id=' . $goods_id;
        $width = 430;
        $post_data = '{"path":"' . $noncestr . '","width":' . $width . '}';
        $url = 'https://api.weixin.qq.com/wxa/getwxacode?access_token=' . $access_token;
        $result = $this->api_notice_increment($url, $post_data);
        $image_name = md5(uniqid(rand())) . ".jpg";
        $filepath = IA_ROOT."/attachment/choujiang_page/{$image_name}";
        $file_put = file_put_contents($filepath, $result);
        if ($file_put) {
            $sql = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_verification') . " where uniacid=:uniacid and goods_id = :id", array(":uniacid" => $uniacid, ':id' => $goods_id));
            if (empty($sql)) {
                $datas = array('verification' => $image_name, 'uniacid' => $uniacid, 'goods_id' => $goods_id);
                pdo_insert_cj("choujiang_verification", $datas);
            } else {
                $datas = array('verification' => $image_name);
                pdo_update_cj("choujiang_verification", $datas, array('goods_id' => $goods_id, 'uniacid' => $uniacid));
            }
        } else {
            $filepath = "attachment/choujiang_page/{$image_name}";
        }

        echo $filepath;

    }

    /********************************************统计 start***********************************************/

    /**
     * 统计
     */
    public function doPageStat()
    {
        global $_W, $_GPC;
        //每日访问人数
        if ($_GPC['stat_type'] == 'visit') {
            $this->_statVisitUser($_GPC['user_id']);
        }

        //渠道二位码 - 扫码次数
        if ($_GPC['stat_type'] == 'qr_channel_scan_amount') {
            $this->_statQrChannelScanAmount();
            $this->_statChannelUser($_GPC['user_id']);
        }
    }

    /**
     * 统计 - 新增用户数
     */
    private function _statNewUser()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $date = date("Ymd");
        $curHour = date("H");

        $redis = connect_redis();
        $userAmountKey = sprintf("cj_user_amount:%s:%s",$uniacid,$date);

        //新增用户数统计
        $userAmountTmp = $redis->hGetAll($userAmountKey);
        $userAmount = json_decode($userAmountTmp[$curHour], true);
        $userAmount['new'] = ! empty($userAmount['new']) ? $userAmount['new']+1 : 1;

        $redis->hMset($userAmountKey, [$curHour => json_encode($userAmount)]);
    }

    /**
     * 统计 - 每日访问人数
     */
    private function _statVisitUser($userId)
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $date = date("Ymd");
        $curHour = date("H");
        $redis = connect_redis();
        $userAmountKey = sprintf("cj_user_amount:%s:%s",$uniacid,$date);
        $visitKey = sprintf("cj_visit_list:%s:%s",$uniacid,$date);

        if (empty($userId)) {
            return false;
        }

        if ($redis->sIsMember($visitKey, $userId)) { ///今日是否已统计过
            return false;
        }

        $userAmountTmp = $redis->hGetAll($userAmountKey);
        $userAmount = json_decode($userAmountTmp[$curHour], true);
        $userAmount['visit'] = ! empty($userAmount['visit']) ? $userAmount['visit']+1 : 1;

        $redis->hMset($userAmountKey, [$curHour => json_encode($userAmount)]);
        $redis->sAdd($visitKey, $userId);
    }

    /**
     * 统计用户分享
     * 1.上下级关系
     * 2.用户引流人数
     */
    private function _statUserShare($params=[])
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        pdo_insert_cj("choujiang_user_share", [
            'user_id' => $params['user_id'],
            'share_user_id' => $_GPC['share_user_id'],
            'create_at' => date('Y-m-d H:i:s')
        ]);
        ///统计 用户引流数据
        $today = date('Y-m-d');
        $stat = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_stat_user_share') . " where `user_id`='{$_GPC['share_user_id']}' and create_at='{$today}'");
        if ($stat) {
            pdo_update_cj("choujiang_stat_user_share", ['amount +=' => 1], [
                'user_id' => $_GPC['share_user_id'],
                'create_at' => $today
            ]);
        } else {
            pdo_insert_cj("choujiang_stat_user_share", [
                'user_id' => $_GPC['share_user_id'],
                'create_at' => $today,
                'amount' => 1
            ]);
        }
    }

    /**
     * 统计 - 渠道二位码 - 扫码次数
     */
    private function _statQrChannelScanAmount()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $date = date("Ymd");
        $curHour = date("H");

        $redis = connect_redis();
        $qrChannelAmountKey = sprintf("cj_qr_channel_amount:%s:%s",$uniacid,$date);

        $qrChannelAmountTmp = $redis->hGetAll($qrChannelAmountKey);
        $qrChannelAmount = json_decode($qrChannelAmountTmp[$_GPC['channel']], true);
        $qrChannelAmount[$curHour]['scan'] = ! empty($qrChannelAmount[$curHour]['scan']) ? $qrChannelAmount[$curHour]['scan']+1 : 1;

        $redis->hMset($qrChannelAmountKey, [$_GPC['channel'] => json_encode($qrChannelAmount)] );
    }

    /**
     * 统计 - 渠道二位码 - 扫码新增人数
     */
    private function _statChannelNew()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $date = date("Ymd");
        $curHour = date("H");

        $redis = connect_redis();
        $qrChannelAmountKey = sprintf("cj_qr_channel_amount:%s:%s",$uniacid,$date);

        $qrChannelAmountTmp = $redis->hGetAll($qrChannelAmountKey);
        $qrChannelAmount = json_decode($qrChannelAmountTmp[$_GPC['share_channel']], true);
        $qrChannelAmount[$curHour]['new'] = ! empty($qrChannelAmount[$curHour]['new']) ? $qrChannelAmount[$curHour]['new']+1 : 1;

        $redis->hMset($qrChannelAmountKey, [$_GPC['share_channel'] => json_encode($qrChannelAmount)] );
    }

    /**
     * 统计 - 渠道二位码 - 扫码人数
     */
    private function _statChannelUser($userId)
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $date = date("Ymd");
        $curHour = date("H");
        $redis = connect_redis();
        $qrChannelAmountKey = sprintf("cj_qr_channel_amount:%s:%s",$uniacid,$date);
        $visitKey = sprintf("cj_qr_channel_visit_list:%s:%s:%s",$uniacid,$_GPC['channel'],$date);

        if (empty($userId)) {
            return false;
        }

        if ($redis->sIsMember($visitKey, $userId)) { ///今日是否已统计过
            return false;
        }

        $qrChannelAmountTmp = $redis->hGetAll($qrChannelAmountKey);
        $qrChannelAmount = json_decode($qrChannelAmountTmp[$_GPC['channel']], true);
        $qrChannelAmount[$curHour]['visit'] = ! empty($qrChannelAmount[$curHour]['visit']) ? $qrChannelAmount[$curHour]['visit']+1 : 1;

        $redis->hMset($qrChannelAmountKey, [$_GPC['channel'] => json_encode($qrChannelAmount)] );
        $redis->sAdd($visitKey, $userId);
    }
    /********************************************统计 end***********************************************/
}

