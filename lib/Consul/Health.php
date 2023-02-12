<?php

//require_once __DIR__.'/Consul.php';
//require_once  dirname(__DIR__).'/helper/OptionsResolver.php';
namespace Lib\Consul;

use Lib\Consul\Consul;
use Lib\Helper\OptionsResolver;

/**
 * Class Health
 *
 */
class Health
{
    /**
     *
     */
    private $consul;

    public function __construct()
    {
        $this->consul = new Consul();
    }

    /**
     * @param string $node
     * @param array  $options
     *
     */
    public function node(string $node, array $options = [])
    {
        $params = array(
            'query' => OptionsResolver::resolve($options, ['dc']),
        );

        return $this->consul->get('/v1/health/node/' . $node, $params);
    }

    /**
     * @param string $service
     * @param array  $options
     *
     */
    public function checks(string $service, array $options = [])
    {
        $params = [
            'query' => OptionsResolver::resolve($options, ['dc']),
        ];

        return $this->consul->get('/v1/health/checks/' . $service, $params);
    }

    /**
     * @param string $service
     * @param array  $options
     *
     */
    public function service(string $service, array $options = [])
    {
        $params = [
            'query' => OptionsResolver::resolve($options, ['dc', 'tag', 'passing']),
        ];

        return $this->consul->get('/v1/health/service/' . $service, $params);
    }

    /**
     * @param string $state
     * @param array  $options
     *
     */
    public function state(string $state, array $options = [])
    {
        $params = [
            'query' => OptionsResolver::resolve($options, ['dc']),
        ];

        return $this->consul->get('/v1/health/state/' . $state, $params);
    }
}