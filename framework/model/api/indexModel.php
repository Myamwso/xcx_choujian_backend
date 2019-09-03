<?php

class indexModel
{
    public function getBaseInfo()
    {
        global $_W;
        $uniacid = $_W['uniacid'];
        $info = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_base') . " WHERE uniacid = :uniacid", array(':uniacid' => $uniacid));
        return $info;
    }
}