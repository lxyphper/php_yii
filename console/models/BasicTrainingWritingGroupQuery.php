<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[BasicTrainingWritingGroup]].
 *
 * @see BasicTrainingWritingGroup
 */
class BasicTrainingWritingGroupQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return BasicTrainingWritingGroup[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return BasicTrainingWritingGroup|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
