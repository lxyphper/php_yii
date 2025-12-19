<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[SpeakingSpecialItemTopic]].
 *
 * @see SpeakingSpecialItemTopic
 */
class SpeakingSpecialItemTopicQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return SpeakingSpecialItemTopic[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return SpeakingSpecialItemTopic|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
