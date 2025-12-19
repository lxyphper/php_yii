<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[StatisticsDayReading]].
 *
 * @see StatisticsDayReading
 */
class StatisticsDayReadingQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return StatisticsDayReading[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return StatisticsDayReading|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
