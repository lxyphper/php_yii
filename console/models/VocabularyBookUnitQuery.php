<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[VocabularyBookUnit]].
 *
 * @see VocabularyBookUnit
 */
class VocabularyBookUnitQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return VocabularyBookUnit[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return VocabularyBookUnit|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
