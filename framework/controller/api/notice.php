<?php

class Cj_Notice extends Common
{

    public function __construct()
    {
        parent::__construct();
    }

    /*
     * 公告栏接口
     */

    public function noticeList()
    {
        $today = date("Y-m-d");
        $sql = "SELECT * FROM " . tablename_cj("choujiang_notice") . " WHERE is_del = 0 AND start_at <= '{$today}' AND end_at >= '{$today}' ORDER BY sort_num asc, id desc";
        $list = pdo_fetchall_cj($sql);
        $result = [
            'list' => $list
        ];

        return $this->result(0, 'success', $result);
    }

}