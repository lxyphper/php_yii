<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[WritingEssayRecord]].
 *
 * @see WritingEssayRecord
 */
class WritingEssayRecordQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return WritingEssayRecord[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return WritingEssayRecord|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
