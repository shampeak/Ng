<?php

/**
 * --------------------系统配置-------------------------
 */


return [


    // 默认时区,PRC是中国
    'Timezone'  => 'PRC',

    // 编码 utf-8|RPC
    'Charset'   => 'utf-8',

    'Error_manage'      => FALSE,               //是否接管错误信息显示        true：所有错误信息将由系统格式化输出 false：所有错误信息将原样输出


    /**
     * HALT
     * //是否开启调试模式
     * true：显示错误信息,
     * false：所有错误将不显示
     */
    'Debug'      => TRUE,

    /**
     * 错误级别
     * E_ALL,
     * E_ALL & ~E_NOTICE,
     * E_ALL & ~E_NOTICE & ~E_STRICT,
     * E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED,
     */
    'Error_report'      => E_ALL & ~E_NOTICE,

    /**
     * 跳转页面
     * 一般的操作成功,或者直接跳转,能涉及
     * 1 : 直接页面跳转
     * 2 : 操作成功返回 , 或刷新
     * 同步数据流
     * RE() -> RE()
     * //todo 有时间再处理
     */
    'Redirect_page'    => GRACE.'../Grace/Error/Redirect.php',



    /**
     * 404错误文件的路径,该文件会在系统找不到相关内容时显示,
     * 文件里面可以使用$msg变量获取出错提示内容
     * halt中断也会使用该页面 halt(404)
     */
    'Error_page_404'    => GRACE.'../Grace/Error/Error_404.php',

    /**
     * 系统错误文件的路径,该文件会在发生Fatal错误和Exeption时显示,
     * 文件里面可以使用$msg变量获取出错提示内容
     * halt中断也会使用该页面 halt(40x)
     */
    'Error_page_50x'    => GRACE.'../Grace/Error/Error_50x.php',
    'Error_page'        => GRACE.'../Grace/Error/Error.php',

    /**
     * 数据库错误文件的路径,该文件会在发生数据库错误时显示,
     * 文件里面可以使用$msg变量获取出错提示内容
     */
//  'error_page_db' => 'application/error/error_db.php',










    /** - 路由相关
     * 控制器相关
     * 默认控制器文件名称
     * 就是控制器文件夹下面的home文件夹里面Index.php(假定后缀是.php)
     */
    'Router'=>[
        'Application_Folder'=> GRACE . '../App/',
        'Controller_Folder' => GRACE . '../App/Controller/',      //存放控制器文件的文件夹路径名称
        'Model_Folder'      => GRACE . '../App/models/',           //存放模型文件的文件夹路径名称,支持数组
        'View_Folder'       => GRACE . '../App/views/',            //存放视图文件的文件夹路径名称,支持数组
        'library_Folder'    => GRACE . '../App/library/',          //存放类库文件的文件夹路径名称,存放在该文件夹的类库中的类会自动加载,支持数组
        'Helper_Folder'     => GRACE . '../App/helper/',           //存放函数文件的文件夹路径名称,支持数组
        'Table_Cache_Folder'=> GRACE . '../App/cache/',            // table()方法缓存表字段信息的文件夹路径名称
        'Hmvc_Folder'       => GRACE . '../App/modules/',          //存放HMVC模块的文件夹路径名称

        'Default_Controller'        => 'Home',
        'Default_Controller_Method' => 'Index',         //默认控制器方法名称,不要带前缀
        'Controller_Method_Prefix'  => 'do',            //控制器方法名称前缀
//        'Controller_file_Subfix'    => '.php',          //控制器文件名称后缀,比如.php或者.controller.php
//        'View_File_Subfix'          => '.view.php',     //视图文件名称后缀,比如.view.php
    ],

        //TODO
//    'Router'=>[
//        //'|^([^/]+)/(.+)$|u' => '$1.$2',//index.php/home/index路由支持
//        //'|^([^/]+)/([^/]+)/(.+)$|u' => '$1.$2.$3',//index.php/user/home/index路由支持
//    ],

    /**
     * server容器的相关配置
     */
    'Server'=>[
        //容器对象配置
        'ProvidersConfig'  => [
            'V'         =>'V.php',
            'Smarty'    => 'Smarty.php',
            'View'      => 'View.php',

            /**
             * Memcache配置
             */
            'Mmc'=>[
                'MEM_ENABLE'  => true,   //不启用
                'MEM_SERVER'  =>
                    [
                        ['127.0.0.1', 11211],
                    ],
                'MEM_GROUP'   => 'channelgst',
            ],

            /**
             * LOG 相关配置
             */
            'Log'       => [
                //日志文件 文件地址
                'path'=> GRACE.'../App/Cache/Log/dt.log'
            ],

            /**
             * 容器中数据库对象
             */
            'Db'        => [
                'hostname'      => '127.0.0.1',         //服务器地址
                'port'          => '3306',              //端口
                'username'      => 'root',              //用户名
                'password'      => 'root',              //密码
                'database'      => 'viga',              //数据库名
                'charset'       => 'utf8',              //字符集设置
                'pconnect'      => 0,                   //1  长连接模式 0  短连接
                'quiet'         => 0,                   //安静模式 生产环境的
                'slowquery'     => 1,                   //对慢查询记录
                'rootpath'      => GRACE.'../App/Cache/',   //慢查询记录文件
            ],

            /**
             * cookies配置
             * cookie中的数据进行了encrypt加密存储在本地
             */
            'Cookies'   => [
                'prefix'   => 'GraceEasy',              // cookie prefix 前缀         获取 config('Cookies')['prefix']
                'securekey'=> 'uJeixezgPNyALm',         // encrypt key   密钥
                'expire'   => 36000,                    //超时时间
            ],

            /**
             * cache相关配置
             */
            'Cache'     => [

                /**
                 * File cache
                 */
                'cacheType' => 'File',
                'cacheDir'  =>  GRACE.'../App/Cache/tmp',
                'adapter'   => \Desarrolla2\Cache\Adapter\File::class,

                /**
                 * memcache
                 * 需要安装memcache
                 */
//                'cacheType' => 'Memcache',
//                'adapter'   => \Desarrolla2\Cache\Adapter\Memcache::class,
//                'server'    => [
//                    ['127.0.0.1', 11211]
//                ],

                //默认缓存时间
                'ttl'       => 3600

            ],

        ],

        //容器对象
        'Providers'=>[
            'Parsedown' => Grace\Parsedown\Parsedown::class,
            'Req'       => Grace\Req\Req::class,
            'Db'        => Grace\Db\Db::class,
            'Cookies'   => Grace\Cookies\Cookies::class,
            'Cache'     => Grace\Cache\Cache::class,
            'Mmc'       => Grace\Mmc\Mmc::class,
//            'Xls'       => Grace\Xls\Xls::class,
//            'Smarty'    => Grace\Smarty\Smarty::class,
//            'View'      => Grace\View\View::class,
            'Log'       => Grace\Log\Log::class,
        ],

    ]

];
