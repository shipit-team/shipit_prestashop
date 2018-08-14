<?php
/**
 * Integral logistics for eCommerce with pickup or fulfillment through Shipit
 *
 * @author    Rolige <www.rolige.com>
 * @copyright 2011-2018 Rolige - All Rights Reserved
 * @license   Proprietary and confidential
 */

require_once(dirname(__FILE__).'/../../config/config.inc.php');
include_once(dirname(__FILE__).'/rg_shipit.php');

$module = new Rg_Shipit();

if ($module->active && Tools::getValue('secure_key') == $module->secure_key) {
    if ($data = Tools::file_get_contents('php://input')) {
        $data = str_replace('info=', '', $data);
        $data = urldecode($data);

        if ($json = Tools::jsonDecode($data)) {
            $module->processWebhook($json);
            die('1');
        }
    }
}

die('0');
