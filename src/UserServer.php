<?php
/**
 * Created By PhpStorm
 * User: 曹伟
 * Date: 2021/1/27
 */

namespace Weigot\Swoole;


use Illuminate\Support\Facades\Redis;
use Weigot\Swoole\Enum\CacheKeyEnum;

class UserServer
{
    /**
     * 获取房间中的所有用户
     * @param $roomId
     * @return mixed
     */
    public static function getUsersByRoom($roomId)
    {
        $key = self::chatRoomUserKey($roomId);
        $users = Redis::zrevrange($key, 0, -1, true);
        ksort($users);
        return $users;
    }

    /**
     * 获取房间的缓存key
     * @param $roomId
     * @return string
     */
    public static function chatRoomUserKey($roomId)
    {
        return CacheKeyEnum::ROOM_UID_KEY . $roomId;
    }

    /**
     * 通过用户id获得用户在房间中的fd
     * @param $roomId
     * @param $userId
     * @return mixed
     */
    public static function getUserFd($roomId, $userId)
    {
        $key = self::chatRoomUserKey($roomId);
        $fd = Redis::zscore($key, $userId);
        return (int)$fd;
    }

    /**
     * 房间加入用户
     * @param $roomId
     * @param $userId
     * @param $fd
     */
    public static function roomAddUser($roomId, $userId, $fd)
    {
        $key = self::chatRoomUserKey($roomId);
        // 记录fd和用户标志的对应关系(利用有序集合特点，fd做分值，userId做value)
        Redis::zadd($key, $fd, $userId);
    }

    /**
     * 通过签名获得用户的详情
     * @param $sign
     * @return mixed
     */
    public static function userInfoBySign($sign)
    {
        $key = CacheKeyEnum::SIGN_KEY . $sign;
        $user = Redis::hgetall($key);
        return $user;
    }

    /**
     * 通过用户id获得用户详情
     * @param $userId
     * @return mixed
     */
    public static function userInfoById($userId)
    {
        $key = CacheKeyEnum::USER_INFO_KEY . $userId;
        $userInfo = Redis::hgetall($key);
        return $userInfo;
    }

    /**
     * 设置用户信息
     * @param $userInfo
     * @param int $expireTime
     */
    public static function setUserInfoById($userInfo, $expireTime = 172800)
    {
        empty($userInfo["uid"]) && $userInfo["uid"] = SignServer::uid();
        empty($userInfo["created"]) && $userInfo["created"] = SignServer::created();
        $key = CacheKeyEnum::USER_INFO_KEY . $userInfo["id"];
        Redis::hdel($key);
        Redis::hmset($key, $userInfo);
        if ($expireTime > 0) {
            Redis::expire($key, $expireTime);
        }
    }

    /**
     * 通过fd获得房间中的用户id
     * @param $roomId
     * @param $fd
     * @return mixed
     */
    public static function userIdsByFd($roomId, $fd)
    {
        $key = self::chatRoomUserKey($roomId);
        $userIds = Redis::zrangebyscore($key, $fd, $fd);
        return $userIds;
    }

    /**
     * 用户userId和fd绑定
     * @param $userId
     * @param $fd
     */
    public static function bindUserIdFd($userId, $fd)
    {
        $uidBindKey = CacheKeyEnum::BIND_UID_KEY;
        Redis::zadd($uidBindKey, $fd, $userId);
    }

    /**
     * 通过fd获取绑定的用户id
     * @param $fd
     * @return mixed
     */
    public static function getBindUserIds($fd)
    {
        $uidBindKey = CacheKeyEnum::BIND_UID_KEY;
        $userIds = Redis::zrangebyscore($uidBindKey, $fd, $fd);
        return $userIds;
    }

    /**
     * 解除绑定
     * @param $userId
     */
    public static function unBindUserId($userId)
    {
        // 解除绑定
        $uidBindKey = CacheKeyEnum::BIND_UID_KEY;
        Redis::zrem($uidBindKey, $userId);
    }

    /**
     * 从房间中移除用户
     * @param $userInfo
     * @param $userId
     */
    public static function removeUserFromRoom($userInfo, $userId)
    {
        // 移除房间中的用户
        $key = self::chatRoomUserKey($userInfo["groupId"]);
        Redis::zrem($key, $userId);
        // 删除用户中的房间标记
        unset($userInfo["groupId"]);
        self::setUserInfoById($userInfo);
        // 删除用户的绑定fd
        $bindKey = CacheKeyEnum::BIND_UID_KEY;
        Redis::zrem($bindKey, $userId);
    }
}