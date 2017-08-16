<?php

namespace app\modules\parser\components;

use app\modules\parser\exceptions\ServerException;
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
     * @param $sourceType
     * @return string
     */
    public function generateRequestHref($sourceType)
    {
        $url = \Yii::$app->params['serverHost'] . '/v1pro/external/order/create?access_token='
            . \Yii::$app->params['access_tokens'][$sourceType];

        return $url;
    }

    /**
     * Метод формирования массива для отправки данных на сервер
     *
     * @param $data
     * @param $sourceType
     * @return array
     */
    public function generateRequestArray($data, $sourceType)
    {
        $request_array = [];
        $request_array['source_type'] = $sourceType;
        $request_array['provider_ext_code'] = $data['provider_ext_code'];
        $request_array['order_kind'] = 'normal';
        $request_array['order_type'] = $data['order_type'];
        $request_array['order_tip_type'] = $data['order_tip_type'];
        $request_array['order_price'] = $data['order_price'];

        if ($data['order_tip'] !== 'cash') {
            $request_array['order_tip'] = $data['order_tip'];
        }

        $request_array['customer_name'] = $data['customer_name'];
        $request_array['customer_phone_num'] = $data['customer_phone_num'];

        if (!empty($data['customer_address'])) {
            $request_array['customer_address'] = $data['customer_address'];
        }

        if (!empty($data['customer_notes'])) {
            $request_array['customer_notes'] = $data['customer_notes'];
        }

        $request_array['order_note'] = $data['order_note'];
        $request_array['order_note_payments'] = $data['order_note_payments'];
        $request_array['order_number'] = $data['order_number'];
        $request_array['source_email'] = $data['message_body'];

        return $request_array;
    }

    /**
     * Метод отправления запроса по ссылке
     *
     * @param $href
     * @param $data
     * @param $sourceType
     * @throws \Exception
     * @return mixed
     */
    public function sendApiRequest($href, $data, $sourceType)
    {
        $request_array = $this->generateRequestArray($data, $sourceType);
        $cl = curl_init();
        curl_setopt($cl, CURLOPT_URL, $href);
        curl_setopt($cl, CURLOPT_POST, 1);
        curl_setopt($cl, CURLOPT_POSTFIELDS, http_build_query($request_array));
        curl_setopt($cl, CURLOPT_RETURNTRANSFER, true);

        $line = curl_exec($cl);

        $result = json_decode($line);
        curl_close($cl);

        if ($result && $result->status === 'success') {
            return $result->data->order_id;
        } else {
            if (isset($result->status) && $result->status === 'error') {
                throw new ServerException($href, 'An error has occurred: ' . $result->message);
            }
            if ($line === false) {
                throw new ServerException($href, 'An error has occurred: Curl error.');
            }
            throw new ServerException($href, 'An error has occurred: ' . $line);
        }
    }
}
