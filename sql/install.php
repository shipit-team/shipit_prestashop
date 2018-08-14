<?php
/**
 * Integral logistics for eCommerce with pickup or fulfillment through Shipit
 *
 * @author    Rolige <www.rolige.com>
 * @copyright 2011-2018 Rolige - All Rights Reserved
 * @license   Proprietary and confidential
 */

$sql = array();

$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'rg_shipit_services` (
    `id_service` int(11) NOT NULL AUTO_INCREMENT,
    `code` varchar(64) NOT NULL,
    `desc` varchar(64) NOT NULL,
    `id_reference` int(11) DEFAULT NULL,
    `date_add` datetime NOT NULL,
    `date_upd` datetime NOT NULL,
    PRIMARY KEY  (`id_service`),
    KEY `code` (`code`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'rg_shipit_cache` (
    `id_cart` int(10) UNSIGNED NOT NULL,
    `hash_cart` varchar(32) COLLATE utf8_general_ci DEFAULT NULL,
    `package` text COLLATE utf8_general_ci DEFAULT NULL,
    `carriers` text COLLATE utf8_general_ci DEFAULT NULL,
    PRIMARY KEY  (`id_cart`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'rg_shipit_commune` (
    `id` int(11) NOT NULL,
    `name` varchar(128) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'rg_shipit_shipment` (
    `id_rg_shipit_shipment` int(11) NOT NULL AUTO_INCREMENT,
    `shipit_id` int(11) NOT NULL,
    `id_order` int(11) NOT NULL,
    `status` varchar(64) NOT NULL,
    `tracking` varchar(64) NULL,
    `courier` varchar(64) NULL,
    `packing` varchar(64) NULL,
    `date_add` datetime NOT NULL,
    `date_upd` datetime NOT NULL,
    PRIMARY KEY  (`id_rg_shipit_shipment`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
