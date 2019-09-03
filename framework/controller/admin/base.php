<?php
define('IN_SYS', true);
require_once IA_ROOT . "/addons/choujiang_page/common.func.php";

class cj_admin_base extends Common
{
    public function info()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $item = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_base') . " WHERE uniacid = :uniacid", array(':uniacid' => $uniacid));
        $info = '1111111111111111';
        include $this->template('choujiang_test');
    }

    public function send()
    {
        $config['smtp_server'] = 'smtp.163.com';
        $config['smtp_port'] = '25';
        $config['smtp_user'] = 'h916771081@163.com';
        $config['smtp_pwd'] = '3h1995';
        $code = rand(100000, 999999);
        $to = '916771081@qq.com';
        $subject = '尊敬的会员，您的验证码为...';
        $content = '亲爱的幸运28用户'.','.'您的绑定验证码是'.$code;
        $data = send_email($config, $to, $subject, $content);
        var_dump($data);
    }

}