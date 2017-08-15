<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\modules\parser\exceptions;;

use yii\base\Exception;

/**
 * Class ServerException
 * @package yii\web
 */
class ServerException extends Exception
{
    /**
     * @var string
     */
    public $href;


    /**
     * Constructor.
     * @param $href
     * @param string $message error message
     * @param int $code error code
     * @param \Exception $previous The previous exception used for the exception chaining.
     */
    public function __construct($href, $message = null, $code = 0, \Exception $previous = null)
    {
        $this->href = $href;
        parent::__construct($message, $code, $previous);
    }
}
