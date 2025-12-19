<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[BasicTrainingReadingGrammar]].
 *
 * @see BasicTrainingReadingGrammar
 */
class BasicTrainingReadingGrammarQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return BasicTrainingReadingGrammar[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return BasicTrainingReadingGrammar|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
