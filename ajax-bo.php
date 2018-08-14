<?php
/**
 * Integral logistics for eCommerce with pickup or fulfillment through Shipit
 *
 * @author    Rolige <www.rolige.com>
 * @copyright 2011-2018 Rolige - All Rights Reserved
 * @license   Proprietary and confidential
 */

require_once(dirname(__FILE__).'/../../config/config.inc.php');
require_once(dirname(__FILE__).'/../../init.php');
include_once(dirname(__FILE__).'/rg_shipit.php');

$module = new Rg_Shipit();

if (Tools::getValue('secure_key') == $module->secure_key) {
    Configuration::updateValue('SHIPIT_ALERTED_ASM', 1);
    die('1');
}

die('0');
