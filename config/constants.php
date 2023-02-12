<?php
//基础信息
defined('APP_DEBUG') OR define('APP_DEBUG', true);
defined("SERVICES_TIMEOUT") OR define('SERVICES_TIMEOUT', 10);//服务信息保留的时间 单位秒
defined("HMAC_KEY") OR define('HMAC_KEY', '5f6e16554bfb8437799765659f3b1b5c69fbcfe0f65747f69231dcb2065227aa');//HMAC加密key
defined("INVITE_PREFIX")  OR define('INVITE_PREFIX', 16598450);
defined("AES_ENCRYPTION_KEY")  OR define('AES_ENCRYPTION_KEY', '43743c86523cd414916415229e3241d2f174dc14133999c4f093054172ec1261');

//redis配置信息
defined('REDIS_HOST') OR define('REDIS_HOST', '127.0.0.1');
defined('REDIS_PORT') OR define('REDIS_PORT', 6379);
defined('REDIS_PASSWORD') OR define('REDIS_PASSWORD', '');


