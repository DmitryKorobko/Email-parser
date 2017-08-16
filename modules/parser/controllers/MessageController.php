<?php

namespace app\modules\parser\controllers;

use app\modules\parser\exceptions\ServerException;
use yii\console\Controller;
use PhpImap\Mailbox;
use yii\console\ErrorHandler;
use Yii;
use yii\helpers\Console;
use app\modules\parser\models\Logs;

/**
 * Class MessageController
 * @package app\modules\parser\controllers
 */
class MessageController extends Controller
{
    /**
     * @var string
     */
    public $defaultAction = 'run';


    public function actionRun()
    {
        $this->color = true;
        try {
            $mailbox = new Mailbox(
                '{' . Yii::$app->params['host'] . ':993/imap/ssl/novalidate-cert}',
                Yii::$app->params['login'], Yii::$app->params['password'],  __DIR__
            );

            $mailsIds = $mailbox->searchMailbox('ALL');
            if (!$mailsIds) {
                $this->stdout('Incoming messages not found!' . PHP_EOL, Console::FG_RED);
            }

            foreach ($mailsIds as $mailId) {
                try {
                    $message = $mailbox->getMail($mailId);
                    $this->stdout("Parsing starts: [ {$message->subject} ]" . PHP_EOL, Console::FG_GREEN);
                    if ($data = Yii::$app->messageDispatcher->run($message, $mailbox)) {
                        $mailbox->moveMail($mailId, $data['email_folder']);
                        Logs::recordLog($message, 1, null, $data, $message->textHtml);
                        $this->stdout("Parsing of message with subject [ {$message->subject} ] was successful" . PHP_EOL, Console::FG_GREEN);
                    } else {
                        $this->stdout("Parser for sender [ {$message->fromAddress} ] not found or this message was already parented earlier!" . PHP_EOL, Console::FG_RED);
                    }
                } catch (ServerException $e) {
                    $this->stdout($e->getMessage() . PHP_EOL, Console::FG_RED);
                    $messageError = 'Server Answer Error: ' . PHP_EOL . 'sender: ' . $message->fromAddress . ', ' . PHP_EOL .
                        'subject: ' . $message->subject . ', ' . PHP_EOL . 'error_message: ' . $e->getMessage() . PHP_EOL .
                        'href: ' . $e->href . PHP_EOL;
                    Yii::error($messageError, 'parser');
                }
                catch (\Exception $e) {
                    $this->stdout($e->getMessage() . PHP_EOL, Console::FG_RED);
                    $messageError = 'Parsing Error: ' . PHP_EOL . 'sender: ' . $message->fromAddress . ', ' . PHP_EOL .
                        'subject: ' . $message->subject . ', ' . PHP_EOL . 'error_message: ' . $e->getMessage() . PHP_EOL;
                    Yii::error($messageError, 'parser');
                }
            }
        } catch(\Exception $e) {
            $this->stdout($e->getMessage() . PHP_EOL, Console::FG_RED);
            Yii::error(ErrorHandler::convertExceptionToString($e));
        }
    }
}

