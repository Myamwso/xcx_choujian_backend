<?php

class Cj_wishingWall extends Common
{
    private $max_formid = 5;
    public function __construct()
    {
        $this->model = new wishingWallModel();
        $this->score = new Score();
        $this->redis = connect_redis();
        parent::__construct();
    }

    /*
     * 用户添加心愿接口
     */
    public function addWishing()
    {
        global $_GPC, $_W;
        $uniacid = $_W['uniacid'];
        $openid = trim($_GPC['openId']);

        /// 判断本周是否已经达到发布上限
        $wishingReleaseKey = sprintf($_W['redis_key']['wishing_release'], $uniacid ,$openid);
        $timeStamp = time();

        /// 本周结束时间
        $weekEnd = mktime(23,59,59,date("m"),date("d")-date("N")+7,date("Y"));

        $redisReleaseInfo = $this->surplusReleaseTimes($wishingReleaseKey, $timeStamp);
        if ($redisReleaseInfo['surplusTimes'] == 0) {
            return $this->result(0, 'success', ['error'=>1,'message'=>'您已经达到本周发布心愿上限']);
        }

        $redisData = $redisReleaseInfo['redisInfo'];


        ///获取心愿参数
        $goodsName = trim($_GPC['goodsName']);
        $goodsPrice = trim($_GPC['goodsPrice']);
        $goodsUrl = trim($_GPC['goodsUrl']);
        $goodsImg = trim($_GPC['goodsImg']);
        $goodsInfo = trim($_GPC['goodsInfo']);
        $formid = trim($_GPC['formId']);
        $writeTime = date("Y-m-d H:i:s");

        ///设置redis阻止重复提交
        $wishingLockKey = sprintf("cj_user_wishing_lock:%s:%s",$uniacid,$openid);
        if($this->redis->exists($wishingLockKey)){
            return $this->result(0, 'success', ['error'=>1,'message'=>'心愿无需重复提交']);
    }
        $this->redis->set($wishingLockKey, 1);
        /// 预防死锁
        $this->redis->expire($wishingLockKey, 300);

        ///验证必填参数
        if(empty($goodsName)){
            $this->redis->del($wishingLockKey);
            return $this->result(0, 'success', ['error'=>1,'message'=>'商品名称不能为空']);
        }

        $message = '商品价格不能为空';
        if(empty($goodsPrice)){
            $this->redis->del($wishingLockKey);
            return $this->result(0, 'success', ['error'=>1,'message'=>$message]);
        }
        if ($goodsPrice<$this->baseConfig['wishing_min'] || $goodsPrice>$this->baseConfig['wishing_max']) {
            $message = '商品价格不符合规范';
            $this->redis->del($wishingLockKey);
            return $this->result(0, 'success', ['error'=>1,'message'=>$message]);
        }

        if(empty($goodsUrl)){
            $this->redis->del($wishingLockKey);
            return $this->result(0, 'success', ['error'=>1,'message'=>'商品链接不能为空']);
        }

        if(empty($goodsImg)){
            $this->redis->del($wishingLockKey);
            return $this->result(0, 'success', ['error'=>1,'message'=>'商品图片不能为空']);
        }

        /// 判断openid是否为真实用户
        $userInfo = pdo_get_cj("choujiang_user", ['openid' => $openid]);
        if (empty($userInfo)) {
            $this->redis->del($wishingLockKey);
            return $this->result(0, 'success', ['error'=>1,'message'=>'请授权后再发布心愿']);
        }

        $data = [
            'openid' => $openid,
            'goods_name' => $goodsName,
            'goods_price' => $goodsPrice,
            'goods_url' => $goodsUrl,
            'goods_img' => $this->getImgPath($goodsImg),
            'goods_info' => $goodsInfo,
            'likes_num' => 0,
            'formid' => json_encode([
                ['dataTime' => $writeTime,
                'formid' => $formid,]
            ]),
            'accomplish_wishing' => ceil($goodsPrice*$this->baseConfig['wishing_ratio']),
            'release_goods' => 0,
            'create_at' => $writeTime,
            'update_at' => $writeTime,
        ];

//        var_dump($data);exit;

        ///写入心愿表
        $wishingInsert = $this->model->wishingInsert($data);
        $wishingId = pdo_insertid_cj();


        ///判断是否写入成功返回数据
        if($wishingInsert){
            /// 给心愿发布者发放积分
            $this->score->addScore($openid, $this->baseConfig['score']['release_wishing'], ["getInfo"=>"发布【{$goodsName}】，id为{$wishingId}"]);

            /// 发布心愿成功，设置redis
            $this->redis->hMset($wishingReleaseKey, $redisData);
            $this->redis->expire($wishingReleaseKey, $weekEnd-$timeStamp+3600);

            /// 心愿发布成，删除所
            $this->redis->del($wishingLockKey);
            return $this->result(0, 'success', ['error'=>0,'message'=>'添加心愿成功', 'wishing_id' => $wishingId]);
        }else{
            $this->redis->del($wishingLockKey);
            return $this->result(0, 'success', ['error'=>1,'message'=>'添加心愿失败']);
        }
    }

    /*
     * 心愿编辑接口
     */
    public function editWishing(){
        global $_GPC,$_W;

        ///获取心愿参数
        $uniacid = $_W['uniacid'];
        $wishingId = trim($_GPC['wishingId']);
        $openid = trim($_GPC['openId']);
        $goodsName = trim($_GPC['goodsName']);
        $goodsPrice = trim($_GPC['goodsPrice']);
        $goodsUrl = trim($_GPC['goodsUrl']);
        $goodsImg = trim($_GPC['goodsImg']);
        $goodsInfo = trim($_GPC['goodsInfo']);
        $formid = trim($_GPC['formId']);
        $writeTime = date("Y-m-d H:i:s");

        ///设置redis阻止重复提交
        $wishingLockKey = sprintf("cj_user_wishing_edit_lock:%s:%s",$uniacid,$openid);
        $redis = connect_redis();
        if($redis->exists($wishingLockKey)){
            return $this->result(0, 'success', ['error'=>1,'message'=>'心愿编辑重复提交']);
        }
        $redis->set($wishingLockKey, 1);
        $redis->expire($wishingLockKey, 300);

        ///验证必填参数
        if(empty($wishingId)){
            $redis->del($wishingLockKey);
            return $this->result(0, 'success', ['error'=>1,'message'=>'心愿id不能为空']);
        }

        if(empty($goodsName)){
            $redis->del($wishingLockKey);
            return $this->result(0, 'success', ['error'=>1,'message'=>'商品名称不能为空']);
        }

        $message = '商品价格不能为空';
        if(empty($goodsPrice)){
            $redis->del($wishingLockKey);
            return $this->result(0, 'success', ['error'=>1,'message'=>$message]);
        }
        if ($goodsPrice<$this->baseConfig['wishing_min'] || $goodsPrice>$this->baseConfig['wishing_max']) {
            $message = '商品价格不符合规范';
            $redis->del($wishingLockKey);
            return $this->result(0, 'success', ['error'=>1,'message'=>$message]);
        }

        if(empty($goodsUrl)){
            $redis->del($wishingLockKey);
            return $this->result(0, 'success', ['error'=>1,'message'=>'商品链接不能为空']);
        }

        if(empty($goodsImg)){
            $redis->del($wishingLockKey);
            return $this->result(0, 'success', ['error'=>1,'message'=>'商品图片不能为空']);
        }

        ///获取心愿详情
        $wishingInfo = $this->model->getWishingInfo(['id' => $wishingId]);

        if($wishingInfo) {

            /// 已审核心愿不能编辑
            if ( $wishingInfo['status'] == 1 ) {
                $redis->del($wishingLockKey);
                return $this->result(0, 'success', ['error'=>1,'message'=>'已审核心愿不能编辑']);
            }

            ///判断心愿是否有用户参与，已有用户参与的心愿不能比较
            if ( $wishingInfo['likes_num'] > 0 ) {
                $redis->del($wishingLockKey);
                return $this->result(0, 'success', ['error'=>1,'message'=>'已有用户参与不能编辑']);
            }

            ///判断当前用户是否为心愿发布者
            if( $wishingInfo['openid'] != $openid ) {
                $redis->del($wishingLockKey);
                return $this->result(0, 'success', ['error'=>1,'message'=>'您没有操作权限']);
            }

            /// 判断是否可编辑
            if( $wishingInfo['status'] == 3 ) {
                $redis->del($wishingLockKey);
                return $this->result(0, 'success', ['error'=>1,'message'=>'您已经达到编辑次数上限']);
            }

            ///已经发布商品的心愿不能编辑
            if( $wishingInfo['release_goods'] != 0 ) {
                $redis->del($wishingLockKey);
                return $this->result(0, 'success', ['error'=>1,'message'=>'已经发布商品的心愿不能编辑']);
            }
        } else {

            ///心愿查询不存在直接返回结果
            $redis->del($wishingLockKey);
            return $this->result(0, 'success', ['error'=>1,'message'=>'非法请求']);
        }

        $data = [
            'goods_name' => $goodsName,
            'goods_price' => $goodsPrice,
            'goods_url' => $goodsUrl,
            'goods_img' => $this->getImgPath($goodsImg),
            'goods_info' => $goodsInfo,
            'accomplish_wishing' => ceil($goodsPrice*$this->baseConfig['wishing_ratio']),
            'status' => 0,
            'update_at' => $writeTime,
        ];

        $formIds = json_decode($wishingInfo['formid'], true);
        if (!is_array($formIds)) {
            $formIds = [];
        }
        $tempArr = [];
        /// 循环判断formid是否过期
        foreach ($formIds as $key => $val) {
            if (strtotime($val['dataTime']) >= time()-(3600*24*7-6*3600)) {
                $tempArr[]= $val;
            }
        }

        if (count($tempArr)<$this->max_formid) {
            /// formid未达到上限，写入新的formid
            $tempArr[]= [
                'dataTime' => date('Y-m-d H:i:s'),
                'formid' => $formid,
            ];
            $data['formid'] = json_encode($tempArr);
        }

        ///写入心愿表
        $wishingUpdate = $this->model->updataWishing($data, ['id' => $wishingId]);

        ///判断是否写入成功返回数据
        if($wishingUpdate){
            $redis->del($wishingLockKey);
            return $this->result(0, 'success', ['error'=>0,'message'=>'编辑心愿成功']);
        }else{
            $redis->del($wishingLockKey);
            return $this->result(0, 'success', ['error'=>1,'message'=>'编辑心愿失败']);
        }
    }

    /*
     * 心愿墙心愿单列表接口
     */
    public function wishingList(){
        global $_GPC;
        $psize = 15;
        $pindex = $_GPC['page'] ? trim($_GPC['page']) : 1;
        $openid = trim($_GPC['openId']);

        $weekStart = date("Y-m-d H:i:s",mktime(0, 0 , 0,date("m"),date("d")-date("N")+1,date("Y")));
        $weekEnd = date("Y-m-d H:i:s",mktime(23,59,59,date("m"),date("d")-date("N")+7,date("Y")));

        ///获取当前页心愿数据
        $myWishingList = pdo_fetchall_cj("SELECT * FROM " . tablename_cj('choujiang_wishing') . " WHERE openid = '{$openid}' AND status = 1 AND release_goods = 0 AND create_at>='".$weekStart."' AND create_at<='".$weekEnd."' ORDER BY likes_num DESC, id ASC");

        $where = [
            'release_goods' => 0,
            'status' => 1,
//            'openid !=' => $openid,
            'create_at >=' => $weekStart,
            'create_at <=' => $weekEnd,
        ];
        $wishingList = $this->model->getWishingList($where, [], '', ["likes_num DESC", "id ASC"],[$pindex, $psize]);
//        ], [], '', ["openid<>'{$openid}' ASC","likes_num DESC"],[$pindex, $psize]);

        /// 循环获取是否参与点赞
        $wishingList['list'] = $this->wishingInfoCheck($openid, $wishingList['list'], "this", 1);


        $myWishingList = $this->wishingInfoCheck($openid, $myWishingList, "this", 1, 1);
        /// 我的心愿排名获取
        foreach ($myWishingList as $key => $val) {
            $sql = "SELECT * FROM (SELECT A.id,(@rowno:=@rowno+1) AS rowno FROM " . tablename_cj("choujiang_wishing") . " AS A,(select (@rowno:=0)) AS B WHERE A.create_at >= '{$weekStart}' AND A.create_at <= '{$weekEnd}' AND A.status = '{$where['status']}' AND A.release_goods = '{$where['release_goods']}' ORDER BY A.`likes_num` desc, A.`id` asc) AS C WHERE C.id='{$val['id']}'";
            $wishingRowInfo = pdo_fetch_cj($sql);
            $myWishingList[$key]['goods_img'] = $this->getImage($val['goods_img']);
            $myWishingList[$key]['rowNo'] = $wishingRowInfo['rowno'];
            $myWishingList[$key]['isShow'] = $wishingRowInfo['rowno']>$psize ? 1 : 0 ;
        }

        if (!empty($openid)) {
            $allSurplusTimes = $this->allSurplusTimes($openid);
        } else {
            $allSurplusTimes = [
                'releaseTimes' => 0,
                'likesTimes' => 0,
            ];
        }

        /// 查询当前用户信息
        $onlineUserInfo = pdo_get_cj('choujiang_user', ['openid' => $openid]);

        $return = [
            'myList'=>$myWishingList,
            'list'=>$wishingList['list'],
            'total'=>(int)$wishingList['total'],
            'pageSize'=>(int)$psize,
            'releaseTimes'=>(int)$allSurplusTimes['releaseTimes'],
            'likesTimes'=>(int)$allSurplusTimes['likesTimes'],
            "nickname" => $onlineUserInfo['nickname'],
            'wishingMaxPrice'=>(int)$this->baseConfig['wishing_max'],
            'wishingMinPrice'=>(int)$this->baseConfig['wishing_min'],
//            'wishingRule' => [
//                'wishingReleaseWeek' => $this->baseConfig['wishing_week_release'],
//                'wishingDailyJoin' => $this->baseConfig['wishing_daily_join'],
//            ],
//            'scroeRule' => $this->baseConfig['score'],
        ];

        return $this->result(0, 'success', $return);
    }

    /*
     * 心愿详情接口
     */
    public function wishingDetails($id = 0){
        global $_GPC;
        $wishingId = trim($_GPC['wishingId']);
        $openid = trim($_GPC['openId']);

        if ($id != 0) {
            $wishingId = $id;
        }

        if(empty($wishingId)){
            return $this->result(0, 'success', ['error'=>1,'message'=>'心愿id不能为空']);
        }

//        $wishingInfo = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_wishing') . " WHERE id = :id", [':id' => $wishingId]);
        $wishingInfo = $this->model->getWishingInfo(['id' => $wishingId, 'is_del' => 0]);

        if ( empty($wishingInfo) ) {
            /// 心愿不存在直接返回
            return $this->result(0, 'success', [ 'error'=>1,'message'=>"心愿不存在"]);
        }

        $wishingInfo = $this->wishingInfoCheck($openid, $wishingInfo);

//        $userInfo = pdo_get_cj("choujiang_user", ['openid'=>$wishingInfo['openid']]);
//        $wishingInfo['nickname'] = $userInfo['nickname'];

//        $lastWeekStart = mktime(0, 0 , 0,date("m"),date("d")-date("N")+1-7,date("Y"));
//        $lastWeekEnd = mktime(23,59,59,date("m"),date("d")-date("N")+7-7,date("Y"));
//
//        if ($lastWeekStart>strtotime($wishingInfo['create_at'])) {
//            /// 心愿过期直接返回
//            return $this->result(0, 'success', [ 'error'=>1,'message'=>"心愿已经过期"]);
//        }

        if ( $wishingInfo['weekInfo'] == "other" ) {
            /// 心愿过期直接返回
            return $this->result(0, 'success', [ 'error'=>1,'message'=>"心愿已经过期"]);
        }

        if (!empty($openid)) {
            $allSurplusTimes = $this->allSurplusTimes($openid);
//            $wishingRecordInfo = $this->model->getWishingRecordInfo(['wishing_id' => $wishingId, 'openid' => $openid]);
//            $wishingInfo['likes_id'] = empty($wishingRecordInfo) ? 0 : $wishingRecordInfo['id'] ;
        } else {
//            $wishingInfo['likes_id'] = 0;
            $allSurplusTimes = [
                'releaseTimes' => 0,
                'likesTimes' => 0,
            ];
        }

        $return = [
            'error'=>0,
            'details'=>$wishingInfo,
            'releaseTimes'=>(int)$allSurplusTimes['releaseTimes'],
            'likesTimes'=>(int)$allSurplusTimes['likesTimes'],
        ];

//        if ($lastWeekStart<=strtotime($wishingInfo['create_at'])&&$lastWeekEnd>=strtotime($wishingInfo['create_at'])) {
//            $return['weekInfo'] = "last";
//        }

        if ($id != 0) {
            return $return;
        } else {
            return $this->result(0, 'success', $return);
        }

    }

    /*
     * 心愿用户想要接口
     */
    public function addLikes(){
        global $_GPC, $_W;
        $openid = trim($_GPC['openId']);
        $uniacid = $_W['uniacid'];

        /// 判断今天是否已经达到想要上限
        $wishingLikesKey = sprintf($_W['redis_key']['wishing_likes'], $uniacid ,$openid);
        $timeStamp = time();
//        $redisData = ['timeStamp' => $timeStamp, 'likesTimes' => 0];
//        /// 今天开始时间
//        $dayStart = strtotime(date("Y-m-d"));
        /// 今天结束时间
        $dayEnd = strtotime(date("Y-m-d"). " 23:59:59");
//        if($this->redis->exists($wishingLikesKey)){
//            $wishingLikesInfo = $this->redis->hGetAll($wishingLikesKey);
//            if ($wishingLikesInfo['timeStamp']>=$dayStart && $wishingLikesInfo['timeStamp']<=$dayEnd) {
//                if ($wishingLikesInfo['likesTimes'] >= $this->baseConfig['wishing_daily_join']) {
//                    return $this->result(0, 'success', ['error'=>1,'message'=>'您已经达到每日想要上限']);
//                } else {
//                    $redisData ['likesTimes'] = $wishingLikesInfo['likesTimes'] + 1;
//                }
//            } else {
//                $redisData ['likesTimes'] = 1;
//            }
//        } else {
//            $redisData ['likesTimes'] = 1;
//        }

        $redisLikesInfo = $this->surplusLikesTimes($wishingLikesKey, $timeStamp);
        if ($redisLikesInfo['surplusTimes'] == 0) {
            return $this->result(0, 'success', ['error'=>1,'message'=>'您已经达到每日想要上限']);
        }

        $redisData = $redisLikesInfo['redisInfo'];


        $wishingId = trim($_GPC['wishingId']);
        $formid = trim($_GPC['formId']);
        $writeTime = date("Y-m-d H:i:s");


        ///设置redis阻止重复提交
        $wishingLikesLockKey = sprintf("cj_user_wishing_likes_lock:%s:%s:%s", $uniacid, $wishingId, $openid);
        if($this->redis->exists($wishingLikesLockKey)){
            return $this->result(0, 'success', ['error'=>1,'message'=>'重复点击想要']);
        }
        $this->redis->set($wishingLikesLockKey, 1);
        /// 预防死锁
        $this->redis->expire($wishingLikesLockKey, 300);



        if(empty($wishingId)){
            $this->redis->del($wishingLikesLockKey);
            return $this->result(0, 'success', ['error'=>1,'message'=>'心愿id不能为空']);
        }

        ///是否参与过点赞
//        $isJion = pdo_fetch_cj("SELECT id FROM " . tablename_cj('choujiang_wishing_record') . " WHERE wishing_id = :wishing_id AND openid = :openid", [':wishing_id' => $wishingId, ':openid' => $openid]);
        $isJion = $this->model->getWishingRecordInfo(['wishing_id' => $wishingId, 'openid' => $openid]);

        if($isJion){
            $this->redis->del($wishingLikesLockKey);
            return $this->result(0, 'success', ['error'=>1,'message'=>'已经点击过想要，不能重复点击']);
        }

        ///心愿信息
//        $sql = "SELECT * FROM " . tablename_cj('choujiang_wishing') . " WHERE id = :id";
//        $wishingInfo = pdo_fetch_cj($sql, ['id' => $wishingId]);
        $wishingInfo = $this->model->getWishingInfo(['id' => $wishingId]);

        if ($wishingInfo) {
            /// 本周之前的心愿不能参与想要
            $thisWeekStart = mktime(0, 0 , 0,date("m"),date("d")-date("N")+1,date("Y"));
            if ( $thisWeekStart>strtotime($wishingInfo['create_at']) ) {
                $this->redis->del($wishingLikesLockKey);
                return $this->result(0, 'success', ['error'=>1,'message'=>'心愿已经过去不能参与想要']);
            }

            ///心愿发起人不能点击想要
            if ( $wishingInfo['openid'] == $openid ) {
                $this->redis->del($wishingLikesLockKey);
                return $this->result(0, 'success', ['error'=>1,'message'=>'心愿发起人不能点击想要']);
            }

            ///已经发布商品的心愿不能点击想要
            if ( $wishingInfo['release_goods'] != 0 ) {
                $this->redis->del($wishingLikesLockKey);
                return $this->result(0, 'success', ['error'=>1,'message'=>'已经发布商品的心愿不能点击']);
            }
        } else {
            $this->redis->del($wishingLikesLockKey);
            return $this->result(0, 'success', ['error'=>1,'message'=>'非法请求']);
        }

        $data = [
            'openid' => $openid,
            'wishing_id' => $wishingId,
            'formid' => json_encode([
                ['dataTime' => $writeTime,
                'formid' => $formid,]
            ]),
            'share_id' => 0,
            'create_at' => $writeTime,
            'update_at' => $writeTime,
        ];

        /// 通过分享参与想要
        if ($_GPC['cj_share_c'] == -1 && $_GPC['cj_share_u'] > 0 && $_GPC['cj_share_id'] == $wishingId && $_GPC['cj_share_type']=='1') {
            $data['share_id'] = $_GPC['cj_share_u'];
        }

        ///写入心愿点赞表
//        $recordInsert = pdo_insert_cj("choujiang_wishing_record", $data);
        $recordInsert = $this->model->wishingRecordInsert($data);
        $recordId = pdo_insertid_cj();

        ///更新心愿点赞人数
//        $wishingUpdate = pdo_update_cj("choujiang_wishing", ['likes_num +=' => 1, 'update_at' => $writeTime], ['id' => $wishingId]);
        $wishingUpdate = $this->model->updataWishing(['likes_num +=' => 1, 'update_at' => $writeTime], ['id' => $wishingId]);

        if ($recordInsert && $wishingUpdate) {
            /// 想要成功，给用户发放积分
            $this->score->addScore($openid, $this->baseConfig['score']['i_likes'], ["getInfo"=>"参与想要【{$wishingInfo['goods_name']}】的心愿，id为{$wishingInfo['id']}"]);

            /// 通过分享参与想要,给分享者积分
            if ($_GPC['cj_share_c'] == -1 && $_GPC['cj_share_u'] > 0 && $_GPC['cj_share_id'] == $wishingId && $_GPC['cj_share_type']=='1') {
                /// 分享用户信息
                $shareUserInfo = pdo_get_cj('choujiang_user', ['id' => $_GPC['cj_share_u']]);
                $this->score->addScore($shareUserInfo['openid'], $this->baseConfig['score']['share_likes'], ["getInfo"=>"分享用户参与想要【{$wishingInfo['goods_name']}】的心愿，心愿id为{$wishingInfo['id']}，想要id为{$recordId}"]);
            }

            /// 想要成功，更新想要次数缓存
            $this->redis->hMset($wishingLikesKey, $redisData);
            $this->redis->expire($wishingLikesKey, $dayEnd-$timeStamp+600);

            /// 想要操作完成，删除所
            $this->redis->del($wishingLikesLockKey);
            $newWishingInfo = $this->wishingDetails($wishingId);
            $newWishingInfo = $this->wishingInfoCheck($openid, $newWishingInfo, "this");
            return $this->result(0, 'success', ['error'=>0,'message'=>'想要成功', 'likes_id'=>$recordId, "newWishing"=>$newWishingInfo]);
        } else {
            $this->redis->del($wishingLikesLockKey);
            return $this->result(0, 'success', ['error'=>1,'message'=>'想要失败']);
        }
    }


    /*
     * 心愿墙想要列表接口
     */
    public function likesList(){
        global $_GPC;
        $openid = trim($_GPC['openId']);
        $wishingId = trim($_GPC['wishingId']);
        $pindex = $_GPC['page'] ? trim($_GPC['page']) : 1;
        $psize = 15;
//        $offest = ($pindex-1)*$psize;

//        $total = pdo_fetch_cj("SELECT COUNT(*) AS count FROM " . tablename_cj('choujiang_wishing_record') . " WHERE wishing_id = :id", [':id' => $wishingId]);
//        $wishingList = pdo_fetchall_cj("SELECT * FROM " . tablename_cj('choujiang_wishing_record') . " WHERE wishing_id = :id ORDER BY openid<>'{$openid}', id DESC LIMIT {$offest},{$psize}", [':id' => $wishingId]);
        $wishingList = $this->model->getWishingRecordList([ 'wishing_id' => $wishingId], [], '', ["openid<>'{$openid}' ASC","id DESC"],[$pindex, $psize]);

        $return = [
            'total' => (int)$wishingList['total'],
            'list' => $wishingList['list'],
        ];

        return $this->result(0, 'success', $return);
    }

    /*
     * 心愿墙获取formid接口
     */
    public function wishingMessage(){
        global $_GPC;
        $openid = trim($_GPC['openId']);
        $wishingId = trim($_GPC['wishingId']);
        $formId = trim($_GPC['formId']);
        $wishingInfo = $this->model->getWishingInfo(['id' => $wishingId]);
        if ( !empty($wishingInfo) ) {
            if ($wishingInfo['openid'] == $openid) {
                $this->getMoreFormId("choujiang_wishing", $openid, $wishingId, $formId);
            } else {
                $this->getMoreFormId("choujiang_wishing_record", $openid, $wishingId, $formId);
            }
        }

        return true;
    }

    /*
     * 心愿墙，搜集formId，用于给用户推送消息
     * $who 搜集formId的表名
     * $id wishingId
     * $openid
     * $formId
     */
    private function getMoreFormId( $who, $openid, $id, $formId ){
        if ( $who == 'choujiang_wishing' ) {
            $Info = $this->model->getWishingInfo(['id' => $id]);
        } else {
            $Info = $this->model->getWishingRecordInfo(['wishing_id' => $id, 'openid' => $openid]);
        }

        /// 没有相应的数据直接退出
        if ( !$Info ) {
            return false;
        }

        $formIds = json_decode($Info['formid'], true);
        if (!is_array($formIds)) {
            $formIds = [];
        }

        $tempArr = [];
        foreach ($formIds as $key => $val) {
            if (strtotime($val['dataTime']) >= time()-(3600*24*7-6*3600)) {
                $tempArr[]= $val;
            }
        }
        if (count($tempArr)<$this->max_formid) {
            $tempArr[]= [
                'dataTime' => date('Y-m-d H:i:s'),
                'formid' => $formId,
            ];
            $data['formid'] = json_encode($tempArr);
            $Update = pdo_update_cj($who, $data, ['id' => $Info['id']]);

            if ($Update) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /*
     * 心愿墙上新榜列表接口
     */
    public function newWishingList(){
        global $_GPC;
        $psize = 15;
        $pindex = $_GPC['page'] ? trim($_GPC['page']) : 1;
        $openid = trim($_GPC['openId']);

        ///获取当前页心愿数据
        $myWishingList = pdo_fetchall_cj("SELECT * FROM " . tablename_cj('choujiang_wishing') . " WHERE openid = '{$openid}' AND release_goods = 0 AND create_at>='".date("Y-m-d H:i:s",mktime(0, 0 , 0,date("m"),date("d")-date("N")+1,date("Y")))."' AND create_at<='".date("Y-m-d H:i:s",mktime(23,59,59,date("m"),date("d")-date("N")+7,date("Y")))."' ORDER BY likes_num DESC, id ASC");
        $weekStart = date("Y-m-d H:i:s",mktime(0, 0 , 0,date("m"),date("d")-date("N")+1,date("Y")));
        $weekEnd = date("Y-m-d H:i:s",mktime(23,59,59,date("m"),date("d")-date("N")+7,date("Y")));
        $where = [
            'release_goods' => 0,
            'status' => 1,
//            'openid !=' => $openid,
            'create_at >=' => $weekStart,
            'create_at <=' => $weekEnd,
        ];
        $wishingList = $this->model->getWishingList($where, [], '', ["id DESC"],[$pindex, $psize]);
//        ], [], '', ["openid<>'{$openid}' ASC","likes_num DESC"],[$pindex, $psize]);

        ///循环获取是否参与点赞
//        foreach ($wishingList['list'] as $key => $val) {
////            $wishingJion = pdo_fetch_cj("SELECT id FROM " . tablename_cj('choujiang_wishing_record') . " WHERE wishing_id = :wishing_id AND openid = :openid", [':wishing_id' => $val['id'], ':openid' => $openid]);
//            $wishingJion = $this->model->getWishingRecordInfo(['wishing_id' => $val['id'], 'openid' => $openid]);
//            $userInfo = pdo_get_cj('choujiang_user', ['openid' => $val['openid']]);
//            $wishingList['list'][$key]['nickname'] = $userInfo['nickname'];
//            if ($wishingJion) {
//                $wishingList['list'][$key]['likes_id'] = $wishingJion['id'];
//            } else {
//                $wishingList['list'][$key]['likes_id'] = 0;
//            }
//        }
        $wishingList['list'] = $this->wishingInfoCheck($openid, $wishingList['list'], "this", 1);


        $myWishingList = $this->wishingInfoCheck($openid, $myWishingList, "this", 1, 1);
        foreach ($myWishingList as $key => $val) {
            $sql = "SELECT * FROM (SELECT A.id,(@rowno:=@rowno+1) AS rowno FROM " . tablename_cj("choujiang_wishing") . " AS A,(select (@rowno:=0)) AS B WHERE A.create_at >= '{$weekStart}' AND A.create_at <= '{$weekEnd}' AND A.status = '{$where['status']}' AND A.release_goods = '{$where['release_goods']}' ORDER BY A.`likes_num` desc, A.`id` asc) AS C WHERE C.id='{$val['id']}'";
            $wishingRowInfo = pdo_fetch_cj($sql);
//            $userInfo = pdo_get_cj('choujiang_user', ['openid' => $val['openid']]);
//            $myWishingList[$key]['nickname'] = $userInfo['nickname'];
            $myWishingList[$key]['rowNo'] = $wishingRowInfo['rowno'];
            $myWishingList[$key]['isShow'] = $wishingRowInfo['rowno']>$psize ? 1 : 0 ;
        }


        if (!empty($openid)) {
            $allSurplusTimes = $this->allSurplusTimes($openid);
        } else {
            $allSurplusTimes = [
                'releaseTimes' => 0,
                'likesTimes' => 0,
            ];
        }

        $return = [
            'myList'=>$myWishingList,
            'list'=>$wishingList['list'],
            'total'=>(int)$wishingList['total'],
            'pageSize'=>(int)$psize,
            'releaseTimes'=>(int)$allSurplusTimes['releaseTimes'],
            'likesTimes'=>(int)$allSurplusTimes['likesTimes'],
        ];

        return $this->result(0, 'success', $return);
    }

    /*
     * 我的心愿本周列表接口
     */
    public function myWishingList(){
        global $_GPC;
        $psize = 15;
        $pindex = $_GPC['page'] ? trim($_GPC['page']) : 1;
        $openid = trim($_GPC['openId']);

        ///获取当前页心愿数据
        $myReleaseWishing = pdo_fetchall_cj("SELECT * FROM " . tablename_cj('choujiang_wishing') . " WHERE openid = '{$openid}' AND create_at>='".date("Y-m-d H:i:s",mktime(0, 0 , 0,date("m"),date("d")-date("N")+1,date("Y")))."' AND create_at<='".date("Y-m-d H:i:s",mktime(23,59,59,date("m"),date("d")-date("N")+7,date("Y"))) . "'");
        $myJoinWishingList = pdo_fetchall_cj("SELECT W.*, WR.id AS likes_id FROM " . tablename_cj('choujiang_wishing') . " AS W LEFT JOIN " . tablename_cj('choujiang_wishing_record') . " AS WR ON W.id = WR.wishing_id WHERE WR.openid = '{$openid}' AND W.create_at>='".date("Y-m-d H:i:s",mktime(0, 0 , 0,date("m"),date("d")-date("N")+1,date("Y")))."' AND W.create_at<='".date("Y-m-d H:i:s",mktime(23,59,59,date("m"),date("d")-date("N")+7,date("Y")))."' ORDER BY W.likes_num DESC, W.id ASC LIMIT  " . ($pindex - 1) * $psize . "," . $psize);
        $myJoinCount = pdo_fetch_cj("SELECT count(*) AS count FROM " . tablename_cj('choujiang_wishing') . " AS W LEFT JOIN " . tablename_cj('choujiang_wishing_record') . " AS WR ON W.id = WR.wishing_id WHERE WR.openid = '{$openid}' AND W.create_at>='".date("Y-m-d H:i:s",mktime(0, 0 , 0,date("m"),date("d")-date("N")+1,date("Y")))."' AND W.create_at<='".date("Y-m-d H:i:s",mktime(23,59,59,date("m"),date("d")-date("N")+7,date("Y"))) . "'");
//        $wishingList = $this->model->getWishingList([
//            'release_goods' => 0,
//            'create_at >=' => date("Y-m-d H:i:s",mktime(0, 0 , 0,date("m"),date("d")-date("N")+1,date("Y"))),
//            'create_at <=' => date("Y-m-d H:i:s",mktime(23,59,59,date("m"),date("d")-date("N")+7,date("Y"))),
//        ], [], '', ["openid<>'{$openid}' ASC","id DESC"],[$pindex, $psize]);

        foreach ($myReleaseWishing as $key => $val) {
            $userInfo = pdo_get_cj('choujiang_user', ['openid' => $val['openid']]);
            $myReleaseWishing[$key]['goods_img'] = $this->getImage($val['goods_img']);
            $myReleaseWishing[$key]['nickname'] = $userInfo['nickname'];
            $myReleaseWishing[$key]['weekInfo'] = "this";
        }

        foreach ($myJoinWishingList as $key => $val) {
            $userInfo = pdo_get_cj('choujiang_user', ['openid' => $val['openid']]);
            $myJoinWishingList[$key]['goods_img'] = $this->getImage($val['goods_img']);
            $myJoinWishingList[$key]['nickname'] = $userInfo['nickname'];
            $myJoinWishingList[$key]['weekInfo'] = "this";
        }

        $resutl = [
            'myReleaseWishing' => $myReleaseWishing,
            'list' => $myJoinWishingList,
            'total' => $myJoinCount['count'],
        ];

        return $this->result(0, 'success', $resutl);
    }

    /*
     * 我的心愿上周列表接口
     */
    public function myLastWeekWishingList(){
        global $_GPC;
        $psize = 15;
        $pindex = $_GPC['page'] ? trim($_GPC['page']) : 1;
        $openid = trim($_GPC['openId']);

        ///获取当前页心愿数据
        $weekStart = date('Y-m-d H:i:s',mktime(0, 0 , 0,date("m"),date("d")-date("N")+1-7,date("Y")));
        $weekEnd = date('Y-m-d H:i:s',mktime(23,59,59,date("m"),date("d")-date("N")+7-7,date("Y")));
        $lastMyReleaseWishing = pdo_fetchall_cj("SELECT * FROM " . tablename_cj('choujiang_wishing') . " WHERE openid = '{$openid}' AND create_at>='".$weekStart."' AND create_at<='".$weekEnd . "'");
//        $lastMyJoinWishingList = pdo_fetchall_cj("SELECT * FROM " . tablename_cj('choujiang_wishing') . " WHERE openid != '{$openid}' AND create_at>='".date('Y-m-d H:i:s',mktime(0, 0 , 0,date("m"),date("d")-date("N")+1-7,date("Y")))."' AND create_at<='".date('Y-m-d H:i:s',mktime(23,59,59,date("m"),date("d")-date("N")+7-7,date("Y")))."' ORDER BY likes_num DESC, id ASC LIMIT  " . ($pindex - 1) * $psize . "," . $psize);
//        $lastMyJoinCount = pdo_fetch_cj("SELECT count(*) AS count FROM " . tablename_cj('choujiang_wishing') . " WHERE openid != '{$openid}' AND create_at>='".date('Y-m-d H:i:s',mktime(0, 0 , 0,date("m"),date("d")-date("N")+1-7,date("Y")))."' AND create_at<='".date('Y-m-d H:i:s',mktime(23,59,59,date("m"),date("d")-date("N")+7-7,date("Y"))) . "'");
        $wishingList = $this->model->getWishingList([
//            'openid !=' => $openid,
            'status' => 1,
            'create_at >=' => $weekStart,
            'create_at <=' => $weekEnd,
        ], [], '', ["likes_num DESC", "id ASC"],[$pindex, $psize]);

//        foreach ($wishingList['list'] as $key => $val) {
//            $userInfo = pdo_get_cj('choujiang_user', ['openid' => $val['openid']]);
//            $wishingList['list'][$key]['nickname'] = $userInfo['nickname'];
//        }
        $wishingList['list'] = $this->wishingInfoCheck($openid, $wishingList['list'], "last", 1);

        $lastMyReleaseWishing = $this->wishingInfoCheck($openid, $lastMyReleaseWishing, "this", 1, 1);
        foreach ($lastMyReleaseWishing as $key => $val) {
            $sql = "SELECT * FROM (SELECT A.id,(@rowno:=@rowno+1) AS rowno FROM " . tablename_cj("choujiang_wishing") . " AS A,(select (@rowno:=0)) AS B WHERE A.status = 1 AND A.create_at >= '{$weekStart}' AND A.create_at <= '{$weekEnd}' ORDER BY A.`likes_num` desc, A.`id` asc) AS C WHERE C.id='{$val['id']}'";
            $wishingRowInfo = pdo_fetch_cj($sql);
//            $userInfo = pdo_get_cj('choujiang_user', ['openid' => $val['openid']]);
//            $lastMyReleaseWishing[$key]['nickname'] = $userInfo['nickname'];
            $lastMyReleaseWishing[$key]['rowNo'] = $wishingRowInfo['rowno'];
            $lastMyReleaseWishing[$key]['isShow'] = $wishingRowInfo['rowno']>$psize ? 1 : 0 ;
        }

        $resutl = [
            'myReleaseWishing' => $lastMyReleaseWishing,
//            'myJoinList' => $lastMyJoinWishingList,
//            'total' => $lastMyJoinCount['count'],
            'list' => $wishingList['list'],
            'total' => (int)$wishingList['total'],
        ];

        return $this->result(0, 'success', $resutl);
    }

    /*
     * 心愿规则接口
     */
    public function wishingRule(){
        global $_GPC;
        $return = [
            'wishingRule' => [
                'wishingReleaseWeek' => $this->baseConfig['wishing_week_release'],
                'wishingDailyJoin' => $this->baseConfig['wishing_daily_join'],
            ],
            'scroeRule' => $this->baseConfig['score'],
        ];

        return $this->result(0, 'success', $return);
    }

    /*
     * 返回用户本周剩余发布心愿次数
     */
    private function surplusReleaseTimes($wishingReleaseKey, $timeStamp) {
        /// 判断本周是否已经达到发布上限

        /// 新redis数据
        $redisData = ['timeStamp' => $timeStamp, 'releaseTimes' => 0];
        /// 剩余次数
        $surplusTimes = $this->baseConfig['wishing_week_release'];
        /// 本周开始时间
        $weekStart = mktime(0, 0 , 0,date("m"),date("d")-date("N")+1,date("Y"));
        /// 本周结束时间
        $weekEnd = mktime(23,59,59,date("m"),date("d")-date("N")+7,date("Y"));
        if($this->redis->exists($wishingReleaseKey)){
            $wishingReleaseInfo = $this->redis->hGetAll($wishingReleaseKey);
            if ($wishingReleaseInfo['timeStamp']>=$weekStart && $wishingReleaseInfo['timeStamp']<=$weekEnd) {
                if ($wishingReleaseInfo['releaseTimes'] >= $this->baseConfig['wishing_week_release']) {
//                    return $this->result(0, 'success', ['error'=>1,'message'=>'您已经达到本周发布心愿上限']);
                    $surplusTimes = 0;
                } else {
                    $redisData ['releaseTimes'] = $wishingReleaseInfo['releaseTimes'] + 1;
                    $surplusTimes = $this->baseConfig['wishing_week_release'] - $wishingReleaseInfo['releaseTimes'];
                }
            } else {
                $redisData ['releaseTimes'] = 1;
            }
        } else {
            $redisData ['releaseTimes'] = 1;
        }

        $return = [
            'surplusTimes' => $surplusTimes,
            'redisInfo' => $redisData,
        ];

        return $return;
    }

    /*
     * 返回用户今日剩余想要次数
     */
    private function surplusLikesTimes($wishingLikesKey, $timeStamp) {
        /// 判断今天是否已经达到想要上限

        /// 新redis数据
        $redisData = ['timeStamp' => $timeStamp, 'likesTimes' => 0];
        /// 剩余次数
        $surplusTimes = $this->baseConfig['wishing_daily_join'];
        /// 今天开始时间
        $dayStart = strtotime(date("Y-m-d"));
        /// 今天结束时间
        $dayEnd = strtotime(date("Y-m-d"). " 23:59:59");;
        if($this->redis->exists($wishingLikesKey)){
            $wishingLikesInfo = $this->redis->hGetAll($wishingLikesKey);
            if ($wishingLikesInfo['timeStamp']>=$dayStart && $wishingLikesInfo['timeStamp']<=$dayEnd) {
                if ($wishingLikesInfo['likesTimes'] >= $this->baseConfig['wishing_daily_join']) {
                    //return $this->result(0, 'success', ['error'=>1,'message'=>'您已经达到每日想要上限']);
                    $surplusTimes = 0;
                } else {
                    $redisData ['likesTimes'] = $wishingLikesInfo['likesTimes'] + 1;
                    $surplusTimes = $this->baseConfig['wishing_daily_join'] - $wishingLikesInfo['likesTimes'];
                }
            } else {
                $redisData ['likesTimes'] = 1;
            }
        } else {
            $redisData ['likesTimes'] = 1;
        }

        $return = [
            'surplusTimes' => $surplusTimes,
            'redisInfo' => $redisData,
        ];

        return $return;
    }

    /*
     * 当前用户心愿相关剩余次数
     */
    private function allSurplusTimes($openid) {
        global $_W;
        $uniacid = $_W['uniacid'];
        $wishingLikesKey = sprintf($_W['redis_key']['wishing_likes'], $uniacid ,$openid);
        $wishingReleaseKey = sprintf($_W['redis_key']['wishing_release'], $uniacid ,$openid);
        $timeStamp = time();

        $redisLikesInfo = $this->surplusLikesTimes($wishingLikesKey, $timeStamp);
        $redisReleaseInfo = $this->surplusReleaseTimes($wishingReleaseKey, $timeStamp);
        $return = [
            'releaseTimes' => $redisReleaseInfo['surplusTimes'],
            'likesTimes' => $redisLikesInfo['surplusTimes'],
        ];
        return $return;
    }

    /*
     * 心愿详情数据统一格式化
     * twoDimensional 是否二维数组
     * 数据周信息 默认为空，会自动判断， 取值“this（这周）、last（上周）、other（上周以前数据）”
     * isMy 是否自己发布的心愿
     */
    private function wishingInfoCheck($openid, $data, $weekInfo = "", $twoDimensional = 0, $isMy = 0) {
        $weekData = [ "this", "last", "other" ];

        $eachWishing = $data;

        if ( !$twoDimensional ) {
            /// 如果是一维数组转出二维数组进行数据处理
            $eachWishing = [];
            $eachWishing[] = $data;
        }

        // 上周时间戳
        $lastWeekStart = mktime(0, 0 , 0,date("m"),date("d")-date("N")+1-7,date("Y"));
        $lastWeekEnd = mktime(23,59,59,date("m"),date("d")-date("N")+7-7,date("Y"));
        // 本周时间戳
        $thisWeekStart = mktime(0, 0 , 0,date("m"),date("d")-date("N")+1,date("Y"));
        $thisWeekEnd = mktime(23,59,59,date("m"),date("d")-date("N")+7,date("Y"));

        foreach ($eachWishing as $k => $v) {
            /// 添加心愿发布者昵称
            $userInfo = pdo_get_cj('choujiang_user', ['openid' => $v['openid']]);
            $eachWishing[$k]['nickname'] = empty($userInfo) ? "" : $userInfo['nickname'];
            $eachWishing[$k]['goods_img'] = $this->getImage($v['goods_img']);

            /// 添加参与想要记录ID
            $eachWishing[$k]['likes_id'] = 0;
            if ( !$isMy ) {
                if ( !empty($openid)) {
                    // openid 不为空是查询用户是否存在参与记录
                    $wishingJion = $this->model->getWishingRecordInfo(['wishing_id' => $v['id'], 'openid' => $openid]);
                    $eachWishing[$k]['likes_id'] = empty($wishingJion) ? 0 : $wishingJion['id'] ;
                }
            }

            /// 添加周信息
            if ( empty($weekInfo) || ( !empty($weekInfo) && !in_array($weekInfo,$weekData) ) ) {
                if ( strtotime($v['create_at'])< $lastWeekStart) {
                    $eachWishing[$k]['weekInfo'] = "other";
                }
                if ( strtotime($v['create_at'])>= $lastWeekStart && strtotime($v['create_at'])<= $lastWeekEnd ) {
                    $eachWishing[$k]['weekInfo'] = "last";
                }
                if ( strtotime($v['create_at'])>= $thisWeekStart && strtotime($v['create_at'])<= $thisWeekEnd ) {
                    $eachWishing[$k]['weekInfo'] = "this";
                }
            } else {
                $eachWishing[$k]['weekInfo'] = $weekInfo;
            }
        }

        if ( !$twoDimensional ) {
            /// 如果是一维数返回时还原为一维数组
            $eachWishing = $eachWishing[0];
        }

        return $eachWishing;
    }

    /*
     * 更新自动参与抽奖formid
     */
    public function editAutoJoinFormid() {
        global $_GPC;
        $goodsid = trim($_GPC['goodsId']);
        $openid = trim($_GPC['openId']);
        $formid = trim($_GPC['formId']);

        $recordInfo = pdo_update_cj("choujiang_record", ['formid' => $formid], ['goods_id' => $goodsid, 'openid' => $openid]);
        if ($recordInfo) {
            return true;
        } else {
            return false;
        }
    }

}