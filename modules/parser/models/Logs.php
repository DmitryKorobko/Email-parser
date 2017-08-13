<?php

namespace app\modules\parser\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Class Logs
 *
 * @package common\models
 *
 * @property integer $id
 * @property string $subject
 * @property string $href
 * @property string $unique_message_identifier
 * @property string $sender
 * @property integer $complete
 * @property integer $message_date
 * @property integer $created_at
 * @property integer $updated_at
 * @property integer $order_id
 * @property string $message_error
 */
class Logs extends ActiveRecord
{
    const SCENARIO_CREATE = 'create';
    const SCENARIO_UPDATE = 'update';

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%logs}}';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class'              => TimestampBehavior::className(),
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
                'value'              => function () {
                    return time();
                },
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [
                ['subject', 'unique_message_identifier', 'message_date', 'sender', 'complete'],
                'required',
                'on' => [self::SCENARIO_CREATE, self::SCENARIO_UPDATE]
            ],
            [['unique_message_identifier', 'sender', 'message_error', 'href'], 'string'],
            [[ 'created_at', 'updated_at', 'complete', 'order_id'], 'integer'],
        ];
    }

    /**
     * Method of recording log to the database
     *
     * @param $message
     * @param $complete
     * @param $messageError
     * @param $data
     * @return bool
     */
    public static function recordLog($message, $complete, $messageError, $data)
    {
        $logs = new self();
        $logs->setScenario(self::SCENARIO_CREATE);
        $data = [
            'subject'                   => $message->subject,
            'unique_message_identifier' => $message->messageId,
            'sender'                    => $message->fromAddress,
            'complete'                  => $complete,
            'message_date'              => strtotime($message->date),
            'order_id'                  => (isset($data['orderId'])) ? $data['orderId'] : null,
            'href'                      => (isset($data['href'])) ? $data['href'] : null,
            'message_error'             => $messageError
        ];

        $logs->setAttributes($data, false);

        return $logs->save();
    }
}
