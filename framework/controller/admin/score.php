<?php

class cj_admin_score extends Common
{

    public function __construct()
    {
        parent::__construct();
    }

    /*
     * 积分列表接口
     */

    public function scoreList()
    {
        global $_GPC,$_W;
        $op = trim($_GPC['op']);
        $psize = $_GPC['pageNum'] ? trim($_GPC['pageNum']) : 15;
        $pindex = $_GPC['page'] ? trim($_GPC['page']) : 1;

        if ($op == "userList" && $_GPC['ajaxGet'] == "true") {
            $searchData = [];
            $where = 1;
            if ( !empty(trim($_GPC['keywords'])) ) {
                if (isset($_GPC['fields'])&&$_GPC['fields'] ==1) {
                    $searchData['openid'] = trim($_GPC['keywords']);
                    $where = "openid='{$searchData['openid']}'";
                } else if (isset($_GPC['fields'])&&$_GPC['fields'] ==2) {
                    $searchData['nickname like'] = "%".trim($_GPC['keywords'])."%";
                    $where = "nickname like '{$searchData['nickname like']}'";
                }
            }

            $sortField = $_GPC['listField'] ? trim($_GPC['listField']) :"id";

            /// 获取单页列表数据
            $Total = pdo_fetch_cj("SELECT count(*) AS total FROM " . tablename_cj("choujiang_score") . " WHERE {$where}");
            $result = pdo_getall_cj("choujiang_score",$searchData,[], '', ["{$sortField} {$_GPC['sort']}"],[$pindex, $psize]);

            echo json_encode([
                'code' => 10000,
                'msg' => 'success',
                'total' =>(int)$Total['total'],
                'data' => $result
            ]);
            exit;
        } else if ($op == "scroeRecordList" && $_GPC['ajaxGet'] == "true") {
            $searchData = [];
            $where = 1;
            if ( !empty(trim($_GPC['keywords'])) ) {
                if (isset($_GPC['fields'])&&$_GPC['fields'] ==1) {
                    $searchData['openid'] = trim($_GPC['keywords']);
                    $where = "openid='{$searchData['openid']}'";
                } else if (isset($_GPC['fields'])&&$_GPC['fields'] ==2) {
                    $searchData['nickname like'] = "%".trim($_GPC['keywords'])."%";
                    $where = "nickname like '{$searchData['nickname like']}'";
                }
            }

            $sortField = $_GPC['listField'] ? trim($_GPC['listField']) :"id";

            /// 获取单页列表数据
            $Total = pdo_fetch_cj("SELECT count(*) AS total FROM " . tablename_cj("choujiang_score_record") . " WHERE {$where}");
            $result = pdo_getall_cj("choujiang_score_record",$searchData,[], '', ["{$sortField} {$_GPC['sort']}"],[$pindex, $psize]);

            foreach($result as $key => $val){
                $result[$key]['extact'] = json_decode($val['extact'], true);
            }

            echo json_encode([
                'code' => 10000,
                'msg' => 'success',
                'total' =>(int)$Total['total'],
                'data' => $result
            ]);
            exit;
        }

        include $this->template('choujiang_score');
    }

    /*
     * 用户积分列表接口
     */

    public function scoreListUser()
    {
        global $_GPC,$_W;
        $op = trim($_GPC['op']);
        $psize = $_GPC['pageNum'] ? trim($_GPC['pageNum']) : 15;
        $pindex = $_GPC['page'] ? trim($_GPC['page']) : 1;
        $openid = trim($_GPC['openid']);


        $searchData['openid'] = $openid;

        $where = "openid='{$openid}'";


        $sortField = $_GPC['listField'] ? trim($_GPC['listField']) :"id";

        /// 获取单页列表数据
        $Total = pdo_fetch_cj("SELECT count(*) AS total FROM " . tablename_cj("choujiang_score_record") . " WHERE {$where}");
        $result = pdo_getall_cj("choujiang_score_record",$searchData,[], '', ["{$sortField} {$_GPC['sort']}"],[$pindex, $psize]);

        foreach($result as $key => $val){
            $result[$key]['extact'] = json_decode($val['extact'], true);
        }

        echo json_encode([
            'code' => 10000,
            'msg' => 'success',
            'total' =>(int)$Total['total'],
            'data' => $result
        ]);
        exit;

    }

    /*
     * 积分记录详情
     */
    public function recrodDetails()
    {
        global $_GPC,$_W;
        $id = trim($_GPC['id']);

        $result = pdo_get_cj("choujiang_score_record", ['id' => $id ]);

        $result['extact'] = json_decode($result['extact'], true);

        echo json_encode([
            'code' => 10000,
            'msg' => 'success',
//            'total' =>(int)$Total['total'],
            'data' => $result
        ]);
        exit;

    }

}