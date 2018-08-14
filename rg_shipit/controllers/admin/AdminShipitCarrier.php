<?php
/**
 * Integral logistics for eCommerce with pickup or fulfillment through Shipit
 *
 * @author    Rolige <www.rolige.com>
 * @copyright 2011-2018 Rolige - All Rights Reserved
 * @license   Proprietary and confidential
 */

class AdminShipitCarrierController extends ModuleAdminController
{
    public function __construct()
    {
        $this->table = 'rg_shipit_shipment';
        $this->identifier = 'id_pedido';
        $this->className = 'ShipitShipment';
        $this->lang = false;
        $this->context = Context::getContext();
        $this->list_no_link = true;
        $this->bootstrap = true;
        $this->allow_export = true;

        $this->_select = '
            o.`id_order` AS id_pedido,
            o.`id_order` AS id_pdf,
            o.total_paid_tax_incl,
            o.id_currency,
            o.date_add AS `fecha_pedido`,
            CONCAT(LEFT(c.`firstname`, 1), \'. \', c.`lastname`) AS `customer`';
        $this->_join = '
            RIGHT JOIN `'._DB_PREFIX_.'orders` o ON (o.`id_order` = a.`id_order`)
            INNER JOIN `'._DB_PREFIX_.'customer` c ON (c.`id_customer` = o.`id_customer`)
            INNER JOIN `'._DB_PREFIX_.'carrier` ca ON (o.`id_carrier` = ca.`id_carrier` AND ca.`external_module_name` = "rg_shipit")';
        $this->_orderBy = 'o.id_order';
        $this->_orderWay = 'DESC';

        parent::__construct();

        $this->addRowAction('view');

        $this->fields_list = array(
            'id_pedido' => array(
                'title' => $this->l('Order ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
                'filter_key' => 'a!id_order'
            ),
            'customer' => array(
                'title' => $this->l('Customer'),
                'havingFilter' => true,
            ),
            'total_paid_tax_incl' => array(
                'title' => $this->l('Order Total'),
                'align' => 'center',
                'prefix' => '<b>',
                'suffix' => '</b>',
                'type' => 'price',
                'class' => 'fixed-width-xs',
                'currency' => true
            ),
            'fecha_pedido' => array(
                'title' => $this->l('Order Date'),
                'width' => 152,
                'align' => 'right',
                'type' => 'datetime',
                'filter_key' => 'o!date_add'
            ),
            'status' => array(
                'title' => $this->l('Shipit Status'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
                'type' => 'select',
                'list' => $this->module->available_status,
                'filter_key' => 'a!status',
                'filter_type' => 'string'
            ),
            'courier' => array(
                'title' => $this->l('Courier'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
                'filter_key' => 'a!courier',
                'filter_type' => 'string'
            ),
            'packing' => array(
                'title' => $this->l('Packing'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
                'filter_key' => 'a!packing',
                'filter_type' => 'string'
            ),
            'date_upd' => array(
                'title' => $this->l('Last Update Date'),
                'width' => 152,
                'align' => 'right',
                'type' => 'datetime',
                'filter_key' => 'a!date_add'
            ),
            'tracking' => array(
                'title' => $this->l('Tracking Number'),
                'class' => 'fixed-width-xs',
                'align' => 'center'
            ),
        );
    }

    public function getList($id_lang, $order_by = null, $order_way = null, $start = 0, $limit = null, $id_lang_shop = false)
    {
        parent::getList($id_lang, $order_by, $order_way, $start, $limit, $id_lang_shop);

        foreach ($this->_list as &$row) {
            $row['status'] = (isset($this->module->available_status[$row['status']]) ? $this->module->available_status[$row['status']] : $this->l('Other'));
        }
    }

    public function initToolbar()
    {
        parent::initToolbar();
        unset($this->toolbar_btn['new']);
    }

    public function renderView()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders').'&vieworder&id_order='.(int)Tools::getValue('id_pedido'));
    }

    public function renderKpis()
    {
        $kpis = array();

        $helper = new HelperKpi();
        $helper->id = 'box-generated-shipment-percent';
        $helper->icon = 'icon-bar-chart';
        $helper->color = 'color1';
        $helper->title = $this->l('Generated Percentage', null, null, false);
        $helper->subtitle = $this->l('ALL TIME', null, null, false);
        $valores = Db::getInstance()->getRow(
            'SELECT COUNT(DISTINCT i.`id_order`) as cantidad, COUNT(DISTINCT o.`id_order`) as total
            FROM `'._DB_PREFIX_.'rg_shipit_shipment` i
            RIGHT JOIN `'._DB_PREFIX_.'orders` o ON (o.`id_order` = i.`id_order`)
            INNER JOIN `'._DB_PREFIX_.'carrier` ca ON (o.`id_carrier` = ca.`id_carrier` AND ca.`external_module_name` = "rg_shipit")'
        );
        $helper->value = round($valores['cantidad'] / ($valores['total'] ? $valores['total'] : 1) * 100, 2).'%';
        $kpis[] = $helper->generate();

        $helper = new HelperKpi();
        $helper->id = 'box-generated-shipment-total';
        $helper->icon = 'icon-truck';
        $helper->color = 'color2';
        $helper->title = $this->l('Generated Total', null, null, false);
        $helper->subtitle = $this->l('ALL TIME', null, null, false);
        $helper->value = $valores['cantidad'].'/'.$valores['total'];
        $kpis[] = $helper->generate();

        $helper = new HelperKpiRow();
        $helper->kpis = $kpis;

        return $helper->generate();
    }
}
