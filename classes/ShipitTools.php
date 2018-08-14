<?php
/**
 * Integral logistics for eCommerce with pickup or fulfillment through Shipit
 *
 * @author    Rolige <www.rolige.com>
 * @copyright 2011-2018 Rolige - All Rights Reserved
 * @license   Proprietary and confidential
 */

class ShipitTools
{
    public static function normalizeAddresses()
    {
        @ini_set('max_execution_time', 0);
        $normalize_addresses = Configuration::get('SHIPIT_NORMALIZE_ADDRESSES');
        $addresses = ShipitTools::getAllChileAddresses($normalize_addresses);
        $normalize_id_addresses = array();

        if ($addresses) {
            $cities = ShipitLists::cities();
            $normalization_cities = ShipitLists::normalizationCities();

            foreach ($addresses as $address) {
                $address_city = trim($address['city']); // Trim string.

                if ($address_city == '.') { // Skip opc default addresses.
                    continue;
                }

                $address_city = preg_replace('!\s+!', ' ', $address_city); // Replace multiple spaces.
                $address_city = Tools::replaceAccentedChars($address_city); // Replace accented chars.
                $address_city = Tools::strtolower($address_city); // Convert to lower case.
                $match = false;

                foreach ($normalization_cities as $city) {
                    if (in_array($address_city, $city['alias'])) {
                        if ($city['name'] != $address['city']) {
                            Db::getInstance()->update(
                                'address',
                                array('city' => pSQL($city['name'])),
                                'id_address='.(int)$address['id_address']
                            );
                        }

                        $match = true;
                        break;
                    }
                }

                if (!$match) {
                    foreach ($cities as $city) {
                        if (Tools::strtolower($city['name']) == $address_city) {
                            if ($city['name'] != $address['city']) {
                                Db::getInstance()->update(
                                    'address',
                                    array('city' => pSQL($city['name'])),
                                    'id_address='.(int)$address['id_address']
                                );
                            }

                            $match = true;
                            break;
                        }
                    }
                }

                if ($match == false) {
                    $normalize_id_addresses[] = $address['id_address'];
                }
            }
        }

        if ($normalize_id_addresses) {
            Configuration::updateValue('SHIPIT_NORMALIZE_ADDRESSES', implode(',', $normalize_id_addresses));
        } else {
            Configuration::deleteByName('SHIPIT_NORMALIZE_ADDRESSES');
            Configuration::updateValue('SHIPIT_NORMALIZED', true);
        }
    }

    /**
     * Get all cities and their id from the Chile addresses
     * @return array
     */
    public static function getAllChileAddresses($id_addresses = false)
    {
        $sql = new DbQuery();
        $sql->select('a.`id_address`, `city`');
        $sql->from('address', 'a');
        $sql->innerJoin('customer', 'c', 'c.`id_customer` = a.`id_customer`');
        $sql->where('`id_country` = '.(int)Country::getByIso('CL')
            .' AND a.`deleted` = 0'
            .' AND c.`deleted` = 0'
            .($id_addresses ? ' AND a.`id_address` IN ('.$id_addresses.')' : ''));

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql->build());
    }

    /**
     * Gets the total volume according to the products in the cart
     * @param  [type] $config   Main module options
     * @param  [type] $cart     Object of Cart
     * @return [float]          Return the volume
     */
    public static function getProductsVolume($config, $cart)
    {
        $s_width = (float)$config['SHIPIT_SET_VALUE_WIDTH'];
        $s_height = (float)$config['SHIPIT_SET_VALUE_HEIGHT'];
        $s_depth = (float)$config['SHIPIT_SET_VALUE_DEPTH'];

        switch ($config['SHIPIT_SET_DIMENSIONS']) {
            case 1: // Set the specified dimensions.
                $volume = (float)Db::getInstance()->getValue('
                    SELECT SUM(
                        '.($s_width ? $s_width : 'p.`width`').' *
                        '.($s_height ? $s_height : 'p.`height`').' *
                        '.($s_depth ? $s_depth : 'p.`depth`').' * cp.`quantity`)
                    FROM `'._DB_PREFIX_.'cart_product` cp
                    LEFT JOIN `'._DB_PREFIX_.'product` p ON (cp.`id_product` = p.`id_product`)
                    WHERE cp.`id_cart` = '.(int)$cart->id);

                break;

            case 2: // When the product dimension is missing or not set.
                $volume = (float)Db::getInstance()->getValue('
                    SELECT SUM(
                        IF(p.`width` > 0, p.`width`, '.($s_width ? $s_width : 'p.`width`').') *
                        IF(p.`height` > 0, p.`height`, '.($s_height ? $s_height : 'p.`height`').') *
                        IF(p.`depth` > 0, p.`depth`, '.($s_depth ? $s_depth : 'p.`depth`').') * cp.`quantity`)
                    FROM `'._DB_PREFIX_.'cart_product` cp
                    LEFT JOIN `'._DB_PREFIX_.'product` p ON (cp.`id_product` = p.`id_product`)
                    WHERE cp.`id_cart` = '.(int)$cart->id);

                break;

            case 3: // When the product dimension is less than specified.
                $volume = (float)Db::getInstance()->getValue('
                    SELECT SUM(
                        IF(p.`width` < '.($s_width ? $s_width : '-1').', '.($s_width ? $s_width : 'p.`width`').', p.`width`) *
                        IF(p.`height` < '.($s_height ? $s_height : '-1').', '.($s_height ? $s_height : 'p.`height`').', p.`height`) *
                        IF(p.`depth` < '.($s_depth ? $s_depth : '-1').', '.($s_depth ? $s_depth : 'p.`depth`').', p.`depth`) * cp.`quantity`)
                    FROM `'._DB_PREFIX_.'cart_product` cp
                    LEFT JOIN `'._DB_PREFIX_.'product` p ON (cp.`id_product` = p.`id_product`)
                    WHERE cp.`id_cart` = '.(int)$cart->id);

                break;

            case 4: // When the product dimension is greater than specified.
                $volume = (float)Db::getInstance()->getValue('
                    SELECT SUM(
                        IF(p.`width` > '.($s_width ? $s_width : '-1').', '.($s_width ? $s_width : 'p.`width`').', p.`width`) *
                        IF(p.`height` > '.($s_height ? $s_height : '-1').', '.($s_height ? $s_height : 'p.`height`').', p.`height`) *
                        IF(p.`depth` > '.($s_depth ? $s_depth : '-1').', '.($s_depth ? $s_depth : 'p.`depth`').', p.`depth`) * cp.`quantity`)
                    FROM `'._DB_PREFIX_.'cart_product` cp
                    LEFT JOIN `'._DB_PREFIX_.'product` p ON (cp.`id_product` = p.`id_product`)
                    WHERE cp.`id_cart` = '.(int)$cart->id);

                break;

            default:
                $volume = (float)Db::getInstance()->getValue('
                    SELECT SUM(p.`width` * p.`height` * p.`depth` * cp.`quantity`)
                    FROM `'._DB_PREFIX_.'cart_product` cp
                    LEFT JOIN `'._DB_PREFIX_.'product` p ON (cp.`id_product` = p.`id_product`)
                    WHERE cp.`id_cart` = '.(int)$cart->id);

                break;
        }

        return $volume;
    }

    /**
     * Gets the total weight according to the products in the cart
     * @param  [type] $config   Main module options
     * @param  [type] $cart     Object of Cart
     * @return [float]          Return the total weight
     */
    public static function getProductsWeight($config, $cart)
    {
        $s_weight = (float)$config['SHIPIT_SET_VALUE_WEIGHT'];

        switch ($config['SHIPIT_SET_WEIGHT']) {
            case 1: // Set the specified weight.
                $weight = $cart->nbProducts() * $s_weight;

                break;

            case 2: // When the product weight is missing or not set.
                if (Combination::isFeatureActive()) {
                    $weight_product_with_attribute = (float)Db::getInstance()->getValue('
                        SELECT SUM(IF((p.`weight` + pa.`weight`) > 0, (p.`weight` + pa.`weight`), '.$s_weight.') * cp.`quantity`)
                        FROM `'._DB_PREFIX_.'cart_product` cp
                        LEFT JOIN `'._DB_PREFIX_.'product` p ON (cp.`id_product` = p.`id_product`)
                        LEFT JOIN `'._DB_PREFIX_.'product_attribute` pa ON (cp.`id_product_attribute` = pa.`id_product_attribute`)
                        WHERE (cp.`id_product_attribute` IS NOT NULL AND cp.`id_product_attribute` != 0)
                        AND cp.`id_cart` = '.(int)$cart->id);
                } else {
                    $weight_product_with_attribute = 0;
                }

                $weight_product_without_attribute = (float)Db::getInstance()->getValue('
                    SELECT SUM(IF(p.`weight` > 0, p.`weight`, '.$s_weight.') * cp.`quantity`) as nb
                    FROM `'._DB_PREFIX_.'cart_product` cp
                    LEFT JOIN `'._DB_PREFIX_.'product` p ON (cp.`id_product` = p.`id_product`)
                    WHERE (cp.`id_product_attribute` IS NULL OR cp.`id_product_attribute` = 0)
                    AND cp.`id_cart` = '.(int)$cart->id);

                $weight = $weight_product_with_attribute + $weight_product_without_attribute;

                break;

            case 3: // When the product weight is less than specified.
                if (Combination::isFeatureActive()) {
                    $weight_product_with_attribute = (float)Db::getInstance()->getValue('
                        SELECT SUM(IF((p.`weight` + pa.`weight`) < '.$s_weight.', '.$s_weight.', (p.`weight` + pa.`weight`)) * cp.`quantity`)
                        FROM `'._DB_PREFIX_.'cart_product` cp
                        LEFT JOIN `'._DB_PREFIX_.'product` p ON (cp.`id_product` = p.`id_product`)
                        LEFT JOIN `'._DB_PREFIX_.'product_attribute` pa ON (cp.`id_product_attribute` = pa.`id_product_attribute`)
                        WHERE (cp.`id_product_attribute` IS NOT NULL AND cp.`id_product_attribute` != 0)
                        AND cp.`id_cart` = '.(int)$cart->id);
                } else {
                    $weight_product_with_attribute = 0;
                }

                $weight_product_without_attribute = (float)Db::getInstance()->getValue('
                    SELECT SUM(IF(p.`weight` < '.$s_weight.', '.$s_weight.', p.`weight`) * cp.`quantity`) as nb
                    FROM `'._DB_PREFIX_.'cart_product` cp
                    LEFT JOIN `'._DB_PREFIX_.'product` p ON (cp.`id_product` = p.`id_product`)
                    WHERE (cp.`id_product_attribute` IS NULL OR cp.`id_product_attribute` = 0)
                    AND cp.`id_cart` = '.(int)$cart->id);

                $weight = $weight_product_with_attribute + $weight_product_without_attribute;

                break;

            case 4: // When the product weight is greater than specified.
                if (Combination::isFeatureActive()) {
                    $weight_product_with_attribute = (float)Db::getInstance()->getValue('
                        SELECT SUM(IF((p.`weight` + pa.`weight`) > '.$s_weight.', '.$s_weight.', (p.`weight` + pa.`weight`)) * cp.`quantity`)
                        FROM `'._DB_PREFIX_.'cart_product` cp
                        LEFT JOIN `'._DB_PREFIX_.'product` p ON (cp.`id_product` = p.`id_product`)
                        LEFT JOIN `'._DB_PREFIX_.'product_attribute` pa ON (cp.`id_product_attribute` = pa.`id_product_attribute`)
                        WHERE (cp.`id_product_attribute` IS NOT NULL AND cp.`id_product_attribute` != 0)
                        AND cp.`id_cart` = '.(int)$cart->id);
                } else {
                    $weight_product_with_attribute = 0;
                }

                $weight_product_without_attribute = (float)Db::getInstance()->getValue('
                    SELECT SUM(IF(p.`weight` > '.$s_weight.', '.$s_weight.', p.`weight`) * cp.`quantity`) as nb
                    FROM `'._DB_PREFIX_.'cart_product` cp
                    LEFT JOIN `'._DB_PREFIX_.'product` p ON (cp.`id_product` = p.`id_product`)
                    WHERE (cp.`id_product_attribute` IS NULL OR cp.`id_product_attribute` = 0)
                    AND cp.`id_cart` = '.(int)$cart->id);

                $weight = $weight_product_with_attribute + $weight_product_without_attribute;

                break;

            default:
                $weight = $cart->getTotalWeight();

                break;
        }

        return $weight;
    }

    /**
     * Generates a unique hash according some key cart params
     * @param  [int] $id_cart   [Current id of cart]
     * @param  [array] $config  [Module configuration]
     * @return [string]         [hash md5]
     */
    public static function getHashCart($id_cart, $config)
    {
        $config = serialize($config);

        return Db::getInstance()->getValue(
            'SELECT MD5(GROUP_CONCAT(cp.`id_product`, cp.`id_product_attribute`, cp.`quantity`, \''.md5($config).'\', a.`city`))
            FROM `'._DB_PREFIX_.'cart_product` cp
            LEFT JOIN `'._DB_PREFIX_.'address` a ON (cp.`id_address_delivery` = a.`id_address`)
            WHERE cp.`id_cart` = '.(int)$id_cart
        );
    }

    /**
     * Convert weight to kilogram, in case weight unit is different to kg in the configuration
     */
    public static function convertToKg($val)
    {
        if ($val) {
            $unit = Configuration::get('PS_WEIGHT_UNIT');

            if ($unit == 'lb' || $unit == 'lbs' || $unit == 'pound' || $unit == 'pounds') {
                $val = $val / 2.20462; // 1 kg = 2.20462 lb
            } /* elseif () {
              // Create more conversions in case necessary
              } */
        }

        return $val;
    }

    /**
     * Convert dimension to centimeter, in case dimension unit is different to cm in the configuration
     */
    public static function convertToCm($val)
    {
        if ($val) {
            $unit = Configuration::get('PS_DIMENSION_UNIT');

            if ($unit == 'in' || $unit == 'ins' || $unit == 'inch' || $unit == 'inchs') {
                $val = $val / 0.39370; // 1 cm = 0.39370 in
            } /* elseif () {
              // Create more conversions in case necessary
              } */
        }

        return $val;
    }

    public static function log($object)
    {
        return self::error_log(
            '['.date('Y-m-d H:i:s').'] '.print_r($object, true)."\n",
            3,
            dirname(__FILE__).'/../error_log'
        );
    }

    /**
     * Backward compatibility for PrestaShop 1.5
     */
    public static function error_log($object, $message_type = null, $destination = null, $extra_headers = null)
    {
        if (version_compare(_PS_VERSION_, '1.6.0.0', '>=') === true) {
            return Tools::error_log($object, $message_type, $destination, $extra_headers);
        }

        return error_log(print_r($object, true), $message_type, $destination, $extra_headers);
    }

    /**
     * Backward compatibility for PrestaShop <= 1.5.5.0
     */
    public static function setGroups($groups, $id_carrier, $delete = true)
    {
        if ($delete) {
            Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'carrier_group WHERE id_carrier = '.(int)$id_carrier);
        }

        if (!is_array($groups) || !count($groups)) {
            return true;
        }

        $sql = 'INSERT INTO '._DB_PREFIX_.'carrier_group (id_carrier, id_group) VALUES ';
        foreach ($groups as $id_group) {
            $sql .= '('.(int)$id_carrier.', '.(int)$id_group.'),';
        }

        return Db::getInstance()->execute(rtrim($sql, ','));
    }
}
