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


/**
 * 数据流调试
 */

/**
 * 1 : 设置
 */

//设置一
//$arr = [
//    'Title'=>"irones"
//];
//C($arr);
//D(C());
//D(C('Title'));

//设置二
//C('rec','123');
//D(C());

//2 : 读取


C($as);
C($as2);            //设置

//读取一 ：
//1 : 返回全部
//D(C());

//读取二
//2 : 返回相关
//D(C(['db','cookie']));

//3 : 返回一个
//D(C('db'));

/*
 * //获取
$res = C(['db2'.'cookie2']);    //相关
$res = C('rec');                //单一
$res = C();                     //全部
D($res);
 */



