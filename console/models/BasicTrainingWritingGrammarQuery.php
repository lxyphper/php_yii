<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[BasicTrainingWritingGrammar]].
 *
 * @see BasicTrainingWritingGrammar
 */
class BasicTrainingWritingGrammarQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return BasicTrainingWritingGrammar[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return BasicTrainingWritingGrammar|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
