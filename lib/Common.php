<?php

namespace Lib;


class Common{

    /**
     * @param array $services
     * @return mixed
     */
    public static function get_ip(array $services)
    {
        $ip = $services['remote_addr'];
        if (isset($services['x-forwarded-for']) && !empty($services['x-forwarded-for'])){
            $ip = explode(',', $services['x-forwarded-for'])[0];
        }
        return $ip;
    }

    /**
     * 检查字符串是否为空，为空返回true, 否则返回false
     * @param string $str
     * @return boolean
     */
    public static function checkEmpty(string $str)
    {
        if(!isset($str)){
            return true;
        }
        if($str === NULL){
            return true;
        }
        if(trim($str) === ''){
            return true;
        }
        return false;
    }

    /**
     * 设置env
     */
    public static function set_env(): void
    {
        $filename = dirname(__DIR__).'/.env';
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
    }



}
