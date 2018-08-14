<?php
/**
 * Integral logistics for eCommerce with pickup or fulfillment through Shipit
 *
 * @author    Rolige <www.rolige.com>
 * @copyright 2011-2018 Rolige - All Rights Reserved
 * @license   Proprietary and confidential
 */

require_once(dirname(__FILE__).'/../../config/config.inc.php');
require_once dirname(__FILE__).'/rg_shipit.php';

header('Content-Type: text/plain');
$module = new Rg_Shipit();

if ($module->active && Tools::getValue('token') == $module->secure_key) {
    $text = Tools::file_get_contents('error_log');

    if ($text) {
        echo $text;
    } else {
        echo 'No existen errores registrados.';
    }

    exit;
}

die('¡Acceso denegado!');
