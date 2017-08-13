<?php

namespace app\modules\parser\validators;
use yii\base\Component;

/**
 * Class MessageValidator
 *
 * Валидатор писем
 *
 * @package app\modules\parser\validators
 */
class MessageValidator extends Component
{
    public function ValidateTextOfLetter($content)
    {
        if (!(strpos(mb_strtolower($content), 'pickup'))) {
            if (strpos(mb_strtolower($content), 'delivery')) {
                return true;
            }
        }

        return false;
    }
}