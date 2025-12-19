<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[BasicTrainingListeningRecord]].
 *
 * @see BasicTrainingListeningRecord
 */
class BasicTrainingListeningRecordQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return BasicTrainingListeningRecord[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return BasicTrainingListeningRecord|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
