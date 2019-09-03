<?php

class Cj_Score extends Common
{

    public function __construct()
    {
        $this->model = new wishingWallModel();
        $this->score = new Score();
        $this->redis = connect_redis();
        parent::__construct();
    }

    /*
     * 积分列表接口
     */

    public function scoreList()
    {
        global $_GPC,$_W;
        $psize = 15;
        $pindex = $_GPC['page'] ? trim($_GPC['page']) : 1;
        $openid = trim($_GPC['openId']);

        /// 获取三个月的起止时间
        $dateTimeStart = date('Y-m-d 00:00:00', strtotime(date('Y-m-01') . ' -2 month'));
        $nowMonthFristDay = date('Y-m-01', strtotime(date("Y-m-d")));
        $dateTimeEnd = date('Y-m-d', strtotime("{$nowMonthFristDay} +1 month -1 day"))." 23:59:59";

        $sql1 = "SELECT count(*) AS total FROM " . tablename_cj('choujiang_score_record') . " WHERE openid='{$openid}' AND create_at>='{$dateTimeStart}' AND create_at<='{$dateTimeEnd}'";
        $ListCount = pdo_fetch_cj($sql1);

        $sql = "SELECT * FROM " . tablename_cj('choujiang_score_record') . " WHERE openid='{$openid}' AND create_at>='{$dateTimeStart}' AND create_at<='{$dateTimeEnd}' ORDER BY create_at DESC LIMIT " . ($pindex - 1) * $psize . ',' . $psize;
        $List = pdo_fetchall_cj($sql);


        /// 统计三个月收入和支出

        // 收入支出类型组合
        $addScoreTypes = [];
        $useScoreTypes = [];
        foreach ($_W['score_type_all'] as $key => $val) {
            if ($val['types'] == "add") {
                $addScoreTypes[] = $key;
            } else if ($val['types'] == "use") {
                $useScoreTypes[] = $key;
            }
        }
        // 收入类型
        $sqlWhere['add'] = implode(",",$addScoreTypes);
        // 支出类型
        $sqlWhere['use'] = implode(",",$useScoreTypes);

        // 当月
        $monthUse['thisMonth']['start'] = date("Y-m-01");
        $monthUse['thisMonth']['end'] = date("Y-m-t");
        // 上个月
        $monthUse['lastMonth']['start'] = date("Y-m-01",strtotime("{$monthUse['thisMonth']['start']} -1 month"));
        $monthUse['lastMonth']['end'] = date("Y-m-t",strtotime("{$monthUse['thisMonth']['start']} -1 day"));
        // 上两个月
        $monthUse['lastTwoMonth']['start'] = date("Y-m-01",strtotime("{$monthUse['lastMonth']['start']} -1 month"));
        $monthUse['lastTwoMonth']['end'] = date("Y-m-t",strtotime("{$monthUse['lastMonth']['start']} -1 day"));

        $threeMonthTotal = [];
        foreach ($monthUse as $key => $val) {
//            $monthKey = preg_replace("/-01/", "", $val['start']);
            $threeMonthTotal[$key]['monthStart'] = $val['start'];
            $threeMonthTotal[$key]['monthEnd'] = $val['end'];
            if ( $key == "thisMonth" ) {
                $threeMonthTotal[$key]['today'] = date('Y-m-d');
                $threeMonthTotal[$key]['yesterday'] = date('Y-m-d', strtotime("-1 day"));
                $threeMonthTotal[$key]['twoLastDay'] = date('Y-m-d', strtotime("-2 day"));
            }

            foreach ($sqlWhere as $sqlKey => $sqlVal) {
                $sqlUseTotal = "SELECT SUM(`achieve_score`) AS total FROM " . tablename_cj('choujiang_score_record') . " WHERE openid='{$openid}' AND score_types in ({$sqlVal}) AND create_at>='{$val['start']} 00:00:00' AND  create_at<='{$val['end']} 23:59:59'";
                $useTotal = pdo_fetch_cj($sqlUseTotal);
                $threeMonthTotal[$key][$sqlKey] = $useTotal['total'] == null ? 0 : (int)$useTotal['total'];
            }
        }

        /// 设置显示的使用详情
        foreach ($List as $key => $val) {
            $List[$key]['message'] = $_W['score_type_all'][$val['score_types']]['info'];
            if ( $val['score_types']== 1 || $val['score_types']== 3) {
                $List[$key]['types'] = 1;
            } else if ($val['score_types']== 1) {
                $List[$key]['types'] = 2;
            }
        }

        /// 当前用户积分总记录
        $sql = "SELECT * FROM " . tablename_cj('choujiang_score') . " WHERE openid='{$openid}'";
        $userScore = pdo_fetch_cj($sql);

        $resutl = [
            'list' => $List,
            'totalInfo' => $threeMonthTotal,
            'userScore' => (int)$userScore['total_score']-$userScore['use_score'],
            'total' => (int)$ListCount['total'],
        ];

        return $this->result(0, 'success', $resutl);
    }

}