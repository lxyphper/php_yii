<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[SpeakingPetQuestion]].
 *
 * @see SpeakingPetQuestion
 */
class SpeakingPetQuestionQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return SpeakingPetQuestion[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return SpeakingPetQuestion|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
