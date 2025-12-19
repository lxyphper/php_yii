<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[ListeningExamPaperUnit]].
 *
 * @see ListeningExamPaperUnit
 */
class ListeningExamPaperUnitQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return ListeningExamPaperUnit[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return ListeningExamPaperUnit|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
