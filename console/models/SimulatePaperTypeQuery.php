<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[SimulatePaperType]].
 *
 * @see SimulatePaperType
 */
class SimulatePaperTypeQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return SimulatePaperType[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return SimulatePaperType|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
