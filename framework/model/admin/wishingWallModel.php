<?php

class admin_wishingWallModel
{
    /*
     * wishing表单条信息查询
     */
    public function getWishingInfo($data)
    {
        $info = pdo_get_cj("choujiang_wishing", $data);

        $userInfo = pdo_get_cj("choujiang_user", ["openid" => $info['openid']]);
        $info['goods_img'] = $this->getImage($info['goods_img']);

        $info['avatar'] = $userInfo['avatar'];
        $info['nickname'] = $userInfo['nickname'];

        return $info;
    }

    /*
     * wishing_goods表 单条信息查询
     */
    public function getWishingGoodsInfo($data)
    {
        $info = pdo_get_cj("choujiang_wishing_goods", $data);
        return $info;
    }

    /*
     * wishing表 列表信息查询
     */
    public function getWishingList($condition = array(), $fields = array(), $keyfield = '', $orderby = array(), $limit = array())
    {
        $where = '';
        if ( !empty($condition) ) {
            foreach ( $condition as $key => $val) {
                if (strpos(trim($key), " ") !== false) {
                    $temp = explode(" ", $key);
                    if ( $temp[1] == "in") {
                        $where .= "W.".$temp[0]." {$temp[1]} ".$val." and ";
                    } else {
                        $where .= "W.".$temp[0]." {$temp[1]} '".$val."' and ";
                    }
                } else {
                    $where .= "W.".$key."='".$val."' and ";
                }
            }
            $where = preg_replace("/ and $/","",$where);
        } else {
            $where = 1;
        }

//        $useOrderby = [];
//        if( !empty($orderby) ){
//            foreach($orderby as $key => $val) {
//                $useOrderby["W.".$key] = $val;
//            }
//        }
        $orderbyStr = self::parseOrderby($orderby, "W");
//        if ( !empty($orderby) ) {
//            $orderbyStr = admin_wishingWallModel::parseOrderby($orderby);
//            $orderbyStr .= 'ORDER BY ';
//            foreach ( $orderby as $key => $val) {
//                if () {
//
//                }
//            }
//        }

        $limitStr = '';
        if ( !empty($limit) ) {
            $offset = ($limit[0]-1)*$limit[1];
            $limitStr = "LIMIT {$offset},{$limit[1]}";
        }

        $total = pdo_fetch_cj('SELECT count(*) as count FROM ' . tablename_cj('choujiang_wishing') . " AS W where {$where}");
        $result = pdo_fetchall_cj("SELECT W.*, WG.goods_id FROM " . tablename_cj('choujiang_wishing') . " AS W LEFT JOIN " . tablename_cj('choujiang_wishing_goods') . " AS WG ON W.id = WG.wishing_id where {$where} {$orderbyStr} {$limitStr}");
//        $result = pdo_getall_cj("choujiang_wishing", $condition, $fields, $keyfield, $orderby, $limit);
//        foreach ( $result as $key => $val) {
//            $oneGoods = pdo_fetch_cj('SELECT * FROM ' . tablename_cj('choujiang_wishing_goods') . " where wishing_id{$val['id']}");
//            $result[$key]['goods'] = $oneGoods;
//        }

        foreach ($result as $k =>$v) {
            $userInfo = pdo_get_cj("choujiang_user", ["openid" => $v['openid']]);
            $result[$k]['avatar'] = $userInfo['avatar'];
            $result[$k]['nickname'] = $userInfo['nickname'];
        }

        $list = [
            "total" => $total['count'],
            "list" => $result
        ];
        return $list;
    }

    /*
     * wishing_record表 列表查询
     */
    public function wishingRecordList($condition = array(), $fields = array(), $keyfield = '', $orderby = array(), $limit = array())
    {
        $where = '';
        if ( !empty($condition) ) {
            foreach ( $condition as $key => $val) {
                $where .= $key."='".$val."' AND ";
            }
            $where = preg_replace("/ AND $/","",$where);
        } else {
            $where = 1;
        }

        $useOrderby = [];
        if( !empty($orderby) ){
            foreach($orderby as $key => $val) {
                $useOrderby[$key] = $val;
            }
            $orderbyStr = self::parseOrderby($useOrderby);
        } else {
            $orderbyStr = "ORDER BY id DESC";
        }



        $limitStr = '';
        if ( !empty($limit) ) {
            $offset = ($limit[0]-1)*$limit[1];
            $limitStr = "LIMIT {$offset},{$limit[1]}";
        }

        $total = pdo_fetch_cj('SELECT count(*) as count FROM ' . tablename_cj('choujiang_wishing_record') . " where {$where}");
        $result = pdo_fetchall_cj("SELECT * FROM " . tablename_cj('choujiang_wishing_record') . " where {$where} {$orderbyStr} {$limitStr}");

        foreach ($result as $key => $val) {
            $userInfo = pdo_get_cj("choujiang_user", ["openid" => $val['openid']]);
            $result[$key]['nickname'] = $userInfo['nickname'];
            $result[$key]['avatar'] = $userInfo['avatar'];
        }

        $list = [
            "total" => $total['count'],
            "list" => $result
        ];
        return $list;
    }

    /*
     * wishing表 数据更新
     */
    public function updataWishing ($data = array(), $params = array(), $glue = 'AND') {
        $updateResult = pdo_update_cj('choujiang_wishing', $data, $params, $glue);
        return $updateResult;
    }

    /*
     * wishing表 插入数据
     */
    public function wishingInsert ($data = array(), $replace = FALSE) {
        $updateResult = pdo_insert_cj('choujiang_wishing', $data, $replace);
        return $updateResult;
    }

    /*
     * wishing_goods表 插入数据
     */
    public function wishingGoodsInsert ($data = array(), $replace = FALSE) {
        $insertResult = pdo_insert_cj('choujiang_wishing_goods', $data, $replace);
        return $insertResult;
    }

    /*
     * 生成排序SQL语句
     */
    public static function parseOrderby($orderby, $alias = '') {
        $orderbysql = '';
        if (empty($orderby)) {
            return $orderbysql;
        }

        if (!is_array($orderby)) {
            $orderby = explode(',', $orderby);
        }
        foreach ($orderby as $i => &$row) {
            $row = strtolower($row);
            list($field, $orderbyrule) = explode(' ', $row);

            if ($orderbyrule != 'asc' && $orderbyrule != 'desc') {
                unset($orderby[$i]);
            }
            $field = self::parseFieldAlias($field, $alias);
            $row = "{$field} {$orderbyrule}";
        }
        $orderbysql = implode(',', $orderby);
        return !empty($orderbysql) ? " ORDER BY $orderbysql " : '';
    }

    /*
     * 生成包含别名的字段名
     */
    private static function parseFieldAlias($field, $alias = '') {
        if (strexists($field, '.') || strexists($field, '*')) {
            return $field;
        }
        if (strexists($field, '(')) {
            $select_fields = str_replace(array('(', ')'), array('(' . (!empty($alias) ? "`{$alias}`." : '') .'`',  '`)'), $field);
        } else {
            $select_fields = (!empty($alias) ? "`{$alias}`." : '') . "`$field`";
        }
        return $select_fields;
    }

    //服务器图片是否存在
    public function getImage($img, $isStyle = true)
    {
        global $_W;
        $item = pdo_get_cj('choujiang_base', ["uniacid" => $_W['uniacid']]);
        if(strstr($img,'https://') !== false || strstr($img,'http://') !== false) {
            return $img;
        }else{
            if ($item['type'] == 1) {  //aliyun osss
                if($item['cdn_speed'] && $item['cdn_url']){ //cdn加速开启 cdn域名和图片接口存在
                    $url = $item['cdn_url'].$img;
                }else{ ///oss
                    $url = $item['url'] . $img;
                }

                if ($item['img_api'] && $isStyle) {//图片样式
                    $url = $url .'?x-oss-process=style/'.$item['img_api'];
                }

                return $url;
            } else { //本地存储
                $item['url'] = $_W['siteroot'];
                return $item['url'] . $img;
            }
        }
    }

}