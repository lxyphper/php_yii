<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[BasicTrainingReadingGroup]].
 *
 * @see BasicTrainingReadingGroup
 */
class BasicTrainingReadingGroupQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return BasicTrainingReadingGroup[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return BasicTrainingReadingGroup|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
