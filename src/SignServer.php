<?php

namespace Weigot\Swoole;


use Illuminate\Support\Facades\Redis;
use Weigot\Swoole\Enum\CacheKeyEnum;

class SignServer
{
    /**
     * @param $userId 用户id/唯一标识
     * @param $username 用户名称
     * @param int $expireTime 过期时间
     * @return string
     */
    public static function getSign($userId, $username, $expireTime = 0)
    {
        $user = [
            "id" => $userId,
            "username" => $username,
            "uid" => md5(uniqid()),
            "created" => time()
        ];
        $key = implode("-", $user);
        $sign = self::createSign($key);
        // @do 存入缓存
        $cacheKey = CacheKeyEnum::SIGN_KEY . $sign;
        Redis::hmset($cacheKey, $user);
        if ($expireTime > 0) {
            Redis::expire($cacheKey, $expireTime);
        }
        // @do 存入用户详情
        $userCacheKey = CacheKeyEnum::USER_INFO_KEY . $userId;
        Redis::hmset($userCacheKey, $user);
        return $sign;
    }

    /**
     * @param $key
     * @return string
     */
    protected static function createSign($key)
    {
        return md5($key);
    }
}