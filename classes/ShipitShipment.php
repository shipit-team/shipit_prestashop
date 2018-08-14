<?php
/**
 * Integral logistics for eCommerce with pickup or fulfillment through Shipit
 *
 * @author    Rolige <www.rolige.com>
 * @copyright 2011-2018 Rolige - All Rights Reserved
 * @license   Proprietary and confidential
 */

class ShipitShipment extends ObjectModel
{
    public $shipit_id;
    public $id_order;
    public $status;
    public $tracking;
    public $courier;
    public $packing;
    public $date_upd;
    public $date_add;

    public static $definition = array(
        'table' => 'rg_shipit_shipment',
        'primary' => 'id_rg_shipit_shipment',
        'fields' => array(
            'shipit_id' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'id_order' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'status' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'default' => 'in_preparation'),
            'tracking' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'courier' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'packing' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDateFormat', 'copy_post' => false),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDateFormat', 'copy_post' => false)
        )
    );

    public static function getIdShipitByIdOrder($id_order)
    {
        return Db::getInstance()->getValue(
            'SELECT `id_rg_shipit_shipment`
            FROM `'._DB_PREFIX_.'rg_shipit_shipment`
            WHERE id_order='.(int)$id_order
        );
    }

    public static function isShipitCarrierByIdOrder($id_order)
    {
        return (bool)Db::getInstance()->getValue(
            'SELECT *
            FROM `'._DB_PREFIX_.'orders` o
            INNER JOIN `'._DB_PREFIX_.'carrier` ca ON (o.`id_carrier` = ca.`id_carrier` AND ca.`external_module_name` = "rg_shipit")
            WHERE o.`id_order`='.(int)$id_order
        );
    }

    public static function getIdShipitByShipitId($shipit_id)
    {
        return Db::getInstance()->getValue(
            'SELECT `id_rg_shipit_shipment`
            FROM `'._DB_PREFIX_.'rg_shipit_shipment`
            WHERE shipit_id='.(int)$shipit_id
        );
    }
}
