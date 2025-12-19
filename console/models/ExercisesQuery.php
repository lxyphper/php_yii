<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[Exercises]].
 *
 * @see Exercises
 */
class ExercisesQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return Exercises[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return Exercises|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
