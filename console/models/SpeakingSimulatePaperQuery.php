<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[SpeakingSimulatePaper]].
 *
 * @see SpeakingSimulatePaper
 */
class SpeakingSimulatePaperQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return SpeakingSimulatePaper[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return SpeakingSimulatePaper|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
