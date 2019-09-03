<?php
global $_W;
defined('IN_IA') or exit('Access Denied');

function autoLoad($classname)
{
    ///控制器目录
    if (preg_match('/admin/', $classname)) {
        $suf = 'admin/';
    } else {
        global $_GPC;
        if (preg_match('/Model/', $classname)) {
            $suf = 'api/';
        } else {
            switch ($_GPC['v']) {
                case 'v8':
                    $suf = 'api_v8/';
                    break;
                default:
                    $suf = 'api/';
            }
        }
    }
    ///模型目录
    if (preg_match('/Model/', $classname)) {
        $subDir = '/model/';
    } else {
        $subDir = '/controller/';
    }
    ///类名
    if (preg_match('/_/', $classname)) {
        $fileName = substr($classname, strpos($classname, "_") + 1);
        ///Admin
        if(preg_match('/admin/',$fileName)){
            $fileName = substr($fileName, strpos($fileName, "_") + 1);
        }
    }else{
        $fileName = $classname;
    }
    ///继承类
    if (preg_match('/Common/', $classname)) {
        $fileName = $classname;
        $suf = '';
    }
    $file = __DIR__ . $subDir . $suf . $fileName . '.php';
    if (file_exists($file)) {
        include_once($file);
        return true;
    }
    return false;
}

spl_autoload_register('autoLoad', false);