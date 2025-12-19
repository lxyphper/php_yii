<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[ListeningExamPaper]].
 *
 * @see ListeningExamPaper
 */
class ListeningExamPaperQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return ListeningExamPaper[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return ListeningExamPaper|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
