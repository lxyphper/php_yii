<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[BasicTrainingReadingRecord]].
 *
 * @see BasicTrainingReadingRecord
 */
class BasicTrainingReadingRecordQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return BasicTrainingReadingRecord[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return BasicTrainingReadingRecord|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
