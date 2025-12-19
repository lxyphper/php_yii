<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[WritingPracticeRecord]].
 *
 * @see WritingPracticeRecord
 */
class WritingPracticeRecordQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return WritingPracticeRecord[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return WritingPracticeRecord|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
