<?php

namespace app\modules\parser\services;

use app\modules\parser\helpers\Helper;
use app\modules\parser\interfaces\ParserInterface;
use app\modules\parser\models\Logs;
use PhpImap\Exception;
use Symfony\Component\DomCrawler\Crawler;
use Yii;
use PhpImap\Mailbox;
use app\modules\parser\exceptions\ServerException;

/**
 * Class ParserGrubhub
 *
 * Класс парсера, который парсит письма от отправителя GrubHub
 *
 * @package app\modules\parser\services
 */
class ParserGrubhub implements ParserInterface
{
    /**
     * @var string
     */
    const EMAIL_FOLDER = 'grubhub';

    /**
     * @var string
     */
    const SOURCE_TYPE = 'grubhub';


    /**
     * @return mixed
     */
    public function getSender()
    {
        return Yii::$app->params['senders']['grubhub'];
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
                $href = \Yii::$app->apiClient->generateRequestHref($data, self::SOURCE_TYPE);
                $orderId = \Yii::$app->apiClient->sendApiRequest($href);
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

        $customer_notes = '';
        $customer_address =
            $crawler->filter('div[style="display:none; display: none !important;"] > div')->children()->getNode(2)->textContent
            . ', ' . $crawler->filter('div[style="display:none; display: none !important;"] > div')->children()->getNode(3)->textContent
            . ', ' . $crawler->filter('div[style="display:none; display: none !important;"] > div')->children()->getNode(4)->textContent
            . ', ' . $crawler->filter('div[style="display:none; display: none !important;"] > div')->children()->getNode(5)->textContent
            . ', ' . $crawler->filter('div[style="display:none; display: none !important;"] > div')->children()->getNode(6)->textContent;

        if ((strpos(mb_strtolower($html), 'special instructions'))) {
            $customer_notes = preg_replace('/\s{2}/', '', $crawler
                ->filter('table[style="width:400px; border:1px solid black;"] > tbody > tr > td')->last()->text());
        }

        $order_tip = substr(stristr($crawler->filter('table[style="width:400px;"] > tbody')->getNode(1)
            ->childNodes->item(3)->childNodes->item(1)->textContent, '$'), 1);
        $order_tip_type = (!strpos($crawler->filter('table[style="width:400px;"] > tbody')->getNode(1)
            ->childNodes->item(3)->childNodes->item(1)->textContent, '$')) ? 'cash' : "prepaid";

        $order_type = 'prepaid';
        if (!stripos(mb_strtolower($crawler->filter('div > div > span')->last()->text()),'prepaid')) {
            $order_type = 'cash';
        }

        $order_number = substr(stristr($crawler->filter('div[id="cust_service_info"]')
            ->text(), '#'), 1, 10);

        return [
            'provider_ext_code'   => $crawler->filter('div > div > span')->first()->text(),
            'place'               => $crawler->filter('table > tbody > tr > td')->getNode(3)->childNodes->item(0)->textContent,
            'restaurant'          => $crawler->filter('div > div > span')->first()->text(),
            'order_time'          => substr($crawler->filter('table > tbody > tr > td')->getNode(5)->childNodes->item(0)->textContent, 4,
                strlen($crawler->filter('table > tbody > tr > td')->getNode(5)->childNodes->item(0)->textContent) - 5),
            'customer_name'       => $crawler->filter('table > tbody > tr > td')->getNode(2)->textContent,
            'customer_phone_num'  => $crawler->filter('table > tbody > tr > td')->getNode(7)->textContent,
            'customer_address'    => $customer_address,
            'customer_notes'      => $customer_notes,
            'order_note'          => Helper::wordWrappingForNote(
                $crawler->filter('table[style="width:500px; border:1px solid black;"] > tbody')->children()),
            'order_price'         => str_replace('$', '',
                $crawler->filter('table[style="width:400px;"] > tbody > tr > td')->last()->text()),
            'order_tip'           => preg_replace('/\s{2}/', '', $order_tip),
            'order_tip_type'      => $order_tip_type,
            'order_type'          => $order_type,
            'order_note_payments' => Helper::wordWrappingForPayments(preg_replace('/\n\s{2}/', ' ',
                ($crawler->filter('table[style="width:400px;"] > tbody')->getNode(1)->childNodes->item(0)->textContent . ' ' .
                    $crawler->filter('table[style="width:400px;"] > tbody')->getNode(1)->childNodes->item(1)->textContent . ' ' .
                    $crawler->filter('table[style="width:400px;"] > tbody')->getNode(1)->childNodes->item(2)->textContent . ' ' .
                    $crawler->filter('table[style="width:400px;"] > tbody')->getNode(1)->childNodes->item(3)->textContent . ' ' .
                    $crawler->filter('table[style="width:400px;"] > tbody')->getNode(1)->childNodes->item(4)->textContent))),
            'subj'                => $message->subject,
            'sender'              => $message->fromAddress,
            'order_number'        => $order_number,
            'message_body'        => $message->textHtml
        ];
    }
}

