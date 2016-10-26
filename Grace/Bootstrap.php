<?php

namespace Grace;

!defined('GRACE') && die('Undefine GRACE');
define('IN_GRACE_APP',  TRUE);      //标记

include(GRACE."Helper.php");

/**
 * 定义常量
 */
define('GRACE_PATH',    dirname(strtr(__FILE__,'\\','/'))."/");
define('WDS',           DIRECTORY_SEPARATOR);
define('DEBUG',         Config('Debug'));
define('CHARSET',       Config('Charset'));

@ini_set('magic_quotes_sybase', 0); //关闭
@ini_set("magic_quotes_runtime",0);

/**
 * debug = false 的时候关闭错误显示
 */
ini_set("display_errors",  Config('Debug'));

/**
 * 异常显示的级别
 */
ini_set('error_reporting',  Config('Error_report'));

function_exists('date_default_timezone_set') && date_default_timezone_set(Config('Timezone'));

/**
 * 版本限制
 * 5.3为最低版本
 */
version_compare('5.3',PHP_VERSION,'>') && die('GracePHP requires PHP version 5.3 or higher. You are running version '.PHP_VERSION.'.');
define('__SELF__',	$_SERVER['PHP_SELF']);
define('__REF__', 	$_SERVER['HTTP_REFERER']);

//初始时间和内存   //对时间和内存进行标记
C(['Ini' => [
    'Microtime' => Mrtime(), //开始时间标记 毫秒
    'Time'      => time(),      //开始时间标记
    'Memory'    => MemoryUsed(),//开始内存标记
        ]
]);

//OK 初始化完成
//================================================

//获取路由字段 -> in

// 执行控制器 [ 根据路由 ]


/**
 *
//获取输入的信息 => Input
//1 : 实体转义
$rc = ['par'=>'123'];
$ns = \Grace\Input::xss_clean($rc);
D($ns);

//是否ajax
$res = \Grace\Input::is_ajax();
D($res);
 *
$res = \Grace\Input::isCli();    //== is_Cli()
D($res);

//cookies
server('cookies')->set('name',"<a>irones</a>",50);
$res = \Grace\Input::cookie('name',true);
D($res);

//获取get
$res = \Grace\Input::get();
$res = \Grace\Input::post();
$res = \Grace\Input::cookie();
$res = \Grace\Input::session();
D($res);

//获取路由 控制器根据该信息执行
 *

//$res = \Grace\Router::GetRouter();                //根据参数
//$res = \Grace\Router::GetRouter('/afsd',"123");   //根据地址栏路由
D($res);

 */

class Bootstrap {

    public static function Get($ca,$params)             //调用控制器执行结果集
    {
        $res = \Grace\Controller::Run($ca,$params);
        return $res;
    }
        //实例开始执行
    public static function Run() {
        \Grace\Controller::Run();        //控制器执行
    }

}




//-------------------------------------------------
//查看所有定义好的常量
//调试
//-------------------------------------------------
//print_r( get_defined_constants(true)['user']);

