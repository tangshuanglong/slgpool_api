<?php

namespace App;

require_once dirname(__DIR__).'/vendor/autoload.php';
//require_once '../lib/consul/Health.php';
//require_once '../lib/helper/HttpClient.php';
//require_once '../lib/helper/JsonHelper.php';

use Swoole\Http\Server;
use Lib\Consul\Health;
use Lib\Helper\HttpClient;
use Lib\Helper\JsonHelper;
use Lib\Helper\Log;

/**
 * Class Gateway_server
 * author: lyj
 * date: 2019/11/19
 * 微服务网关，包括调用服务，负载均衡，重试机制，熔断机制，统一报错响应，
 * 还可以再实现限流，鉴权，灰度
 */
class Gateway_server{
    /**
     * @var string
     */
    private $host = '0.0.0.0';
    /**
     * @var string
     */
    private $port = '8888';
    /**
     * consul服务列表的超时时间
     * @var int
     */
    private $services_timeout = 10;
    /**
     * 服务器实例
     * @var Server
     */
    private $server;
    /**
     * @var Health
     */
    private $health;
    /**
     * consul服务列表
     * @var array
     */
    private static $services = [];
    /**
     * consul服务超时列表
     * @var array
     */
    private static $services_timeout_list = [];
    /**
     * 负载均衡简单轮训算法的索引值
     * @var array
     */
    private static $index = [];
    /**
     * http客户端实例
     * @var HttpClient
     */
    private $http_client;

    /**
     * 可连接总数
     * @var array
     */
    private static $count = [];

    private $re_request = 1;

    private $log_path = '/logs/swoole';

	public function __construct()
	{
	    //初始化实例
        $this->health = new Health();
        $this->http_client = new HttpClient();
        //构建http服务器
        $this->server = new Server($this->host, $this->port, SWOOLE_BASE, SWOOLE_SOCK_TCP);
        $this->server->set([
            //进程数
            'worker_num' => 2,
            //线程数
            'reactor_num' => 2,
            //cpu亲和设置
            'open_cpu_affinity' => 1,
            //最大连接
            'max_conn' => 1024,
            //listen队列长度
            'backlog' => 128,
            //log日志
            'log_file' => '/logs/swoole/swoole.log',
            //异步进程数
            //'task_worker_num' => 10,
            //是否开启静态文件处理，和document_root配合使用
            'enable_static_handler' => true,
            'document_root' => "/www/swoole/data",
            //开启守护进程
            'daemonize' => true,
            //用户组
            'user' => 'www',
            'group' => 'www',
            'pid_file' => '/logs/swoole/server.pid',
            //设置最大上传文件大小6m
            'package_max_length' => 6*1024*1024,
            //'task_ipc_mode' => 3,
            //ssl证书
            // 'ssl_cert_file' => SSLDIR . 'signed.crt',
            // 'ssl_key_file' => SSLDIR . 'domain.key',
            //是否自动解析post请求数据
            'http_parse_post' => false,
        ]);
        $this->server->on('request', [$this, 'onRequest']);

        $this->server->start();
	}

	public function onRequest($request, $response)
    {
        //获取$_SERVER信息
        $request_info = $request->server;
        //得到请求方法
        $method = $request_info['request_method'];
        //请求uri
        $request_uri = $request_info['request_uri'];
        //远程地址
        $remote_addr = $request_info['remote_addr'];
        //切割uri
        $explode_uri = explode('/', $request_uri);
        //请求body和headers
        $request_body = $request->rawContent();
        $request_headers = $request->header;
        $log_str = sprintf(
            "remote_ip=%s -- %s %s %s -- host=%s -- %s",
            $remote_addr,
            $method,
            $request_uri,
            $request_info['server_protocol'],
            $request_headers['host'],
            $request_headers['user-agent']
        );
        Log::write_log($log_str, $this->log_path);
        //get请求的参数
        $query_string = isset($request_info['query_string']) ? $request_info['query_string'] : '';
        //uri的第一个参数代表consul服务名
        $service = $explode_uri[1];
        if (empty($explode_uri[1])){
            $response->status(404);
            $response->end('Not Found');
            return false;
        }
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

        $services = [];
        //获取对应注册的服务
        $data = $this->health->service($service);
        if ($data['statusCode'] != 200){
            $response->status(404);
            $response->end('Not Found');
            return false;
        }
        $body = JsonHelper::decode($data['body'], true);
        if (empty($body)){
            $response->status(404);
            $response->end('Not Found');
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
            $services[] = [
                'host' => $val['Service']['Address'],
                'port' => $val['Service']['Port'],
            ];
            unset($val);
        }
        if (empty($services)){
            $response->status(404);
            $response->end('Not Found');
            return false;
        }
        $res = $this->send_request($services, $service, $method, $uri, $request_body, $request_headers);
        if($res === false) {
            $this->set_headers($response);
            $response->end(JsonHelper::encode([]));
            return false;
        }
        $is_json = JsonHelper::verify_json($res['body']);
        if ($is_json === true){
            $this->set_headers($response);
        }
        $response->status($res['statusCode']);
        $response->end($res['body']);
        unset($data, $body, $services, $res);
    }

    /**
     * 设置固定响应头
     * @param $response
     */
    private function set_headers($response)
    {
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'GET,POST,PUT,DELETE');
        $response->header('Access-Control-Allow-Headers', 'x-requested-with,content-type');
        $response->header('Content-Type', 'application/json');
        unset($response);
    }

    /**
     * 简单轮训算法
     * @param $service 服务名
     * @param $count 服务总数
     * @return mixed int
     */
    private function simple_loop($service, $count)
    {
        //简单轮训算法
        $index = (self::$index[$service] + 1) % $count;
        self::$index[$service] = $index;
        return $index;
    }

    /**
     * 获取对应的服务信息
     * @param $services
     * @param $service 服务名
     * @return array|mixed
     */
    private function get_service_info($services, $service)
    {
        //初始化index
        $count = count($services);
        if ($count === 0){
            return [];
        }
        if(!isset(self::$index[$service])){
            self::$index[$service] = 0;
        }
        //初始化index
        $index = $this->simple_loop($service, $count);
        return $services[$index];
    }

    /**
     * 发起请求
     * @param $services
     * @param $service
     * @param $method
     * @param $uri
     * @param $request_body
     * @param $request_headers
     * @return array|bool
     * @throws \Exception
     */
    private function send_request($services, $service, $method, $uri, $request_body, $request_headers)
    {
        $service_info = $this->get_service_info($services, $service);
        //服务都挂掉了，服务降级，返回空
        if (empty($service_info)){
            return false;
        }
        //如果请求错误
        $res = $this->http_client->request($service_info['host'], $service_info['port'], $method, $uri, $request_body, $request_headers);
        if ($res === false){
            //重试机制
            for($i = 0; $i < $this->re_request; $i++){
                $res = $this->http_client->request($service_info['host'], $service_info['port'], $method, $uri, $request_body, $request_headers);
                if ($res){
                    break;
                }
            }
        }
        //负载均衡轮训其他服务
        if($res === false) {
            return $this->send_request($services, $service, $method, $uri, $request_body, $request_headers);
        }
        return $res;
    }
}

new Gateway_server();