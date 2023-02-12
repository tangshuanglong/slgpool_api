<?php

namespace Lib\Helper;

use Swoole\Coroutine\Http\Client;

/**
 * Class HttpClient
 */
class HttpClient{


    /**
     * @param string $host
     * @param $port
     * @param string $method
     * @param string $uri
     * @param $body
     * @param array $headers
     * @param int $timeout
     * @return array|bool
     */
    public function request(string $host, $port, string $method, string $uri, $body, array $headers, $timeout = 10)
    {

        try {
            // Http request
            $client = new Client($host, $port);
            $client->setMethod($method);
            $client->setHeaders($headers);
            if ($timeout > 0){
                $client->set(['timeout' => $timeout]);
            }

            // Set body
            if (!empty($body)) {
                $client->setData($body);
            }
            $client->execute($uri);
            // Response
            $headers    = $client->headers;
            $statusCode = $client->statusCode;
            $body       = $client->body;
            // Close
            $client->close();
            if ($statusCode == -1) {
                throw new \Exception('Connect error');
            }
            //如果请求超时，或者服务器强制切断连接，返回502，网关超时 Bad Gateway
            if ($statusCode == -2 || $statusCode == -3) {
                $statusCode = 502;
                $body = 'Bad Gateway';
            }
        } catch (\Exception $e) {
            return false;
        }

        return ['headers' => $headers, 'body' => $body, 'statusCode' => $statusCode];
    }

}
