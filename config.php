<?php
/**
 * 多应用配置文件
 *
 * @author chizhehong
 * @url
 */

//var_dump(456456);
global $_W;

$_W['module_prefix'] = array();
$_W['module_prefix'][10] = "ims_";
$_W['module_prefix'][11] = "dj_";

$_W['redis_key'] = [
    'goods_detail' => 'cj_goods_detail:%s:%s', //$uniacid:$goods_id
    'user_red_share_num' => 'cj_user_red_share:%s:%s', //$uniacid:$day
    'wishing_release' => 'cj_wishing_release_week:%s:%s', //$uniacidi:$openid
    'wishing_likes' => 'cj_wishing_likes_daily:%s:%s', //$uniacid:$openid
    'wishing_push_message' => 'cj_wishing_message:%s', //$uniacid
];
$_W['member_info'] = 'member_info:%s:%s';
$_W['share_type_all'] = [
    /// 分享心愿
    1 => "心愿分享",
];

$_W['score_type_all'] = [
    /// 积分类型 info为前端显示信息，types为使用积分或者赚积分
    1 => [ 'info' =>"参与活动", "types" => "add"],
    2 => [ 'info' =>"消费积分", "types" => "use"],
    3 => [ 'info' =>"分享心愿", "types" => "add"],
];
