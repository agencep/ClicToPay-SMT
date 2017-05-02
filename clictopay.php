<?php

/*
 * (c) 2017 ExtrumWeb International <info@extrumweb.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Clictopay extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();
    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;

    public $SMT_ID;
    public $SMT_SANDBOX;


    ////////////////////////////////////////////////////////////////////
    public function __construct()
    {
        $this->name = 'clictopay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'ExtrumWeb International';
        $this->is_eu_compatible = 1;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->bootstrap = true;

        parent::__construct();
        $this->displayName = $this->l('ClicToPay SMT');
        $this->description = $this->l('This module allows you to accept online payments based on the SPS Clictopay SMT.');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    ////////////////////////////////////////////////////////////////////
    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        if (!$this->registrationHook()) {
            return false;
        }

        if (!$this->installOrderState('SMT :: Autorisation acceptée', 'SMT ::  Authorized acceptance', 'accepted', 'LimeGreen', false, false, true, true, true)) {
            return false;
        }

        if (!$this->installOrderState('SMT :: Erreur de paiement', 'SMT :: Payment Error', 'error', '#e31818')) {
            return false;
        }
        if (!$this->installOrderState('SMT :: Paiement refusé', 'SMT :: Payment refused', 'refused', '#f80fa8')) {
            return false;
        }

        if (!$this->installOrderState('SMT :: Paiement annulé', 'SMT :: Payment cancelled', 'canceled', '#f88d0f')) {
            return false;
        }

        if (!$this->installOrderState('Panier en attente de paiement', 'SMT :: Shopping Cart', 'awaiting', 'RoyalBlue')) {
            return false;
        }


        if (!Configuration::updateValue('SMT_SANDBOX', $this->SMT_SANDBOX)
            || !Configuration::updateValue('SMT_ID', $this->SMT_ID)
        ) {
            return false;
        }

        return true;
    }

    ////////////////////////////////////////////////////////////////////
    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }

        if (!Configuration::deleteByName('SMT_SANDBOX')
            || !Configuration::deleteByName('SMT_ID')
        ) {
            return false;
        }

        return true;
    }

    ////////////////////////////////////////////////////////////////////
    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            $smt_id = strval(Tools::getValue('SMT_ID'));
            $smt_sandbox = (int)Tools::getValue('SMT_SANDBOX');

            if (!$smt_id
                || empty($smt_id)
                || !Validate::isGenericName($smt_id)
            )
                $output .= $this->displayError($this->l('Invalid Configuration value'));
            else {
                Configuration::updateValue('SMT_ID', $smt_id);
                Configuration::updateValue('SMT_SANDBOX', $smt_sandbox);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }


        return $output . $this->displayForm();
    }

    ////////////////////////////////////////////////////////////////////
    public function displayForm()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('Terminal Configuration provided by SMT'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Terminal Number'),
                    'name' => 'SMT_ID',
                    'placeholder' => $this->l('Terminal ID provided by SMT'),
                    'size' => 20,
                    'required' => true
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Enable Sandbox'),
                    'name' => 'SMT_SANDBOX',
                    'is_bool' => true,
                    'desc' => $this->l('Enable or disable sandbox mode (test)'),
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Yes')
                        ],
                        [
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('No')
                        ]
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            ]
        ];

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = [
            'save' => [
                [
                    'desc' => $this->l('Save'),
                    'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                        '&token=' . Tools::getAdminTokenLite('AdminModules'),
                ],
                'back' => [
                    'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                    'desc' => $this->l('Back to list')
                ]

            ]
        ];


        // Load current value
        $helper->fields_value['SMT_ID'] = Configuration::get('SMT_ID');
        $helper->fields_value['SMT_SANDBOX'] = (int)Configuration::get('SMT_SANDBOX');

        return $helper->generateForm($fields_form);
    }

    ////////////////////////////////////////////////////////////////////
    private function registrationHook()
    {
        if (!$this->registerHook('paymentOptions')
        ) {
            return false;
        }
        return true;
    }

    ////////////////////////////////////////////////////////////////////
    private function installOrderState($nameFR, $nameEN, $type, $color = 'LimeGreen', $send_email = false, $hidden = false, $delivery = false, $logable = false, $invoice = false)
    {
        $order_state = new OrderState();
        $order_state->name = array();
        $order_state->module_name = $this->name;
        foreach (Language::getLanguages() as $language) {
            if (Tools::strtolower($language['iso_code']) == 'fr') {
                $order_state->name[$language['id_lang']] = $nameFR;
            } else {
                $order_state->name[$language['id_lang']] = $nameEN;
            }
        }
        $order_state->send_email = $send_email;
        $order_state->color = $color;
        $order_state->hidden = $hidden;
        $order_state->delivery = $delivery;
        $order_state->logable = $logable;
        $order_state->invoice = $invoice;
        $order_state->unremovable = true;

        if ($order_state->add()) {
            $source = _PS_MODULE_DIR_ . $this->name . '/views/img/clictopay_' . $type . '.png';
            $destination = _PS_ROOT_DIR_ . '/img/os/' . (int)$order_state->id . '.gif';
            copy($source, $destination);
            Configuration::updateValue('SMT_OS_' . strtoupper($type), (int)$order_state->id);
        }
        return true;
    }

    ////////////////////////////////////////////////////////////////////
    public function hookPaymentOptions($params)
    {
        $payments_options = '';
        $payment_options = new PaymentOption();

        $action_text = $this->l("Pay with SMT");
        $payment_options->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/payments.png'));

        $action_text .= ' | ' . $this->l("It's easy, simple and secure");

        $this->context->smarty->assign(array(
            'path' => $this->_path,
        ));

        $authorized = false;
        foreach (Module::getPaymentModules() as $module)
            if ($module['name'] == 'clictopay') {
                $authorized = true;
                break;
            }

        if (!$authorized)
            die($this->module->l('This payment method is not available.', 'validation'));

        $payment_options->setCallToActionText($action_text)
            ->setAction($this->getUrl())
            ->setInputs([
                'Reference' => [
                    'name' => 'Reference',
                    'type' => 'hidden',
                    'value' => $this->context->cart->id,
                ],
                'Montant' => [
                    'name' => 'Montant',
                    'type' => 'hidden',
                    'value' => sprintf("%.3f", $this->context->cart->getOrderTotal()),
                ],
                'Devise' => [
                    'name' => 'Devise',
                    'type' => 'hidden',
                    'value' => $this->context->currency->iso_code
                ],
                'sid' => [
                    'name' => 'sid',
                    'type' => 'hidden',
                    'value' => md5($this->context->cookie->id_connections),
                ],
                'affilie' => [
                    'name' => 'affilie',
                    'type' => 'hidden',
                    'value' => Configuration::get('SMT_ID'),
                ],

            ])
            ->setAdditionalInformation("<p>You will be redirect to SMT website to make a payment securely</p>");

        $payments_options = [
            $payment_options,
        ];


        return $payments_options;

    }

    ////////////////////////////////////////////////////////////////////
    public function setOrder($status, $cart, $autorisation = false)
    {
        $extra = array();
        if ($autorisation) {
            $extra = array('transaction_id' => $autorisation);
        }
        $this->validateOrder((int)$cart->id, $status, $cart->getOrderTotal(), 'Carte bancaire SMT', '', $extra);
    }

    ////////////////////////////////////////////////////////////////////
    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);
        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    ////////////////////////////////////////////////////////////////////
    public function getUrl()
    {
        if (Configuration::get('SMT_SANDBOX') == 1) {
            return 'https://clictopay.monetiquetunisie.com/clicktopay/';
        } else {
            return 'https://www.smt-sps.com.tn/clicktopay/';
        }
    }

}