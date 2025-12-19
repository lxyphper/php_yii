<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[BasicTrainingReadingQuestion]].
 *
 * @see BasicTrainingReadingQuestion
 */
class BasicTrainingReadingQuestionQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return BasicTrainingReadingQuestion[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return BasicTrainingReadingQuestion|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
