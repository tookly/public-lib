<?php
/**
 * RankFactory
 *
 * @author : tookly
 * @created: 17/03/23
 */

namespace Quanmin\Activity\Rank;

use Log;
use RankConfig;

class RankFactory {

    private static $active = null;

    public static function creatAvtivity($activity = 'base', $dev = false)
    {
        $activity = strtolower($activity);

        if(empty($activity)) {
            return new RankBase;
        }

        try {
            return call_user_func(array(RankBaseOnConfig::class, 'getInstance'), $activity, $dev);
        } catch (\Exception $e) {
            Log::info('传入参数无效');
            return new RankBase;
        }
    }

}
