<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[StatisticsDayListening]].
 *
 * @see StatisticsDayListening
 */
class StatisticsDayListeningQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return StatisticsDayListening[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return StatisticsDayListening|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
