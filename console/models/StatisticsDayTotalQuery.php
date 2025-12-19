<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[StatisticsDayTotal]].
 *
 * @see StatisticsDayTotal
 */
class StatisticsDayTotalQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return StatisticsDayTotal[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return StatisticsDayTotal|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
