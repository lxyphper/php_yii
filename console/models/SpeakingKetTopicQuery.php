<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[SpeakingKetTopic]].
 *
 * @see SpeakingKetTopic
 */
class SpeakingKetTopicQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return SpeakingKetTopic[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return SpeakingKetTopic|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
