<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
defined('IN_IA') or exit('Access Denied');

function connect_redis() {
    global $_W;

    try {
        $redisConfig = $_W['config']['setting']['redis'];

        $redis = new redis();
        $redis->connect($redisConfig['server'], $redisConfig['port']);
        if (! empty ($redisConfig['requirepass']) ) {
            $redis->auth($redisConfig['requirepass']);
        }

        $redis->select(0);
        return $redis;
    } catch (Exception $e) {
        return false;
    }
}

function tablename_cj($table, $una="", $module_prefix='') {
    global $_W;
    if($module_prefix==""){
        $module_prefix = $_W['module_prefix'];
    }
    if($una==""){
        $una = $GLOBALS['_W']['uniacid'];
    }
    if(empty($GLOBALS['_W']['config']['db']['master'])) {
        if($una){
            return "`{$module_prefix[$una]}{$table}`";
        }
        return "`{$GLOBALS['_W']['config']['db']['tablepre']}{$table}`";
    }
    return "`{$GLOBALS['_W']['config']['db']['master']['tablepre']}{$table}`";
}



function pdo_cj() {
    global $_W;
    //var_dump($_W['config']['db']);
    //exit;
    static $db;
    if(empty($db)) {
        if($_W['config']['db']['slave_status'] == true && !empty($_W['config']['db']['slave'])) {
            load()->classs('slave.db');
            $db = new SlaveDb('master');
        } else {
            load()->classs('db');
            //require_once __DIR__ . "/classes/cjdb.class.php";
            if(empty($_W['config']['db']['master'])) {
                include IA_ROOT . '/data/config.php';
                $_W['config']['db']['master'] = $GLOBALS['_W']['config']['db'];
                $config['db']['master']['tablepre'] = $_W['module_prefix'][$_W['uniacid']] ;
                $db = new DB($config['db']['master']);
            } else {
                $db = new DB('master');
            }
        }
    }
    return $db;
}


function pdos_cj($table = '') {
    return load()->singleton('Query');
}

function pdo_exec_org_cj($sql) {
    $pdoCj = pdo_cj()->getPDO();
    $temp = [];
    foreach ($pdoCj->query($sql)->fetchAll(pdo::FETCH_ASSOC) as $k => $row) {
        array_push($temp,$row);
    }
    return $temp;
}


function pdo_query_cj($sql, $params = array()) {
    return pdo_cj()->query($sql, $params);
}


function pdo_fetchcolumn_cj($sql, $params = array(), $column = 0) {
    return pdo_cj()->fetchcolumn($sql, $params, $column);
}

function pdo_fetch_cj($sql, $params = array()) {
    return pdo_cj()->fetch($sql, $params);
}

function pdo_fetchall_cj($sql, $params = array(), $keyfield = '') {
    return pdo_cj()->fetchall($sql, $params, $keyfield);
}


function pdo_get_cj($tablename, $condition = array(), $fields = array()) {
    return pdo_cj()->get($tablename, $condition, $fields);
}

function pdo_getall_cj($tablename, $condition = array(), $fields = array(), $keyfield = '', $orderby = array(), $limit = array()) {
    return pdo_cj()->getall($tablename, $condition, $fields, $keyfield, $orderby, $limit);
}

function pdo_getslice_cj($tablename, $condition = array(), $limit = array(), &$total = null, $fields = array(), $keyfield = '', $orderby = array()) {
    return pdo_cj()->getslice($tablename, $condition, $limit, $total, $fields, $keyfield, $orderby);
}

function pdo_getcolumn_cj($tablename, $condition = array(), $field) {
    return pdo_cj()->getcolumn($tablename, $condition, $field);
}


function pdo_exists_cj($tablename, $condition = array()) {
    return pdo_cj()->exists($tablename, $condition);
}


function pdo_count_cj($tablename, $condition = array(), $cachetime = 15) {
    return pdo_cj()->count($tablename, $condition, $cachetime);
}


function pdo_update_cj($table, $data = array(), $params = array(), $glue = 'AND') {
    return pdo_cj()->update($table, $data, $params, $glue);
}


function pdo_insert_cj($table, $data = array(), $replace = FALSE) {
    return pdo_cj()->insert($table, $data, $replace);
}


function pdo_delete_cj($table, $params = array(), $glue = 'AND') {
    return pdo_cj()->delete($table, $params, $glue);
}


function pdo_insertid_cj() {
    return pdo_cj()->insertid();
}


function pdo_begin_cj() {
    pdo_cj()->begin();
}


function pdo_commit_cj() {
    pdo_cj()->commit();
}


function pdo_rollback_cj() {
    pdo_cj()->rollBack();
}


function pdo_debug_cj($output = true, $append = array()) {
    return pdo_cj()->debug($output, $append);
}

function pdo_run_cj($sql) {
    return pdo_cj()->run($sql);
}


function pdo_fieldexists_cj($tablename, $fieldname = '') {
    return pdo_cj()->fieldexists($tablename, $fieldname);
}

function pdo_fieldmatch_cj($tablename, $fieldname, $datatype = '', $length = '') {
    return pdo_cj()->fieldmatch($tablename, $fieldname, $datatype, $length);
}

function pdo_indexexists_cj($tablename, $indexname = '') {
    return pdo_cj()->indexexists($tablename, $indexname);
}


function pdo_fetchallfields_cj($tablename){
    $fields = pdo_fetchall("DESCRIBE {$tablename}", array(), 'Field');
    $fields = array_keys($fields);
    return $fields;
}


function pdo_tableexists_cj($tablename){
    return pdo_cj()->tableexists($tablename);
}

function pagination_cj($total, $pageIndex, $pageSize = 15, $url = '', $context = array('before' => 5, 'after' => 4, 'ajaxcallback' => '', 'callbackfuncname' => ''), $pageIndexName='page') {
    global $_W;
    $pdata = array(
        'tcount' => 0,
        'tpage' => 0,
        'cindex' => 0,
        'findex' => 0,
        'pindex' => 0,
        'nindex' => 0,
        'lindex' => 0,
        'options' => ''
    );
    if ($context['ajaxcallback']) {
        $context['isajax'] = true;
    }

    if ($context['callbackfuncname']) {
        $callbackfunc = $context['callbackfuncname'];
    }

    $pdata['tcount'] = $total;
    $pdata['tpage'] = (empty($pageSize) || $pageSize < 0) ? 1 : ceil($total / $pageSize);
    if ($pdata['tpage'] <= 1) {
        return '';
    }
    $cindex = $pageIndex;
    $cindex = min($cindex, $pdata['tpage']);
    $cindex = max($cindex, 1);
    $pdata['cindex'] = $cindex;
    $pdata['findex'] = 1;
    $pdata['pindex'] = $cindex > 1 ? $cindex - 1 : 1;
    $pdata['nindex'] = $cindex < $pdata['tpage'] ? $cindex + 1 : $pdata['tpage'];
    $pdata['lindex'] = $pdata['tpage'];

    if ($context['isajax']) {
        if (empty($url)) {
            $url = $_W['script_name'] . '?' . http_build_query($_GET);
        }
        $pdata['faa'] = 'href="javascript:;" {$pageIndexName}="' . $pdata['findex'] . '" '. ($callbackfunc ? 'onclick="'.$callbackfunc.'(\'' . $url . '\', \'' . $pdata['findex'] . '\', this);return false;"' : '');
        $pdata['paa'] = 'href="javascript:;" {$pageIndexName}="' . $pdata['pindex'] . '" '. ($callbackfunc ? 'onclick="'.$callbackfunc.'(\'' . $url . '\', \'' . $pdata['pindex'] . '\', this);return false;"' : '');
        $pdata['naa'] = 'href="javascript:;" {$pageIndexName}="' . $pdata['nindex'] . '" '. ($callbackfunc ? 'onclick="'.$callbackfunc.'(\'' . $url . '\', \'' . $pdata['nindex'] . '\', this);return false;"' : '');
        $pdata['laa'] = 'href="javascript:;" {$pageIndexName}="' . $pdata['lindex'] . '" '. ($callbackfunc ? 'onclick="'.$callbackfunc.'(\'' . $url . '\', \'' . $pdata['lindex'] . '\', this);return false;"' : '');
    } else {
        if ($url) {
            $pdata['faa'] = 'href="?' . str_replace('*', $pdata['findex'], $url) . '"';
            $pdata['paa'] = 'href="?' . str_replace('*', $pdata['pindex'], $url) . '"';
            $pdata['naa'] = 'href="?' . str_replace('*', $pdata['nindex'], $url) . '"';
            $pdata['laa'] = 'href="?' . str_replace('*', $pdata['lindex'], $url) . '"';
        } else {
            $_GET[$pageIndexName] = $pdata['findex'];
            $pdata['faa'] = 'href="' . $_W['script_name'] . '?' . http_build_query($_GET) . '"';
            $_GET[$pageIndexName] = $pdata['pindex'];
            $pdata['paa'] = 'href="' . $_W['script_name'] . '?' . http_build_query($_GET) . '"';
            $_GET[$pageIndexName] = $pdata['nindex'];
            $pdata['naa'] = 'href="' . $_W['script_name'] . '?' . http_build_query($_GET) . '"';
            $_GET[$pageIndexName] = $pdata['lindex'];
            $pdata['laa'] = 'href="' . $_W['script_name'] . '?' . http_build_query($_GET) . '"';
        }
    }

    $html = '<div><ul class="pagination pagination-centered">';
    if ($pdata['cindex'] > 1) {
        $html .= "<li><a {$pdata['faa']} class=\"pager-nav\">首页</a></li>";
        $html .= "<li><a {$pdata['paa']} class=\"pager-nav\">&laquo;上一页</a></li>";
    }
    if (!$context['before'] && $context['before'] != 0) {
        $context['before'] = 5;
    }
    if (!$context['after'] && $context['after'] != 0) {
        $context['after'] = 4;
    }

    if ($context['after'] != 0 && $context['before'] != 0) {
        $range = array();
        $range['start'] = max(1, $pdata['cindex'] - $context['before']);
        $range['end'] = min($pdata['tpage'], $pdata['cindex'] + $context['after']);
        if ($range['end'] - $range['start'] < $context['before'] + $context['after']) {
            $range['end'] = min($pdata['tpage'], $range['start'] + $context['before'] + $context['after']);
            $range['start'] = max(1, $range['end'] - $context['before'] - $context['after']);
        }
        for ($i = $range['start']; $i <= $range['end']; $i++) {
            if ($context['isajax']) {
                $aa = 'href="javascript:;" {$pageIndexName}="' . $i . '" '. ($callbackfunc ? 'onclick="'.$callbackfunc.'(\'' . $url . '\', \'' . $i . '\', this);return false;"' : '');
            } else {
                if ($url) {
                    $aa = 'href="?' . str_replace('*', $i, $url) . '"';
                } else {
                    $_GET[$pageIndexName] = $i;
                    $aa = 'href="?' . http_build_query($_GET) . '"';
                }
            }
            $html .= ($i == $pdata['cindex'] ? '<li class="active"><a href="javascript:;">' . $i . '</a></li>' : "<li><a {$aa}>" . $i . '</a></li>');
        }
    }

    if ($pdata['cindex'] < $pdata['tpage']) {
        $html .= "<li><a {$pdata['naa']} class=\"pager-nav\">下一页&raquo;</a></li>";
        $html .= "<li><a {$pdata['laa']} class=\"pager-nav\">尾页</a></li>";
    }
    $html .= '</ul></div>';
    //返回前恢复page值，为当前页面
    $_GET[$pageIndexName] = $pdata['cindex'];
    return $html;
}
