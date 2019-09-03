<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/4 0004
 * Time: 下午 4:51
 */

class Score
{

//    public function __construct()
//    {
//        global $_W;
//        $uniacid = $_W['uniacid'];
//        $this->baseConfig = $item = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_base') . " WHERE uniacid = :uniacid", array(':uniacid' => $uniacid));
//        $priceArr =explode('-',$this->baseConfig['wechat_rand_price']);
//        $priceList =json_decode($this->baseConfig['probability_num'],true);
//        $this->baseConfig['loopPrice']=['min'=>$priceArr[0],'max'=>$priceArr[1],'floorNum'=>$this->floorNum,'pirceList'=>$priceList];
//        if ($item['type']) {
//            $this->attachurl = $item['url'];
//        } else {
//            $this->attachurl = $_W['attachurl'];
//        }
//    }

    /*
     * 获取积分
     */
    public function addScore( $openid, $score, $info="" )
    {
//        echo "{$openid}添加{$score}积分";
        $writeTime = date("Y-m-d H:i:s");
        $scoreInfo = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_score') . " WHERE openid = :openid", [':openid' => $openid]);

        if (is_array($info)) {
            $info = json_encode($info);
        }

        $data = [
            'openid' => $openid,
            'achieve_score' => $score,
            'score_types' => 1,
            'score_status' => 1,
            'extact' => $info,
            'create_at' => $writeTime,
            'update_at' => $writeTime
        ];
        if($scoreInfo){
            $data['all_score'] = $scoreInfo['total_score']+$score;
            $data['balance_score'] = $scoreInfo['total_score']-$scoreInfo['use_score']+$score;
//            $resultScore = pdo_update_cj("choujiang_score", ['total_score +=' => $score, 'update_at' => $writeTime],['openid' => $openid]);
            $resultScore = $this->updateScore(['total_score +=' => $score, 'update_at' => $writeTime],['openid' => $openid]);
        }else{
            $userInfo = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_user') . " WHERE openid = :openid", [':openid' => $openid]);
            $data['all_score'] = $score;
            $data['balance_score'] = $score;
//            $resultScore = pdo_insert_cj("choujiang_score", ['uid' => $userInfo['id'], 'openid' => $openid, 'nickname' => $userInfo['nickname'], 'total_score' => $score, 'create_at' => $writeTime, 'update_at' => $writeTime]);
            $resultScore = $this->insertScore(['uid' => $userInfo['id'], 'openid' => $openid, 'nickname' => $userInfo['nickname'], 'total_score' => $score, 'create_at' => $writeTime, 'update_at' => $writeTime]);
        }
        $resultRecord = $this->insertScoreRecord($data);
//        $resultRecord = pdo_insert_cj("choujiang_score_record", $data);

        if($resultRecord && $resultScore){
            return true;
        }else{
            return false;
        }

    }

    /*
     * 使用积分
     */
    public function useScore( $openid, $score, $info='' )
    {

        $writeTime = date("Y-m-d H:i:s");
        $scoreInfo = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_score') . " WHERE openid = :openid", [':openid' => $openid]);

        if (is_array($info)) {
            $info = json_encode($info);
        }

        $data = [
            'openid' => $openid,
            'achieve_score' => $score,
            'score_types' => 2,
            'score_status' => 1,
            'extact' => $info,
            'create_at' => $writeTime,
            'update_at' => $writeTime
        ];
        if($scoreInfo){
            if($scoreInfo['total_score']-$scoreInfo['use_score']>=$score){
                $data['all_score'] = $scoreInfo['total_score'];
                $data['balance_score'] = $scoreInfo['total_score']-$scoreInfo['use_score']-$score;
//                $resultScore = pdo_update_cj("choujiang_score", ['use_score +=' => $score, 'update_at' => $writeTime],['openid' => $openid]);
                $resultScore = $this->updateScore(['use_score +=' => $score, 'update_at' => $writeTime],['openid' => $openid]);
            }else{
                return false;
            }
        }else{
            return false;
        }
        $resultRecord = $this->insertScoreRecord($data);
//        $resultRecord = pdo_insert_cj("choujiang_score_record", $data);

        if($resultRecord && $resultScore){
            return true;
        }else{
            return false;
        }
    }

    /*
     * 积分更新到积分表里
     */
    private function updateScore( $data, $where ){
        return pdo_update_cj("choujiang_score", $data,$where);
    }

    /*
     * 积分添加到积分表里
     */
    private function insertScore($data){
        return pdo_insert_cj("choujiang_score",$data);
    }

    /*
     * 积分变更记录添加到积分记录表里
     */
    private function insertScoreRecord($data){
        return pdo_insert_cj("choujiang_score_record",$data);
    }

}