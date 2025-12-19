<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[ExamCollectionRecord]].
 *
 * @see ExamCollectionRecord
 */
class ExamCollectionRecordQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return ExamCollectionRecord[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return ExamCollectionRecord|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
