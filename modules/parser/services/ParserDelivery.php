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
 * Class ParserDelivery
 *
 * Класс парсера, который парсит письма от отправителя delivery
 *
 * @package app\modules\parser\services
 */
class ParserDelivery implements ParserInterface
{
    /**
     * @var string
     */
    const EMAIL_FOLDER = 'delivery';

    /**
     * @var string
     */
    const SOURCE_TYPE = 'delivery.com';

    /**
     * @return mixed
     */
    public function getSender()
    {
        return Yii::$app->params['senders']['delivery.com'];
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

                $href = \Yii::$app->apiClient->generateRequestHref(self::SOURCE_TYPE);

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

        $customer_address = $crawler->filter('tr[id="CUSTOMER-INFO-AND-SPECIAL-INSTRUCTIONS"] > td[style="width: 50%;"]')->getNode(0)->childNodes->item(6)->textContent . ', '
            . $crawler->filter('tr[id="CUSTOMER-INFO-AND-SPECIAL-INSTRUCTIONS"] > td[style="width: 50%;"]')->getNode(0)->childNodes->item(8)->textContent;

        $customer_address = str_replace(["\r", "\n", '#'], '', str_replace(', , ', ', ', str_replace(' ,', ',', $customer_address)));

        $customer_notes = $crawler->filter('tr[id="CUSTOMER-INFO-AND-SPECIAL-INSTRUCTIONS"] > td[style="width: 50%; max-width: 308px;"]')->getNode(0)->childNodes->item(5)->textContent;

        $order_tip = str_replace(["\r", "\n", '$'], '', $crawler->filter('td[id="MERCHANT-RECEIVES-VALUES"]')->getNode(0)->childNodes->item(8)->textContent);
        $order_tip_type = (!is_numeric($order_tip[0])) ? 'cash' : "prepaid";
        $order_tip = ($order_tip_type === 'cash') ? '0.00' : $order_tip;

        $order_type = 'prepaid';
        if (stripos($crawler->filter('span[style="font-size: 17px; font-weight: bold; text-align: center;"]')->text(),
            'cash')) {
            $order_type = 'cash';
        }

        $customer_phone_number = $crawler->filter('tr[id="CUSTOMER-INFO-AND-SPECIAL-INSTRUCTIONS"] > td[style="width: 50%;"]')
            ->getNode(0)->childNodes->item(10)->textContent;

        return [
            'provider_ext_code'   => str_replace(["\r", "\n"], '', preg_replace('#-.*#', '',
                $crawler->filter('td[style="font-size: 12px;line-height: 13px;text-align: right; vertical-align: text-top;width: 415px; border-bottom-width: 1px; border-bottom-style: solid; border-bottom-color: #000;"]')
                ->getNode(0)->firstChild->textContent)),
            'place'               => str_replace(["\r", "\n", '(Order placed: ', ')'], '', $crawler->filter('tr[id="CUSTOMER-INFO-AND-SPECIAL-INSTRUCTIONS"] > td[style="width: 50%;"]')
                ->getNode(0)->childNodes->item(12)->textContent),
            'restaurant'          => str_replace(["\r", "\n"], '', preg_replace('#-.*#', '',
                $crawler->filter('td[style="font-size: 12px;line-height: 13px;text-align: right; vertical-align: text-top;width: 415px; border-bottom-width: 1px; border-bottom-style: solid; border-bottom-color: #000;"]')
                ->getNode(0)->firstChild->textContent)),
            'order_time'          => substr(str_replace(["\r", "\n", '(Order placed: ', ')'], '', $crawler->filter('tr[id="CUSTOMER-INFO-AND-SPECIAL-INSTRUCTIONS"] > td[style="width: 50%;"]')
                ->getNode(0)->childNodes->item(12)->textContent), 0, 8),
            'customer_name'       => str_replace(["\r", "\n"], '', $crawler->filter('tr[id="CUSTOMER-INFO-AND-SPECIAL-INSTRUCTIONS"] > td[style="width: 50%;"]')
                ->getNode(0)->childNodes->item(2)->textContent),
            'customer_phone_num'  => Helper::deleteNaNFromTelNum($customer_phone_number),
            'customer_address'    => $customer_address,
            'customer_notes'      => $customer_notes,
            'order_note'          => str_replace(["\r", "\n"], '', Helper::wordWrappingForNoteByDelivery($crawler
                ->filter('table[id="ITEMS-TABLE"] > tr[style="min-height: 26px; border-bottom-width: 2px; border-bottom-style: solid; border-bottom-color: #000;"] > td'))),
            'order_tip'           => $order_tip,
            'order_price'         => str_replace('$', '', $crawler->filter('td[id="MERCHANT-RECEIVES-VALUES"] > span[style="font-weight: bold; font-size: 13px;"]')->text()),
            'order_note_payments' => str_replace(["\r", "\n", '$'], '', $crawler->filter('td[id="MERCHANT-RECEIVES-LABELS"]')->getNode(0)->childNodes->item(0)->textContent) . ' $' .
                str_replace(["\r", "\n", '$'], '', $crawler->filter('td[id="MERCHANT-RECEIVES-VALUES"]')->getNode(0)->childNodes->item(0)->textContent) . '\n ' .
                str_replace(["\r", "\n", '$'], '', $crawler->filter('td[id="MERCHANT-RECEIVES-LABELS"]')->getNode(0)->childNodes->item(2)->textContent) . ' $' .
                str_replace(["\r", "\n", '$'], '', $crawler->filter('td[id="MERCHANT-RECEIVES-VALUES"]')->getNode(0)->childNodes->item(2)->textContent) . '\n ' .
                str_replace(["\r", "\n", '$'], '', $crawler->filter('td[id="MERCHANT-RECEIVES-LABELS"]')->getNode(0)->childNodes->item(4)->textContent) . ' $' .
                str_replace(["\r", "\n", '$'], '', $crawler->filter('td[id="MERCHANT-RECEIVES-VALUES"]')->getNode(0)->childNodes->item(4)->textContent) . '\n ' .
                str_replace(["\r", "\n", '$'], '', $crawler->filter('td[id="MERCHANT-RECEIVES-LABELS"]')->getNode(0)->childNodes->item(6)->textContent) . ' $' .
                str_replace(["\r", "\n", '$'], '', $crawler->filter('td[id="MERCHANT-RECEIVES-VALUES"]')->getNode(0)->childNodes->item(6)->textContent) . '\n ' .
                str_replace(["\r", "\n", '$'], '', $crawler->filter('td[id="MERCHANT-RECEIVES-LABELS"]')->getNode(0)->childNodes->item(8)->textContent) . ' $' .
                str_replace(["\r", "\n", '$'], '', $crawler->filter('td[id="MERCHANT-RECEIVES-VALUES"]')->getNode(0)->childNodes->item(8)->textContent) . '\n ' .
                str_replace(["\r", "\n", '$'], '', $crawler->filter('td[id="MERCHANT-RECEIVES-LABELS" ] > span[style="font-weight: bold; font-size: 13px;"]')->text()) . ' $' .
                str_replace(["\r", "\n", '$'], '', $crawler->filter('td[id="MERCHANT-RECEIVES-VALUES"] > span[style="font-weight: bold; font-size: 13px;"]')->text()) . '\n',
            'order_type'          => $order_type,
            'order_tip_type'      => $order_tip_type,
            'subj'                => $message->subject,
            'sender'              => $message->fromAddress,
            'order_number'        => str_replace(["\r", "\n", 'Order #'], '', $crawler->filter('tr[id="DCOM-LOGO-AND-MERCHANT-INFO"] > td > span[style="font-weight: bold;"]')->text()),
            'message_body'        => $message->textHtml,
            'is_update'           => false,
            'confirmation_link'   => $crawler->filter('div[style="font-size:32pt;font-family: Arial,Helvetica;font-weight: bold;text-align:center;"] > a')->extract(['href'])[0]
        ];
    }
}
