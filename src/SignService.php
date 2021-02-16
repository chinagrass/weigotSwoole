<?php

namespace Weigot\Swoole;


use Illuminate\Support\Facades\Redis;
use Weigot\Swoole\Enum\CacheKeyEnum;
use Weigot\Swoole\Exception\WGException;

class SignService
{
    /**
     * @param $user
     * @param $roomId
     * @param int $expireTime
     * @return string
     * @throws WGException
     */
    public static function getSign($user, $roomId, $expireTime = 86400)
    {
        if (empty($user["id"]) || empty($user["username"])) {
            throw new WGException("Basic information of users is error");
        }
        //@do 限定连接人数
        $cacheKeyRoom = CacheKeyEnum::LIMIT_CONNECTIONS . $roomId;
        $roomLimitConnections = Redis::get($cacheKeyRoom);
        if (!$roomLimitConnections) {
            //@do 判断连接数量
            $userNum = UserService::roomUserNum($roomId);
            if ($userNum >= $roomLimitConnections) {
                throw new WGException("Connection number overrun");
            }
        }
        $user["uid"] = self::uid();
        $user["created"] = self::created();
        $key = implode("-", $user);
        $sign = self::createSign($key);
        // @do 存入缓存
        $cacheKey = CacheKeyEnum::SIGN_KEY . $sign;
        Redis::hmset($cacheKey, $user);
        if ($expireTime > 0) {
            Redis::expire($cacheKey, $expireTime);
        }
        // @do 存入用户详情
        UserService::setUserInfoById($user, $expireTime * 2);
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

    /**
     * 生成uid
     * @return string
     */
    public static function uid()
    {
        return md5(uniqid());
    }

    /**
     * 设置创建时间
     * @return int
     */
    public static function created()
    {
        return time();
    }
}