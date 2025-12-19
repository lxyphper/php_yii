<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[ReadingExamQuestionOption]].
 *
 * @see ReadingExamQuestionOption
 */
class ReadingExamQuestionOptionQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return ReadingExamQuestionOption[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return ReadingExamQuestionOption|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
