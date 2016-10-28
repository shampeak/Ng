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
 * 中断
 */


/**
 * 最终流程走向
 * 1 : R跳转
 * R('http://www.baidu.com',1,"tiaozhuan");
 * R('http://www.baidu.com',2);
 * R('http://www.baidu.com');
*/

/*
 *
 * 2 : 中断
 * halt();
 * halt(404);
 * halt(500);
 * RE($url,5,"操作成功")            ->R //todo
 */

