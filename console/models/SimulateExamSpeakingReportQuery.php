<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[SimulateExamSpeakingReport]].
 *
 * @see SimulateExamSpeakingReport
 */
class SimulateExamSpeakingReportQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return SimulateExamSpeakingReport[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return SimulateExamSpeakingReport|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
