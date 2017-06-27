<?php
/**
 * RankIBase
 *
 * @author : tookly
 * @created: 17/03/23
 */

namespace Rank;

interface RankIBase
{
    public function subject($uid);
    public function room($uid, $categoryId, $owid);
}
