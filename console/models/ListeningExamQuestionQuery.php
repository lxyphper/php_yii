<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[ListeningExamQuestion]].
 *
 * @see ListeningExamQuestion
 */
class ListeningExamQuestionQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return ListeningExamQuestion[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return ListeningExamQuestion|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
