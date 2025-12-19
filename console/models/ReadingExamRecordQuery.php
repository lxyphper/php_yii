<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[ReadingExamRecord]].
 *
 * @see ReadingExamRecord
 */
class ReadingExamRecordQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return ReadingExamRecord[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return ReadingExamRecord|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
