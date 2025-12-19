<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[VocabularyBook]].
 *
 * @see VocabularyBook
 */
class VocabularyBookQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return VocabularyBook[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return VocabularyBook|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
