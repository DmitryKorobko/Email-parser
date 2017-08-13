<?php

namespace app\modules\parser\components;

use app\modules\parser\interfaces\ParserInterface;
use PhpImap\Mailbox;
use \yii\base\Component;

/**
 * Class MessageDispatcher
 *
 * Класс, который решает какой парсер запустить на основе отправителя
 *
 * @package app\modules\parser\components
 */
class MessageDispatcher extends Component
{
    /* @var array $parsers */
    public $parsers = [];

    /**
     * Метод запуска парсера, на основе отправителя сообщения
     *
     * @param $message
     * @param Mailbox $mailbox
     * @return mixed
     */
    public function run($message, Mailbox $mailbox)
    {
        if ($this->parsers) {
            foreach ($this->parsers as $parser) {
                if (!$parser instanceof ParserInterface) {
                    $object = \Yii::createObject($parser);
                    if ($message->fromAddress === $object->getSender()) {
                        return $object->run($message, $mailbox);
                    }
                }
            }
        }
    }
}