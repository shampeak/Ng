<?php

namespace App\Model;

class Test{

    public function __construct(){
    }

    public function Run($params = []){
        echo '<pre>';
        print_r($params);
        echo '</pre>';
    }



}
