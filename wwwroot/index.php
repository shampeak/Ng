<?php

/**
 * 测试
 */


/**
 * 错误提示
 * $error_reporting       = E_ALL ^ E_NOTICE;
* ini_set('error_reporting', $error_reporting);
 *
*/

define('GRACE', '../Grace/');
include("../vendor/autoload.php");
include(GRACE."Bootstrap.php");



print_r($res);
echo '-';
\Grace\Bootstrap::Run();        //执行







//输入的信息

/**
 * 1 ： 
 */



exit;


$res = config('Router');
D($res);

$query = \Grace\Req\Environment::getInstance()->all();
D($query);


echo '--';
exit;

/**
 * 分析
 */

//数据调用
//$res = \Grace\Bootstrap::Get('home/res',444);
//$res .= \Grace\Bootstrap::Get('home/res',444);
//==
//$res = \Grace\Controller::Run('home/res',444);
//==
//$res = \Grace\Controller::ControllerRun(\Grace\Router::GetRouter('home/res',444));


//==============================================
// |

//server()
//c()       数据流
//config()  配置读取
//headers();指定编码
//流程走向
//1 : R         跳转
//2 : halt();   中断

//==============================================





/**
 * 最终流程走向
 * 1 : R跳转
 * R('http://www.baidu.com',1,"tiaozhuan");
 * R('http://www.baidu.com',2);
 * R('http://www.baidu.com');
 * 2 : 中断
 * halt()
 * halt(404)
 * halt(500)
 * RE($url,5,"操作成功")            ->R
 */



/**
 * 数据流调试
 *
 * 1 : 设置
 * C($arr);
 * C('rec','123');
 * 2 : 返回全部
 * C()
 * 3 : 返回相关
 * C(['db','cookie'])
 * 4 : 返回一个
 * C('db')
 *
 *
$as = [
'db' => [
'dbname'        => 'user1',
'dbpassword'    => 'user11',
],
'cookie' => [
'dbname1'        => 'cccc1',
'dbpassword2'    => 'cccc11',
],
];
$as2 = [
'db2' => [
'dbname'        => 'user2',
'dbpassword'    => 'user12',
],
'cookie2' => [
'dbname1'        => 'cccc2',
'dbpassword2'    => 'cccc12',
],
];
C($as);
C($as2);            //设置
C('rec','123');     //设置
//获取
$res = C(['db2'.'cookie2']);    //相关
$res = C('rec');                //单一
$res = C();                     //全部
D($res);
 *
 *
 */




/**
 * server容器调试
 */
/*
define('GRACE', '../Grace/');
include("../vendor/autoload.php");
include(GRACE."Bootstrap.php");

//实例化server容器
    $config = config();
    $server =  \Grace\Server\Server::getInstance($config);
    var_dump($server);

//db对象
    $db = server('db');
    $res = server('db')->getrow("select * from user");
    D($res);

//cookie
    $cookies = server('Cookies');
    server('Cookies')->set("123","test",10);
    $res = server('Cookies')->get("123");
    D($res);

//cache
    server('Cache')->set("vvv2","test",10);
    $res = server('Cache')->get("vvv2");
    D($res);

//Log
    server('Log')->log("warning","zhuyi");

//Parsedown
$rc = "
## title
>nr
";
$res = server('Parsedown')->text($rc);
D($res);
*/

