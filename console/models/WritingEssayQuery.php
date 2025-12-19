<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[WritingEssay]].
 *
 * @see WritingEssay
 */
class WritingEssayQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return WritingEssay[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return WritingEssay|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
