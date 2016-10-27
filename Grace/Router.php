<?php

namespace Grace;

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2015/12/4 0004
 * Time: 16:37
 */

class Router {

    private static $_config = [];
    public function __construct() {
        $this->_config = Config('Router');
    }

    /**
     * @return array
     * 输出like
    /*
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
    public static function GetRouter($_ca = '',$_params = '')
    {
        self::$_config = Config('Router');
        if(!empty($_ca)){
            //手动构造
            return self::BulidRouter($_ca,$_params);        //转交
            exit;
        }
        $get = \Grace\Input::get();
        if(isset($get['r'])){
            $get['r'] = trim($get['r'],'/');
            if(!empty($get['r'])){      //对路由ca重写
                list($cc, $aa) = explode('/',$get['r']);
            }
        }
        $c = $cc?:$get['c'];
        $a = $aa?:$get['a'];

        //REQUEST_METHOD
        $c  = $c?ucfirst(strtolower($c)):self::$_config['Default_Controller'];
        $a  = $a?ucfirst(strtolower($a)):self::$_config['Default_Controller_Method'];

        $res =  [
            'Controller'    => $c,
            'Mothed'        => $a,
            'Ext'           => '',
            'REQUEST_METHOD'=>$_SERVER['REQUEST_METHOD'],
            'Profix'        => 'do',
            'ControllerBase'=> Config('Router')['Controller_Folder'],
            'ControllerFile'=> Config('Router')['Controller_Folder'].$c.'/'.$c.'.php',
//            'ControllerFile_'=> Config('Router')['Controller_Folder'].$c.'/'.$a.'.php',
            'Action'        => ($_SERVER['REQUEST_METHOD'] == 'GET')?'do'.$a:'do'.$a.ucfirst(strtolower($_SERVER['REQUEST_METHOD'])),
            //'Params'    => '',                                    //path路由的时候用得上
        ];

        //验证控制器和方法的有效性
        if (!preg_match('/^[0-9a-zA-Z]+$/', $res['Controller']) || !preg_match('/^[0-9a-zA-Z]+$/',$res['Mothed'])
        ) {
            halt('router error');
        }
        if (!preg_match('/^[a-zA-Z]+$/', substr($res['Controller'], 0, 1)) || !preg_match('/^[a-zA-Z]+$/',
                substr($res['Mothed'], 0, 1))
        ) {
            halt('router error2');
        }

        //OK,控制器需要的参数都已经具备了
        return $res;
    }

    /**
     * @param string $_ca
     * @param string $_params
     * 手动构造路由
     */
    public static function BulidRouter($_ca = '',$_params = '')
    {

        $_ca = trim($_ca,'/');
        if(!empty($_ca)){      //对路由ca重写
            list($c, $a) = explode('/',$_ca);
        }

        //REQUEST_METHOD
        $c  = $c?ucfirst(strtolower($c)):self::$_config['Default_Controller'];
        $a  = $a?ucfirst(strtolower($a)):self::$_config['Default_Controller_Method'];

        $res =  [
            'Controller'    => $c,
            'Mothed'        => $a,
            'Ext'           => '',
            'REQUEST_METHOD'=> 'DEFINED',
            'Profix'        => 'do',
            'ControllerBase'=> Config('Router')['Controller_Folder'],
            'ControllerFile'=> Config('Router')['Controller_Folder'].$c.'/'.$c.'.php',
//            'ControllerFile_'=> Config('Router')['Controller_Folder'].$c.'/'.$a.'.php',
            'Action'        => 'do'.$a,
            'Params'        => $_params,                                    //path路由的时候用得上
        ];

        //验证控制器和方法的有效性
        if (!preg_match('/^[0-9a-zA-Z]+$/', $res['Controller']) || !preg_match('/^[0-9a-zA-Z]+$/',$res['Mothed'])
        ) {
            halt('router error');
        }
        if (!preg_match('/^[a-zA-Z]+$/', substr($res['Controller'], 0, 1)) || !preg_match('/^[a-zA-Z]+$/',
                substr($res['Mothed'], 0, 1))
        ) {
            halt('router error2');
        }

        //OK,控制器需要的参数都已经具备了
        return $res;
    }


}


