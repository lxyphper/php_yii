<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[SimulateExamListening]].
 *
 * @see SimulateExamListening
 */
class SimulateExamListeningQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return SimulateExamListening[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return SimulateExamListening|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
