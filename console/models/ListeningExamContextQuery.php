<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[ListeningExamContext]].
 *
 * @see ListeningExamContext
 */
class ListeningExamContextQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return ListeningExamContext[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return ListeningExamContext|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
