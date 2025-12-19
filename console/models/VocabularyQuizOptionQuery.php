<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[VocabularyQuizOption]].
 *
 * @see VocabularyQuizOption
 */
class VocabularyQuizOptionQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return VocabularyQuizOption[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return VocabularyQuizOption|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
