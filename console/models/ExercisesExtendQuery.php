<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[ExercisesExtend]].
 *
 * @see ExercisesExtend
 */
class ExercisesExtendQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return ExercisesExtend[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return ExercisesExtend|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
