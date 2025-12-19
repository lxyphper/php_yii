<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[ExercisesGuide]].
 *
 * @see ExercisesGuide
 */
class ExercisesGuideQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return ExercisesGuide[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return ExercisesGuide|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
