<?php

namespace App\Home;

class Home extends BaseController{

    public function __construct(){
        parent::__construct();
    }

    public function doIndex(){
        echo 123;
    }

    public function doRes($params = []){
        $html =  'home.res';
        $html .= "<pre>";
        $html .= print_r($params,true);
        $html .= "</pre>";
        return $html;
    }


}
