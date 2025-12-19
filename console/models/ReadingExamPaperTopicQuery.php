<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[ReadingExamPaperTopic]].
 *
 * @see ReadingExamPaperTopic
 */
class ReadingExamPaperTopicQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return ReadingExamPaperTopic[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return ReadingExamPaperTopic|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
