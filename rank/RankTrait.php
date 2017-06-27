<?php
/**
 * RankTrait
 *
 * @author : tookly
 * @created: 17/03/23
 */

namespace Rank;

use Log;

trait RankTrait
{
    public static function getUserRankCacheKey($uid, $attr, $config, ...$args)
    {
        $key = self::getBaseKey('U', $attr, $config, ...$args);
        return $key = $key.":".$uid;
    }

    public static function getUserDetailKey($uid, $attr, $config, ...$args)
    {
        $key = self::getBaseKey('HASH', $attr, $config, ...$args);
        return $key = $key.":".$uid;
    }

    public static function getUserKeysByConf($attr, $config, $uid = 0, $categoryId = 0)
    {
        return self::getBaseKeysByConf('HASH', $attr, $config, $uid, $categoryId);
    }

    public static function getCacheKey($attr, $config, ...$args)
    {
        return self::getBaseKey('rank', $attr, $config, ...$args);
    }

    public static function getCacheKeysByConf($attr, $config, $uid = 0, $categoryId = 0)
    {
        return self::getBaseKeysByConf('rank', $attr, $config, $uid, $categoryId);
    }

    public static function getKey($attr, $config, ...$args)
    {
        return self::getBaseKey('zset', $attr, $config, ...$args);
    }

    public static function getKeysByConf($attr, $config, $uid = 0, $categoryId = 0)
    {
        return self::getBaseKeysByConf('zset', $attr, $config, $uid, $categoryId);
    }

    /**
     * 根据参数获取排行榜key
     *
     * @param $logo
     * @param $attr
     * @param $config
     * @param array ...$args
     *
     * @return string
     */
    public static function getBaseKey($logo, $attr, $config, ...$args)
    {
        $key = '';
        if(empty($config['activity']) || empty($config['ranks'][$attr])) {
            return $key;
        }
        $key = sprintf('%s:%s:%s', strtoupper($logo), strtoupper($config['activity']), strtoupper($attr));

        foreach ($args as $arg) {
            if($arg && (is_string($arg) || is_int($arg))) {
                $key = $key.":".strtoupper($arg);
            }
        }

        return $key;
    }

    /**
     * 根据配置获取排行榜keys
     *
     * @param $logo
     * @param $attr
     * @param $config
     * @param $uid
     * @param $categoryId
     *
     * @return array
     */
    public static function getBaseKeysByConf($logo, $attr, $config, $uid = 0, $categoryId = 0)
    {
        $keys = [];
        if(empty($config['activity']) || empty($config['ranks'][$attr])) {
            return $keys;
        }
        $prefix = sprintf('%s:%s:%s', strtoupper($logo), strtoupper($config['activity']), strtoupper($attr));

        //判断主分类
        $type = self::getRankTypeByCategory($config['ranks'][$attr]['types'], $categoryId);
        if($type){
            $prefix = $prefix.":".$type;
        }

        //增加时间维度
        $oldPrefix = $prefix;
        foreach ($config['ranks'][$attr]['types'] as $item) {
            if(strtoupper($item['type']) != $type) {
                continue;
            }

            //增加主播维度
            if(isset($item['uid']) && $item['uid'] === true) {
                $prefix = $oldPrefix.":".$uid;
            }

            foreach ($item['date'] as $date) {
                $date = self::getDate($date, $config['activity']);
                if($date) {
                    $keys[] = $prefix.":".$date;
                } else {
                    $keys[] = $prefix;
                }
            }
        }

        return $keys;
    }

    /**
     * 获取分类
     *
     * @param $configs
     * @param $categoryId
     * @return string
     */
    public static function getRankTypeByCategory($configs, $categoryId)
    {
        // 无配置采用默认分类方法
        if(empty($configs)) {
            return '';
        }

        // 有配置根据配置返回榜单名
        foreach ($configs as $config) {
            if(in_array($categoryId, $config['category'])) {
                return strtoupper($config['type']);
            }
        }

        return strtoupper($configs['base']['type']);
    }

    /**
     * 获取榜单日期 0:总榜　１:当日日榜　-1:昨天日榜 　
     *
     * @param $date
     * @return int|string
     */
    public static function getDate($date, $activity)
    {
        switch ($date) {
            case 0:
                return 0;
            case 1:
                return date('Ymd', time());
            case -1:
                return date('Ymd', time() - 86400);
            default:
                return 0;
        }
    }


    /**
     * 获取排行榜活动时间
     *
     * @param $config
     * @return array
     */
    public static function getActiveTime($config)
    {
        if(!$config) {
            Log::error('未找到排行榜相关配置,返回无效时间');
            return array('begin' => '2017-01-01 00:00:00', 'end' => '2017-01-01 00:00:00');
        }

        return array('begin' => $config['startDate'], 'end' => $config['endDate']);
    }
}
