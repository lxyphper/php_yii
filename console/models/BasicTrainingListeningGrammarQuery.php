<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[BasicTrainingListeningGrammar]].
 *
 * @see BasicTrainingListeningGrammar
 */
class BasicTrainingListeningGrammarQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return BasicTrainingListeningGrammar[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return BasicTrainingListeningGrammar|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
