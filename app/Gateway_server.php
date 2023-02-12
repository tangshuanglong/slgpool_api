<?php

namespace App;

use http\Params;
use Lib\MySign;
use Lib\MyToken;
use Lib\ServerHelper;
use Lib\SLB;
use Swoole\Http\Server;
use Lib\Consul\Health;
use Lib\Helper\HttpClient;
use Lib\Helper\JsonHelper;
use Lib\Helper\Log;
use Lib\Common;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Table;

/**
 * Class Gateway_server
 * author: lyj
 * date: 2019/11/19
 * 微服务网关，包括调用服务，负载均衡，重试机制，熔断机制，服务降级， 统一报错响应，
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
     * 服务器实例
     * @var Server
     */
    private $server;
    /**
     * @var Health
     */
    private $health;
    /**
     * @var MySign
     */
    private $mySign;
    /**
     * @var MyToken
     */
    private $myToken;
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

    /**
     * 重复请求次数
     * @var int
     */
    private $re_request = 1;

    private $log_path = '/logs/swoole';

    /**
     * 过滤掉的请求头
     * @var array
     */
    private $filter_headers = [
        'server',
        'connection',
        'date',
        'content-length',
        'content-encoding'
    ];

    /**
     * 内存表
     * @var
     */
    private $table;

    /**
     * Gateway_server constructor.
     */
	public function __construct()
	{
	    //初始化实例
        $this->health = new Health();
        $this->http_client = new HttpClient();
        $this->mySign = new MySign();
        $this->myToken = new MyToken();
        $this->port = getenv('PORT') ?: 8888;
        $this->host = getenv('HOST') ?: '0.0.0.0';
        $this->log_path = getenv('LOG_PATH') ?: '/logs/gateway';
        //构建http服务器
        $this->server = new Server($this->host, $this->port, SWOOLE_BASE, SWOOLE_SOCK_TCP);
        $this->server->set([
            //进程数
            'worker_num' => getenv('WORKER_NUM') ?: 4,
            //线程数
            //'reactor_num' => 4,
            //cpu亲和设置
            'open_cpu_affinity' => 1,
            //最大连接
            'max_conn' => 63455,
            //listen队列长度
            'backlog' => 256,
            //log日志
            'log_file' => $this->log_path.'/swoole.log',
            //异步进程数
            //'task_worker_num' => 10,
            //是否开启静态文件处理，和document_root配合使用
            //'enable_static_handler' => true,
            //'document_root' => "/www/swoole/data",
            //开启守护进程
            'daemonize' => getenv('DAEMONIZE') === '' ? false : true,
            //用户组
            'user' => 'www',
            'group' => 'www',
            'pid_file' => $this->log_path.'/server.pid',
            //设置最大上传文件大小6m
            'package_max_length' => 6*1024*1024,
            //'task_ipc_mode' => 3,
            //ssl证书
            // 'ssl_cert_file' => SSLDIR . 'signed.crt',
            // 'ssl_key_file' => SSLDIR . 'domain.key',
            //是否自动解析post请求数据
            'http_parse_post' => false,
            //开启协程
            'enable_coroutine' => true
        ]);

        $this->server->on('request', [$this, 'onRequest']);

        $this->server->start();
	}

    /**
     * @param Request $request
     * @param Response $response
     * @return bool
     * @throws \Exception
     */
	public function onRequest(Request $request, Response $response)
    {
        //得到请求方法
        $method = $request->server['request_method'];
        if ($method == 'OPTIONS'){
            $this->set_headers($response);
            $response->end();
            return true;
        }
        //远程地址
        $remote_addr = Common::get_ip(array_merge($request->server,$request->header));
        //切割uri
        $explode_uri = explode('/', $request->server['request_uri']);
        if (isset($request->header['x-forwarded-for']) && !empty($request->header['x-forwarded-for'])) {
            $request->header['X-Forwarded-For'] = $request->header['x-forwarded-for'] .','.$request->server['remote_addr'];
        } else {
            $request->header['X-Forwarded-For'] = $request->server['remote_addr'];
        }

        //get请求的参数
        $query_string = $request->server['query_string'] ?? '';
        $params = ServerHelper::log_format($request->server, $request->header, $remote_addr, $query_string);
        //uri的第一个参数代表consul服务名
        $service = $explode_uri[1];
        if (empty($service)){
            $this->default_response($response, $params);
            return false;
        }
        //拼接请求consul注册的服务的uri
        $uri = ServerHelper::get_uri($explode_uri, $query_string);
        //获取服务列表
        $services = ServerHelper::get_server($service, $this->health);
        if (empty($services)){
            $this->default_response($response, $params);
            return false;
        }
        //发送请求之前先验证签名
//        $verifyRes = ServerHelper::baseVerify($request, $this->mySign);
//        if ($verifyRes['statusCode'] !== 200) {
//            $params['status_code'] = $verifyRes['statusCode'];
//            $this->default_response($response, $params, '');
//            return false;
//        }
//        //如果存在登录token,验证登录token
//        if (isset($request_headers['token']) && !empty($request_headers['token'])) {
//            $device_id = $request_headers['device-id'] ?? '';
//            $checkRes = $this->myToken->checkToken($request_headers['token'], $request_headers['client-type'], $device_id);
//            if ($checkRes === false) {
//                $params['status_code'] = 5010;
//                $this->default_response($response, $params, '登录已过期');
//                return false;
//            }
//            $request_headers['uid'] = $checkRes['uid'];
//            $request_headers['account'] = $checkRes['account'];
//        }
        $res = $this->send_request($services, $service, $method, $uri, $request->rawContent(), $request->header);
        if($res === false) {
            $this->default_response($response, $params);
            return false;
        }
        //自定义的网关错误
        if ($res['statusCode'] === 502) {
            $params['status_code'] = $res['statusCode'];
            $this->default_response($response, $params, $res['body']);
            return false;
        }
        //添加应用服务器的响应头
        if (!empty($res['headers'])) {
            foreach ($res['headers'] as $key => $val) {
                if (in_array($key, $this->filter_headers)) {
                    continue;
                }
                $response->header($key, $val);
            }
        }
        //$this->set_headers($response);
        $response->status($res['statusCode']);
        $response->end($res['body']);
        $params['status_code'] = $res['statusCode'];
        Log::server_log($params, $this->log_path);
    }


    /**
     * @param $response
     * @param $params
     * @param string $msg
     * @param array $data
     */
    private function default_response(Response $response, array $params, string $msg = 'Not Found Resource!', array $data = []): void
    {
        //设置状态码
        $response->status($params['status_code']);
        //设置响应格式为json
        $response->header('Content-Type', 'application/json');
        //设置跨域响应头
        //$this->set_headers($response);
        if (empty($data)) {
            $data = (object)[];
        }
        //定义默认响应格式
        $res = [
            'code' => $params['status_code'],
            'msg' => $msg,
            'body' => $data,

        ];
        $response->end(JsonHelper::encode($res));
        //写入日志
        Log::server_log($params, $this->log_path);
    }

    /**
     * 设置固定响应头
     * @param Response $response
     */
    private function set_headers(Response $response): void
    {
        $origin = getenv('ALLOW_ORIGIN');
        if (empty($origin)) {
            return;
        }
        $response->header('Access-Control-Allow-Origin', $origin);
        $response->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, token, client-type, device-id');
        $response->header('Access-Control-Allow-Max-Age', 3600);
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
    private function send_request(array $services, string $service, string $method, string $uri, string $request_body, array $request_headers)
    {
        //获取服务信息
        $service_info = SLB::get_service_info($services, $service);

        //服务都挂掉了，返回空
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
            unset($services[self::$index[$service]]);
            $services = array_merge($services);
            return $this->send_request($services, $service, $method, $uri, $request_body, $request_headers);
        }
        return $res;
    }
}
