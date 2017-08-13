<?php

namespace app\modules\parser\interfaces;

use PhpImap\Mailbox;

/**
 * Interface ParserInterface
 * @package app\modules\parser\interfaces
 */
interface ParserInterface
{
    public function run($message, Mailbox $mailbox);

    public function getSender();
}