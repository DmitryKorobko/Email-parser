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
 * Class ParserChowNow
 *
 * Класс парсера, который парсит письма от отправителя ChowNow
 *
 * @package app\modules\parser\services
 */
class ParserChowNow implements ParserInterface
{
    /**
     * @var string
     */
    const EMAIL_FOLDER = 'chownow';

    /**
     * @var string
     */
    const SOURCE_TYPE = 'chownow';


    /**
     * @return mixed
     */
    public function getSender()
    {
        return Yii::$app->params['senders']['chownow'];
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

        $order_number = substr(stristr($crawler->filter('h1 > span')->text(), '#'), 1, 8);
        $provider_ext_code = stristr(substr($message->subject, (strpos($message->subject, ' - ') + 3)), ' - ', true);
        $place = $crawler->filter('div[style="padding:3px 5px; width:184px; float:left; font-family:arial"] > p > span')->getNode(2)->textContent;
        $order_time = $crawler->filter('div[style="padding:3px 5px; width:184px; float:left; font-family:arial"] > p > span')->getNode(0)->textContent
            . ', ' . $crawler->filter('div[style="padding:3px 5px; width:184px; float:left; font-family:arial"] > p > span')->getNode(1)->textContent;
        $restaurant = stristr(substr($message->subject, (strpos($message->subject, ' - ') + 3)), ' - ', true);
        $customer_name = $crawler->filter('div[style="padding:3px 5px; width:184px; float:left; font-family:arial"] > p > span')->getNode(3)->textContent;
        $order_price = str_replace('$', '', $crawler->filter('table > tr > td')->last()->text());
        $confirmation_link = '';
        $order_type = 'prepaid';
        $customer_notes = '';
        $customer_address = str_replace(["\r", "\n", '#', '№', "  "], '', $crawler->filter('div > p > span')->getNode(5)->childNodes->item(3)->textContent);

        if (!empty($crawler->filter('div > p > span')->getNode(5)->childNodes->item(7)->textContent)){
            $customer_notes .= str_replace(["\r", "\n", "  "], '', $crawler->filter('div > p > span')->getNode(5)->childNodes->item(5)->textContent);

            if (!empty($crawler->filter('div > p > span')->getNode(5)->childNodes->item(9)->textContent)){
                $customer_notes .=  ', ' .str_replace(["\r", "\n", "  "], '', $crawler->filter('div > p > span')->getNode(5)->childNodes->item(7)->textContent);
            } else {
                $customer_address .= ', ' . str_replace(["\r", "\n", "  "], '', $crawler->filter('div > p > span')->getNode(5)->childNodes->item(7)->textContent);
            }
        } else {
            $customer_address .= ', ' . str_replace(["\r", "\n", "  "], '', $crawler->filter('div > p > span')->getNode(5)->childNodes->item(5)->textContent);
        }

        $customer_phone_number =  Helper::deleteNaNFromTelNum($crawler->filter('div > p > span')->getNode(4)->textContent);
        $order_tip = str_replace('$', '', $crawler->filter('table')->last()->filter('tr > td')->getNode(9)->textContent);
        $order_tip_type = (!is_numeric($order_tip[0])) ? 'cash' : "prepaid";
        $order_note_payments = $crawler->filter('table')->last()->filter('tr > td')->getNode(0)->textContent
            . ' ' . $crawler->filter('table')->last()->filter('tr > td')->getNode(1)->textContent
            . ' ' . $crawler->filter('table')->last()->filter('tr > td')->getNode(2)->textContent
            . ' ' . $crawler->filter('table')->last()->filter('tr > td')->getNode(3)->textContent
            . ' ' . $crawler->filter('table')->last()->filter('tr > td')->getNode(4)->textContent
            . ' ' . $crawler->filter('table')->last()->filter('tr > td')->getNode(5)->textContent
            . ' ' . $crawler->filter('table')->last()->filter('tr > td')->getNode(6)->textContent
            . ' ' . $crawler->filter('table')->last()->filter('tr > td')->getNode(7)->textContent
            . ' ' . $crawler->filter('table')->last()->filter('tr > td')->getNode(8)->textContent
            . ' ' . $crawler->filter('table')->last()->filter('tr > td')->getNode(9)->textContent
            . ' ' . $crawler->filter('table')->last()->filter('tr > td')->getNode(10)->textContent
            . ' ' . $crawler->filter('table')->last()->filter('tr > td')->getNode(11)->textContent;
        $order_note = Helper::wordWrappingForNoteByChowNow($crawler->filter('table')->first()->filter('tr > td'));
        $is_update = Logs::getLogsByOrderNumber($order_number);

        return [
            'provider_ext_code'   => $provider_ext_code,
            'place'               => $place,
            'restaurant'          => $restaurant,
            'order_time'          => $order_time,
            'customer_name'       => $customer_name,
            'customer_phone_num'  => $customer_phone_number,
            'customer_address'    => $customer_address,
            'customer_notes'      => $customer_notes,
            'order_note'          => $order_note,
            'order_price'         => $order_price,
            'order_tip'           => $order_tip,
            'order_tip_type'      => $order_tip_type,
            'order_type'          => $order_type,
            'order_note_payments' => $order_note_payments,
            'subj'                => $message->subject,
            'sender'              => $message->fromAddress,
            'order_number'        => $order_number,
            'message_body'        => $message->textHtml,
            'is_update'           => $is_update,
            'order_api_id'        => Logs::getLogOrderId($order_number, $is_update),
            'confirmation_link'   => $confirmation_link
        ];
    }
}

