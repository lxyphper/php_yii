<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[VocabularyUnitRelation]].
 *
 * @see VocabularyUnitRelation
 */
class VocabularyUnitRelationQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return VocabularyUnitRelation[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return VocabularyUnitRelation|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
