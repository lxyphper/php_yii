<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[ExamCollectionRelation]].
 *
 * @see ExamCollectionRelation
 */
class ExamCollectionRelationQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return ExamCollectionRelation[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return ExamCollectionRelation|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
