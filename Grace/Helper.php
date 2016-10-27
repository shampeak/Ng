<?php

!defined('IN_GRACE_APP') && die('Undefine GRACE2');

















function Model($Modelname = '',$params = [])
{
    $class = '\\App\\Model\\'.ucfirst(strtolower($Modelname));
    return new $class($params);
}







    /**
     * @param string $key
     * @param null   $value
     * @return array|mixed|null
     * 数据流 ESB
     */
    function C($key = '',$value=null){
        static $_configc = array();
        $args = func_num_args();
        if($args == 1){
            if(is_string($key)){  //如果传入的key是字符串
                return isset($_configc[$key])?$_configc[$key]:null;
            }
            if(is_array($key)){
                //如果传入的key是关联数组
                if(array_keys($key) !== range(0, count($key) - 1)){
                    $_configc = array_merge($_configc, $key);
                }else{
                    $ret = array();
                    foreach ($key as $k) {
                        $ret[$k] = isset($_configc[$k])?$_configc[$k]:null;
                    }
                    return $ret;
                }
            }
        }elseif($args == 2){
            if(is_string($key)){
                $_configc[$key] = $value;
            }else{
                die('Params Error!');
            }
        }else{
            return $_configc;
        }
        return null;
    }


    /**
     *
     * 对server对象的封装
     *
     */
    if (! function_exists('server')) {
        function server($make = null, $parameters = [])
        {
            static $_server = null;
            if(empty($_server)){
                $config = config();
                $_server = \Grace\Server\Server::getInstance($config);
            }
            if (empty($make)) {
                return $_server;
            }else{
                return $_server->make($make,$parameters);
            }
        }
    }

    /**
     * 获取配置文件和配置信息
     * 有参数返回相应配置,无参数返回全部
     * ESB
     */
    if (! function_exists('config')) {
        function config($key = null)
        {
            static $_config = array();
            if(empty($_config)){
                //寻找配置文件
                !is_file(GRACE.'Config/Config.php') && die("Miss ConfigFile : ".GRACE.'Config/Config.php');
                $_config = include(GRACE.'Config/Config.php');
            }
            if (empty($key)) {
                return $_config;
            }else{
                return isset($_config[$key])?$_config[$key]:[];
    //            return $_config[$key];
            }
        }
    }











/*
 * 调试用 终止,并且显示回溯
 * */
function halt($code = 200,$msg = ''){
    headers();
    switch ($code){
        case 404:
            $file = Config('Error_page_404');
            if(is_file($file)){
                include($file);
            }else{
                IF(DEBUG)  echo "Missfile : $file"."<br>";
                die("404 : $msg");
            }
            break;
        case 500:
        case 501:
        case 502:
            $file = Config('Error_page_50x');
            if(is_file($file)){
                include($file);
            }else{
                IF(DEBUG)  echo "Missfile : $file"."<br>";
                die("50x : $msg");
            }
            break;
        default:
            $file = Config('Error_page');
            if(is_file($file)){
                include($file);
            }else{
                IF(DEBUG)  echo "Missfile : $file"."<br>";
                die("50x : $msg");
            }
            break;
    }

    exit;
}


    /*
    |------------------------------------------------------
    | 系统 全局
    |------------------------------------------------------
    */

    /**
     * 调试用的函数
     */
    if (! function_exists('D')) {
        function D($arr = [])
        {
            if(!DEBUG) Die();
            echo '<pre>';
            print_r($arr);
            echo '<hr>';
            debug_print_backtrace();
            echo "</pre>";
            exit;
        }
    }


    //输出头信息
    function headers($st = '')
    {
        $Charset = $st?:(config('Charset')?config('Charset'):'utf-8');
        header("Content-type: text/html; charset=$Charset");
    }

    //页面跳转
    function R($url, $time=0, $msg='') {
        $url = trim($url);
        if (empty($msg)) $msg = "系统将在{$time}秒之后自动跳转到{$url}！";
        if (!headers_sent()) {
            //编码
            headers();
            // redirect
            if (empty($time)) {
                header('Location: ' . $url);
            } else {
                header("refresh:{$time};url={$url}");
                echo($msg);
            }
            exit();
        } else {
            $str = "<meta http-equiv='Refresh' content='{$time};URL={$url}'>";
            if (!empty($time)) $str .= $msg;
            exit($str);
        }
    }


/**
 * @return float
 * 时间标记
 */
function Mrtime() {
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

/**
 * @return string
 * 占用多少内存
 */
function MemoryUsed() {
    $size = memory_get_usage();
    $unit=array('B','KB','MB','GB','TB','PB');
    return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
}

/*
 |------------------------------------------------------
 | 对数据进行魔术变换
 |------------------------------------------------------
*/
function saddslashes($string) {
    if(is_array($string)) {
        foreach($string as $key => $val) {
            $string[$key] = saddslashes($val);
        }
    } else {
        $string = addslashes($string);
    }
    return $string;
}


function includeIfExist($path){
    if(file_exists($path)){
        include_once($path);
    }
}
