<?php

/**
 * Description of ${name}
 *
 * @author ${user}
 * @package 
 */
class Wpjb_List_Currency
{
    public static function getAll()
    {
        $all = array(
            1  => array('code'=>'AUD', 'name'=>__('Australian Dollars', WPJB_DOMAIN), 'symbol'=>null),
            2  => array('code'=>'CAD', 'name'=>__('Canadian Dollars', WPJB_DOMAIN), 'symbol'=>null),
            3  => array('code'=>'CHF', 'name'=>__('Swiss Franc', WPJB_DOMAIN), 'symbol'=>null),
            4  => array('code'=>'CZK', 'name'=>__('Czech Koruna', WPJB_DOMAIN), 'symbol'=>'Kč'),
            5  => array('code'=>'DKK', 'name'=>__('Danish Krone', WPJB_DOMAIN), 'symbol'=>'kr'),
            6  => array('code'=>'EUR', 'name'=>__('Euros', WPJB_DOMAIN), 'symbol'=>'€'),
            7  => array('code'=>'GBP', 'name'=>__('Pounds Sterling', WPJB_DOMAIN), 'symbol'=>'£'),
            8  => array('code'=>'HKD', 'name'=>__('Hong Kong Dollar', WPJB_DOMAIN), 'symbol'=>null),
            9  => array('code'=>'HUF', 'name'=>__('Hungarian Forint', WPJB_DOMAIN), 'symbol'=>'Hf'),
            10 => array('code'=>'ILS', 'name'=>__('Israeli Shekel', WPJB_DOMAIN), 'symbol'=>null),
            11 => array('code'=>'JPY', 'name'=>__('Japanese Yen', WPJB_DOMAIN), 'symbol'=>'¥'),
            12 => array('code'=>'MXN', 'name'=>__('Mexican Peso', WPJB_DOMAIN), 'symbol'=>null),
            13 => array('code'=>'NOK', 'name'=>__('Norwegian Krone', WPJB_DOMAIN), 'symbol'=>null),
            14 => array('code'=>'NZD', 'name'=>__('New Zealand Dollar', WPJB_DOMAIN), 'symbol'=>null),
            15 => array('code'=>'PLN', 'name'=>__('Polish Zloty', WPJB_DOMAIN), 'symbol'=>'zł'),
            16 => array('code'=>'SEK', 'name'=>__('Swedish Krona', WPJB_DOMAIN), 'symbol'=>null),
            17 => array('code'=>'SGD', 'name'=>__('Singapore Dollar', WPJB_DOMAIN), 'symbol'=>null),
            18 => array('code'=>'USD', 'name'=>__('United States Dollars', WPJB_DOMAIN), 'symbol'=>'$'),
            19 => array('code'=>'ZAR','name'=>__('South African Rands',WPJB_DOMAIN),'symbol'=>'R')
        );
        
        $all = apply_filters("wpjb_list_currency", $all);
        
        return $all;
    }
    
    /**
     * Returns list of currencies
     *
     * @return ArrayIterator 
     */
    public static function getList()
    {
        return new ArrayIterator(self::getAll());
    }

    /**
     * Returns array representing given currency
     *
     * @param string $id
     * @return array
     */
    public static function getCurrency($id)
    {
        $currency = self::getAll();
        if(isset($currency[$id])) {
            return $currency[$id];
        }
        return array();
    }

    public static function getCurrencySymbol($code, $space = " ")
    {
        $currency = self::getCurrency($code);

        if(!is_null($currency['symbol'])) {
            return $currency['symbol'];
        } else {
            return $currency['code'].$space;
        }
    }
}
?>
