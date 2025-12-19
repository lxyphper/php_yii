<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[ListeningExamQuestionOption]].
 *
 * @see ListeningExamQuestionOption
 */
class ListeningExamQuestionOptionQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return ListeningExamQuestionOption[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return ListeningExamQuestionOption|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
