<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[StatisticsDaySpecial]].
 *
 * @see StatisticsDaySpecial
 */
class StatisticsDaySpecialQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return StatisticsDaySpecial[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return StatisticsDaySpecial|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
