<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[BasicTrainingWritingTopic]].
 *
 * @see BasicTrainingWritingTopic
 */
class BasicTrainingWritingTopicQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return BasicTrainingWritingTopic[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return BasicTrainingWritingTopic|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
