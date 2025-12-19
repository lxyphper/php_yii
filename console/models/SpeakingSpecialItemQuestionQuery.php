<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[SpeakingSpecialItemQuestion]].
 *
 * @see SpeakingSpecialItemQuestion
 */
class SpeakingSpecialItemQuestionQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return SpeakingSpecialItemQuestion[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return SpeakingSpecialItemQuestion|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
