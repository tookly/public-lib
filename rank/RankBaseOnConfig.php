<?php
/**
 * RankBase
 *
 * @author : tookly
 * @created: 17/03/23
 */

namespace Rank;

use User\User;
use Redis\RedisUtil;
use Room\Room;
use Cache\CacheTemplate;
use RankConfig;

class RankBaseOnConfig implements RankIBase
{
    use RankTrait;

    public $dev;
    public $activity;             //活动名称
    public $redisRank;            //redis对象
    public $config;               //活动配置
    public $configRank;           //活动榜单相关配置

    private function __construct($activity = 'base', $config = null)
    {
        $this->activity = $activity;
        $this->redisRank = RedisUtil::getRedis();
        $this->config = $config;
        $this->configRank = $this->config['ranks'];
    }

    public static function getInstance($activity = 'base')
    {
        $config = RankConfig::getConfig($activity);
        if($config && isset($config['ranks'])) {
            return new self($activity, $config);
        }
        return new RankBase;
    }

    /**
     * 获取专题页排行榜
     *
     * @param $uid
     * @param $attrs
     * @param $types
     * @param $dates
     *
     * @return array
     */
    public function subject($uid = 0, $attrs = [], $types = [], $dates = [])
    {
        $data = [];

        if(!isset($this->config['show']['subject'])) {
            return $data;
        }
        $subject = $this->config['show']['subject'];

        //获取用户信息
        $userInfo = [];
        $userField = $subject['userField'];
        if($userField !== false && is_array($userField)) {
            $userInfo = $this->getUserInfo($uid, $userField);
        }

        //获取用户贡献详情
        $rankDetail = [];
        $rankDetailConfig = $subject['rankDetail'];
        if($rankDetailConfig !== false) {
            $attr = array_shift($rankDetailConfig);
            $rankDetail = $this->getUserRankDetail($uid, $attr, ...$rankDetailConfig);
        }

        $data['userInfo'] = array_merge($userInfo, $rankDetail);

        //获取排行榜信息
        $ranks = $subject['ranks'];
        foreach ($ranks as $rank) {
            if(!in_array($rank['name'], $attrs)) {
                continue;
            }
            if(!in_array($rank['type'], $types)) {
                continue;
            }
            if(!in_array($rank['date'], $dates)) {
                continue;
            }
            $item['attr'] = $rank['name'];
            $item['type'] = $rank['type'];
            $item['date'] = RankTrait::getDate($rank['date'], $this->activity);
            $item['rank'] = self::getCacheRank($item['attr'], $item['type'], $item['date']);
            $data['ranks'][] = $item;
        }

        return $data;
    }

    private function generateArgs($config, $categoryId, $uid)
    {
        $args = [];

        if($config['type'] === true) {
            $this->type  = $this->getRankTypeByCategory($this->configRank[$config['name']]['types'], $categoryId);
        } else {
            $this->type = $config['type'];
        }
        $args[] = $this->type;

        if(isset($config['uid']) && $config['uid'] === true) {
            $args[] = $uid;
        }

        $this->date = RankTrait::getDate($config['date'], $this->activity);
        $args[] = $this->date;

        return $args;
    }


    /**
     * 获取排行榜缓存数据
     *
     * @param string $attr
     * @param $args
     *
     * @return array|mixed|null|string
     */
    public function getCacheRank($attr, ...$args)
    {
        $cacheKey = RankTrait::getCacheKey($attr, $this->config, ...$args);
        $cacheTime = $this->configRank[$attr]['info']['cacheTime'];
        $cacheTemple = new CacheTemplate($cacheTime);
        $cache = $cacheTemple->fetch($cacheKey, array($this, "getRank"), $attr, ...$args);
        return isset($cache) ? $cache : [];
    }

    /**
     * 获取排行榜源数据
     *
     * @param $attr
     * @param $args
     * @return array
     */
    public function getRank($attr, ...$args)
    {
        if (empty($attr)) {
            return [];
        }

        $start = $this->configRank[$attr]['info']['start'];
        $end = $this->configRank[$attr]['info']['end'];
        $userField = $this->configRank[$attr]['info']['userField'];
        $userExtraField = $this->configRank[$attr]['info']['userExtraField'];

        $rankKey = RankTrait::getKey($attr, $this->config, ...$args);
        $list = $this->redisRank->zRevRange($rankKey, $start, $end, true);
        $rank = [];
        foreach ($list as $uid => $value) {
            if ($value <= 0) continue;
            $userInfo = self::getUserInfo($uid, $userField);
            $userInfo['score'] = $value;
            foreach ($userExtraField as $field) {
                $function = 'get'.ucfirst($field);
                if(method_exists(static::class, $function)) {
                    $userInfo[$field] = $this->$function($uid, $attr, ...$args);
                }
            }
            $rank[] = $userInfo;
        }

        return empty($rank) ? [] : $rank;
    }


    /**
     * 获取用户排行信息源数据
     *
     * @param $uid
     * @param $attr
     * @param $args
     *
     * @return array|mixed
     */
    public function getCacheUserRankInfo($uid, $attr, ...$args)
    {
        $cacheKey = RankTrait::getUserRankCacheKey($uid, $attr, $this->config, ...$args);
        $cacheTime = $this->configRank[$attr]['userInfo']['cacheTime'];
        $cacheTemple = new CacheTemplate($cacheTime);
        $cache = $cacheTemple->fetch($cacheKey, array($this, "getUserRankInfo"), $uid, $attr, ...$args);
        return isset($cache) ? $cache : [];
    }

    /**
     * 获取用户排名信息
     *
     * @param $uid
     * @param $attr
     * @param $args
     *
     * @return array
     */
    public function getUserRankInfo($uid, $attr, ...$args)
    {
        $rankInfo = [];
        if(empty($uid) || empty($attr)) {
            return $rankInfo;
        }

        $userExtraField = $this->configRank[$attr]['userInfo']['userExtraField'];
        foreach ($userExtraField as $field) {
            $function = 'get'.ucfirst($field);
            if(method_exists(static::class, $function)) {
                $rankInfo[$field] = $this->$function($uid, $attr, ...$args);
            }
        }

        return $rankInfo;
    }

    public function getSupport($uid, $attr, ...$args)
    {
        $userInfo = [];
        $key = RankTrait::getKey($this->configRank[$attr]['support'], $this->config, ...$args);
        $key = $key.":".$uid;
        $support = $this->redisRank->zRevRange($key, 0, 0, true);
        if ($support) {
            $userInfo['uid'] = array_keys($support)[0];
            $userInfo['score'] = array_values($support)[0];
            $userInfo['nickname'] = User::getUser($userInfo['uid'], 'nickname');
        }
        return $userInfo;
    }

    public function getRanking($uid, $attr, ...$args)
    {
        $key = RankTrait::getKey($attr, $this->config, ...$args);
        $ranking = $this->redisRank->zRevRank($key, $uid);
        $maxRank = $this->configRank[$attr]['userInfo']['maxRank'];
        if($ranking === false || $ranking > $maxRank) {
            return $this->configRank[$attr]['userInfo']['maxRank']."+";
        } else {
            $ranking += 1;
            return $ranking;
        }
    }

    public function getScore($uid, $attr, ...$args)
    {
        $key = RankTrait::getKey($attr, $this->config, ...$args);
        $score = $this->redisRank->zScore($key, $uid);
        return empty($score) ? 0 : $score;
    }

    public function getGap($uid, $attr, ...$args)
    {
        $key = RankTrait::getKey($attr, $this->config, ...$args);
        $ranking = $this->redisRank->zRevRank($key, $uid);
        if($ranking == 0) {
            $gap = 0;
        } else {
            $current = $this->redisRank->zScore($key, $uid);
            $previous = $this->redisRank->zRevRange($key, $ranking - 1, $ranking - 1, true);
            $previous = empty($previous) ? 0 : (int)current($previous);
            $gap = abs($current - $previous);
            $end = $this->configRank[$attr]['info']['end'];
            if($ranking > $end + 2 && $gap == 0) {
                $gap = $this->configRank[$attr]['userInfo']['minGap'];
            }
        }
        return $gap;
    }

    public function getUserInfo($uid, $userField)
    {
        if(empty($uid)) {
            return [];
        }

        $user = User::getUser($uid, $userField);
        $user['uid'] = $uid;
        return $user;
    }

    public function getUserRankDetail($uid, $attr, ...$args)
    {
        $data = [];
        if(empty($uid)) {
            return $data;
        }

        $userKey = RankTrait::getUserDetailKey($uid, $attr, $this->config, ...$args);
        $data = $this->redisRank->hGetAll($userKey);
        return $data;
    }
}
