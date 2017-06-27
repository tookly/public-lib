<?php
/**
 * RankBase
 *
 * @author : tookly
 * @created: 17/03/23
 */

namespace Rank;


class RankBase implements RankIBase
{

    public function __construct()
    {

    }


    /**
     * 获取专题页排行榜
     */
    public function subject($uid)
    {
        return [];
    }

}
