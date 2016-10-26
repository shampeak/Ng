<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/10/25
 * Time: 17:11
 */


class WoniuModel extends MpLoaderPlus {
    private static $instance;
    /**
     * 实例化一个模型
     * @param type $classname_path
     * @param type $hmvc_module_floder
     * @return type WoniuModel
     */
    public static function instance($classname_path = null, $hmvc_module_floder = NULL) {
        if (!empty($hmvc_module_floder)) {
            MpRouter::switchHmvcConfig($hmvc_module_floder);
        }
        //这里调用控制器instance是为了触发自动加载，从而避免了插件模式下，直接instance模型，自动加载失效的问题
        WoniuController::instance();
        if (empty($classname_path)) {
            $renew = is_bool($classname_path) && $classname_path === true;
            MpLoader::classAutoloadRegister();
            return empty(self::$instance) || $renew ? self::$instance = new self() : self::$instance;
        }
        $system = systemInfo();
        $classname_path = str_replace('.', DIRECTORY_SEPARATOR, $classname_path);
        $classname = basename($classname_path);
        $model_folders = $system['model_folder'];
        if (!is_array($model_folders)) {
            $model_folders = array($model_folders);
        }
        $count = count($model_folders);
        //在plugin模式下，路由器不再使用，那么自动注册不会被执行，自动加载功能会失效，所以在这里再尝试加载一次，
        //如此一来就能满足两种模式
        MpLoader::classAutoloadRegister();
        foreach ($model_folders as $key => $model_folder) {
            $filepath = $model_folder . DIRECTORY_SEPARATOR . $classname_path . $system['model_file_subfix'];
            $alias_name = $classname;
            if (in_array($alias_name, array_keys(WoniuModelLoader::$model_files))) {
                return WoniuModelLoader::$model_files[$alias_name];
            }
            if (file_exists($filepath)) {
                if (!class_exists($classname, FALSE)) {
                    MpLoader::includeOnce($filepath);
                }
                if (class_exists($classname, FALSE)) {
                    return WoniuModelLoader::$model_files[$alias_name] = new $classname();
                } else {
                    if ($key == $count - 1) {
                        trigger404('Model Class:' . $classname . ' not found.');
                    }
                }
            } else {
                if ($key == $count - 1) {
                    trigger404($filepath . ' not  found.');
                }
            }
        }
    }
}
class MpModel extends WoniuModel {
}
