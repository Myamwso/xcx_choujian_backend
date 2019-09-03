<?php
define('IN_SYS', true);

class cj_admin_wishingWall extends Common
{
    public function __construct()
    {
        $this->model = new admin_wishingWallModel();
        parent::__construct();
    }
    /*
     * 心愿墙列表接口
     */
    public function wishingList()
    {
        global $_GPC;

        if ($_GPC['op'] == 'content') {

            $searchData = [];

            if ($_GPC['searchDo']) {
                if ($_GPC['searchDo']==1) {
                    /// 本周
                    $searchData['create_at >='] = date("Y-m-d H:i:s" ,mktime(0, 0 , 0,date("m"),date("d")-date('N')+1,date("Y")));
                    $searchData['create_at <='] = date("Y-m-d H:i:s" ,mktime(23,59,59,date("m"),date("d")-date('N')+7,date("Y")));
                } else if ($_GPC['searchDo']==2) {
                    /// 上周
                    $searchData['create_at >='] = date("Y-m-d H:i:s" ,mktime(0, 0 , 0,date('m'),date('d')-date('N')+1-7,date('Y')));
                    $searchData['create_at <='] = date("Y-m-d H:i:s" ,mktime(23,59,59,date('m'),date('d')-date('N')+7-7,date('Y')));
                }
            }

            if ( !empty(trim($_GPC['keywords'])) ) {
                if (!isset($_GPC['fields']) || isset($_GPC['fields'])&&$_GPC['fields'] ==0 ) {
                    $searchData['openid'] = trim($_GPC['keywords']);
                } else if (isset($_GPC['fields'])&&$_GPC['fields'] ==1) {
                    $searchData['goods_name like'] = "%".trim($_GPC['keywords'])."%";
                } else if (isset($_GPC['fields'])&&$_GPC['fields'] ==2) {
                    $searchData['id'] = trim($_GPC['keywords']);
                } else if (isset($_GPC['fields'])&&$_GPC['fields'] ==3) {
                    $allUser = pdo_getall_cj("choujiang_user", ["nickname like" => "%".trim($_GPC['keywords'])."%"]);
//                    var_dump($allUser);
                    $allUserStr = "";
                    if (!empty($allUser)) {
                        $allUserStr = "(";
                        foreach ($allUser as $k => $v) {
                            $allUserStr .= "'".$v['openid']."',";
                        }
                        $allUserStr = preg_replace("/,$/", "", $allUserStr);
                        $allUserStr .= ")";
                        $searchData['openid in'] = $allUserStr;
                    } else {
                        echo json_encode([
                            'code' => 10000,
                            'msg' => 'success',
                            'total' => 0,
                            'data' => []
                        ]);
                        exit;
                    }
                }
            }

            $sortField = $_GPC['listField'] ? trim($_GPC['listField']) :"id";

            /// 获取单页列表数据
            $result = $this->model->getWishingList($searchData, [], '', ["{$sortField} {$_GPC['sort']}"],[$_GPC['page'], $_GPC['pageNum']]);

            /// 本周
            $thisWeekStart = mktime(0, 0 , 0,date("m"),date("d")-date('N')+1,date("Y"));
            $thisWeekEnd = mktime(23,59,59,date("m"),date("d")-date('N')+7,date("Y"));
            /// 上周
            $lastWeekStart = mktime(0, 0 , 0,date('m'),date('d')-date('N')+1-7,date('Y'));
            $lastWeekEnd = mktime(23,59,59,date('m'),date('d')-date('N')+7-7,date('Y'));

            foreach ($result['list'] as $key => $val) {
                $wishingCreate = strtotime($val['create_at']);
                $result['list'][$key]['likes_num'] = (int)$val['likes_num'];
                $result['list'][$key]['accomplish_wishing'] = (int)$val['accomplish_wishing'];
                $result['list'][$key]['goods_img'] = $this->getImage($val['goods_img']);
                $result['list'][$key]['week'] = "other";
                if ($thisWeekStart <= $wishingCreate && $thisWeekEnd >= $wishingCreate) {
                    $result['list'][$key]['week'] = "this";
                }
                if ($lastWeekStart <= $wishingCreate && $lastWeekEnd >= $wishingCreate) {
                    $result['list'][$key]['week'] = "last";
                }
            }

            echo json_encode([
                'code' => 10000,
                'msg' => 'success',
                'total' =>(int)$result['total'],
                'data' => $result['list']
            ]);
            exit;
        }

        include $this->template('choujiang_wishingWall');
    }

    /*
     * 心愿详情
     */
    public function details()
    {
        global $_GPC;
        $wishingId = $_GPC['wishingId'];

        $wishingInfo = $this->model->getWishingInfo(['id' => $wishingId]);

        $wishingInfo['goods_img'] = $this->getImage($wishingInfo['goods_img']);

        echo json_encode([
            'code' => 10000,
            'msg' => 'success',
            'data' => $wishingInfo
        ]);
    }

    /*
     * 编辑心愿商品
     */
    public function edit()
    {
        global $_GPC;
        $wishingId = $_GPC['wishingId'];
        $id = $_GPC['id'];
//        $openid = $_GPC['openid'];
        $writeTime = date('Y-m-d H:i:s');
        $data['goods_name'] = $_GPC['goods_name'];
        $data['goods_price'] = $_GPC['goods_price'];
        $data['goods_url'] = $_GPC['goods_url'];
        $data['goods_img'] = $this->getImgPath($_GPC['goods_img']);
        $data['goods_info'] = $_GPC['goods_info'];
        $data['accomplish_wishing'] = $_GPC['accomplish_wishing'];
        $data['update_at'] = $writeTime;

        $wishingInfo = $this->model->getWishingInfo(['id' => $wishingId]);;

        /// 判断心愿商品是否存在，否则退出
        if ( !$wishingInfo ) {
            echo json_encode([
                'code' => 10001,
                'msg' => 'success',
                'data' => '心愿商品不存在'
            ]);
            exit;
        }

        /// 判断两次传参id是否一致
        if( $id != $wishingId){
            echo json_encode([
                'code' => 10001,
                'msg' => 'success',
                'data' => '修改id和编辑id不一致'
            ]);
            exit;
        }

        /// 判断内容是否发生变更
        if ( $data['goods_name'] == $wishingInfo['goods_name'] && $data['goods_price'] == $wishingInfo['goods_price'] && $data['goods_url'] == $wishingInfo['goods_url'] && $data['goods_img'] == $wishingInfo['goods_img'] && $data['goods_info'] == $wishingInfo['goods_info'] && $data['accomplish_wishing'] == $wishingInfo['accomplish_wishing'] ) {
            echo json_encode([
                'code' => 10001,
                'msg' => 'success',
                'data' => ' 请修改内容后再保存'
            ]);
            exit;
        }

        if ( $wishingInfo['likes_num'] > 0 ) {
            /// 心愿已经有用户参与点赞，商品信息不能修改，只能修改达成心愿的点赞数
            unset($data['goods_name']);
            unset($data['goods_price']);
            unset($data['goods_url']);
            unset($data['goods_img']);
            unset($data['goods_info']);
        } else {
            /// 心愿无人点赞，心愿详情可以修改
            if ( $wishingInfo['goods_price'] != $data['goods_price'] && $data['accomplish_wishing'] == $wishingInfo['accomplish_wishing'] ) {
                /// 心愿价格变更，如果达成心愿点赞数与数据库达成心愿数一致，从新计算新价格的达成心愿数量，否则按操作者指定的达成心愿数写入数据库
                $data['accomplish_wishing'] = ceil($data['goods_price']*$this->baseConfig['wishing_ratio']);
            }
        }

        /// 更心愿数据
        $updateResult = $this->model->updataWishing($data, ['id' => $wishingId]);

        if ( $updateResult ) {
            echo json_encode([
                'code' => 10000,
                'msg' => 'success',
                'data' => '修改成功'
            ]);
        } else {
            echo json_encode([
                'code' => 10000,
                'msg' => 'success',
                'data' => '修改失败'
            ]);
        }
    }

    /*
     * 检查商品是否达到发布要求
     */
    public function checkHasRelease ( $id ) {
        $wishingInfo = $this->model->getWishingInfo(['id' => $id]);

        $return = ['error' => 0, 'message' => ''];

        if ( empty($wishingInfo) ) {
            /// 心愿商品不存在
            $return = ['error' => 1, 'message' => "心愿id不存在，请核实后再发布"];
        } else {
            /// 心愿商品存在

            if ( $wishingInfo['likes_num'] < $wishingInfo['accomplish_wishing'] ) {
                /// 心愿未达到发布要求
                $return = ['error' => 1, 'message' => "心愿未达到发布要求"];
            }

            $wishingGoods = $this->model->getWishingGoodsInfo(['wishing_id' => $id]);
            if ( $wishingInfo['release_goods'] == 1 || !empty($wishingGoods) ) {
                /// 心愿已经发布
                $return = ['error' => 1, 'message' => "心愿商品已经发布，请勿重复发布"];
            }
        }
        return $return;
    }

    /*
     * 发布心愿商品
     */
    public function releaseGoods ( $id, $googs_id ) {
        /// 检查心愿是否符合发布要求
//        $isRelease = $this->checkHasRelease($id);
//        if ( $isRelease['error'] == 1 ) {
//            return $isRelease;
//        }

        $goodsWishingInfo = $this->model->getWishingGoodsInfo(['wishing_id' => $id]);
        if ( !empty($goodsWishingInfo) ) {
            return ['error' => 1, 'message' => '心愿商品已经发布，不可重复发布'];
        }

        $writeTime = date('Y-m-d H:i:s');

        /// 更新心愿表是否发布字段
        $updateResult = $this->model->updataWishing(['release_goods' => 1, 'release_at' => $writeTime], ['id' => $id]);

        /// 插入心愿商品与奖品关联数据
        $data = [
            'goods_id' => $googs_id,
            'wishing_id' => $id,
            'is_notice' => 0,
            'create_at' => $writeTime,
            'update_at' => $writeTime,
        ];
        $wishingInsert = $this->model->wishingGoodsInsert($data);

        if (!empty($updateResult) && !empty($wishingInsert)) {
            return ['error' => 0, 'message' => ''];
        } else {
            if ( empty($updateResult) ) {
                return ['error' => 1, 'message' => 'choujiang_wishing修改发布失败'];
            } else {
                return ['error' => 1, 'message' => 'ims_choujiang_wishing_goods插入失败'];
            }
        }
    }

    /*
     * 想要列表
     */
    public function likesList () {
        global $_GPC;
        $wishingId = $_GPC['id'];


        $sortField = $_GPC['listField'] ? trim($_GPC['listField']) :"id";

        $list = $this->model->wishingRecordList(['wishing_id' => $wishingId], [], '', ["{$sortField} {$_GPC['sort']}"],[$_GPC['page'], $_GPC['pageNum']]);

//        $list = $this->model->wishingRecordList(['wishing_id' => $wishingId], [], '', [],[]);

        echo json_encode([
            'code' => 10000,
            'msg' => 'success',
            'total' =>(int)$list['total'],
            'data' => $list['list']
        ]);
        exit;
    }

    /*
     * 审核操作
     */
    public function auditDo () {
        global $_GPC;
        $wishingId = $_GPC['wishingId'];
        $audit = $_GPC['audit'];
        /// 所有操作值
        $auditAll = [1,2,3,4];

        if ( in_array($audit,$auditAll) ) {
            $wishingInfo = $this->model->getWishingInfo(['id' => $wishingId]);

            /// 本周
            $thisWeekStart = mktime(0, 0 , 0,date("m"),date("d")-date('N')+1,date("Y"));
            $thisWeekEnd = mktime(23,59,59,date("m"),date("d")-date('N')+7,date("Y"));
            /// 上周
            $lastWeekStart = mktime(0, 0 , 0,date('m'),date('d')-date('N')+1-7,date('Y'));
            $lastWeekEnd = mktime(23,59,59,date('m'),date('d')-date('N')+7-7,date('Y'));

            $wishingCreate = strtotime($wishingInfo['create_at']);
            $wishingInfo['week'] = "other";
            if ($thisWeekStart <= $wishingCreate && $thisWeekEnd >= $wishingCreate) {
                $wishingInfo['week'] = "this";
            }
            if ($lastWeekStart <= $wishingCreate && $lastWeekEnd >= $wishingCreate) {
                $wishingInfo['week'] = "last";
            }



            if ( !empty($wishingInfo)) {
                if ( $audit != 4 ) {
                    $data = ['status' => $audit, 'refuse_reason' => ''];
                    if ($audit == 3 || $audit == 2) {
                        if ( empty(trim($_GPC['refuseReason']))) {
                            echo json_encode([
                                'code' => 10001,
                                'msg' => '拒绝原因必须填写',
                            ]);
                            exit;
                        }
                        $data['refuse_reason'] = $_GPC['refuseReason'];
                    }
                    $updateResult = $this->model->updataWishing($data,['id' => $wishingId]);
                } else {
                    $updateResult = $this->model->updataWishing(['is_del'=> 1],['id' => $wishingId]);
                }
                if ( $updateResult ) {
                    if ($audit == 3 || $audit == 2) {
                        /// 发出审核拒绝的通知消息
                        $this->doPageInform($wishingId);
                    }
                    if ($audit != 4) {
                        $result = $wishingInfo;
                        $result['status'] = $audit;
                        if ($audit == 3 || $audit == 2) {
                            $result['refuse_reason'] = $_GPC['refuseReason'];
                        } else {
                            $result['refuse_reason'] = "";
                        }
                    } else {
                        $result = [];
                    }
                } else {
                    echo json_encode([
                        'code' => 10001,
                        'msg' => '操作失败',
                    ]);
                    exit;
                }
            } else {
                /// 该心愿商品不存在
                echo json_encode([
                    'code' => 10002,
                    'msg' => '非法操作',
                ]);
                exit;
            }
        } else {
            /// 操作值不在规定数组内
            echo json_encode([
                'code' => 10003,
                'msg' => '非法操作',
            ]);
            exit;
        }

        echo json_encode([
            'code' => 10000,
            'msg' => 'success',
            'data' => $result
        ]);
        exit;
    }


    //心愿拒绝 模板通知
    public function doPageInform($id)
    {
//        global $_GPC, $_W;
//        $uniacid = $_W['uniacid'];
//        $base = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_base') . "where `uniacid`='{$uniacid}' ");
//        $template_id = trim($base['refuse_template_id']);

        $template_id = trim($this->baseConfig['refuse_template_id']);

        $wxapp = WeAccount::create();
        $access_token = $wxapp->getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=' . $access_token;
        $dd = array();
        $sdInfo = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_wishing') . " where `id`='{$id}'");

        $formIds = json_decode($sdInfo['formid'],true);
//        'formid' => json_encode([
//        ['dataTime' => $writeTime,
//            'formid' => $formid,]
//    ]),
        $formId = $formIds[0]['formid'];
        $tempFormid = [];
        foreach ($formIds as $key => $val) {
            if ( $key != 0) {
                $tempFormid = $val;
            }
        }
//        array_splice($formIds, 0, 1);
        $formIdJson = json_encode($tempFormid);
        pdo_update_cj("choujiang_wishing", ['formid' => $formIdJson], ['id' => $id]);

        $dd['form_id'] = $formId;
        $dd['touser'] = $sdInfo['openid'];
//        var_dump($sdInfo['refuse_reason']);
        $content = array(
            "keyword1" => array(
                "value" => '【'.$sdInfo['goods_name'].'】'.'心愿发布被拒绝',
                "color" => "#4a4a4a"
            ),
            "keyword2" => array(
                "value" => $sdInfo['refuse_reason'],
                "color" => "#9b9b9b"
            ),
        );
        $dd['template_id'] = $template_id;
        $dd['page'] = 'choujiang_page/release/release?id=' . $id;  //点击模板卡片后的跳转页面，仅限本小程序内的页面。支持带参数,该字段不填则模板无跳转
        $dd['data'] = $content;                        //模板内容，不填则下发空模板
        $dd['color'] = '';                        //模板内容字体的颜色，不填默认黑色
        $dd['emphasis_keyword'] = '';    //模板需要放大的关键词，不填则默认无放大
        $result = $this->https_curl_json($url, $dd, 'json');
        return $result;
    }

}