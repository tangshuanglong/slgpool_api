<?php

namespace Lib;

use Lib\Consul\Health;
use Lib\Helper\JsonHelper;
use Swoole\Http\Request;
use Swoole\Http\Response;

class ServerHelper {

    /**
     * 临时保存所有服务信息
     * @var array
     */
    private static $services = [];

    /**
     * @var array consul服务超时列表
     */
    private static $services_timeout_list = [];

    /**
     * 临时存储时间 10秒
     * @var int
     */
    private static $services_timeout = SERVICES_TIMEOUT;


    /**
     * 获取服务列表
     * @param string $service
     * @param Health $health
     * @return array|bool|mixed
     */
    public static function get_server(string $service, Health $health)
    {
        $time = time();
        $services = self::$services[$service] ?? [];
        //如果服务没有记录。或者已经过期才获取新的服务列表
        if (!isset(self::$services_timeout_list[$service]) || self::$services_timeout_list[$service] < $time || empty($services)){
            //初始化
            self::$services[$service] = [];
            //获取对应注册的服务
            $data = $health->service($service);
            if ($data['statusCode'] != 200){
                return false;
            }
            $body = JsonHelper::decode($data['body'], true);
            if (empty($body)){
                return false;
            }
            //检查服务是否可用，可用的存在self::$services,并且记录过期时间
            foreach($body as $val){
//                if(!isset($val['Checks'][1]) || $val['Checks'][1]['Status'] !== 'passing'){
//                    continue;
//                }
                if($val['Service']['Tags'][0] !== 'http'){
                    continue;
                }
                self::$services[$service][] = $services[] = [
                    'host' => $val['Service']['Address'],
                    'port' => $val['Service']['Port'],
                ];
                self::$services_timeout_list[$service] = $time + self::$services_timeout;
            }
        }
        return $services;
    }

    /**
     * 拼接请求consul注册的服务的uri
     * @param array $explode_uri
     * @param string $query_string
     * @return string
     */
    public static function get_uri(array $explode_uri, string $query_string)
    {
        //拼接请求consul注册的服务的uri
        $uri = '';
        for($i = 0; $i < count($explode_uri); $i++){
            if ($i <= 1){
                continue;
            }else{
                $uri .= '/'.$explode_uri[$i];
            }
        }
        if ($query_string){
            $uri .= '?'.$query_string;
        }
        return $uri;
    }

    /**
     * 接口基本验证
     * @param Request $request
     * @param MySign $mySign
     * @return array
     */
    public static function baseVerify(Request $request, MySign $mySign): array
    {
        $client_type = $request->header['client-type'];
        if (empty($client_type)){
            return ['statusCode' => 400];
        }
        //得到请求方法
        $method = $request->server['request_method'];
        if ($client_type !== 'web') {
            $device_id = $request->header['device-id'];
            if (empty($device_id)){
                return ['statusCode' => 400];
            }
        }
        $request_body = $request->rawContent();
        //get请求不验证签名，放行
        if ($method == 'GET' || $client_type === 'web'){
            return ['statusCode' => 200, 'body' => $request_body];
        }
        $request_data = JsonHelper::decode($request_body, true);
        if ($request_data === false) {
            return ['statusCode' => 400];
        }
        if (!isset($request_data['timestamp']) || !isset($request_data['sign'])){
            return ['statusCode' => 400];
        }
        $res = $mySign->checkSign($request_data);
        if(APP_DEBUG === false){
            if ($res === false) {
                return ['statusCode' => 403];
            }
        }
        //unset($request_data['timestamp'], $request_data['sign']);
        return ['statusCode' => 200, 'body' => JsonHelper::encode($request_data)];
    }

    /**
     * 日志格式
     * @param array $request_info
     * @param array $request_headers
     * @param $remote_addr
     * @param $query_string
     * @return array
     */
    public static function log_format(array $request_info, array $request_headers, $remote_addr, string $query_string)
    {
        return [
            'client_ip' => $remote_addr,
            'x_forwarded_for' => $request_headers['x-forwarded-for'] ?? '',
            'remote_addr' => $request_info['remote_addr'],
            'method' => $request_info['request_method'],
            'request_uri' => $request_info['request_uri']. ($query_string ? '?'.$query_string : ''),
            'server_protocol' => $request_info['server_protocol'],
            'status_code' => 404, //默认404
            'host' => $request_headers['host'],
            'user_agent' => $request_headers['user-agent']
        ];
    }
}
