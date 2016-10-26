<?php

namespace Grace;

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2015/12/4 0004
 * Time: 16:37
 */

class Controller {

    private static $_config = [];
    public function __construct() {
    }

    /**
     * @return array
     * 得到的路由格式如下
Array
(
[Controller] => Irones
[Mothed] => Asdf
[Ext] =>
[REQUEST_METHOD] => GET
[Profix] => do
[ControllerBase] => ../Grace/App/
[ControllerFile] => ../Grace/App/Irones/Irones.php
[ControllerFile_] => ../Grace/App/Irones/Asdf.php
[Action] => doAsdf
)
     */
    public static function ControllerRun(array $router = [])
    {

        $_Controller = '\\App\\'.$router['Controller'].'\\'.$router['Controller'];
        $_Mothed = $router['Mothed'];
        $_Action = $router['Action'];
        $_Params = $router['Params'];

        $file = $router['ControllerBase'] . $router['Controller'] . '/BaseController.php';
        includeIfExist($file);
        $_file[] = $file;

        //Controller/Mothed.php
//        $file = $router['ControllerFile_'];
//        includeIfExist($file);
//        $_file[] = $file;

        if (!method_exists($_Controller, $_Action)) {
            $file = $router['ControllerFile'];
            includeIfExist($file);
            $_file[] = $file;
        }

        if (!method_exists($_Controller, $_Action)) {
            echo 'Miss Controller : ', $_Controller .'::'. $_Action .'<br>';
            echo '<hr>File Trace :  <BR><pre>';
            print_r($_file);
            echo '</pre>';
            exit;
        }

        //执行 返回数据
        $controller = new $_Controller();
        $res = $controller->$_Action($_Params);
        return $res;
    }

    /**
     * @param string $_ca
     * @param string $_params
     *
     * @return array
     */
    public static function Run($_ca = '',$_params = '')
    {
        $router = \Grace\Router::GetRouter($_ca,$_params);   //构造路由
        return SELF::ControllerRun($router);
    }

}


