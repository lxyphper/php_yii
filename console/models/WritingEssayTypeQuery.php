<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[WritingEssayType]].
 *
 * @see WritingEssayType
 */
class WritingEssayTypeQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return WritingEssayType[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return WritingEssayType|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
