<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[ListeningExamPaperTopic]].
 *
 * @see ListeningExamPaperTopic
 */
class ListeningExamPaperTopicQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return ListeningExamPaperTopic[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return ListeningExamPaperTopic|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
