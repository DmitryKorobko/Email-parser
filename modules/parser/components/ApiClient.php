<?php

namespace app\modules\parser\components;

use \yii\base\Component;

/**
 * Class ApiClient
 *
 *
 * @package app\modules\parser\components
 */
class ApiClient extends Component
{
    /**
     * Метод формирования ссылки для отправки данных на сервер
     *
     * @param $data
     * @param $sourceType
     * @return string
     */
    public function generateRequestHref($data, $sourceType)
    {
        $url = \Yii::$app->params['serverHost'] . '/v1pro/external/order/create?source_type=clorder' .
            '&access_token=' . \Yii::$app->params['access_tokens'][$sourceType];

        $url .= '&source_type=' . $sourceType;
        $url .= '&provider_ext_code=' . $data['provider_ext_code'];
        $url .= '&order_kind=normal';
        $url .= '&order_type=' . $data['order_type'];
        $url .= '&order_tip_type=' . $data['order_tip_type'];
        $url .= '&order_price=' . $data['order_price'];

        if ($data['order_tip'] !== 'cash') {
            $url .= '&order_tip=' . $data['order_tip'];
        }

        $url .= '&customer_name=' . $data['customer_name'];
        $url .= '&customer_phone_num=' . $data['customer_phone_num'];
        $url .= '&customer_address=' . $data['customer_address'];

        if (!empty($data['customer_notes'])) {
            $url .= '&customer_notes=' . $data['customer_notes'];
        }

        $url .= '&order_note=' . $data['order_note'];
        $url .= '&order_note_payments=' . $data['order_note_payments'];
        $url .= '&order_number=' . $data['order_number'];

        return $url;
    }

    /**
     * Метод отправления запроса по ссылке
     *
     * @param $href
     * @throws \Exception
     * @return mixed
     */
    public function sendApiRequest($href)
    {
        $href = str_replace(" ", '%20', $href);
        $cl = curl_init();
        curl_setopt($cl, CURLOPT_URL, $href);
        curl_setopt($cl, CURLOPT_RETURNTRANSFER, 1);
        $line = curl_exec($cl);
        $result = json_decode($line);
        curl_close($cl);

        if ($result && $result->status === 'success') {
            return $result->data->order_id;
        } else {
            if (isset($result->status) && $result->status === 'error') {
                throw new \Exception('An error has occurred: ' . $result->message);
            }
            throw new \Exception('An error has occurred: ' . $line);
        }
    }
}
