<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[WritingBigEssaySampleText]].
 *
 * @see WritingBigEssaySampleText
 */
class WritingBigEssaySampleTextQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return WritingBigEssaySampleText[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return WritingBigEssaySampleText|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
