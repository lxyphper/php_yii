<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[ExamCollectionPage]].
 *
 * @see ExamCollectionPage
 */
class ExamCollectionPageQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return ExamCollectionPage[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return ExamCollectionPage|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
