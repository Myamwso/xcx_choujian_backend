<?php
/**
 * nohup基类
 */
define('IN_IA', true);
define('IA_ROOT', str_replace("\\", '/', dirname(dirname(dirname(dirname(dirname(__FILE__)))))));

global $_W;
require(IA_ROOT.'/data/config.php');
require(IA_ROOT.'/addons/choujiang_page/config.php');
$libConfig['mysql']['we7_framework'] = $config['db']['master'];
$libConfig['module_prefix'] = $_W['module_prefix'];

$libConfig['redis'] = $config['setting']['redis'];