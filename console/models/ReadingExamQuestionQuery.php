<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[ReadingExamQuestion]].
 *
 * @see ReadingExamQuestion
 */
class ReadingExamQuestionQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return ReadingExamQuestion[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return ReadingExamQuestion|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
