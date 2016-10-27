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
 * 自动加载
 */


$class = new \Test();
$class->run2();