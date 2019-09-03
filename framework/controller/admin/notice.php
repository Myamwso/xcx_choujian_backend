<?php

class cj_admin_notice extends Common
{

    public function __construct()
    {
        parent::__construct();
    }

    /*
     * 公告列表接口
     */

    public function noticeList()
    {
        global $_GPC,$_W;
        $op = trim($_GPC['op']);
        $psize = $_GPC['pageNum'] ? trim($_GPC['pageNum']) : 15;
        $pindex = $_GPC['page'] ? trim($_GPC['page']) : 1;

        if ($op == "noticeList" && $_GPC['ajaxGet'] == "true") {
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

            $where .= " AND is_del = 0";
            $searchData['is_del'] = 0;

            $sortField = $_GPC['listField'] ? trim($_GPC['listField']) :"id";

            /// 获取单页列表数据
            $Total = pdo_fetch_cj("SELECT count(*) AS total FROM " . tablename_cj("choujiang_notice") . " WHERE {$where}");
            $result = pdo_getall_cj("choujiang_notice",$searchData,[], '', ["{$sortField} {$_GPC['sort']}"],[$pindex, $psize]);

            echo json_encode([
                'code' => 10000,
                'msg' => 'success',
                'total' =>(int)$Total['total'],
                'data' => $result
            ]);
            exit;
        }

        include $this->template('choujiang_notice');
    }

    /*
     * 添加公告接口
     */
    public function addNotice()
    {
        global $_GPC;
        $writeTime = date("Y-m-d H:i:s");

        $data = [
            'message' => $_GPC['message'],
            'sort_num' => (int)$_GPC['sort_num'] == '' ? 0 : (int)$_GPC['sort_num'],
            'start_at' => $_GPC['start_at'],
            'end_at' => $_GPC['end_at'],
            'create_at' => $writeTime,
            'update_at' => $writeTime,
        ];
        $result = pdo_insert_cj("choujiang_notice", $data);

        if ($result) {
            echo json_encode([
                'code' => 10000,
                'msg' => 'success',
//            'total' =>(int)$Total['total'],
                'data' => $result
            ]);
        } else {
            echo json_encode([
                'code' => 10001,
                'msg' => '公告添加失败',
//            'total' =>(int)$Total['total'],
                'data' => $result
            ]);
        }
        exit;

    }

    /*
     * 编辑公告接口
     */
    public function editNotice()
    {
        global $_GPC;

        if ( !trim($_GPC['id']) ) {
            echo json_encode([
                'code' => 10001,
                'msg' => '无法编辑',
            ]);
            exit;
        }

        $writeTime = date("Y-m-d H:i:s");

        $data = [
            'message' => $_GPC['message'],
            'sort_num' => (int)$_GPC['sort_num'] == '' ? 0 : (int)$_GPC['sort_num'],
            'start_at' => $_GPC['start_at'],
            'end_at' => $_GPC['end_at'],
            'update_at' => $writeTime,
        ];
        $result = pdo_update_cj("choujiang_notice", $data, ['id' => trim($_GPC['id'])]);

        if ($result) {
            echo json_encode([
                'code' => 10000,
                'msg' => 'success',
                'data' => $result
            ]);
        } else {
            echo json_encode([
                'code' => 10001,
                'msg' => '写入消息失败',
                'data' => []
            ]);
        }
        exit;

    }

    /*
     * 删除公告接口
     */
    public function deleteNotice()
    {
        global $_GPC;
        $writeTime = date("Y-m-d H:i:s");

        if ( !trim($_GPC['id']) ) {
            echo json_encode([
                'code' => 10001,
                'msg' => '无法操作',
            ]);
            exit;
        }

        $data = [
            'is_del' => 1,
            'update_at' => $writeTime,
        ];
        $result = pdo_update_cj("choujiang_notice", $data, ['id' => trim($_GPC['id'])]);

        if ($result) {
            echo json_encode([
                'code' => 10000,
                'msg' => 'success',
                'data' => $result
            ]);
        } else {
            echo json_encode([
                'code' => 10001,
                'msg' => '删除失败',
                'data' => $result
            ]);
        }
        exit;
    }

}