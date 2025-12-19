<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[ListeningExamPaperType]].
 *
 * @see ListeningExamPaperType
 */
class ListeningExamPaperTypeQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return ListeningExamPaperType[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return ListeningExamPaperType|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
