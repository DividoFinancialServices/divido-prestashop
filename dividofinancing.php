<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once 'vendor/autoload.php';

class DividoFinancing extends PaymentModule
{

    const
        DIVIDO_STATUS_ACCEPTED = 'ACCEPTED',
        DIVIDO_STATUS_SIGNED = 'SIGNED',
        SHOW_WIDGET_YES = 1,
        SHOW_WIDGET_NO = 0,
        PROD_SEL_ALL = 0,
        PROD_SEL_TRESHOLD = 1,
        PROD_SEL_SELECTED = 2,
        PLANS_ALL = 0,
        PLANS_SELECTED = 1,
        PROD_PLANS_ALL = 0,
        PROD_PLANS_SELECTED = 1,
        CACHE_KEY_PLANS = 'divido_plans',
        CACHE_TTL = 3600;

    public $apiKey;

    public function __construct ()
    {
        $this->name          = 'dividofinancing';
        $this->tab           = 'payments_gateways';
        $this->version       = '1.0';
        $this->author        = 'Divido';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.5', 'max' => '1.6');
        $this->bootstrap     = true;

        parent::__construct();

        $this->displayName      = $this->l('Divido');
        $this->description      = $this->l('Pay in instalments with Divido');
        $this->confirmUninstall = $this->l("Are you sure you want to uninstall Divido? This will remove all Divido data!");

        $this->setup();
    }

    public function setup ()
    {
        if (! $apiKey = $this->getApiKey()) {
            return false;
        }

        Divido::setMerchant($apiKey);
    }

    public function install ()
    {
        Configuration::updateValue('DIVIDO_API_KEY', null);
        Configuration::updateValue('DIVIDO_ENABLED', false);
        Configuration::updateValue('DIVIDO_SHOW_WIDGET', self::SHOW_WIDGET_YES);
        Configuration::updateValue('DIVIDO_CREATE_ORDER_STATUS', self::DIVIDO_STATUS_ACCEPTED);
        Configuration::updateValue('DIVIDO_TITLE', 'Pay in instalments with Divido');
        Configuration::updateValue('DIVIDO_PROD_SELECTION', self::PROD_SEL_ALL);
        Configuration::updateValue('DIVIDO_PRICE_THRESHOLD', 0);
        Configuration::updateValue('DIVIDO_CART_THRESHOLD', 0);
        Configuration::updateValue('DIVIDO_PLANS_OPTION', self::PLANS_ALL);
        Configuration::updateValue('DIVIDO_PLANS', null);

        $parentInstall   = parent::install();
        $dbInstall       = $this->createDb();

        $boHeadHook      = $this->registerHook('backOfficeHeader');
        $boProdHook      = $this->registerHook('displayAdminProductsExtra');
        $boProdSaveHook  = $this->registerHook('actionProductUpdate');
        $boOrderInfoHook = $this->registerHook('displayAdminOrder');
        $foHeadHook      = $this->registerHook('header');
        $foPaymentHook   = $this->registerHook('payment');
        $foProdPriceHook = $this->registerHook('displayProductPriceBlock');

        return $parentInstall && $dbInstall;
    }

    public function uninstall ()
    {
        $dropLookup = sprintf("DROP TABLE IF EXISTS %sdivido_lookup", _DB_PREFIX_);
        $dropProducts = sprintf("DROP TABLE IF EXISTS %sdivido_products", _DB_PREFIX_);
        $dbUninstall = Db::getInstance()->execute($dropLookup) 
            && Db::getInstance()->execute($dropProducts);

        $parentUninstall = parent::uninstall();

        return $parentUninstall && $dbUninstall;
    }

    public function getContent ()
    {
        $output = '';

        if (Tools::isSubmit('submitDividoBackend')) {
            $this->postProcess();
        }

        return $output . $this->displayForm();
    }

    protected function postProcess()
    {
        if (Cache::isStored(self::CACHE_KEY_PLANS)) {
            Cache::clean(self::CACHE_KEY_PLANS);
        }

        $values = Tools::getAllValues();
        foreach ($values as $key => $value) {
            if (substr($key, 0, 7) != 'DIVIDO_') {
                continue;
            }

            if (is_array($value)) {
                $value = implode(',', $value);
            }

            Configuration::updateValue($key, $value);
        }

        $this->confirmation_message = (_PS_VERSION_ < '1.6' ?
            '<div class="conf confirmation">'.$this->l('Settings updated').'</div>':
            $this->displayConfirmation($this->l('Settings updated')));
    }

    public function hookBackOfficeHeader ()
    {
        $this->context->controller->addJS($this->_path . 'views/js/backoffice.js');
    }

    public function hookHeader ()
    {
        if ($scriptUrl = $this->getScriptUrl()) {
            $this->context->controller->addJS($scriptUrl);
        }

        $this->context->controller->addJS($this->_path . 'views/js/frontoffice.js');
        $this->context->controller->addCSS($this->_path . 'views/css/divido.css');
    }

    public function hookPayment ($params)
    {
        if (! $this->isGloballyAvailable($params)) {
            return false;
        }

        $this->smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_divido' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
        ));

        return $this->display(__FILE__, 'payment.tpl');
    }

    public function hookDisplayAdminProductsExtra ($params)
    {
        $prodId = (int)Tools::getValue('id_product');
        if (Validate::isLoadedObject($product = new Product($prodId))) {

            $prodSettings = self::getProdSettings($prodId);

            $availablePlans = array();
            if ($allPlans = $this->getAllPlans()) {
                foreach ($allPlans as $plan) {
                    $availablePlans[$plan->id] = $plan->text;
                }
            }

            $smartyVars = array(
                'allPlans' => $availablePlans,
                'prod_plans_option' => $prodSettings['plans_opt'],
                'prod_plans' => explode(',', $prodSettings['plans']),
            );

            $this->context->smarty->assign($smartyVars);

            return $this->display(__FILE__, 'product.tpl');
        }
    }

    public function hookDisplayAdminOrder ($params)
    {
        xdebug_break();
        $cartId = $params['cart']->id;

        $lookup = $this->getLookup($cartId);
        $data = array(
            'proposal_id'    => $lookup['credit_request_id'],
            'application_id' => $lookup['credit_application_id'],
            'deposit_amount' => $lookup['deposit_amount'],
        );

        $this->context->smarty->assign($data);

        return $this->display(__FILE__, 'order_info.tpl');

    }
    public function hookActionProductUpdate($params)
    {
        $prodId = (int)Tools::getValue('id_product');
        $planIds = Tools::getValue('prod_plans');
        $planOpt = Tools::getValue('prod_plans_option');

        if (is_array($planIds)) {
            $planIds = implode(',', $planIds);
        }

        $data = array(
            'product_id' => $prodId,
            'plans_opt' => $planOpt,
            'plans' => $planIds,
        );

        return Db::getInstance()->insert('divido_products', $data, true, false, Db::ON_DUPLICATE_KEY);
    }

    public function hookDisplayProductPriceBlock ($params)
    {
        $prod = $params['product'];

        if ($params['type'] != 'after_price') {
            return false;
        }

        $showWidget = Configuration::get('DIVIDO_SHOW_WIDGET');
        if ($showWidget != self::SHOW_WIDGET_YES) {
            return false;
        }

        if (! $this->isAvailableOnProd($prod)) {
            return false;
        }
        
        $plans = $this->getProductPlans($prod->id);
        $plans = array_map(function ($p) { return $p->id; }, $plans);
        $plans = implode(',', $plans);

        $data = array(
            'price' => $prod->getPrice(),
            'plans' => $plans,
        );

        $this->context->smarty->assign($data);

        return $this->display(__FILE__, 'widget.tpl');
    }

    public function isGloballyAvailable ($params)
    {
        if (! $this->active) {
            return false;
        }

        $cart = $params['cart'];

        $cartLimit = (float)Configuration::get('DIVIDO_CART_TRESHOLD');
		$cartValue = (float)$cart->getOrderTotal(true, Cart::BOTH);
        $aboveThreshold = $cartValue >= $cartLimit;

        $hasPlans = count($this->getCartPlans($cart));

        $shipping = new Address(intval($cart->id_address_delivery));
        $countryCode = Country::getIsoById($shipping->id_country);
        $rightCountry = $countryCode == 'GB';

        return $aboveThreshold && $hasPlans && $rightCountry;
    }

    public function isAvailableOnProd ($product)
    {

        if (! $this->active || ! Configuration::get('DIVIDO_ENABLED')) {
            return false;
        }

        $productOptions        = Configuration::get('DIVIDO_PROD_SELECTION');
        $productPriceThreshold = Configuration::get('DIVIDO_PRICE_THRESHOLD');

        switch ($productOptions) {
        case self::PROD_SEL_TRESHOLD:
            if ($product->getPrice() < $productPriceThreshold) {
                return false;
            }
            break;

        case self::PROD_SEL_SELECTED:
            $productPlans = $this->getProductPlans($product);
            if (! $productPlans) {
                return false;
            }
        }

        return true;
    }

    public function getAllPlans()
    {
        if (! $apiKey = $this->getApiKey()) {
            return false;
        }
        xdebug_break();

        if (! $allPlans = Cache::getInstance()->get(self::CACHE_KEY_PLANS)) {
            $plans = Divido_Finances::all();
            if ($plans->status != 'ok') {
                return false;
            }

            $allPlans = $plans->finances;
            Cache::getInstance()->set(self::CACHE_KEY_PLANS, $allPlans, self::CACHE_TTL);
        }


        return $allPlans;
    }

    public function getGlobalPlans ()
    {
        $plansOptions  = Configuration::get('DIVIDO_PLANS_OPTION');
        $planSelection = Configuration::get('DIVIDO_PLANS');
        $planSelection = !empty($planSelection) ? explode(',', $planSelection) : array();

        $allPlans = $this->getAllPlans();

        // Show all plans or no plans are selected
        if ($plansOptions == self::PLANS_ALL || empty($planSelection)) {
            return $allPlans;
        }

        $globalPlans = array();
        foreach ($allPlans as $key => $plan) {
            if (in_array($plan->id, $planSelection)) {
                $globalPlans[] = $plan;
            }
        }

        return $globalPlans;
    }

    public function getProductPlans ($prodId)
    {
        $globalPlansOptions  = Configuration::get('DIVIDO_PLANS_OPTION');

        $prodSettings = self::getProdSettings($prodId);
        $plansOptions = $prodSettings['plans_opt'];
        $planSelection = !empty($prodSettings['plans']) ? explode(',', $prodSettings['plans']) : array();

        if ($plansOptions == self::PROD_PLANS_ALL || (empty($plansOptions) && $globalPlansOptions != self::PROD_SEL_SELECTED)) {
            return $this->getGlobalPlans();
        }

        $allPlans = $this->getAllPlans();

        $prodPlans = array();
        foreach ($allPlans as $plan) {
            if (in_array($plan->id, $planSelection)) {
                $prodPlans[] = $plan;
            }
        }

        return $prodPlans;
    }

    public function getCartPlans ($cart)
    {
        $products = $cart->getProducts();
        $total   = (float)$cart->getOrderTotal(true, Cart::BOTH);

        $plans = array();
        foreach ($products as $prod) {
            $prodPlans = $this->getProductPlans($prod['id_product']);
            $plans = array_merge($plans, $prodPlans);
        }

        foreach ($plans as $key => $plan) {
            $planMinTotal = $total - ($total * ($plan->min_deposit / 100));
            if ($plan->min_amount > $planMinTotal) {
                unset($plans[$key]);
            }
        }

        return $plans;
    }

    public static function getProdSettings ($prodId)
    {
        $q = 'select * from ' . _DB_PREFIX_ . 'divido_products where product_id = ' . $prodId;
        return Db::getInstance()->getRow($q);
    }

    public function getScriptUrl ()
    {
        if (! $apiKey = $this->getApiKey()) {
            return false;
        }

        $jsKeyParts = explode('.', $apiKey);
        $jsKey = array_shift($jsKeyParts);
        $jsKey = strtolower($jsKey);

        return "//cdn.divido.com/calculator/{$jsKey}.js";
    }

    public function hash_cart ($salt, $cartId)
    {
        return hash('sha256', $salt.$cartId);
    }

    public function getApiKey ()
    {
        if (! $this->apiKey) {
            $key = Configuration::get('DIVIDO_API_KEY');
            if ($key) {
                $this->apiKey = $key;
            } elseif ($key = Tools::getValue('DIVIDO_API_KEY')) {
                $this->apiKey = $key;
            } else {
                $this->apiKey = false;
            }
        }

        return $this->apiKey;
    }

    public function createDb ()
    {
        $lookup = sprintf("
            CREATE TABLE IF NOT EXISTS `%sdivido_lookup` (
                `lookup_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Id',
                `salt` varchar(255) NOT NULL COMMENT 'Salt',
                `cart_id` int(10) unsigned NOT NULL COMMENT 'Quote ID',
                `credit_request_id` text NOT NULL COMMENT 'Credit request ID',
                `credit_application_id` text NOT NULL COMMENT 'Credit application ID',
                `order_id` int(11) DEFAULT NULL COMMENT 'Order ID',
                `deposit_amount` decimal(10,2) NOT NULL COMMENT 'Credit application ID',
                `canceled` tinyint(1) DEFAULT NULL COMMENT 'The application has ben cancelled',
                `declined` tinyint(1) DEFAULT NULL COMMENT 'The application was denied',
                PRIMARY KEY (`lookup_id`),
                UNIQUE KEY `UNQ_DIVIDO_LOOKUP_CART_ID` (`cart_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Contains info on applications from Divido'
            ", _DB_PREFIX_);

        $products = sprintf("
            CREATE TABLE IF NOT EXISTS `%sdivido_products` (
                `product_id` int(10) unsigned NOT NULL COMMENT 'Product Id',
                `plans_opt` tinyint(1) DEFAULT NULL COMMENT 'Plans settings',
                `plans` text,
                PRIMARY KEY (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Contains product settings for Divido'
            ", _DB_PREFIX_);

        return Db::getInstance()->execute($lookup)
            && Db::getInstance()->execute($products);
    }

    public function getConfigFieldsValues ()
    {
        return array(
            'DIVIDO_API_KEY'             => Configuration::get('DIVIDO_API_KEY'),
            'DIVIDO_ENABLED'             => Configuration::get('DIVIDO_ENABLED'),
            'DIVIDO_SHOW_WIDGET'         => Configuration::get('DIVIDO_SHOW_WIDGET'),
            'DIVIDO_CREATE_ORDER_STATUS' => Configuration::get('DIVIDO_CREATE_ORDER_STATUS'),
            'DIVIDO_TITLE'               => Configuration::get('DIVIDO_TITLE'),
            'DIVIDO_PROD_SELECTION'      => Configuration::get('DIVIDO_PROD_SELECTION'),
            'DIVIDO_PRICE_THRESHOLD'     => Configuration::get('DIVIDO_PRICE_THRESHOLD'),
            'DIVIDO_CART_THRESHOLD'      => Configuration::get('DIVIDO_CART_THRESHOLD'),
            'DIVIDO_PLANS_OPTION'        => Configuration::get('DIVIDO_PLANS_OPTION'),
            'DIVIDO_PLANS[]'             => explode(',', Configuration::get('DIVIDO_PLANS')),
        );
    }
    public function createLookup ($values = array()) {
        $values = array_map(function ($val) { return pSQL($val); }, $values);
        return DB::getInstance()->insert('divido_lookup', $values, false, true, Db::ON_DUPLICATE_KEY);
    }

    public function getLookup ($cartId)
    {
        $q = 'select * from ' . _DB_PREFIX_ . 'divido_lookup where cart_id = ' . $cartId;
        return Db::getInstance()->getRow($q);
    }

    public function doCreditRequest ($requestData)
    {
        return Divido_CreditRequest::create($requestData);
    }

    public function displayForm ()
    {
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        $plansOptions = array();
        if ($allPlans = $this->getAllPlans()) {
            foreach ($allPlans as $plan) {
                $plansOptions[] = array(
                    'id_plans' => $plan->id,
                    'name' => $plan->text,
                );
            }
        }

        $fields_form[0]['form'] = array(
            'legend' => array(
                $this->l('Settings'),
            ),
            'input' => array(
                array(
                    'type'     => 'text',
                    'label'    => $this->l('API-key'),
                    'name'     => 'DIVIDO_API_KEY',
                    'size'     => 52,
                    'required' => false,
                ),
                array(
                    'type'     => (_PS_VERSION_ < '1.6' ? 'radio':'switch'),
                    'label'    => $this->l('Enabled'),
                    'name'     => 'DIVIDO_ENABLED',
                    'required' => false,
                    'is_bool'  => true,
                    'values'   => array(
                        array(
                            'id'    => 'id_enabled',
                            'value' => 1,
                            'label' => $this->l('Yes'),
                        ),
                        array(
                            'id'    => 'id_enabled',
                            'value' => 0,
                            'label' => $this->l('No'),
                        ),
                    ),
                ),
                array(
                    'type'     => (_PS_VERSION_ < '1.6' ? 'radio':'switch'),
                    'label'    => $this->l('Show widget'),
                    'name'     => 'DIVIDO_SHOW_WIDGET',
                    'required' => false,
                    'is_bool'  => true,
                    'values'   => array(
                        array(
                            'id'    => 'id_enabled',
                            'value' => self::SHOW_WIDGET_YES,
                            'label' => $this->l('Yes'),
                        ),
                        array(
                            'id'    => 'id_enabled',
                            'value' => self::SHOW_WIDGET_NO,
                            'label' => $this->l('No'),
                        ),
                    ),
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->l('Title'),
                    'name'     => 'DIVIDO_TITLE',
                    'size'     => 52,
                    'required' => false,
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Create order on'),
                    'name' => 'DIVIDO_CREATE_ORDER_STATUS',
                    'required' => false,
                    'options' => array(
                        'id' => 'id_create_status',
                        'name' => 'name',
                        'query' => array(
                            array(
                                'id_create_status' => self::DIVIDO_STATUS_ACCEPTED,
                                'name' => $this->l('Accepted'),
                            ),
                            array(
                                'id_create_status' => self::DIVIDO_STATUS_SIGNED,
                                'name' => $this->l('Signed'),
                            ),
                        ),
                    ),
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->l('Cart threshold'),
                    'name'     => 'DIVIDO_CART_THRESHOLD',
                    'size'     => 52,
                    'required' => false,
                ),
                array(
                    'type'     => 'select',
                    'label'    => $this->l('Product Selection'),
                    'name'     => 'DIVIDO_PROD_SELECTION',
                    'required' => false,
                    'options' => array(
                        'id' => 'id_selection',
                        'name' => 'name',
                        'query' => array(
                            array(
                                'id_selection' => self::PROD_SEL_ALL,
                                'name' => $this->l('All products'),
                            ),
                            array(
                                'id_selection' => self::PROD_SEL_SELECTED,
                                'name' => $this->l('Selected products'),
                            ),
                            array(
                                'id_selection' => self::PROD_SEL_TRESHOLD,
                                'name' => $this->l('All products above a defined price'),
                            ),
                        ),
                    ),
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->l('Price threshold'),
                    'name'     => 'DIVIDO_PRICE_THRESHOLD',
                    'size'     => 5,
                    'required' => false,
                ),
                array(
                    'type'     => 'select',
                    'label'    => $this->l('Show default plan'),
                    'name'     => 'DIVIDO_PLANS_OPTION',
                    'required' => false,
                    'options'  => array(
                        'id'    => 'id_plan',
                        'name'  => 'name',
                        'query' => array(
                            array(
                                'id_plan' => self::PLANS_ALL,
                                'name' => $this->l('Show all plans'),
                            ),
                            array(
                                'id_plan' => self::PLANS_SELECTED,
                                'name' => $this->l('Select default plans'),
                            ),
                        ),
                    ),
                ),
                array(
                    'type' => 'select',
                    'multiple' => true,
                    'label' => $this->l('Select plans'),
                    'name' => 'DIVIDO_PLANS[]',
                    'options' => array(
                        'id' => 'id_plans',
                        'name' => 'name',
                        'query' => $plansOptions,
                    ),
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'btn btn-default pull-right',
            ),
        );

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        $helper->title = $this->displayName;

        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submitDividoBackend';
        $helper->toolbar_btn = array(
            'save' => array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex 
                . "&configure={$this->name}"
                . "&save={$this->name}"
                . "&token={$helper->token}",
            ),
            'back' => array(
                'desc' => $this->l('Back'),
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
            ),
        );

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
        );

        return $helper->generateForm($fields_form);
    }

}

