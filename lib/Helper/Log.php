<?php

namespace Lib\Helper;

use Swoole\Coroutine\System;

class Log{

    /**
     * @param $data
     * @param string $path
     * @return bool
     */
    public static function write_log(string $data, string $path = '/logs/default'): bool
    {
        if (!is_string($data)) {
            return false;
        }
        $filename = $path .'/'. date("Y_m_d") . '.log';
        if(!is_dir($path)){
            mkdir($path, 0777, true);
        }
        $time = date("Y-m-d H:i:s");
        $content = "日期：".$time."----信息：".$data . PHP_EOL;
        //异步写入日志
        //$res = System::writeFile($filename, $content, FILE_APPEND);
        $res = file_put_contents($filename, $content, FILE_APPEND);
        unset($data, $path, $filename, $time, $content);
        return $res;
    }

    /**
     * @param array $params
     * @param string $log_path
     * @return bool
     */
    public static function server_log(array $params, string $log_path): bool
    {
        $log_str = sprintf(
            "remote_addr=%s -- x_forwarded_for=%s -- client_ip=%s -- %s %s %s %s -- host=%s -- %s",
            $params['remote_addr'],
            $params['x_forwarded_for'],
            $params['client_ip'],
            $params['method'],
            $params['request_uri'],
            $params['server_protocol'],
            $params['status_code'],
            $params['host'],
            $params['user_agent']
        );
        return Log::write_log($log_str, $log_path);
    }
}
