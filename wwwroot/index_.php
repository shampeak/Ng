<?php
/*
 * Copyright 2015 狂奔的蜗牛.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * MicroPHP
 *
 * An open source application development framework for PHP 5.2.0 or newer
 *
 * @package       MicroPHP
 * @author        狂奔的蜗牛
 * @email         672308444@163.com
 * @copyright     Copyright (c) 2013 - 2015, 狂奔的蜗牛, Inc.
 * @link          http://git.oschina.net/snail/microphp
 * @since         Version 2.3.2
 * @createdtime   2015-05-15 15:49:19

 */


//获取完整的url
echo 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
echo '<br>';
echo 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'];	#http://localhost/testurl.php?id=3

//==============================================
define('GRACE', '../Grace/');
include("../vendor/autoload.php");
include(GRACE."Bootstrap.php");



$system = include "../MicroPHP.System.php";

/* End of file index.php */
include('../MicroPHP.php');
MpRouter::setConfig($system);           //设置
MpRouter::loadClass();

//

//路由信息

//
//querystring=[]
//c=>
//a=>
//params=>
//prefix=>

//[class] => Welcome
//[method] => doIndex


//[module] =>
//[query] =>
//[mpath] => welcome.index
//[m] => index
//[c] => welcome
//[prefix] => do
//[cpath] => welcome
//[folder] =>
//    [file] => E:\phpleague\Study\microphp.so/application/controllers\welcome.php
//[class] => Welcome
//[method] => doIndex
//[parameters] => Array
//(
//)


