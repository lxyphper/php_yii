<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[ReadingExamPaper]].
 *
 * @see ReadingExamPaper
 */
class ReadingExamPaperQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return ReadingExamPaper[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return ReadingExamPaper|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
