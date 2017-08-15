<?php

namespace app\modules\parser\services;

use app\modules\parser\helpers\Helper;
use app\modules\parser\interfaces\ParserInterface;
use app\modules\parser\models\Logs;
use PhpImap\Exception;
use Symfony\Component\DomCrawler\Crawler;
use Yii;
use PhpImap\Mailbox;

/**
 * Class ParserEatStreet
 *
 * Класс парсера, который парсит письма от отправителя Eatstreet
 *
 * @package app\modules\parser\services
 */
class ParserEatStreet implements ParserInterface
{
    /**
     * @var string
     */
    const EMAIL_FOLDER = 'eatstreet';

    /**
     * @var string
     */
    const SOURCE_TYPE = 'eatstreet';


    /**
     * @return mixed
     */
    public function getSender()
    {
        return Yii::$app->params['senders']['eatstreet'];
    }

    /**
     * @param $message
     * @param Mailbox $mailbox
     * @return array
     * @throws Exception
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

        $customer_address = $crawler->filter('div#page')->children()->getNode(2)->firstChild->firstChild->childNodes
            ->item(2)->childNodes->item(4)->textContent;
        $customer_address .= $crawler->filter('div#page')->children()->getNode(2)->firstChild->firstChild->childNodes
            ->item(2)->childNodes->item(7)->textContent;

        $order_tip = trim($crawler->filter('div#page')->children()->getNode(16)->getElementsByTagName('td')
            ->item(12)->textContent);
        $order_tip_type = ($order_tip === 'cash') ? 'cash' : "prepaid";

        $order_type = 'prepaid';
        if (!stripos($crawler->filter('div#orderInfo')->text(), 'paid')) {
            $order_type = 'cash';
        }

        return [
            'provider_ext_code'   => trim($crawler->filter('div#page tr > td')->children()->getNode(2)->textContent),
            'date'                => $crawler->filter('div#page tr > td')->children()->last()->text(),
            'sanzab'              => trim($crawler->filter('div#page tr > td')->children()->getNode(2)->textContent),
            'customer_name'       => trim($crawler->filter('div#page')->children()->getNode(2)->firstChild->firstChild
                ->firstChild->childNodes->item(3)->textContent),
            'customer_phone_num'  => $crawler->filter('div#page')->children()->getNode(2)->firstChild->firstChild
                ->firstChild->childNodes->item(6)->textContent,
            'customer_address'    => trim($customer_address),
            'customer_notes'      => '', //todo сделать
            'order_note'          => Helper::wordWrappingForNoteByEatstreet(preg_replace('~\*~', '',
                $crawler->filter('table.items')->text())),
            'order_tip'           => $order_tip,
            'order_type'          => $order_type,
            'order_tip_type'      => $order_tip_type,
            'order_price'         => trim($crawler->filter('div#page')->children()->getNode(16)
                ->getElementsByTagName('td')->item(14)->textContent),
            'order_note_payments' => Helper::wordWrappingForPayments(preg_replace('/\s{2}/', ' ',
                $crawler->filter('div#page')->children()->getNode(16)->textContent)),
            'subj'                => $message->subject,
            'sender'              => $message->fromAddress,
            'order_number'        => preg_replace('~\D+~', '', $crawler->filter('div#page tr')->children()->last()->text()),
            'message_body'        => $message->textHtml
        ];
    }
}

