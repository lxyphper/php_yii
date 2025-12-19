<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[BasicTrainingListeningQuestion]].
 *
 * @see BasicTrainingListeningQuestion
 */
class BasicTrainingListeningQuestionQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return BasicTrainingListeningQuestion[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return BasicTrainingListeningQuestion|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
