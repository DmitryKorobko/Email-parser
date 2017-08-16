<?php

namespace app\modules\parser\helpers;

/**
 * Class Helper
 * @package app\modules\parser\helpers
 */
class Helper
{
    /** Method of transferring words to a new line
     *
     * @param $str
     * @return string
     */
    public static function wordWrappingForPayments($str)
    {
        preg_match_all('#[\w\$\.]+#', $str, $matches);

        $result = [];
        $num = 1;
        foreach ($matches[0] as $match) {
            if ($num % 2 == 0) {
                $match .= ' \\n';
            } else {
                $match .= ' ';
            }
            $result[] = $match;
            $num ++;
        }

        return implode('', $result);
    }

    /** Method of transferring words to a new line
     *
     * @param $str
     * @return string
     */
    public static function wordWrappingForNote($str)
    {
        foreach ($str as $item) {
            $table[] = $item->textContent;
        }

        $th = array_shift($table);
        preg_match_all('~\bQty|Item|Price\b~', $th, $keys);

        $lines = [];
        foreach ($table as $item) {
            preg_match('~\d[a-zA-Z]~', $item, $qtyValue);
            $item = preg_replace('~\d[A-Za-z]~', '', $item);

            preg_match('~\$\d+.?\d+~', $item, $priceValue);
            $item = preg_replace('~\s{2}~', '', preg_replace('~\$\d+.?\d+~', '', $item));

            $lines[] = [
                'qtyValue'   => isset($qtyValue[0])   ? $qtyValue[0]   : '',
                'priceValue' => isset($priceValue[0]) ? $priceValue[0] : '',
                'itemValue'  => $item
            ];
        }

        $result = '';
        if (is_array($lines) && !empty($lines)) {
            foreach ($lines as $line) {
                $result[] = 'qty: ' . $line['qtyValue'] . ', item: ' .  $line['itemValue'] . ', price: ' . $line['priceValue'] . ' \\n';
            }

            return implode('', $result);
        }

        return $result;
    }

    /** Method of transferring words to a new line
    *
    * @param $str
    * @return string
    */
    public static function wordWrappingForNoteByEatstreet($str)
    {
        $str = preg_replace('~\bItems|Price\b~', '', $str);
        preg_match_all('~[\d.]+\s{2}+~', $str, $price);

        $str = preg_replace('~[\d.]+\s{2}+~', ',', $str);
        $items = explode(',', $str);

        $count = 0;
        if (isset($price) && is_array($price)) {
            $price = array_shift($price);
            $count = count($price);
        }

        $result = '';
        if ($count) {
            while ($count) {
                $result[] = 'Items: ' . trim(array_shift($items)) . ', ' . 'Price: ' . trim(array_shift($price)) . '\\n';
                $count --;
            }

            return implode('', $result);
        }

        return $result;
    }

    /** Method of deleting NaN symbols from phone number
     *
     * @param $num
     * @return string
     */
    public static function deleteNaNFromTelNum($num)
    {
        $number = '';
        for ($i =0; $i < strlen($num); $i++) {
            if (is_numeric($num[$i])) {
                $number .= $num[$i];
            }
        }

        return $number;
    }
}
