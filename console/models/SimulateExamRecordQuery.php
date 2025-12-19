<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[SimulateExamRecord]].
 *
 * @see SimulateExamRecord
 */
class SimulateExamRecordQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return SimulateExamRecord[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return SimulateExamRecord|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
