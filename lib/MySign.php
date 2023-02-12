<?php

namespace Lib;

use Lib\Redis;

/**
 * 验签类
 * Class MySign
 * @package Lib
 */
class MySign
{

    private $hash_key;
    private $redis_key = 'redis_rsa_pub_key';
    private $redis_sign_token_key = 'redis_sign_token';

    /**
     * @var mixed
     */
    private $app_sign_timeout;

    /**
     * @var mixed
     */
    private $app_sign_cache_timeout;


    public function __construct()
    {
        $this->encryption_key = 'encryption_key';
        $this->app_sign_timeout = 3600;
        $this->app_sign_cache_timeout = 3600;
    }

    /**
     * sha256接口验签
     * @param array $data 数组 请求数据
     * @return boolean
     */
    public function checkSign(array $data): bool
    {
        $verifySign = $data['sign'];
        if(APP_DEBUG === false){
            //请求超时
            if(time() > (round($data['timestamp'] / 1000) + $this->app_sign_timeout)){
                return false;
            }
            //每个签名只可以使用一次
//            if(Redis::exists($verifySign)){
//                return false;
//            }
        }
        //将sign置为空，在生成签名字符串时会自动去掉
        $data['sign'] = '';
        $signContent = $this->getSignContent($data);
        //生成签名
        $sign = $this->sign($signContent);
        //验签
        if(strcasecmp($verifySign, $sign) != 0){
            return false;
        }
        //将签名为唯一key存入缓存
        //Redis::set($verifySign, true, $this->app_sign_cache_timeout);
        return true;
    }


    /**
     * 创建签名
     * @param array $params
     * @param bool $isAes
     * @return string
     */
    public function generateSign(array $params)
    {
        //获取待生成验签字符串
        $signContent = $this->getSignContent($params, $type = 1);
        //生成签名字符串
        return $this->sign($signContent);
    }

    /**
     * PC端和手机端无需登录的接口生成签名字符串
     * @param string $data
     * @return string
     */
    public function sign(string $data)
    {
        return base64_encode(hash('sha256', $data, true));
    }

    /**
     * 获取签名字符串
     * @param array $params
     * @param int $type $type 1-PC端和手机端无需登录的接口签名字符串，2-app和PC端和手机端需要登录的接口
     * @return string
     */
    public function getSignContent(array $params, int $type = 1)
    {
        //按关联数组的键名做升序排序
        ksort($params);
        reset($params);
        $stringToBeSigned = '';
        $i = 0;
        //拼接成key=value&key=value字符串形式
        foreach($params as $key => $val){
            if(Common::checkEmpty($val) === false && substr($val, 0, 1) != '@'){
                if($i == 0){
                    if($type === 1){
                        $stringToBeSigned .= $key . "=" . $val;
                    }else{
                        $stringToBeSigned .= $key . "-" . $val;
                    }
                }else{
                    if($type === 1){
                        $stringToBeSigned .= "&" . $key . "=" . $val;
                    }else{
                        $stringToBeSigned .= "&" . $key . "-" . $val;
                    }

                }
                $i++;
            }
        }

        return $stringToBeSigned;
    }
}
