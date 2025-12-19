<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[ReadingExamPaperUnit]].
 *
 * @see ReadingExamPaperUnit
 */
class ReadingExamPaperUnitQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return ReadingExamPaperUnit[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return ReadingExamPaperUnit|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
