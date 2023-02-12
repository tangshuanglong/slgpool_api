<?php


namespace Lib;

/**
 * 负载均衡
 * Class SLB
 * @package Lib
 */
class SLB
{
    /**
     * 负载均衡简单轮训算法的索引值
     * @var array
     */
    private static $index = [];

    /**
     * 简单轮训算法
     * @param string $service 服务名
     * @param int $count 服务总数
     * @return mixed int
     */
    private static function simple_loop(string $service, int $count): int
    {
        if (!isset(self::$index[$service])) {
            self::$index[$service] = 0;
        }
        //简单轮训算法
        $index = (self::$index[$service] + 1) % $count;
        self::$index[$service] = $index;
        return $index;
    }

    /**
     * 获取对应的服务信息
     * @param array $services
     * @param string $service 服务名
     * @return array|mixed
     */
    public static function get_service_info(array $services, string $service): array
    {
        //初始化index
        $count = count($services);
        if ($count === 0){
            return [];
        }
        //初始化index
        $index = self::simple_loop($service, $count);
        return $services[$index];
    }
}