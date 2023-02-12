<?php


namespace Lib\Consul;

use Swoole\Coroutine\Http\Client;

/**
 * Class Consul
 *
 */
class Consul
{
    /**
     * @var string
     */
    private $host = '127.0.0.1';

    /**
     * @var int
     */
    private $port = 8500;

    /**
     * Seconds
     *
     * @var int
     */
    private $timeout = 3;

    /**
     * @param string $url
     * @param array  $options
     */
    public function get(string $url = null, array $options = [])
    {
        return $this->request('GET', $url, $options);
    }

    /**
     * @param string $url
     * @param array  $options
     */
    public function head(string $url, array $options = [])
    {
        return $this->request('HEAD', $url, $options);
    }

    /**
     * @param string $url
     * @param array  $options
     */
    public function delete(string $url, array $options = [])
    {
        return $this->request('DELETE', $url, $options);
    }

    /**
     * @param string $url
     * @param array  $options
     */
    public function put(string $url, array $options = [])
    {
        return $this->request('PUT', $url, $options);
    }

    /**
     * @param string $url
     * @param array  $options
     */
    public function patch(string $url, array $options = [])
    {
        return $this->request('PATCH', $url, $options);
    }

    /**
     * @param string $url
     * @param array  $options
     *
     * @return Response
     * @throws ClientException
     * @throws ServerException
     */
    public function post(string $url, array $options = [])
    {
        return $this->request('POST', $url, $options);
    }

    /**
     * @param string $url
     * @param array  $options
     *
     * @return Response
     * @throws ClientException
     * @throws ServerException
     */
    public function options(string $url, array $options = [])
    {
        return $this->request('OPTIONS', $url, $options);
    }

    /**
     * @param $method
     * @param $uri
     * @param $options
     */
    private function request($method, $uri, $options)
    {
        $body = $options['body'] ?? '';
        if (is_array($body)) {
            $body = json_encode($body, JSON_UNESCAPED_UNICODE);
        }

        $query = $options['query'] ?? [];
        if (!empty($query)) {
            $query = http_build_query($query);
            $uri   = sprintf('%s?%s', $uri, $query);
        }

        try {
            // Http request
            $client = new Client($this->host, $this->port);
            $client->setMethod($method);
            $client->set(['timeout' => $this->timeout]);

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

            if ($statusCode == -1 || $statusCode == -2 || $statusCode == -3) {
                throw new \Exception(
                    sprintf(
                        'Request timeout!(host=%s, port=%d timeout=%d)',
                        $this->host,
                        $this->port,
                        $this->timeout
                    )
                );
            }

        } catch (Throwable $e) {
            $message = sprintf('Consul is fail! (uri=%s status=%s body=%s).', $uri, $e->getMessage(), $body);
            throw new \Exception($message);
        }

        if (400 <= $statusCode) {
            $message = sprintf('Consul is fail! (uri=%s status=%s  body=%s)', $uri, $statusCode, $body);
            if (500 <= $statusCode) {
                throw new \Exception($message, $statusCode);
            }
            throw new \Exception($message, $statusCode);
        }

        return ['headers' => $headers, 'body' => $body, 'statusCode' => $statusCode];
    }
}