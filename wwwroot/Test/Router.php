<?php
/**
 * Created by PhpStorm.
 * User: shampeak
 * Date: 2016/10/26
 * Time: 23:56
 */


define('GRACE', '../../Grace/');
include("../../vendor/autoload.php");
include(GRACE."Bootstrap.php");


/**
 * 对控制器数据调用
 */


//地址栏路由
$router = \Grace\Router::GetRouter();
D($router);

//手动制定路由
$router = \Grace\Router::GetRouter('home/res',444);
D($router);