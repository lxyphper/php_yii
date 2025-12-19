<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[SimulateExamSpeaking]].
 *
 * @see SimulateExamSpeaking
 */
class SimulateExamSpeakingQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return SimulateExamSpeaking[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return SimulateExamSpeaking|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
