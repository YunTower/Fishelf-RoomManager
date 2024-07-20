<?php

namespace fishelf\Room;

use support\Redis;

class Manager
{
    private Redis $redis;
    private array $room_list = [];
    private array $online_list = [];

    public function __construct()
    {
        $this->redis = new Redis();
        // 同步redis中的数据
        $this->room_list = $this->redis->get('RM_room_list') ?? [];
        $this->online_list = $this->redis->get('RM_online_list') ?? [];
    }

    /**
     * 创建直播间
     * @param string $room_id roomid
     * @param int $room_owner 主播id
     * @param string $room_owner_clientid 主播clientid
     * @param string|null $room_title 房间标题
     * @return void
     */
    public function addRoom(string $room_id, int $room_owner, string $room_owner_clientid, string $room_title = null): void
    {
        $this->room_list[] = [
            'room_id' => $room_id,
            'room_title' => $room_title,
            'room_owner' => $room_owner,
            'room_owner_clientid' => $room_owner_clientid
        ];
        $this->redis->set('RM_room_list', $this->room_list);
    }

    /**
     * 删除房间
     * @param string $roomid roomid
     * @return void
     */
    public function removeRoom(string $roomid): void
    {
        // 检索room_id匹配的房间
        $room = array_filter($this->room_list, function ($room) use ($roomid) {
            return $room['room_id'] === $roomid;
        });
        if (count($room) > 0) {
            // 删除房间
            $this->room_list = array_filter($this->room_list, function ($room) use ($roomid) {
                return $room['room_id'] !== $roomid;
            });
        }
        $this->redis->set('RM_room_list', $this->room_list);
    }

    /**
     * 获取房间列表
     * @return array
     */
    public function getRooms(): array
    {
        return $this->room_list;
    }

    /**
     * 添加用户到房间
     * @param int $uid uid
     * @param string $clientid clientid
     * @param string $roomid roomid
     * @param array $setting 用户配置
     * @return void
     */
    public function addUserToRoom(int $uid, string $clientid, string $roomid, array $setting = ['mute' => false]): void
    {
        $this->online_list[$roomid] = !empty($this->online_list[$roomid]) ?? [];
        $this->online_list[$roomid][] = [
            'uid' => $uid,
            'clientid' => $clientid,
            'roomid' => $roomid,
            'setting' => $setting
        ];
        $this->redis->set('RM_online_list', $this->online_list);
    }

    /**
     * 删除用户
     * @param int $uid uid
     * @param string $roomid roomid
     * @return void
     */
    public function removeUserFromRoom(int $uid, string $roomid): void
    {
        // 通过uid删除用户
        $this->online_list = array_filter($this->online_list[$roomid], function ($user) use ($uid) {
            return $user['uid'] !== $uid;
        });
        $this->redis->set('RM_online_list', $this->online_list);
    }

    /**
     * 获取房间用户列表
     * @param string $roomid roomid
     * @return array
     */
    public function getRoomUsers(string $roomid): array
    {
        return $this->online_list[$roomid];
    }

    /**
     * 设置房间用户配置
     * @param string $roomid roomid
     * @param int $uid uid
     * @param array $setting 用户配置
     * @return void
     */
    public function setRoomUserSetting(string $roomid, int $uid, array $setting): void
    {
        $this->online_list[$roomid] = array_map(function ($user) use ($uid, $setting) {
            if ($user['uid'] === $uid) {
                $setting = array_merge($user['setting'], $setting);
                $user['setting'] = $setting;
            }
            return $user;
        }, $this->online_list[$roomid]);
        $this->redis->set('RM_online_list', $this->online_list);
    }

    /**
     * 获取房间用户配置
     * @param string $roomid roomid
     * @param int $uid uid
     * @return array
     */
    public function getRoomUserSetting(string $roomid, int $uid): array
    {
        return array_filter($this->online_list[$roomid], function ($user) use ($uid) {
            return $user['uid'] === $uid;
        })[0]['setting'];
    }

    /**
     * 获取房间用户数量
     * @param string $roomid roomid
     * @return int
     */
    public function getRoomUserCount(string $roomid): int
    {
        return count($this->online_list[$roomid]);
    }

    /**
     * 获取房间用户列表
     * @param string $roomid
     * @return array|array[]
     */
    public function getRoomUserList(string $roomid): array
    {
        return array_map(function ($user) {
            return [
                'uid' => $user['uid'],
                'clientid' => $user['clientid'],
                'roomid' => $user['roomid'],
                'setting' => $user['setting']
            ];
        }, $this->online_list[$roomid]);
    }

    /**
     * 获取房间用户信息
     * @param string $roomid roomid
     * @param int $uid uid
     * @return array
     */
    public function getRoomUserInfo(string $roomid, int $uid): array
    {
        return array_filter($this->online_list[$roomid], function ($user) use ($uid) {
            return $user['uid'] === $uid;
        });
    }
}