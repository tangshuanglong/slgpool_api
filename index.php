<?php

// 定义根目录
use Lib\Common;

define('BASE_PATH', __DIR__);
require_once BASE_PATH.'/vendor/autoload.php';

// 定义应用目录
define('APP_PATH', __DIR__ . '/app');

//设置默认时区
date_default_timezone_set('Asia/Shanghai');

//解析请求的控制器和方法 namespace/className:action
$route = $argv[1] ?? '';
if ($route === '') {
    exit("Not Found Route \n");
}
$explode_route = explode(':', $route);
//得到方法名
$action = $explode_route[1] ?? '';
$class_path = $explode_route[0];
$explode_class = explode('/', $class_path);
$count = count($explode_class);

//得到命名空间类
$class = "App";
for ($i = 0; $i < $count; $i++) {
    $class .= "\\".ucfirst($explode_class[$i]);
}
//载入env
$filename = BASE_PATH.'/.env';
if (is_file( $filename)) {
    $env = parse_ini_file($filename, true);
    foreach ($env as $key => $val) {
        $name = strtoupper($key);
        if (is_array($val)) {
            foreach ($val as $k => $v) {
                $item = $name . '.' . strtoupper($k);
                putenv("$item=$v");
            }
        } else {
            putenv("$name=$val");
        }
    }
}
//调用
$app = new $class();
if ($action) {
    $app->$action();
}
