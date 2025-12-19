<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[SimulateExamReading]].
 *
 * @see SimulateExamReading
 */
class SimulateExamReadingQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return SimulateExamReading[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return SimulateExamReading|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
