<?php

class wishingWallModel extends Common
{
    /*
     * wishing表单条信息查询
     */
    public function getWishingInfo($data)
    {
        $info = pdo_get_cj("choujiang_wishing", $data);
        $info['goods_img'] = $this->getImage($info['goods_img']);
        return $info;
    }

    /*
     * wishing表单条信息查询
     */
    public function getWishingRecordInfo($data)
    {
        $info = pdo_get_cj("choujiang_wishing_record", $data);
        return $info;
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
     * wishing_record表 插入数据
     */
    public function wishingRecordInsert ($data = array(), $replace = FALSE) {
        $insertResult = pdo_insert_cj('choujiang_wishing_record', $data, $replace);
        return $insertResult;
    }

    /*
     * wishing表 列表信息查询
     */
    public function getWishingList($condition = array(), $fields = array(), $keyfield = '', $orderby = array(), $limit = array())
    {
        // 生成条查询条件
        if ( !empty($condition) ) {
            $fields = SqlPaser::parseParameter($condition, 'AND');
        } else {
            $fields = [
                'fields' => 1,
                'params' => [],
            ];
        }

        $orderbyStr = self::parseOrderby($orderby);

        $limitStr = '';
        if ( !empty($limit) ) {
            $offset = ($limit[0]-1)*$limit[1];
            $limitStr = "LIMIT {$offset},{$limit[1]}";
        }

        $total = pdo_fetch_cj("SELECT count(*) AS count FROM " . tablename_cj('choujiang_wishing') . " WHERE {$fields['fields']}", $fields['params']);
        $result = pdo_fetchall_cj("SELECT * FROM " . tablename_cj('choujiang_wishing') . " WHERE {$fields['fields']}  {$orderbyStr} {$limitStr}", $fields['params']);

        foreach($result as $key => $val) {
            $result[$key]['goods_img'] = $this->getImage($val['goods_img']);
        }

        $list = [
            "total" => $total['count'],
            "list" => $result
        ];
        return $list;
    }

    /*
  * wishing表 列表信息查询
  */
    public function getWishingRecordList($condition = array(), $fields = array(), $keyfield = '', $orderby = array(), $limit = array())
    {
        // 生成条查询条件
        if ( !empty($condition) ) {
            $fields = SqlPaser::parseParameter($condition, 'AND');
        } else {
            $fields = [
                'fields' => 1,
                'params' => [],
            ];
        }

        $orderbyStr = self::parseOrderby($orderby);

        $limitStr = '';
        if ( !empty($limit) ) {
            $offset = ($limit[0]-1)*$limit[1];
            $limitStr = "LIMIT {$offset},{$limit[1]}";
        }

        $total = pdo_fetch_cj("SELECT count(*) AS count FROM " . tablename_cj('choujiang_wishing_record') . " WHERE {$fields['fields']}", $fields['params']);
        $result = pdo_fetchall_cj("SELECT * FROM " . tablename_cj('choujiang_wishing_record') . " WHERE {$fields['fields']}  {$orderbyStr} {$limitStr}", $fields['params']);

        foreach ($result as $key => $val) {
            $userInfo = pdo_get_cj("choujiang_user", ['openid'=>$val['openid']]);
            $result[$key]['avatar'] = $userInfo['avatar'];
        }

        $list = [
            "total" => $total['count'],
            "list" => $result
        ];
        return $list;
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
        if (strexists($field, '.') || strexists($field, '*') || strexists($field, '<>')) {
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