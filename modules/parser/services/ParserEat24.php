<?php

namespace app\modules\parser\services;

use app\modules\parser\interfaces\ParserInterface;
use Symfony\Component\DomCrawler\Crawler;
use app\modules\parser\helpers\Helper;
use PhpImap\Mailbox;
use Yii;
use app\modules\parser\models\Logs;
use PhpImap\Exception;
use app\modules\parser\exceptions\ServerException;

/**
 * Class ParserEat24
 *
 * Класс парсера, который парсит письма от отправителя Maitred
 *
 * @package app\modules\parser\services
 */
class ParserEat24 implements ParserInterface
{
    /**
     * @var string
     */
    const EMAIL_FOLDER = 'eat24';

    /**
     * @var string
     */
    const SOURCE_TYPE = 'eat24';

    /**
     * @return mixed
     */
    public function getSender()
    {
        return Yii::$app->params['senders']['eat24'];
    }

    /**
     * @param $message
     * @param Mailbox $mailbox
     * @return array
     * @throws Exception
     * @throws ServerException
     */
    public function run($message, Mailbox $mailbox)
    {
        if (Yii::$app->messageValidator->validateTextOfLetter($message->textPlain)) {
            try {
                $data = $this->parserEmailMessage($message);
                $href = Yii::$app->apiClient->generateRequestHref($data, self::SOURCE_TYPE);
                $orderId = Yii::$app->apiClient->sendApiRequest($href);
                return [
                    'href' => $href,
                    'orderId' => $orderId,
                    'email_folder' => self::EMAIL_FOLDER
                ];
            } catch (ServerException $e) {
                Logs::recordLog($message, 0, $e->getMessage(), ['href' => (isset($href)) ? $href : null]);
                throw new ServerException($e->href, $e->getMessage());
            } catch (\Exception $e) {
                Logs::recordLog($message, 0, $e->getMessage(), ['href' => (isset($href)) ? $href : null]);
                throw new Exception($e->getMessage());
            }
        } else {
            $mailbox->moveMail($message->id, self::EMAIL_FOLDER);
        }
    }

    /**
     * Метод парсинга html email сообщения и возвращающий массив данных
     *
     * @param $message
     * @return array
     */
    private function parserEmailMessage($message)
    {
        $html = $message->textHtml;
        $crawler = new Crawler($html);

        $customer_address = '';
        $customer_address_count = $crawler->filter('div[style="font-size: 12pt; margin-bottom: 5px;"]')->count();
        if ($customer_address_count) {
            $customer_address = preg_replace('/\s{2}/', '', $crawler->filter('div[style="font-size: 12pt; margin-bottom: 5px;"]')->text());
        }

        $customer_notes = '';
        $customer_notes_count = $crawler->filter('td[style="font-size: 14pt; padding:20px 0 20px 15px; vertical-align:top"]')->count();
        if ($customer_notes_count) {
            $customer_notes = preg_replace('/\s{2}/', '',
                $crawler->filter('td[style="font-size: 14pt; padding:20px 0 20px 15px; vertical-align:top"]')->text());
        }

        $order_tip = $order_tip = str_replace('$', '', $crawler->filter('table[style="padding:0 0 10px; font-size:12pt"] > tbody > tr')
            ->children()->last()->filter('table tbody')->children()->last()->children()->last()->text());
        $order_tip_type = ($order_tip === 'cash') ? 'cash' : "prepaid";

        $order_type = 'prepaid';
        if (!stripos($crawler->filter('div[style="width:150px; padding:8px; border:1px solid black"] b')->text(),
            'Prepaid')
        ) {
            $order_type = 'cash';
        }
        return [
            'provider_ext_code'   => preg_replace('#-.*#', '', $crawler->filter('div[style="width: 100%; background:#ffffff;"]
                div[style="padding:3px 0"] > b')->text()),
            'place'               => $crawler->filter('div[style="width: 100%; background:#ffffff;"] td[style="font-size:12pt"]')
                ->children()->last()->text(),
            'phobulous'           => $crawler->filter('div[style="width: 100%; background:#ffffff;"] td[style="font-size:12pt"]')
                ->children()->first()->text(),
            'delivery'            => $crawler->filter('table[style="padding:10px 12px; border:1px solid black"]
                td[style="font-size:30pt; text-transform:uppercase"] > b')->text(),
            'customer_name'       => $crawler->filter('table[style="font-size:10pt; border-bottom:2px solid black"]
                tbody td[style="word-break: break-word;"]')->text(),
            'customer_phone_num'  => $crawler->filter('table[style="font-size:10pt; border-bottom:2px solid black"]
                tbody td[style="text-align: right;"]')->text(),
            'customer_address'    => $customer_address,
            'customer_notes'      => $customer_notes,
            'order_note'          => Helper::wordWrappingforNote($crawler->filter('table[style="border-bottom:2px solid black"] tbody')->children()),
            'order_tip'           => preg_replace('/\s{2}/', '', $order_tip),
            'order_price'         => $crawler->filter('tr td[style="padding:4px 0; vertical-align:top"] td[style="font-size:14pt"]')->text(),
            'order_note_payments' => Helper::wordWrappingForPayments($crawler->filter('table[style="padding:0 0 10px; font-size:12pt"] tbody > tr')->children()->last()->text()),
            'order_type'          => $order_type,
            'order_tip_type'      => $order_tip_type,
            'subj'                => $message->subject,
            'sender'              => $message->fromAddress,
            'order_number'        => $crawler->filter('div[style="width: 100%; background:#ffffff;"] div[style="padding:3px 0"] > b')->text(),
            'message_body'        => $message->textHtml
        ];
    }
}
