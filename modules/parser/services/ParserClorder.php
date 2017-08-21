<?php

namespace app\modules\parser\services;

use app\modules\parser\exceptions\ServerException;
use app\modules\parser\helpers\Helper;
use app\modules\parser\interfaces\ParserInterface;
use app\modules\parser\models\Logs;
use PhpImap\Exception;
use Symfony\Component\DomCrawler\Crawler;
use Yii;
use PhpImap\Mailbox;

/**
 * Class ParserClorder
 *
 * Класс парсера, который парсит письма от отправителя Clorder
 *
 * @package app\modules\parser\services
 */
class ParserClorder implements ParserInterface
{
    /**
     * @var string
     */
    const EMAIL_FOLDER = 'clorder';

    /**
     * @var string
     */
    const SOURCE_TYPE = 'clorder';


    /**
     * @return mixed
     */
    public function getSender()
    {
        return Yii::$app->params['senders']['clorder'];
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
        $messageText = ($message->textPlain) ? $message->textPlain : $message->textHtml;

        if (Yii::$app->messageValidator->validateTextOfLetter($messageText)) {
            try {
                $data = $this->parserEmailMessage($message);

                $href = Yii::$app->apiClient->generateRequestHref(self::SOURCE_TYPE, $data);

                $logsHref = Yii::$app->apiClient->generateHrefForLogs($href,
                    Yii::$app->apiClient->generateRequestArray($data, self::SOURCE_TYPE));

                $orderId = Yii::$app->apiClient->sendApiRequest($href, $data, self::SOURCE_TYPE);

                return [
                    'href'         => $logsHref,
                    'orderId'      => $orderId,
                    'email_folder' => self::EMAIL_FOLDER
                ];
            } catch (ServerException $e) {
                Logs::recordLog($message, 0, $e->getMessage(), ['href' => $e->href], $message->textHtml);
                throw new ServerException($e->href, $e->getMessage());
            } catch (\Exception $e) {
                Logs::recordLog($message, 0, $e->getMessage(), ['href' => (isset($logsHref)) ? $logsHref : null], $message->textHtml);
                throw new Exception($e->getMessage());
            }
        } else {
            Logs::recordLog($message, 0, 'no validate', ['href' => (isset($logsHref)) ? $logsHref : null], $message->textHtml);
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

        $customer_address = trim($crawler->filter('td[style="width: 40%; padding: 7.5pt 0; border-style: none;"]
            > div[style="padding: 0; border-right: 1pt solid #000;"] > div > div > span')->getNode(2)->textContent)
            . ' ' . trim($crawler->filter('td[style="width: 40%; padding: 7.5pt 0; border-style: none;"]
            > div[style="padding: 0; border-right: 1pt solid #000;"] > div > div > span')->getNode(3)->textContent);

        $order_tip = str_replace('$', '',
            $crawler->filter('td[style="padding: 0 5pt 7.5pt 0; width: 400px"] > table > tbody')->children()
                ->last()->children()->last()->text());
        $order_tip_type = (($order_tip === 'cash') || ($order_tip === 'CASH') || ($order_tip === 'Cash') ||
            ($order_tip === ' cash ') || ($order_tip === ' CASH ') || ($order_tip === ' Cash ')) ? 'cash' : "prepaid";
        $order_tip = ($order_tip_type === 'cash') ? '0.00' : str_replace(' ', '', $order_tip);

        $order_type = 'prepaid';
        if (!stripos($crawler->filter('td[style="width: 300px; padding: 0 0 7.5pt 0;"] > div')->children()->last()->text(),
            'paid')
        ) {
            $order_type = 'cash';
        }

        $customer_pnone_nubmer =  $crawler->filter('td[style="width: 40%; padding: 7.5pt 0; border-style: none;"]
                span[ style="font-size: 16pt;"] > b')->text();
        $order_number = $crawler->filter('div > span > b')->first()->text();
        $query = Logs::findOne(['order_number' => $order_number]);
        $is_update = (empty($query)) ? false : true;

        return [
            'provider_ext_code'   => preg_replace('#-.*#', '', $crawler->filter('div > span > b')->first()->text()),
            'place'               => $crawler->filter('div > span[style="font-size: 10pt;"]')->text(),
            'restaurant'          => $crawler->filter('div > span[style="font-size: 18pt; alignment-adjust: central"]')->text(),
            'order_time'          => $crawler->filter('td[style="padding: 7.5pt 9pt; border-style: none;"]')->children()->last()->text(),
            'customer_name'       => $crawler->filter('td[style="width: 40%; padding: 7.5pt 0; border-style: none;"] span[ style="font-size: 14pt;"]')->text(),
            'customer_phone_num'  => Helper::deleteNaNFromTelNum($customer_pnone_nubmer),
            'customer_address'    => $customer_address,
            'customer_notes'      => preg_replace('/\s{2}/', '',
                $crawler->filter('td[style="padding: 7.5pt 11.25pt; border-style: none;"]')->children()->last()->text()),
            'order_note'          => Helper::wordWrappingForNote(
                $crawler->filter('table[style="width: 700px; border-style: none none solid none; border-bottom-width: 1.5pt; border-bottom-color: black;"] > tbody')->children()),
            'order_price'         => str_replace('$', '',
                $crawler->filter('td[style="width: 93.75pt; padding: 0;"] span[style="font-size: 14pt;"]')->text()),
            'order_tip'           => preg_replace('/\s{2}/', '', $order_tip),
            'order_tip_type'      => $order_tip_type,
            'order_type'          => $order_type,
            'order_note_payments' => Helper::wordWrappingForPayments(preg_replace('/\s{2}/', ' ',
                $crawler->filter('td[style="padding: 0 5pt 7.5pt 0; width: 400px"]')->text())),
            'subj'                => $message->subject,
            'sender'              => $message->fromAddress,
            'order_number'        => $order_number,
            'message_body'        => $message->textHtml,
            'is_update'           => $is_update,
            'confirmation_link'   => ''
        ];
    }
}

