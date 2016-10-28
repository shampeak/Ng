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
 * server容器调试
 */


//实例化server容器
//$config = config();
//$server =  \Grace\Server\Server::getInstance($config);
//var_dump($server);



//db对象
//$db = server('db');
//$res = server('db')->getall("select * from user_group");
//print_r($res);


//cookie
//$cookies = server('Cookies');
//server('Cookies')->set("123","test",10);
//$res = server('Cookies')->get("123");
//D($res);
//

//server('Cache')->set("vvv2","test",10);
//$res = server('Cache')->get("vvv2");
//D($res);


//Log
//server('Log')->log("warning","zhuyi");


//Parsedown
//$rc = "
//## title
//>nr
//";
//$res = server('Parsedown')->text($rc);
//D($res);

//Mmc：Memcache          //需要安装memcache
//$mmc = server('Mmc');
//var_dump($mmc);
//server('Mmc')->set("vvv2","test",10);
//$res = server('Mmc')->get("vvv2");
//D($res);
