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




