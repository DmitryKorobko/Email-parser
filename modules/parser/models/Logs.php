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
 * @property string $html
 * @property string $unique_message_identifier
 * @property string $sender
 * @property integer $complete
 * @property integer $message_date
 * @property integer $created_at
 * @property integer $updated_at
 * @property integer $order_id
 * @property string $message_error
 * @property string $order_number
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
            [['unique_message_identifier', 'sender', 'message_error', 'href', 'order_number'], 'string'],
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
     * @param $html
     * @return bool
     */
    public static function recordLog($message, $complete, $messageError, $data, $html = null)
    {
        $order_number = str_replace('order_number=', '', stristr(stristr($data['href'], 'order_number='),'&', true));

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
            'html'                      => $html,
            'message_error'             => $messageError,
            'order_number'              => $order_number
        ];

        $logs->setAttributes($data, false);

        return $logs->save();
    }
}
