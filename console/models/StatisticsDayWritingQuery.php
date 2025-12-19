<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[StatisticsDayWriting]].
 *
 * @see StatisticsDayWriting
 */
class StatisticsDayWritingQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return StatisticsDayWriting[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return StatisticsDayWriting|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
