<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "student".
 *
 * @property int $id
 * @property string $mobile 手机号
 * @property string $account 账号名
 * @property string $password 密码
 * @property int $country_id 国家id
 * @property float $target_score 平均目标分数
 * @property float $target_score_listen 听力目标分数
 * @property float $target_score_speak 口语目标分数
 * @property float $target_score_read 阅读目标分数
 * @property float $target_score_write 写作目标分数
 * @property int $expiration_time 写作会员截止时间
 * @property int $reading_expiration_time 阅读会员截止时间
 * @property int $listening_expiration_time 听力会员截止时间
 * @property int $speaking_expiration_time 口语会员截止时间
 * @property int $vip_level 写作会员等级：1、体验 2、3个月 3、6个月 4、代金券1个月 5、新注册用户1周
 * @property int $reading_vip_level 阅读会员等级：1、1个月 2、3个月 3、6个月
 * @property int $listening_vip_level 听力会员等级
 * @property int $speaking_vip_level 口语会员等级
 * @property string $name 姓名
 * @property int $exam_type 考试模式：1机考 2笔试
 * @property string $head_sculpture 头像地址
 * @property string $last_ip 最后登录ip
 * @property int $last_time 最后登录时间
 * @property int $status 启用状态：1正常 2禁用
 * @property int $coach_num 练习次数：0无 -1无限次
 * @property int $review_num 测评次数：0无 -1无限次
 * @property int $essay_num 范文次数：0无 -1无限次
 * @property int $is_approval 是否领取试用：1是 2否
 * @property int $is_guide 是否经过引导：1是 2否
 * @property int $is_delete 是否删除：1是 2否
 * @property int $exam_purpose 考试目的
 * @property int $is_ielts 是否考过雅思
 * @property int $exam_date 考试时间
 * @property int $is_new 是否是新注册用户：1是 2否
 * @property int $source
 * @property int $create_by 创建人
 * @property int $update_by 更新人
 * @property int $create_time 创建时间
 * @property int $update_time 更新时间
 */
class Student extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'student';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['country_id', 'expiration_time', 'reading_expiration_time', 'listening_expiration_time', 'speaking_expiration_time', 'vip_level', 'reading_vip_level', 'listening_vip_level', 'speaking_vip_level', 'exam_type', 'last_time', 'status', 'coach_num', 'review_num', 'essay_num', 'is_approval', 'is_guide', 'is_delete', 'exam_purpose', 'is_ielts', 'exam_date', 'is_new', 'source', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['target_score', 'target_score_listen', 'target_score_speak', 'target_score_read', 'target_score_write'], 'number'],
            [['mobile'], 'string', 'max' => 20],
            [['account', 'password', 'last_ip'], 'string', 'max' => 50],
            [['name', 'head_sculpture'], 'string', 'max' => 500],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'mobile' => 'Mobile',
            'account' => 'Account',
            'password' => 'Password',
            'country_id' => 'Country ID',
            'target_score' => 'Target Score',
            'target_score_listen' => 'Target Score Listen',
            'target_score_speak' => 'Target Score Speak',
            'target_score_read' => 'Target Score Read',
            'target_score_write' => 'Target Score Write',
            'expiration_time' => 'Expiration Time',
            'reading_expiration_time' => 'Reading Expiration Time',
            'listening_expiration_time' => 'Listening Expiration Time',
            'speaking_expiration_time' => 'Speaking Expiration Time',
            'vip_level' => 'Vip Level',
            'reading_vip_level' => 'Reading Vip Level',
            'listening_vip_level' => 'Listening Vip Level',
            'speaking_vip_level' => 'Speaking Vip Level',
            'name' => 'Name',
            'exam_type' => 'Exam Type',
            'head_sculpture' => 'Head Sculpture',
            'last_ip' => 'Last Ip',
            'last_time' => 'Last Time',
            'status' => 'Status',
            'coach_num' => 'Coach Num',
            'review_num' => 'Review Num',
            'essay_num' => 'Essay Num',
            'is_approval' => 'Is Approval',
            'is_guide' => 'Is Guide',
            'is_delete' => 'Is Delete',
            'exam_purpose' => 'Exam Purpose',
            'is_ielts' => 'Is Ielts',
            'exam_date' => 'Exam Date',
            'is_new' => 'Is New',
            'source' => 'Source',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return StudentQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new StudentQuery(get_called_class());
    }
}
