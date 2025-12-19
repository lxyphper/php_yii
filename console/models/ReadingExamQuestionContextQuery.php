<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[ReadingExamQuestionContext]].
 *
 * @see ReadingExamQuestionContext
 */
class ReadingExamQuestionContextQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return ReadingExamQuestionContext[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return ReadingExamQuestionContext|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
