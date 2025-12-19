<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[StatisticsDaySpeaking]].
 *
 * @see StatisticsDaySpeaking
 */
class StatisticsDaySpeakingQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return StatisticsDaySpeaking[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return StatisticsDaySpeaking|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
