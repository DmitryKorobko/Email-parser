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

        $customer_notes = $crawler->filter('div > div[data-field="special-instructions"]')->text();
        $customer_address = $crawler->filter('div > div[data-field="address1"]')->text()
            . ', ' . $crawler->filter('div > div[data-field="address2"]')->text()
            . ', ' . $crawler->filter('div > div[data-field="city"]')->text()
            . ', ' . $crawler->filter('div > div[data-field="state"]')->text()
            . ', ' . $crawler->filter('div > div[data-field="zip"]')->text();
        $customer_address = str_replace(' ,', '', $customer_address);

        $order_tip = $crawler->filter('div > div[data-field="tip"]')->text();
        $tip_type_is_cash = str_replace(' ', '', $crawler->filter('div > div[data-field="tip"]')->text());
        $is_cash = (($order_tip == '0.00') && ($tip_type_is_cash)) ? true : false;
        $order_tip = ($is_cash) ? '0.00' : $order_tip;
        $order_tip_type = ($is_cash) ? 'cash' : "prepaid";

        $order_type = 'prepaid';
        $is_prepaid = stristr($crawler->filter('div > span[style="font-weight:bold; font-size:150%;"]')->last()
            ->text(), 'Prepaid');
        if (empty($is_prepaid)) {
            $order_type = 'cash';
        }

        $order_number = substr(stristr($crawler->filter('div[id="cust_service_info"]')
            ->text(), '#'), 1, 10);

        $customer_phone_number =  $crawler->filter('div > div[data-field="phone"]')->text();

        return [
            'provider_ext_code'   => $crawler
                ->filter('div[id="cust_service_info"] > span[style="font-weight:bold; font-size:150%;"]')->text(),
            'place'               => $crawler->filter('table[style="width:400px;"] > tr > td')->getNode(3)->textContent,
            'restaurant'          => $crawler->filter('div[data-field="restaurant-name"]')->text(),
            'order_time'          => $crawler->filter('table > tr > td[valign="top"]')->text(),
            'customer_name'       => $crawler->filter('div > div[data-field="name"]')->text(),
            'customer_phone_num'  => Helper::deleteNaNFromTelNum($customer_phone_number),
            'customer_address'    => $customer_address,
            'customer_notes'      => $customer_notes,
            'order_note'          => Helper::wordWrappingForNoteByGrubHub($crawler->filter('table[style="width:500px; border:1px solid black;"] > tr >td')),
            'order_price'         => $crawler->filter('div[data-field="total"]')->text(),
            'order_tip'           => $order_tip,
            'order_tip_type'      => $order_tip_type,
            'order_type'          => $order_type,
            'order_note_payments' => 'Subtotal $' . $crawler->filter('div[data-field="subtotal"]')->text() . '\n ' .
                'Delivery Fee $' . $crawler->filter('div[data-field="delivery-charge"]')->text() . '\n ' .
                'Tax $' . $crawler->filter('div[data-field="sales-tax"]')->text() . '\n ' .
                'Tip $' . $crawler->filter('div[data-field="tip"]')->text() . '\n ' .
                'Total $' . $crawler->filter('div[data-field="total"]')->text() . '\n',
            'subj'                => $message->subject,
            'sender'              => $message->fromAddress,
            'order_number'        => $order_number,
            'message_body'        => $message->textHtml,
            'is_update'           => false,
            'confirmation_link'   => $crawler->filter('body[style="font-size:15px;"] > a[target="_blank"]')->extract(['href'])[0]
        ];
    }
}

