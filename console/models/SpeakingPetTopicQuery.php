<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[SpeakingPetTopic]].
 *
 * @see SpeakingPetTopic
 */
class SpeakingPetTopicQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return SpeakingPetTopic[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return SpeakingPetTopic|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
