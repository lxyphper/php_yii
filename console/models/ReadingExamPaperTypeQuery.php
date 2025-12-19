<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[ReadingExamPaperType]].
 *
 * @see ReadingExamPaperType
 */
class ReadingExamPaperTypeQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return ReadingExamPaperType[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return ReadingExamPaperType|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
