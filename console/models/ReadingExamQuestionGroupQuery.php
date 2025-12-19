<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[ReadingExamQuestionGroup]].
 *
 * @see ReadingExamQuestionGroup
 */
class ReadingExamQuestionGroupQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return ReadingExamQuestionGroup[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return ReadingExamQuestionGroup|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
