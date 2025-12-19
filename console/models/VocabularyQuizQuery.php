<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[VocabularyQuiz]].
 *
 * @see VocabularyQuiz
 */
class VocabularyQuizQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return VocabularyQuiz[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return VocabularyQuiz|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
