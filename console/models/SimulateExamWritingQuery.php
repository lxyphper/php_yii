<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[SimulateExamWriting]].
 *
 * @see SimulateExamWriting
 */
class SimulateExamWritingQuery extends \yii\db\ActiveQuery
{
    /**
     * {@inheritdoc}
     * @return SimulateExamWriting[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return SimulateExamWriting|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}

