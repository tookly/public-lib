<?php
/**
 * RankConfig
 *
 * @author : tookly
 * @created: 17/06/16
 */

namespace Rank;

class RankConfig
{
    public static $configs;
    public static $config;

    public static $configKeys = [
        'base', 
    ];

    public static function setConfigs()
    {
        foreach (self::$configKeys as $configKey) {
            self::setConfig($configKey);
        }
    }

    public static function setConfig($configKey)
    {
        $configKey = strtolower($configKey);

        if(!isset(self::$$configKey)) {
            self::$configs[$configKey] = [];
            return;
        }

        $config = array_merge(self::$base, self::$$configKey);
        foreach ($config['ranks'] as $key => $rank) {
            !isset($rank['score']) && $rank['score'] = isset($config['score']) ? $config['score'] : [];
            !isset($rank['types']) && $rank['types'] = $config['types'];
            $rank['info'] = isset($rank['info']) ? array_merge($config['info'], $rank['info']) : $config['info'];
            $rank['userInfo'] = isset($rank['userInfo']) ? array_merge($config['userInfo'], $rank['userInfo']) : $config['userInfo'];
            $ranks[$key] = $rank;
        }
        $config['ranks'] = isset($ranks) ? $ranks : $config['ranks'];
        unset($config['score']);
        unset($config['types']);
        unset($config['info']);
        unset($config['userInfo']);
        self::$configs[$configKey] = $config;
    }

    public static function getConfig($configKey)
    {
        if(!isset(self::$configs[$configKey])) {
            self::setConfig($configKey);
        }
        return self::$configs[$configKey];
    }

    //配置优先级　ranks > activity > base  保留字　category owid uid
    public static $base = [
        //榜单名称
        'activity'  => 'base',

        //开始结束时间
        'startDate' => '2017-01-01 10:00:00',
        'endDate'   => '2017-01-01 23:59:59',

        //榜单展示规则 位置(api) => 榜单
        'show' => [
            'subject' => [
                'userField'  => ['portrait', 'nickname', 'no', 'uid'],   //返回用户信息
                'rankInfo'   => false,   //返回用户排行信息 指定ranks index
                'rankDetail' => false,   //返回用户榜单贡献信息　指定ranks index
                'ranks' => [
                    ['name' => 'anchor', 'type' => 'pastime', 'date' => 0],
                    ['name' => 'anchor', 'type' => 'show', 'date' => 0],
                    ['name' => 'anchor', 'type' => 'game', 'date' => 0],
                    ['name' => 'user', 'type' => 'pastime', 'date' => 0],
                    ['name' => 'user', 'type' => 'show', 'date' => 0],
                    ['name' => 'user', 'type' => 'game', 'date' => 0],
                ],
            ],
        ],

        //榜单 基本属性 活动需要提供的所有榜单
        'ranks' => [
            'anchor' =>
                [
                    'name'         => 'anchor',  //主播榜 基础key => ZSET:{BASE}:{ANCHOR}
                    'rankDetail'   => false,         //是否记录用户贡献详情
                    'support' => 'support', //相应助攻榜
                    'info' => [
                        'start'          => 0,
                        'end'            => 9,
                        'userField'      => ['portrait', 'nickname', 'no', 'uid'],
                        'userExtraField' => ['score', 'status', 'support'],
                        'cacheTime'      => 60,
                    ],
                ],
            'user' =>
                [
                    'name' => 'user',    //用户贡献榜
                ],
            'support' =>
                [
                    'name'  => 'support',    //用户助攻榜
                    'types' => [
                        [
                            'type'     => 'pastime',
                            'category' => [4, 13, 28, 38],
                            'date'     => [0],
                            'owid'     => true,
                        ],
                        [
                            'type' => 'show',
                            'category' => [29],
                            'date' => [0],
                            'owid'     => true,
                        ],
                        'base' => [
                            'type' => 'game',
                            'category' => [],
                            'date' => [0],
                            'owid'     => true,
                        ]
                    ],
                ]
        ],

        //榜单维度规则 主要用于生成榜单数据
        'types' => [
            [
                'type'     => 'pastime',             //榜单分类
                'category' => [4, 13, 28, 38],       //分类规则 category 根据主播所在分类
                'date'     => [0],                   //0-总榜 1-日榜 2-周榜[待扩展] 3-时榜[待扩展]
                //总榜扩展key => 基础key:PASTIME
                //日榜扩展key => 基础key:PASTIME:20170101
            ],
            [
                'type' => 'show',
                'category' => [29],
                'date' => [0],
            ],
            'base' => [
                'type' => 'game',
                'category' => [],
                'date' => [0],
            ]
        ],

        //榜单信息 主要用于生成榜单需要展示的数据
        'info' => [
            'start'          => 0,
            'end'            => 9,
            'userField'      => ['portrait', 'nickname', 'no', 'uid'],
            'userExtraField' => ['score'],
            'cacheTime'      => 60,
        ],

        //用户相关信息 排行信息&贡献信息 用于生成用户信息
        'userInfo' => [
            'userField'      => ['portrait', 'nickname', 'no', 'uid'],
            'userExtraField' => ['ranking', 'score', 'gap'],
            'maxRank'        => '99+',
            'minGap'         => 1,
            'cacheTime'      => 10,
        ]
    ];

}
