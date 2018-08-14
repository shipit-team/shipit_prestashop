<?php
/**
 * Integral logistics for eCommerce with pickup or fulfillment through Shipit
 *
 * @author    Shipit <www.shipit.cl>
 * @copyright 2018- Shipit - All Rights Reserved
 * @license   Proprietary and confidential
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require(dirname(__FILE__).'/classes/ShipitCore.php');

class Rg_Shipit extends ShipitCore
{
    public $id_carrier;
    public $config;
    private $deleting_from_module = false;
    public $available_status = array();

    public function __construct()
    {
        $this->name = 'rg_shipit';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.0';
        $this->author = 'Shipit';
        $this->author_link = 'https://shipit.cl/';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = array('min' => '1.5', 'max' => _PS_VERSION_);
        $this->bootstrap = true;
        $this->secure_key = Tools::encrypt($this->name);

        parent::__construct();

        $this->displayName = $this->l('Quoting Couriers Chile by Shipit');
        $this->description = $this->l('Integral logistics for eCommerce with pickup or fulfillment through Shipit');
        $this->config = $this->getGlobalConfig();

        if (!$this->config['SHIPIT_EMAIL'] || !$this->config['SHIPIT_TOKEN']) {
            $this->warning = $this->l('The API credentials must be configured');
        } elseif (!$this->config['SHIPIT_NORMALIZED']) {
            $this->warning = $this->l('Addresses should be normalized');
        }

        $this->available_status = array(
            'in_preparation' => $this->l('In preparation'),
            'in_route' => $this->l('In route'),
            'delivered' => $this->l('Delivered'),
            'by_retired' => $this->l('By retired'),
            'other' => $this->l('Other'),
            'failed' => $this->l('Failed'),
            'indemnify' => $this->l('Indemnify'),
            'ready_to_dispatch' => $this->l('Ready to dispatch'),
            'dispatched' => $this->l('Dispatched'),
            'at_shipit' => $this->l('At shipit'),
            'returned' => $this->l('Returned')
        );
    }

    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('To install this module, you must enable the CURL php extension on the server');

            return false;
        } elseif (!$this->config['chile_id_country']) {
            $this->_errors[] = $this->l('To install this module, you must have the country of Chile in your countries list');

            return false;
        } elseif (!$this->config['chile_id_currency']) {
            $this->_errors[] = $this->l('To install this module, you must have the Chilean Peso in your currencies list');

            return false;
        } elseif (Module::isEnabled('rg_chilexpress') || Module::isEnabled('rg_correoschile') || Module::isEnabled('rg_starken')) {
            $this->_errors[] = $this->l('Shipit already contains Chilexpress, Starken and Correos Chile carriers. You must disable these modules before install Shipit module.');

            return false;
        }

        include(dirname(__FILE__).'/sql/install.php');
        Configuration::updateValue('SHIPIT_LIVE_MODE', 1);
        Configuration::updateValue('SHIPIT_ACTIVE_QUOTATION', 1);
        Configuration::updateValue('SHIPIT_ACTIVE_GENERATION', 1);
        Configuration::updateValue('SHIPIT_ACTIVE_UPDATE', 1);
        Configuration::updateValue('SHIPIT_PACKAGE', 'Sin empaque');
        Configuration::updateValue('SHIPIT_ESTIMATION_MODE', 3);
        Configuration::updateValue('SHIPIT_DISPATCH_ALGORITHM', 1);
        Configuration::updateValue('SHIPIT_ONLY_CHECKOUT', 1);

        $tab = new Tab();
        $tab->class_name = 'AdminShipitCarrier';
        $tab->id_parent = Tab::getIdFromClassName('AdminParentShipping');
        $tab->module = $this->name;
        $languages = Language::getLanguages();
        foreach ($languages as $lang) {
            $tab->name[$lang['id_lang']] = $this->l('Shipit Carrier');
        }
        $tab->add();

        $return = parent::install() &&
            $this->registerHook('displayBackOfficeHeader') &&
            $this->registerHook('displayHeader') &&
            $this->registerHook('displayAdminOrder') &&
            $this->registerHook('actionObjectCarrierUpdateAfter');

        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=') == true) {
            $return &= $this->registerHook('displayCarrierExtraContent');
        }

        return (bool)$return;
    }

    public function uninstall()
    {
        if ($services = ShipitServices::getAll(true)) {
            foreach ($services as $service) {
                if ($carrier = Carrier::getCarrierByReference($service['id_reference'])) {
                    $this->deleting_from_module = true;
                    $carrier->deleted = true;
                    $carrier->update();
                }
            }
        }

        include(dirname(__FILE__).'/sql/uninstall.php');
        Configuration::deleteByName('SHIPIT_NORMALIZE_ADDRESSES');
        Configuration::deleteByName('SHIPIT_NORMALIZED');
        Configuration::deleteByName('SHIPIT_ALERTED_ASM');
        Configuration::deleteByName('SHIPIT_VERIFICATION');
        $config_values = $this->getConfigFormValues();

        foreach (array_keys($config_values) as $key) {
            Configuration::deleteByName($key);
        }

        $id_tab = Tab::getIdFromClassName('AdminShipitCarrier');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            $tab->delete();
        }

        return parent::uninstall();
    }

    /**
     * Configuration form
     */
    public function getContent()
    {
        $output = '';

        // Warning message when some carriers are not associated with any zone.
        if ($services = ShipitServices::getAll(true)) {
            $carriers = array();
            foreach ($services as $service) {
                if ($carrier = Carrier::getCarrierByReference($service['id_reference'])) {
                    if (!$carrier->getZones()) {
                        $carriers[] = $carrier->name.': '.$carrier->delay[$this->context->language->id];
                    }
                }
            }

            if ($carriers) {
                $msg = $this->l('The following carriers are not associated with any Zone');
                $msg .= '<ul>';
                foreach ($carriers as $carrier) {
                    $msg .= '<li>'.$carrier.'</li>';
                }

                $msg .= '</ul>';
                $output .= $this->adminDisplayWarning($msg);
            }
        }

        // Warning message when advanced stock is enabled.
        if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') && !Configuration::get('SHIPIT_ALERTED_ASM')) {
            $output .= $this->adminDisplayWarning(
                $this->l('Because you are using "Advanced stock", you must make sure to associate the shipping carriers to the warehouses.').' <a id="alert_asm" href="#" class="btn btn-default">'.$this->l('Confirm!').'</a>'
            );
        }

        // Warning message about products without dimensions and/or weight.
        $output .= $this->adminDisplayWarning($this->l('In case some of your products do not have dimensions and/or weight specified, no quotation will be made unless you select a customized dimension and weight option in the module configuration.'));

        /* If values have been submitted in the form, process. */
        if (Tools::isSubmit('update_lists')) {
            $this->loadShipitCommunes(true);

            foreach (ShipitLists::services() as $service) {
                if ($carrier = $this->addCarrier($service)) {
                    $this->addZones($carrier);
                    $this->addGroups($carrier);
                    $this->addRanges($carrier);
                }
            }

            $output .= $this->displayConfirmation($this->l('Services & Communes list were successfully updated'));
        } elseif (((bool)Tools::isSubmit('submitRg_ShipitCredentials')) == true) {
            if (!$error = $this->validateCredentialsForm()) {
                $val = $this->getCredentialsFormValues();
                $this->loadShipitCommunes();

                if (!count(ShipitLists::cities())) {
                    $output .= $this->displayError($this->l('Module could not connect with Shipit services using the configured credentials.'));
                } else {
                    foreach ($val as $k => $v) {
                        Configuration::updateValue($k, $v);
                    }

                    foreach (ShipitLists::services() as $service) {
                        if ($carrier = $this->addCarrier($service)) {
                            $this->addZones($carrier);
                            $this->addGroups($carrier);
                            $this->addRanges($carrier);
                        }
                    }

                    $output .= $this->displayConfirmation($this->l('The credentials were successfully validated'));
                }
            } else {
                $output .= $this->displayError($error);
            }
        } elseif (((bool)Tools::isSubmit('submitRg_ShipitNormalize')) == true) {
            ShipitTools::normalizeAddresses();
            if (Configuration::get('SHIPIT_NORMALIZED') == true) {
                $output .= $this->displayConfirmation($this->l('All addresses were normalized successfully'));
            }
        } elseif (((bool)Tools::isSubmit('submitRg_ShipitConfig')) == true) {
            if (!$error = $this->validateConfigForm()) {
                $val = $this->getConfigFormValues();

                foreach ($val as $k => $v) {
                    Configuration::updateValue($k, $v);
                }

                $output .= $this->displayConfirmation($this->l('Configuration updated successfully'));
            } else {
                $output .= $this->displayError($error);
            }
        }

        $this->context->smarty->assign($this->name, array(
            '_path' => $this->_path,
            'displayName' => $this->displayName,
            'description' => $this->description,
            'version' => $this->version,
            'author' => $this->author,
            'author_link' => $this->author_link,
            'secure_key' => $this->secure_key,
            '_url' => Tools::getShopDomainSsl(true).$this->_path
        ));

        $output .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure-top.tpl');

        if (!Configuration::get('SHIPIT_EMAIL') || !Configuration::get('SHIPIT_TOKEN')) {
            $output .= $this->renderCredentialsForm();
        } elseif (Configuration::get('SHIPIT_NORMALIZED') == false) {
            $output .= $this->renderNormalizeForm();
        } else {
            $output .= $this->renderConfigForm();
        }

        $output .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure-bottom.tpl');

        return $output;
    }

    /**
     * Config form that will be displayed in the configuration
     */
    protected function renderConfigForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = (int)Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitRg_ShipitConfig';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for the inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        $ps_15 = version_compare(_PS_VERSION_, '1.6.0.0', '<');

        return $helper->generateForm(array(
            array(
                'form' => array(
                    'legend' => array(
                        'title' => $this->l('Integration enviroment and updates'),
                        'icon' => 'icon-cogs'
                    ),
                    'input' => array(
                        array(
                            'type' => 'text',
                            'label' => $this->l('Shipit email'),
                            'name' => 'SHIPIT_EMAIL',
                            'class' => 'fixed-width-xxl',
                            'required' => true
                        ),
                        array(
                            'type' => 'text',
                            'label' => $this->l('Shipit integration token'),
                            'name' => 'SHIPIT_TOKEN',
                            'class' => 'fixed-width-xxl',
                            'required' => true
                        ),
                        array(
                            'type' => ($ps_15 ? 'radio' : 'switch'),
                            'label' => $this->l('My e-commerce is validated and in ready to sell production mode'),
                            'name' => 'SHIPIT_LIVE_MODE',
                            'class' => 't',
                            'is_bool' => true,
                            'values' => array(
                                array(
                                    'id' => 'active_on',
                                    'value' => 1,
                                    'label' => $this->l('Enabled')
                                ),
                                array(
                                    'id' => 'active_off',
                                    'value' => 0,
                                    'label' => $this->l('Disabled')
                                )
                            ),
                            ($ps_15 ? 'desc' : 'hint') => $this->l('We recommend to active this option only when all other fields are correctly configured.')
                        ),
                    ),
                    'submit' => array(
                        'title' => $this->l('Save')
                    ),
                    'buttons' => array(
                        'update_lists' => array(
                            'name' => 'update_lists',
                            'type' => 'submit',
                            'title' => $this->l('Refresh Services & Communes List'),
                            'icon' => 'process-icon-refresh'
                        )
                    )
                )
            ),
            array(
                'form' => array(
                    'legend' => array(
                        'title' => $this->l('Basic options'),
                        'icon' => 'icon-cogs'
                    ),
                    'input' => array(
                        array(
                            'type' => ($ps_15 ? 'radio' : 'switch'),
                            'label' => $this->l('Quote real-time dispatch prices using Shipit'),
                            'name' => 'SHIPIT_ACTIVE_QUOTATION',
                            'class' => 't',
                            'is_bool' => true,
                            'values' => array(
                                array(
                                    'id' => 'active_on',
                                    'value' => 1,
                                    'label' => $this->l('Enabled')
                                ),
                                array(
                                    'id' => 'active_off',
                                    'value' => 0,
                                    'label' => $this->l('Disabled')
                                )
                            ),
                            ($ps_15 ? 'desc' : 'hint') => $this->l('If activated, prices will be calculated in real time, otherwise you will define prices manually (recommended).')
                        ),
                        array(
                            'type' => ($ps_15 ? 'radio' : 'switch'),
                            'label' => $this->l('I want to create my dispatches on the Shipit platform'),
                            'name' => 'SHIPIT_ACTIVE_GENERATION',
                            'class' => 't',
                            'is_bool' => true,
                            'values' => array(
                                array(
                                    'id' => 'active_on',
                                    'value' => 1,
                                    'label' => $this->l('Enabled')
                                ),
                                array(
                                    'id' => 'active_off',
                                    'value' => 0,
                                    'label' => $this->l('Disabled')
                                )
                            ),
                            ($ps_15 ? 'desc' : 'hint') => $this->l('If activated, the order will be synchronized in real time with Shipit (recommended).')
                        ),
                        array(
                            'type' => ($ps_15 ? 'radio' : 'switch'),
                            'label' => $this->l('I wish to receive notifications of status updates and tracking of my packages from the Shipit platform'),
                            'name' => 'SHIPIT_ACTIVE_UPDATE',
                            'class' => 't',
                            'is_bool' => true,
                            'values' => array(
                                array(
                                    'id' => 'active_on',
                                    'value' => 1,
                                    'label' => $this->l('Enabled')
                                ),
                                array(
                                    'id' => 'active_off',
                                    'value' => 0,
                                    'label' => $this->l('Disabled')
                                )
                            ),
                            ($ps_15 ? 'desc' : 'hint') => $this->l('If activated, your notifications and order tracking will be synchronized in real time with Shipit (recommended).')
                        )
                    ),
                    'submit' => array(
                        'title' => $this->l('Save')
                    )
                )
            ),
            array(
                'form' => array(
                    'legend' => array(
                        'title' => $this->l('Default weight and dimension of packages'),
                        'icon' => 'icon-cogs'
                    ),
                    'input' => array(
                        array(
                            'type' => 'select',
                            'label' => $this->l('Package type'),
                            'name' => 'SHIPIT_PACKAGE',
                            'class' => 'fixed-width-xxl',
                            'options' => array(
                                'query' => array(
                                    array('id' => 'Sin empaque', 'name' => $this->l('None')),
                                    array('id' => 'Caja de Cartón', 'name' => $this->l('Paperboard')),
                                    array('id' => 'Film Plástico', 'name' => $this->l('Plastic')),
                                    array('id' => 'Caja + Burbuja', 'name' => $this->l('Box + Burble')),
                                    array('id' => 'Papel Kraft', 'name' => $this->l('Kraft')),
                                    array('id' => 'Bolsa Courier + Burbuja', 'name' => $this->l('Courier Bag + Burble')),
                                    array('id' => 'Bolsa Courier', 'name' => $this->l('Courier Bag'))
                                ),
                                'id' => 'id',
                                'name' => 'name'
                            )
                        ),
                        array(
                            'type' => 'select',
                            'label' => $this->l('Estimation mode'),
                            'name' => 'SHIPIT_ESTIMATION_MODE',
                            'class' => 'fixed-width-xxl',
                            'options' => array(
                                'query' => array(
                                    /* array('id' => 1, 'name' => $this->l('Based on weight')), */
                                    array('id' => 2, 'name' => $this->l('Based on weight and cubic volume')),
                                    array('id' => 3, 'name' => $this->l('Based on weight and a volume generated using an efficient algorithm for 3D rectangular box packing'))
                                ),
                                'id' => 'id',
                                'name' => 'name'
                            ),
                            ($ps_15 ? 'desc' : 'hint') => $this->l('Method with which the shipping cost is estimated, highly recommended 3D method.')
                        ),
                        array(
                            'type' => 'select',
                            'label' => $this->l('Set product dimensions'),
                            'name' => 'SHIPIT_SET_DIMENSIONS',
                            'class' => 'fixed-width-xxl',
                            'options' => array(
                                'query' => array(
                                    array('id' => 0, 'name' => $this->l('No')),
                                    array('id' => 1, 'name' => $this->l('Yes, set the specified dimensions')),
                                    array('id' => 2, 'name' => $this->l('Yes, when the product dimension is missing or not set')),
                                    array('id' => 3, 'name' => $this->l('Yes, when the product dimension is less than specified')),
                                    array('id' => 4, 'name' => $this->l('Yes, when the product dimension is greater than specified'))
                                ),
                                'id' => 'id',
                                'name' => 'name'
                            ),
                            ($ps_15 ? 'desc' : 'hint') => $this->l('Set a predefined dimensions to your products at the time of quotation. Leave blank or "0" to omit.')
                        ),
                        array(
                            'type' => 'text',
                            'name' => ($name = 'SHIPIT_SET_VALUE_WIDTH'),
                            'class' => 'input fixed-width-md',
                            'form_group_class' => $name,
                            'prefix' => $this->l('width'),
                            'suffix' => Configuration::get('PS_DIMENSION_UNIT')
                        ),
                        array(
                            'type' => 'text',
                            'name' => ($name = 'SHIPIT_SET_VALUE_HEIGHT'),
                            'class' => 'input fixed-width-md',
                            'form_group_class' => $name,
                            'prefix' => $this->l('height'),
                            'suffix' => Configuration::get('PS_DIMENSION_UNIT')
                        ),
                        array(
                            'type' => 'text',
                            'name' => ($name = 'SHIPIT_SET_VALUE_DEPTH'),
                            'class' => 'input fixed-width-md',
                            'form_group_class' => $name,
                            'prefix' => $this->l('depth'),
                            'suffix' => Configuration::get('PS_DIMENSION_UNIT')
                        ),
                        array(
                            'type' => 'select',
                            'label' => $this->l('Set product weight'),
                            'name' => 'SHIPIT_SET_WEIGHT',
                            'class' => 'fixed-width-xxl',
                            'options' => array(
                                'query' => array(
                                    array('id' => 0, 'name' => $this->l('No')),
                                    array('id' => 1, 'name' => $this->l('Yes, set the specified weight')),
                                    array('id' => 2, 'name' => $this->l('Yes, when the product weight is missing or not set')),
                                    array('id' => 3, 'name' => $this->l('Yes, when the product weight is less than specified')),
                                    array('id' => 4, 'name' => $this->l('Yes, when the product weight is greater than specified'))
                                ),
                                'id' => 'id',
                                'name' => 'name'
                            ),
                            ($ps_15 ? 'desc' : 'hint') => $this->l('Set a predefined weight to your products at the time of quotation.')
                        ),
                        array(
                            'type' => 'text',
                            'name' => ($name = 'SHIPIT_SET_VALUE_WEIGHT'),
                            'class' => 'input fixed-width-md',
                            'form_group_class' => $name,
                            'suffix' => Configuration::get('PS_WEIGHT_UNIT')
                        )
                    ),
                    'submit' => array(
                        'title' => $this->l('Save')
                    )
                )
            ),
            array(
                'form' => array(
                    'legend' => array(
                        'title' => $this->l('Advanced options'),
                        'icon' => 'icon-cogs'
                    ),
                    'input' => array(
                        array(
                            'type' => 'select',
                            'label' => $this->l('Dispatch price algorithm'),
                            'name' => 'SHIPIT_DISPATCH_ALGORITHM',
                            'class' => 'fixed-width-xxl',
                            'options' => array(
                                'query' => array(
                                    array('id' => 1, 'name' => $this->l('Shipit Algorithm: Select the cheapest, among the fastest')),
                                    array('id' => 0, 'name' => $this->l('Couriers'))
                                ),
                                'id' => 'id',
                                'name' => 'name'
                            ),
                            ($ps_15 ? 'desc' : 'hint') => $this->l('Calculation method of the best dispatch price.')
                        ),
                        array(
                            'type' => ($ps_15 ? 'radio' : 'switch'),
                            'label' => $this->l('Quote only on checkout page'),
                            'name' => 'SHIPIT_ONLY_CHECKOUT',
                            'class' => 't',
                            'is_bool' => true,
                            'values' => array(
                                array(
                                    'id' => 'active_on',
                                    'value' => 1,
                                    'label' => $this->l('Enabled')
                                ),
                                array(
                                    'id' => 'active_off',
                                    'value' => 0,
                                    'label' => $this->l('Disabled')
                                )
                            ),
                            ($ps_15 ? 'desc' : 'hint') => $this->l('If enabled, shop navigation will be faster avoiding unnecessary quotations (recommended).')
                        ),
                        array(
                            'type' => 'select',
                            'label' => $this->l('Profit margin over shipment'),
                            'name' => 'SHIPIT_IMPACT_PRICE',
                            'class' => 'fixed-width-xxl',
                            'options' => array(
                                'query' => array(
                                    array('id' => 0, 'name' => $this->l('No')),
                                    array('id' => 1, 'name' => $this->l('Increase (Percent)')),
                                    array('id' => 2, 'name' => $this->l('Increase (Amount)'))
                                ),
                                'id' => 'id',
                                'name' => 'name'
                            ),
                            ($ps_15 ? 'desc' : 'hint') => $this->l('Use this in case you need a small adjustment in the shipping cost.')
                        ),
                        array(
                            'type' => 'text',
                            'name' => ($name = 'SHIPIT_IMPACT_PRICE_AMOUNT'),
                            'class' => 'input fixed-width-md',
                            'form_group_class' => $name,
                            'suffix' => '%'
                        )
                    ),
                    'submit' => array(
                        'title' => $this->l('Save')
                    )
                )
            )
        ));
    }

    /**
     * Values for the inputs
     */
    protected function getConfigFormValues()
    {
        return array(
            ($name = 'SHIPIT_EMAIL') => trim(Tools::getValue($name, Configuration::get($name))),
            ($name = 'SHIPIT_TOKEN') => trim(Tools::getValue($name, Configuration::get($name))),
            ($name = 'SHIPIT_LIVE_MODE') => (int)(bool)Tools::getValue($name, Configuration::get($name)),
            ($name = 'SHIPIT_ACTIVE_QUOTATION') => (int)(bool)Tools::getValue($name, Configuration::get($name)),
            ($name = 'SHIPIT_ACTIVE_GENERATION') => (int)(bool)Tools::getValue($name, Configuration::get($name)),
            ($name = 'SHIPIT_ACTIVE_UPDATE') => (int)(bool)Tools::getValue($name, Configuration::get($name)),
            ($name = 'SHIPIT_PACKAGE') => trim(Tools::getValue($name, Configuration::get($name))),
            ($name = 'SHIPIT_ESTIMATION_MODE') => (int)Tools::getValue($name, Configuration::get($name)),
            ($name = 'SHIPIT_SET_DIMENSIONS') => (int)Tools::getValue($name, Configuration::get($name)),
            ($name = 'SHIPIT_SET_VALUE_WIDTH') => (float)Tools::getValue($name, Configuration::get($name)),
            ($name = 'SHIPIT_SET_VALUE_HEIGHT') => (float)Tools::getValue($name, Configuration::get($name)),
            ($name = 'SHIPIT_SET_VALUE_DEPTH') => (float)Tools::getValue($name, Configuration::get($name)),
            ($name = 'SHIPIT_SET_WEIGHT') => (int)Tools::getValue($name, Configuration::get($name)),
            ($name = 'SHIPIT_SET_VALUE_WEIGHT') => (float)Tools::getValue($name, Configuration::get($name)),
            ($name = 'SHIPIT_DISPATCH_ALGORITHM') => (int)Tools::getValue($name, Configuration::get($name)),
            ($name = 'SHIPIT_ONLY_CHECKOUT') => (int)(bool)Tools::getValue($name, Configuration::get($name)),
            ($name = 'SHIPIT_IMPACT_PRICE') => (int)Tools::getValue($name, Configuration::get($name)),
            ($name = 'SHIPIT_IMPACT_PRICE_AMOUNT') => (float)Tools::getValue($name, Configuration::get($name)),
        );
    }

    private function validateConfigForm()
    {
        $val = $this->getConfigFormValues();

        // Validate email.
        if (!$val['SHIPIT_EMAIL'] || !Validate::isEmail($val['SHIPIT_EMAIL'])) {
            return $this->l('Shipit email').': '.$this->l('must be a valid email address');
        }

        // Validate token.
        if (!$val['SHIPIT_TOKEN'] || !Validate::isString($val['SHIPIT_TOKEN'])) {
            return $this->l('Shipit integration token').': '.$this->l('must be a valid string');
        }

        // Validate product dimensions.
        if ($val['SHIPIT_SET_DIMENSIONS']) {
            if (($val['SHIPIT_SET_VALUE_WIDTH'] == 0) &&
                ($val['SHIPIT_SET_VALUE_HEIGHT'] == 0) &&
                ($val['SHIPIT_SET_VALUE_DEPTH'] == 0)
            ) {
                return $this->l('Set product dimensions').': '.$this->l('you must set at least one product dimension');
            }

            if (($val['SHIPIT_SET_VALUE_WIDTH'] < 0) ||
                ($val['SHIPIT_SET_VALUE_HEIGHT'] < 0) ||
                ($val['SHIPIT_SET_VALUE_DEPTH'] < 0)
            ) {
                return $this->l('Set product dimensions').': '.$this->l('must be a positive integer/float number');
            }
        }

        // Validate product weight.
        if ($val['SHIPIT_SET_WEIGHT'] && ($val['SHIPIT_SET_VALUE_WEIGHT'] <= 0)) {
            return $this->l('Set product weight').': '.$this->l('must be a positive integer/float number');
        }

        // Validate impact on price.
        if ($val['SHIPIT_IMPACT_PRICE'] && ($val['SHIPIT_IMPACT_PRICE_AMOUNT'] <= 0)) {
            return $this->l('Profit margin over shipment').': '.$this->l('must be a positive integer/float number');
        }

        return false;
    }

    /**
     * Normalization form that will be displayed in the configuration
     */
    protected function renderNormalizeForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = (int)Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitRg_ShipitNormalize';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => array(), /* Values for the inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        $ps_15 = version_compare(_PS_VERSION_, '1.6.0.0', '<');
        $normalize_addresses = Configuration::get('SHIPIT_NORMALIZE_ADDRESSES');

        // Structure of the form.
        if ($normalize_addresses) {
            $normalize_addresses = ShipitTools::getAllChileAddresses($normalize_addresses);
            $total = count($normalize_addresses);
            $normalize_addresses = array_slice($normalize_addresses, 0, 20);
            $admin_address_link = $this->context->link->getAdminLink('AdminAddresses', true).'&token='.Tools::getAdminTokenLite('AdminAddresses');
            $links = '';
            foreach ($normalize_addresses as $address) {
                $links .= '<a class="btn btn-default" href="'.$admin_address_link.'&id_address='.$address['id_address'].'&updateaddress" target="_blank">'.$this->l('ID Address').' '.$address['id_address'].', "<strong>'.$address['city'].'</strong>" <i class="icon-external-link"></i></a><br /><br />';
            }

            $form = array(
                'form' => array(
                    'legend' => array(
                        'title' => $this->l('Normalize Addresses').' ('.$total.')',
                        'icon' => 'icon-refresh'
                    ),
                    ($ps_15 ? 'description' : 'warning') => $this->l('Some addresses could not be normalized, therefore, you need to manually update the city of these addresses, you can edit one by one by clicking on the links below.').'<br /><br />'.$this->l('Once completing the changes, you must press again the button "Normalize Addresses".'),
                    'input' => array(
                        array(
                            'type' => 'html',
                            'name' => 'html_data',
                            ($ps_15 ? 'desc' : 'html_content') => $links
                        )
                    ),
                    'submit' => array(
                        'title' => $this->l('Normalize Addresses'),
                        'icon' => 'process-icon-refresh'
                    )
                )
            );
        } else {
            $form = array(
                'form' => array(
                    'legend' => array(
                        'title' => $this->l('Normalize Addresses'),
                        'icon' => 'icon-refresh'
                    ),
                    ($ps_15 ? 'description' : 'warning') => $this->l('Before using this module, it is necessary to normalize all the names of the cities of each customer address that currently are already registered and belong to the country of Chile, to do this, just press the button "Normalize addresses". This process can take several minutes, depending on the number of addresses currently registered.').'<br /><br />'.$this->l('Before continuing, it is highly recommended to do a full backup of the site because changes are not reversible.'),
                    'submit' => array(
                        'title' => $this->l('Normalize Addresses'),
                        'icon' => 'process-icon-refresh'
                    )
                ),
            );
        }

        return $helper->generateForm(array($form));
    }

    /**
     * Form for credentials that will be displayed in the configuration
     */
    protected function renderCredentialsForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = (int)Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitRg_ShipitCredentials';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getCredentialsFormValues(), /* Add values for the inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array(array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Access data'),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Shipit email'),
                        'name' => 'SHIPIT_EMAIL',
                        'class' => 'fixed-width-xxl',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Shipit integration token'),
                        'name' => 'SHIPIT_TOKEN',
                        'class' => 'fixed-width-xxl',
                        'required' => true
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Save')
                )
            )
        )));
    }

    /**
     * Values for the inputs
     */
    protected function getCredentialsFormValues()
    {
        return array(
            ($name = 'SHIPIT_EMAIL') => trim(Tools::getValue($name, Configuration::get($name))),
            ($name = 'SHIPIT_TOKEN') => trim(Tools::getValue($name, Configuration::get($name))),
        );
    }

    private function validateCredentialsForm()
    {
        $val = $this->getCredentialsFormValues();

        // Validate email.
        if (!$val['SHIPIT_EMAIL'] || !Validate::isEmail($val['SHIPIT_EMAIL'])) {
            return $this->l('Shipit email').': '.$this->l('must be a valid email address');
        }

        // Validate token.
        if (!$val['SHIPIT_TOKEN'] || !Validate::isString($val['SHIPIT_TOKEN'])) {
            return $this->l('Shipit integration token').': '.$this->l('must be a valid string');
        }

        return false;
    }

    public function hookDisplayAdminOrder($params)
    {
        if ($this->config['SHIPIT_ACTIVE_GENERATION']) {
            $id_order = (int)$params['id_order'];
            $log_url = Tools::getShopDomainSsl(true).$this->_path.'error_log';
            $admin_order_link = $this->context->link->getAdminLink('AdminOrders').'&vieworder&id_order='.(int)$params['id_order'];

            if (isset($this->context->cookie->rg_shipit_conf)) {
                $this->context->controller->confirmations[] = $this->context->cookie->rg_shipit_conf;
                unset($this->context->cookie->rg_shipit_conf);
            }

            if (ShipitShipment::isShipitCarrierByIdOrder((int)$id_order)) {
                $error = $shipit_id = false;

                if (Tools::isSubmit('submitGenerateShipit')) {
                    $order = new Order((int)$id_order);
                    $address = new Address((int)$order->id_address_delivery);
                    $customer = new Customer((int)$order->id_customer);
                    $request_params = array();
                    $request_params['package'] = array();
                    $request_params['package']['reference'] = (int)$order->id;
                    $request_params['package']['full_name'] = $address->firstname.' '.$address->lastname;
                    $request_params['package']['email'] = $customer->email;
                    $request_params['package']['items_count'] = 1;
                    $request_params['package']['cellphone'] = ($address->phone_mobile ? $address->phone_mobile : $address->phone);
                    $request_params['package']['is_payable'] = false;
                    $request_params['package']['packing'] = $this->config['SHIPIT_PACKAGE'];
                    $request_params['package']['shipping_type'] = 'Normal';
                    $request_params['package']['destiny'] = 'Domicilio';
                    $service = ShipitServices::getByReference((int)$order->id_carrier);
                    $request_params['package']['courier_for_client'] = ($service->code == 'shipit' ? null : $service->code);
                    $cache = new ShipitCache((int)$order->id_cart);
                    $request_params['package']['approx_size'] = ShipitAPI::getPackageSize((float)$cache->package['width'], (float)$cache->package['height'], (float)$cache->package['depth']);
                    $request_params['package']['address_attributes'] = array();
                    $dest_code = ShipitLists::searchCityId($address->city);
                    $request_params['package']['address_attributes']['commune_id'] = $dest_code;
                    $request_params['package']['address_attributes']['street'] = $address->address1.($address->address2 ? ' '.$address->address2 : '');
                    $request_params['package']['address_attributes']['number'] = '0';
                    $request_params['package']['address_attributes']['complement'] = ($address->city ? $address->city : '').($address->other ? ' '.$address->other : '');

                    $api = new ShipitAPI($this->config['SHIPIT_EMAIL'], $this->config['SHIPIT_TOKEN'], (int)!$this->config['SHIPIT_LIVE_MODE']);
                    $errors = array();
                    $shipit_id = $api->generateShipment($request_params, $errors);

                    if ($shipit_id) {
                        $shipment = new ShipitShipment();
                        $shipment->shipit_id = (int)$shipit_id;
                        $shipment->id_order = (int)$id_order;
                        $shipment->courier = $service->desc;
                        $shipment->packing = pSQL($this->config['SHIPIT_PACKAGE']);
                        $shipment->add();

                        $this->context->cookie->rg_shipit_conf = $this->l('The shipment was successfully generated.');
                        Tools::redirectAdmin($admin_order_link);
                    } else {
                        $error = $this->l('Errors generating service. Check log file for more information.').' '.$this->l('You can check log file at:').' <a target="_blank" href="'.$log_url.'">'.$log_url.'</a>';
                        $this->context->controller->errors[] = $error;

                        if ($errors) {
                            ShipitTools::log('PrestaShop ('._PS_VERSION_.'), error: '.print_r($errors, true));
                        }
                    }
                }

                echo '<div class="row" id="'.$this->name.'">'.$this->renderOrderForm((int)$id_order, $error).'</div>';
            }
        }
    }

    public function hookDisplayCarrierExtraContent($params)
    {
        if ($params['carrier']['logo']) {
            $this->context->smarty->assign($this->name, array(
                'id_carrier' => (int)$params['carrier']['id']
            ));

            return $this->display(__FILE__, 'views/templates/hook/display_carrier_extra_content.tpl');
        }
    }

    private function renderOrderForm($id_order, $error = false)
    {
        $id_rg_shipit_shipment = ShipitShipment::getIdShipitByIdOrder((int)$id_order);
        $order = new Order((int)$id_order);
        $submit_action = '';
        $fields_value = array();

        if (!$id_rg_shipit_shipment) {
            $service = ShipitServices::getByReference((int)$order->id_carrier);

            if (!$service) {
                $fields_form = array(
                    'form' => array(
                        'legend' => array(
                            'title' => $this->l('Shipit Shipment'),
                            'image' => $this->_path.'logo.gif'),
                        (version_compare(_PS_VERSION_, '1.6', '<') ? 'description' : 'warning') => $this->l('The information for this order was deleted or does not exist.'),
                    ),
                );
            } else {
                $submit_action = 'submitGenerateShipit';

                if (!$service->desc) {
                    $service->desc = $this->l('None');
                }

                $fields_value = array(
                    'courier' => $service->desc
                );
                $fields_form = array(
                    'form' => array(
                        'legend' => array(
                            'title' => $this->l('Shipit Shipment'),
                            'image' => $this->_path.'logo.gif'),
                        'error' => $error,
                        'input' => array(
                            array(
                                'type' => 'text',
                                'label' => $this->l('Courier'),
                                'name' => 'courier',
                                'readonly' => true
                            )
                        ),
                        'submit' => array(
                            'title' => $this->l('Generate Shipment'),
                            'icon' => 'icon-truck'
                        )
                    ),
                );
            }
        } else {
            $shipit_shipment = new ShipitShipment((int)$id_rg_shipit_shipment);
            $fields_value = array(
                'shipit_id' => $shipit_shipment->shipit_id,
                'tracking' => $shipit_shipment->tracking,
                'courier' => $shipit_shipment->courier,
                'packing' => $shipit_shipment->packing,
                'status' => (isset($this->available_status[$shipit_shipment->status]) ? $this->available_status[$shipit_shipment->status] : $this->l('Other')),
                'date_upd' => Tools::displayDate($shipit_shipment->date_upd, null, true)
            );

            $fields_form = array(
                'form' => array(
                    'legend' => array(
                        'title' => $this->l('Shipit Shipment'),
                        'image' => $this->_path.'logo.gif'),
                    'error' => $error,
                    'input' => array(
                        array(
                            'type' => 'text',
                            'label' => $this->l('Shipit ID'),
                            'name' => 'shipit_id',
                            'readonly' => true
                        ),
                        array(
                            'type' => 'text',
                            'label' => $this->l('Shipment Status'),
                            'name' => 'status',
                            'readonly' => true
                        ),
                        array(
                            'type' => 'text',
                            'label' => $this->l('Courier'),
                            'name' => 'courier',
                            'readonly' => true
                        ),
                        array(
                            'type' => 'text',
                            'label' => $this->l('Packing'),
                            'name' => 'packing',
                            'readonly' => true
                        ),
                        array(
                            'type' => 'text',
                            'label' => $this->l('Tacking Number'),
                            'name' => 'tracking',
                            'readonly' => true
                        ),
                        array(
                            'type' => 'text',
                            'label' => $this->l('Last Shipment Update'),
                            'name' => 'date_upd',
                            'readonly' => true
                        ),
                    ),
                )
            );
        }

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = (int)Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->identifier = $this->identifier;
        $helper->submit_action = $submit_action;
        $helper->tpl_vars = array(
            'fields_value' => $fields_value,
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
            'name_controller' => 'col-lg-7'
        );

        return $helper->generateForm(array($fields_form));
    }

    private function loadShipitCommunes($refresh = false)
    {
        if ($refresh || !count(ShipitLists::cities())) {
            $api = new ShipitAPI(
                Tools::getValue('SHIPIT_EMAIL', $this->config['SHIPIT_EMAIL']),
                Tools::getValue('SHIPIT_TOKEN', $this->config['SHIPIT_TOKEN'])
            );
            $errors = array();
            $communes = $api->getCommunesList($errors);

            if ($communes) {
                if ($refresh) {
                    Db::getInstance()->execute('TRUNCATE `'._DB_PREFIX_.'rg_shipit_commune`');
                }

                $data = array();
                foreach ($communes as $com) {
                    $data[] = array('id' => (int)$com->id, 'name' => ucwords(Tools::strtolower(pSQL($com->name))));
                }
                Db::getInstance()->insert('rg_shipit_commune', $data);
            } elseif ($errors) {
                ShipitTools::log('PrestaShop ('._PS_VERSION_.'), error: '.print_r($errors, true));
            }
        }
    }

    public function getOrderShippingCost($params, $shipping_cost)
    {
        if (!$this->config['SHIPIT_ACTIVE_QUOTATION']) {
            return $shipping_cost;
        }

        $page_name = false;

        if ($this->context->controller->controller_type == 'front') {
            if (version_compare(_PS_VERSION_, '1.7.0.0', '>=') === true) {
                $page_name = $this->context->controller->getPageName();
            } elseif (method_exists($this->context->smarty, 'getTemplateVars')) {
                $page_name = $this->context->smarty->getTemplateVars('page_name');
            }
        }

        // Check if module is active.
        if (!$this->active ||
            // Check if credentials are setted.
            (!$this->config['SHIPIT_EMAIL'] || !$this->config['SHIPIT_TOKEN']) ||
            // Check if there is a valid id_carrier.
            !$this->id_carrier ||
            // Check if running in the Back Office and the employee is logged.
            ($this->context->controller->controller_type == 'admin' && $this->context->employee->id == false) ||
            // Check if running in the Front Office.
            ($this->context->controller->controller_type == 'front' && (
                // If customer is logged.
                $this->context->customer->logged == false || (
                    // If quote on checkout only is enabled.
                    $this->config['SHIPIT_ONLY_CHECKOUT'] &&
                    !in_array($page_name, array('order', 'order-opc', 'checkout')) &&
                    Tools::substr($page_name, 0, 7) != 'module-'
                )
            )) ||
            // Check if there is an active address delivery.
            (!isset($params->id_address_delivery) || !$params->id_address_delivery)
        ) {
            return false;
        }

        $cache = new ShipitCache((int)$params->id);
        $hash_cart = ShipitTools::getHashCart((int)$params->id, $this->config);

        // Check if it has not yet been cached.
        if ($cache->hash_cart != $hash_cart ||
            (!$cache->hasValidCarriers() && !isset($this->context->shipit_cached))
        ) {
            $this->context->shipit_cached = true;
            if (!$this->updateCache($params, $hash_cart)) {
                return false;
            } else {
                $cache = new ShipitCache((int)$params->id);
            }
        }

        if ($cache->carriers && isset($cache->carriers[$this->id_carrier]) && $cache->carriers[$this->id_carrier]) {
            $cost = Tools::convertPriceFull(
                $cache->carriers[$this->id_carrier],
                new Currency($this->config['chile_id_currency']),
                new Currency((int)$params->id_currency)
            );

            return $cost;
        }

        return false;
    }

    public function getOrderShippingCostExternal($params)
    {
        return $this->getOrderShippingCost($params, null);
    }

    protected function addCarrier(array $service)
    {
        if (!ShipitServices::getByCode($service['code'])) {
            $carrier = new Carrier();
            $carrier->name = pSQL($service['desc']);
            $carrier->url = $service['tracking_url'];
            $carrier->is_module = true;
            $carrier->active = (int)$service['active'];
            $carrier->range_behavior = 0;
            $carrier->need_range = 1;
            $carrier->shipping_external = true;
            $carrier->external_module_name = $this->name;
            $carrier->shipping_method = 1;
            $carrier->grade = 2;

            foreach (Language::getLanguages() as $lang) {
                $carrier->delay[$lang['id_lang']] = $service['desc'];
            }

            if ($carrier->add()) {
                if ($service['code'] == 'shipit' || Validate::isAbsoluteUrl($service['image'])) {
                    @copy($service['image'], _PS_SHIP_IMG_DIR_.'/'.(int)$carrier->id.'.jpg');
                }

                $services = new ShipitServices();
                $services->code = $service['code'];
                $services->desc = $service['desc'];
                $services->id_reference = (int)$carrier->id;
                $services->add();

                return $carrier;
            }
        }

        return false;
    }

    protected function addGroups($carrier)
    {
        $groups_ids = array();
        $groups = Group::getGroups(Context::getContext()->language->id);
        foreach ($groups as $group) {
            $groups_ids[] = $group['id_group'];
        }

        if (version_compare(_PS_VERSION_, '1.5.5.0', '>=') === true) {
            $carrier->setGroups($groups_ids);
        } else {
            ShipitTools::setGroups($groups_ids, $carrier->id);
        }
    }

    protected function addRanges($carrier)
    {
        $range_weight = new RangeWeight();
        $range_weight->id_carrier = $carrier->id;
        $range_weight->delimiter1 = '0';
        $range_weight->delimiter2 = '999999';
        $range_weight->add();
    }

    protected function addZones($carrier)
    {
        if ($this->config['chile_id_country']) {
            if ($id_zone = Country::getIdZone($this->config['chile_id_country'])) {
                return $carrier->addZone($id_zone);
            }
        }
    }

    private function prepareCommonHook($page_name = false)
    {
        $chile_cities = ShipitLists::cities();
        $secure_key = false;

        if ($this->context->controller->controller_type == 'admin') {
            $secure_key = $this->secure_key;
        }

        $currency = Currency::getCurrencyInstance($this->config['chile_id_currency']);

        if (version_compare(_PS_VERSION_, '1.6.0.0', '>=') === true) {
            return Media::addJsDef(array($this->name => array(
                'secure_key' => $secure_key,
                'page_name' => $page_name,
                'ps_version' => Tools::substr(_PS_VERSION_, 0, 3),
                'cities' => $chile_cities,
                'id_country' => $this->config['chile_id_country'],
                'currency_sign' => $currency->sign.' ('.$currency->iso_code.')',
                'texts' => array(
                    'wrong_city' => $this->l('City name is wrong'),
                    'no_results' => $this->l('No results found for')
                )
            )));
        // Backward compatibility for PrestaShop 1.5!
        } else {
            $this->context->smarty->assign($this->name, array(
                'secure_key' => $secure_key,
                'page_name' => $page_name,
                'ps_version' => Tools::substr(_PS_VERSION_, 0, 3),
                'cities' => Tools::jsonEncode($chile_cities),
                'id_country' => $this->config['chile_id_country'],
                'currency_sign' => $currency->sign.' ('.$currency->iso_code.')',
                'texts' => Tools::jsonEncode(array(
                    'wrong_city' => $this->l('City name is wrong'),
                    'no_results' => $this->l('No results found for')
                ))
            ));

            return $this->display(__FILE__, 'views/templates/hook/backward_compatibility.tpl');
        }
    }

    /**
     * CSS & JavaScript files loaded in the BO
     */
    public function hookDisplayBackOfficeHeader()
    {
        $controller_name = $this->context->controller->controller_name;

        if (($controller_name == 'AdminModules' && Tools::getValue('configure') == $this->name) ||
            ($controller_name == 'AdminAddresses')
        ) {
            if (method_exists($this->context->controller, 'addJquery')) {
                $this->context->controller->addJquery();
            }

            $this->context->controller->addCSS($this->_path.'views/libs/jquery.flexdatalist.css');
            $this->context->controller->addJS($this->_path.'views/libs/jquery.flexdatalist.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
            $this->context->controller->addJS($this->_path.'views/js/back.js');

            return $this->prepareCommonHook();
        }
    }

    /**
     * CSS & JavaScript files loaded on the FO
     */
    public function hookDisplayHeader()
    {
        $page_name = false;

        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=') === true) {
            $page_name = $this->context->controller->getPageName();
        } elseif (method_exists($this->context->smarty, 'getTemplateVars')) {
            $page_name = $this->context->smarty->getTemplateVars('page_name');
        }

        if ($page_name && in_array($page_name, array('authentication', 'order', 'order-opc', 'checkout', 'address'))) {
            if (method_exists($this->context->controller, 'addJquery')) {
                $this->context->controller->addJquery();
            }

            $this->context->controller->addCSS($this->_path.'views/libs/jquery.flexdatalist.css');
            $this->context->controller->addJS($this->_path.'views/libs/jquery.flexdatalist.js');
            $this->context->controller->addJS($this->_path.'views/libs/jquery.waituntilexists.js');
            $this->context->controller->addJS($this->_path.'views/js/front.js');

            return $this->prepareCommonHook($page_name);
        }
    }

    public function hookActionObjectCarrierUpdateAfter($params)
    {
        if (!$this->deleting_from_module) {
            $carrier = Carrier::getCarrierByReference($params['object']->id_reference);

            if (!$carrier) {
                $services = ShipitServices::getByReference($params['object']->id_reference);

                if ($services) {
                    $services->delete();
                }
            }
        }
    }

    public function processWebhook($json)
    {
        if ($this->config['SHIPIT_ACTIVE_UPDATE']) {
            if ($id_rg_shipit_shipment = ShipitShipment::getIdShipitByShipitId((int)$json->id)) {
                $new_data = false;
                $shipment = new ShipitShipment((int)$id_rg_shipit_shipment);
                $tracking = $json->tracking_number;

                if ($tracking && !$shipment->tracking) {
                    $shipment->tracking = pSQL($tracking);
                    $new_data = true;

                    Db::getInstance()->update('orders', array('shipping_number' => pSQL($tracking)), 'id_order='.(int)$shipment->id_order);

                    $order = new Order((int)$shipment->id_order);

                    if ($id_order_carrier = Db::getInstance()->getValue('SELECT MAX(`id_order_carrier`) FROM `'._DB_PREFIX_.'order_carrier` WHERE `id_order` = '.(int)$shipment->id_order)) {
                        $order_carrier = new OrderCarrier((int)$id_order_carrier);
                        $old_tracking = $order_carrier->tracking_number;

                        $order_carrier->tracking_number = pSQL($tracking);
                        if ($order_carrier->update()) {
                            if ($old_tracking != $tracking) {
                                $customer = new Customer((int)$order->id_customer);
                                $carrier = new Carrier((int)$order->id_carrier, $order->id_lang);
                                $email_sent = false;

                                if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
                                    $email_sent = $order_carrier->sendInTransitEmail($order);
                                } else {
                                    $templateVars = array(
                                        '{followup}' => str_replace('@', $tracking, $carrier->url),
                                        '{firstname}' => $customer->firstname,
                                        '{lastname}' => $customer->lastname,
                                        '{id_order}' => $order->id,
                                        '{shipping_number}' => $tracking,
                                        '{order_name}' => $order->getUniqReference()
                                    );
                                    $email_sent = @Mail::Send((int)$order->id_lang, 'in_transit', Mail::l('Package in transit', (int)$order->id_lang), $templateVars, $customer->email, $customer->firstname.' '.$customer->lastname, null, null, null, null, _PS_MAIL_DIR_, true, (int)$order->id_shop);
                                }

                                if ($email_sent) {
                                    Hook::exec('actionAdminOrdersTrackingNumberUpdate', array(
                                        'order' => $order,
                                        'customer' => $customer,
                                        'carrier' => $carrier
                                    ), null, false, true, false, $order->id_shop);
                                } else {
                                    ShipitTools::log('Ha ocurrido un error al enviar el correo de actualizacion de numero de rastreo al cliente.');
                                }
                            }
                        } else {
                            ShipitTools::log('Ha ocurrido un error al intentar actualizar el numero de rastreo del envio.');
                        }
                    }
                }

                if ($shipment->status != $json->status) {
                    $shipment->status = pSQL(Tools::strtolower($json->status));
                    $new_data = true;
                }

                if ($new_data) {
                    $shipment->update();
                }
            }
        }
    }

    /**
     * Get all global settings
     * @return [array] [All global settings]
     */
    private function getGlobalConfig()
    {
        $config_values = $this->getConfigFormValues();
        $config = Configuration::getMultiple(array_keys($config_values));
        $config['SHIPIT_NORMALIZED'] = Configuration::get('SHIPIT_NORMALIZED');
        $config['chile_id_country'] = (int)Country::getByIso('CL');
        $config['chile_id_currency'] = (int)Currency::getIdByIsoCode('CLP');

        return $config;
    }
}
