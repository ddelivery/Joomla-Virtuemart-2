<?php

defined ('_JEXEC') or die('Restricted access');

if (!class_exists ('vmPSPlugin')) {
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVmShipmentDdelivery extends vmPSPlugin {

	/**
	 * @param object $subject
	 * @param array  $config
	 */
	function __construct (& $subject, $config) {
        $this->_pelement = basename(__FILE__, '.php');    // Required!
        //$this->_createTable();                // Required, see below
		parent::__construct ($subject, $config);

		$this->_loggable = TRUE;
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
		$this->tableFields = array_keys ($this->getTableSQLFields ());
		$varsToPush = $this->getVarsToPush ();
		$this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);
		//vmdebug('Muh constructed plgVmShipmentWeight_countries',$varsToPush);
	}
    
    private function _createTable(){
        $_scheme = DbScheme::get_instance();
        $_scheme->create_scheme('#__vm_order_shipper_'.$this->_selement);
        $_schemeCols = array(
             'id' => array (
                     'type' => 'int'
                    ,'length' => 11
                    ,'auto_inc' => true
                    ,'null' => false
            )
            ,'order_id' => array (
                     'type' => 'int'
                    ,'length' => 11
                    ,'null' => false
            )
            ,'shipper_id' => array (
                     'type' => 'text'
                    ,'null' => false
            )
        );
        $_schemeIdx = array(
             'idx_order_payment' => array(
                     'columns' => array ('order_id')
                    ,'primary' => false
                    ,'unique' => false
                    ,'type' => null
            )
        );
        $_scheme->define_scheme($_schemeCols);
        $_scheme->define_index($_schemeIdx);
        if (!$_scheme->scheme()) {
            JError::raiseWarning(500, $_scheme->get_db_error());
        }
        $_scheme->reset();
    }
	/**
	 * Create the table for this plugin if it does not yet exist.
	 *
	 * @author Valérie Isaksen
	 */
	public function getVmPluginCreateTableSQL () {

		return $this->createTableSQL ('DDelivery Shipping Table');
	}

	/**
	 * @return array
	 */
	function getTableSQLFields () {

		$SQLfields = array(
			'id'                           => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'          => 'int(11) UNSIGNED',
			'order_number'                 => 'char(32)',
			'virtuemart_shipmentmethod_id' => 'mediumint(1) UNSIGNED',
			'shipment_name'                => 'varchar(5000)',
			'order_weight'                 => 'decimal(10,4)',
			'shipment_weight_unit'         => 'char(3) DEFAULT \'KG\'',
			'shipment_cost'                => 'decimal(10,2)',
			'shipment_package_fee'         => 'decimal(10,2)',
			'tax_id'                       => 'smallint(1)'
		);
		return $SQLfields;
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the shipment-specific data.
	 *
	 * @param integer $virtuemart_order_id The order ID
	 * @param integer $virtuemart_shipmentmethod_id The selected shipment method id
	 * @param string  $shipment_name Shipment Name
	 * @return mixed Null for shipments that aren't active, text (HTML) otherwise
	 * @author Valérie Isaksen
	 * @author Max Milbers
	 */
	public function plgVmOnShowOrderFEShipment ($virtuemart_order_id, $virtuemart_shipmentmethod_id, &$shipment_name) {
        
		$this->onShowOrderFE ($virtuemart_order_id, $virtuemart_shipmentmethod_id, $shipment_name);
        
	}
    
    
    public function bootstrap(){
        require_once (implode(DS,array(__DIR__,'ddelivery','application', 'bootstrap.php')));
            require_once (implode(DS,array(__DIR__,'ddelivery','IntegratorShop.php')));
            require_once (implode(DS,array(__DIR__,'ddelivery','application','classes','DDelivery','DDeliveryUI.php')));
    }
    
	/**
	 * This event is fired after the order has been stored; it gets the shipment method-
	 * specific data.
	 *
	 * @param int    $order_id The order_id being processed
	 * @param object $cart  the cart
	 * @param array  $order The actual order saved in the DB
	 * @return mixed Null when this method was not selected, otherwise true
	 * @author Valerie Isaksen
	 */
     
	function plgVmConfirmedOrder (VirtueMartCart $cart, $order) {

	       
		if (!($method = $this->getVmPluginMethod ($order['details']['BT']->virtuemart_shipmentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
        $method->cost_per_transaction = JFactory::getSession()->get('cost',0,'ddelivery');
        $method->tax_id = -1;
        //echo ('<pre>$cart:'.print_r($cart,1).'</pre>');
        
        //jexit ('<pre>method_info:'.print_r($method,1).'</pre>');
		if (!$this->selectedThisElement ($method->shipment_element)) {
			return FALSE;
		}
		$values['virtuemart_order_id'] = $order['details']['BT']->virtuemart_order_id;
		$values['order_number'] = $order['details']['BT']->order_number;
		$values['virtuemart_shipmentmethod_id'] = $order['details']['BT']->virtuemart_shipmentmethod_id;
		$values['shipment_name'] = $this->renderPluginName ($method);
		$values['order_weight'] = $this->getOrderWeight ($cart, $method->weight_unit);
		$values['shipment_weight_unit'] = $method->weight_unit;
		$values['shipment_cost'] = $method->shipment_cost;
		$values['shipment_package_fee'] = $method->package_fee;
		$values['tax_id'] = $method->tax_id;
        
		$this->storePSPluginInternalData ($values);
        try{
            $this->bootstrap();
            
        	$IntegratorShop = new IntegratorShop();
        	$ddeliveryUI = new \DDelivery\DDeliveryUI($IntegratorShop, true);
            $id = JFactory::getSession()->get('order_id', 0, 'ddelivery');
            $shopOrderID = $cart->order_number;
            $status = $order['history'][0]->order_status_code;
            //echo ('<pre>$cart:'.print_r($cart,1).'</pre>');
            if (isset($order['details']['ST']))
                $payment = $order['details']['ST']->virtuemart_paymentmethod_id;
            else $payment = $order['details']['BT']->virtuemart_paymentmethod_id;
            //jexit("id $id shopOrderID $shopOrderID status $status payment $payment");
        	$ddeliveryUI->onCmsOrderFinish( $id, $shopOrderID, $status, $payment);
            $session = JFactory::getSession();
            $session->clear('cost', 'ddelivery'); 
            $session->clear('comment', 'ddelivery'); 
            $session->clear('order_id', 'ddelivery'); 
        }
        catch(\DDelivery\DDeliveryException $e)
        {
            echo $e->getMessage();
            $ddeliveryUI->logMessage($e);
        }
		return TRUE;
	}

	/**
	 * This method is fired when showing the order details in the backend.
	 * It displays the shipment-specific data.
	 * NOTE, this plugin should NOT be used to display form fields, since it's called outside
	 * a form! Use plgVmOnUpdateOrderBE() instead!
	 *
	 * @param integer $virtuemart_order_id The order ID
	 * @param integer $virtuemart_shipmentmethod_id The order shipment method ID
	 * @param object  $_shipInfo Object with the properties 'shipment' and 'name'
	 * @return mixed Null for shipments that aren't active, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	public function plgVmOnShowOrderBEShipment ($virtuemart_order_id, $virtuemart_shipmentmethod_id) {
		if (!($this->selectedThisByMethodId ($virtuemart_shipmentmethod_id))) {
			return NULL;
		}
		$html = $this->getOrderShipmentHtml ($virtuemart_order_id);
		return $html;
	}

	/**
	 * @param $virtuemart_order_id
	 * @return string
	 */
	function getOrderShipmentHtml ($virtuemart_order_id) {
		$db = JFactory::getDBO ();
		$q = 'SELECT * FROM `' . $this->_tablename . '` '
			. 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
		$db->setQuery ($q);
		if (!($shipinfo = $db->loadObject ())) {
			vmWarn (500, $q . " " . $db->getErrorMsg ());
			return '';
		}

		if (!class_exists ('CurrencyDisplay')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
		}
        $shipinfo->tax_id = -1;
		$currency = CurrencyDisplay::getInstance ();
		$tax = ShopFunctions::getTaxByID ($shipinfo->tax_id);
		$taxDisplay = is_array ($tax) ? $tax['calc_value'] . ' ' . $tax['calc_value_mathop'] : $shipinfo->tax_id;
		$taxDisplay = ($taxDisplay == -1) ? JText::_ ('COM_VIRTUEMART_PRODUCT_TAX_NONE') : $taxDisplay;
		$html = '<table class="adminlist table">' . "\n";
		$html .= $this->getHtmlHeaderBE ();
        try{
        require_once (implode(DS,array(__DIR__,'ddelivery','application', 'bootstrap.php')));
        require_once (implode(DS,array(__DIR__,'ddelivery','IntegratorShop.php')));
        require_once (implode(DS,array(__DIR__,'ddelivery','application','classes','DDelivery','DDeliveryUI.php')));
            
        $IntegratorShop = new IntegratorShop();
       	$ddeliveryUI = new \DDelivery\DDeliveryUI($IntegratorShop, true);
		$dd_order = $ddeliveryUI->getOrderbyCmsId((int)$virtuemart_order_id);
        }catch(Exception $e){
            echo $e->getMessage();
        }
        
            /*if (is_object($order)){
                $dd_order_id = (int)$order->ddeliveryID;
                $this->setDDExternalOrderStatus($dd_order_id,$dd_cart[0]['ps_cart_id']);
            }*/
            //$dd_order = $ddeliveryUI->initOrder($dd_data[0]['dd_order_id']);
            if(empty($dd_order)) return;
            $point = $dd_order->getPoint();

           /* if($dd_order->toStreet == NULL && $dd_order->toHouse == NULL){
            	$address = 'Адрес доставки: Самовывоз';
            }else{
             	$address = 'Адрес доставки: '.$dd_order->toStreet . ' ' . $dd_order->toHouse . ' ' . $dd_order->toFlat;
            }*/
            echo '<pre>'.print_r($dd_order,1).'</pre>';
            //$dd_order_id = (is_null($dd_data[0]['dd_order_id_external'])) ? '' : "Номер заказа Digital Delivery: ".$dd_data[0]['dd_order_id_external'];
            $return = array(
                'ID заявки на сервере DD:' => $dd_order->ddeliveryID,
                'Способ доставки:' => ((int)$dd_order->type == 1)?'Самовывоз':'Курьерская доставка',
                'Клиент:' => "{$dd_order->secondName} {$dd_order->firstName} {$dd_order->toEmail} {$dd_order->toPhone}",
                'Компания доставки:' => $point['delivery_company_name'],
                'Стоимость доставки:' => $point['total_price'] .' руб.',//$ddeliveryUI->getOrderClientDeliveryPrice($dd_order),//
                'Выбранный модуль оплаты в магазине:' => $dd_order->paymentVariant,
            );
            if ((int)$dd_order->type == 1){
                $return['Регион:'] = $point['region'];
                $return['Город:'] = $point['city_type'] . ' '.  $point['city'];
                $return['Индекс:'] = $point['postal_code'];
                $return['Пункт самовывоза:'] = $point['name'];
                $return['Тип пункта самовывоза:'] = ($point['type'] == 2)?'Живой пункт':'Ячейка';
                $return['Описание пункта самовывоза:'] = $point['description_out'];
                $return['Адрес пункта самовывоза:'] = $point['address'];
                $return['Режим работы:'] = $point['schedule'];
                if (strlen($point['metro']))
                    $return['Метро:'] = $point['metro'];
                if ((int)$point['is_cash'] == 1 && (int)$point['is_card'] !== 1)
                    $return['Доступные способы оплаты:'] = 'Оплата наличными';
                if ((int)$point['is_cash'] !== 1 && (int)$point['is_card'] == 1)
                    $return['Доступные способы оплаты:'] = 'Оплата картой';
                if ((int)$point['is_cash'] == 1 && (int)$point['is_card'] == 1)
                    $return['Доступные способы оплаты:'] = 'Оплата наличными или картой';
            }elseif((int)$dd_order->type == 2){
                $return['Город:'] = $dd_order->cityName;
                $return['Улица:'] = $dd_order->toStreet;
                if ($dd_order->toHouse)
                    $return['Дом:'] = $dd_order->toHouse;
                if ($dd_order->toHousing)
                    $return['Корпус:'] = $dd_order->toHousing;
                if ($dd_order->toFlat)
                    $return['Квартира:'] = $dd_order->toFlat;
                
                $return['Время доставки (в днях):'] = "от $point[delivery_time_min] до $point[delivery_time_max] (в среднем: $point[delivery_time_avg])";
            }
			$html .= $this->getHtmlRowBE ('DDELIVERY_SHIPPING_NAME', preg_replace('/<a.+?>.+<\/a>/','', $shipinfo->shipment_name));
    		//$html .= $this->getHtmlRowBE ('DDELIVERY_WEIGHT', $shipinfo->order_weight . ' ' . ShopFunctions::renderWeightUnit ($shipinfo->shipment_weight_unit));
    		//$html .= $this->getHtmlRowBE ('DDELIVERY_COST', $currency->priceDisplay ($shipinfo->shipment_cost));
    		//$html .= $this->getHtmlRowBE ('DDELIVERY_PACKAGE_FEE', $currency->priceDisplay ($shipinfo->shipment_package_fee));
    		//$html .= $this->getHtmlRowBE ('DDELIVERY_TAX', $taxDisplay);
            if (is_array($return) && count($return))
                foreach ($return as $k => $v)
                    $html .= $this->getHtmlRowBE ($k, $v);    
        
		$html .= '</table>' . "\n";

		return $html;
	}

	/**
	 * @param VirtueMartCart $cart
	 * @param                $method
	 * @param                $cart_prices
	 * @return int
	 */
	function getCosts (VirtueMartCart $cart, $method, $cart_prices) {
		if (isset($method->free_shipment) && $method->free_shipment && $cart_prices['salesPrice'] >= $method->free_shipment) {
			return 0;
		} else {
            //echo '<pre>'.print_r($method,1).'</pre>';
            $method->shipment_cost = JFactory::getSession()->get('cost',0,'ddelivery');
            $method->package_fee = 0;
			return $method->shipment_cost + $method->package_fee;
		}
	}

	/**
	 * @param \VirtueMartCart $cart
	 * @param int             $method
	 * @param array           $cart_prices
	 * @return bool
	 */
	protected function checkConditions ($cart, $method, $cart_prices) {
        //echo '<pre>'.print_r($cart->products,1).'</pre>';
        $this->bootstrap();    
	    $IntegratorShop = new IntegratorShop();
        $ddeliveryUI = new \DDelivery\DDeliveryUI($IntegratorShop, true);
        $id = JFactory::getSession()->get('order_id', 0, 'ddelivery');
        if ($id >0){
            $dd_order = $ddeliveryUI->initOrder($id);
            $dd_products = $dd_order->getProducts();
            $check = true;
            error_reporting(E_WARNING);
            if (!count($cart->products) || !count($dd_products) || (count($cart->products) !== count($dd_products))){
                $check = false;
            }
            else {
                foreach($dd_products as $product){
                    if (!isset($cart->products[$product->getId()]) || (int)$cart->products[$product->getId()]->quantity !== $product->getQuantity()){
                        $check = false;
                        break;
                    }
                        
                }
            }
            if (!$check){
                JFactory::getSession()->clear('comment','ddelivery');
                JFactory::getSession()->clear('cost','ddelivery');
            }
        }
        return true;    
        //var_dump($check);
            
        //echo '<pre>'.print_r($dd_products,1).'</pre>';
	}

	/**
	 * @param $method
	 */
	function convert (&$method) {
        //$method->weight_start = (float) $method->weight_start;
		//$method->weight_stop = (float) $method->weight_stop;
		$method->orderamount_start =  (float)str_replace(',','.',$method->orderamount_start);
		$method->orderamount_stop =   (float)str_replace(',','.',$method->orderamount_stop);
		$method->zip_start = (int)$method->zip_start;
		$method->zip_stop = (int)$method->zip_stop;
		$method->nbproducts_start = (int)$method->nbproducts_start;
		$method->nbproducts_stop = (int)$method->nbproducts_stop;
		$method->free_shipment = (float)str_replace(',','.',$method->free_shipment);
	}

	/**
	 * @param $cart
	 * @param $method
	 * @return bool
	 */
	private function _nbproductsCond ($cart, $method) {
        
		if (empty($method->nbproducts_start) and empty($method->nbproducts_stop)) {
			//vmdebug('_nbproductsCond',$method);
			return true;
		}

		$nbproducts = 0;
		foreach ($cart->products as $product) {
			$nbproducts += $product->quantity;
		}

		if ($nbproducts) {

			$nbproducts_cond = $this->testRange($nbproducts,$method,'nbproducts_start','nbproducts_stop','products quantity');

		} else {
			$nbproducts_cond = false;
		}

		return $nbproducts_cond;
	}


	private function testRange($value, $method, $floor, $ceiling,$name){

		$cond = true;
		if(!empty($method->$floor) and !empty($method->$ceiling)){
			$cond = (($value >= $method->$floor AND $value <= $method->$ceiling));
			if(!$cond){
				$result = 'FALSE';
				$reason = 'is NOT within Range of the condition from '.$method->$floor.' to '.$method->$ceiling;
			} else {
				$result = 'TRUE';
				$reason = 'is within Range of the condition from '.$method->$floor.' to '.$method->$ceiling;
			}
		} else if(!empty($method->$floor)){
			$cond = ($value >= $method->$floor);
			if(!$cond){
				$result = 'FALSE';
				$reason = 'is not at least '.$method->$floor;
			} else {
				$result = 'TRUE';
				$reason = 'is over min limit '.$method->$floor;
			}
		} else if(!empty($method->$ceiling)){
			$cond = ($value <= $method->$ceiling);
			if(!$cond){
				$result = 'FALSE';
				$reason = 'is over '.$method->$ceiling;
			} else {
				$result = 'TRUE';
				$reason = 'is lower than the set '.$method->$ceiling;
			}
		} else {
			$result = 'TRUE';
			$reason = 'no boundary conditions set';
		}

		vmdebug('shipmentmethod '.$method->shipment_name.' = '.$result.' for variable '.$name.' = '.$value.' Reason: '.$reason);
		return $cond;
	}


	function plgVmOnProductDisplayShipment($product, &$productDisplayShipments){
        
		$vendorId = 1;
		if ($this->getPluginMethods($vendorId) === 0) {
			return FALSE;
		}
		if (!class_exists('VirtueMartCart'))
			require(VMPATH_SITE . DS . 'helpers' . DS . 'cart.php');
		$cart = VirtueMartCart::getCart();
		
        $html = '';
		if (!class_exists('CurrencyDisplay'))
			require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
		$currency = CurrencyDisplay::getInstance();

		foreach ($this->methods as $this->_currentMethod) {
			if($this->_currentMethod->show_on_pdetails){
				if($this->checkConditions($cart,$this->_currentMethod,$cart->pricesUnformatted,$product)){

					$product->prices['shipmentPrice'] = $this->getCosts($cart,$this->_currentMethod,$cart->pricesUnformatted);

					if(isset($product->prices['VatTax']) and count($product->prices['VatTax'])>0){
						reset($product->prices['VatTax']);
						$rule = current($product->prices['VatTax']);
						if(isset($rule[1])){
							$product->prices['shipmentTax'] = $product->prices['shipmentPrice'] * $rule[1]/100.0;
							$product->prices['shipmentPrice'] = $product->prices['shipmentPrice'] * (1 + $rule[1]/100.0);
						}
					}

					$html = $this->renderByLayout( 'default', array("method" => $this->_currentMethod, "cart" => $cart,"product" => $product,"currency" => $currency) );
				}
			}

		}

		$productDisplayShipments[] = $html;

	}

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 *
	 * @author Valérie Isaksen
	 *
	 */
	function plgVmOnStoreInstallShipmentPluginTable ($jplugin_id) {
        require_once (implode(DS,array(__DIR__,'ddelivery','application', 'bootstrap.php')));
        require_once (implode(DS,array(__DIR__,'ddelivery','IntegratorShop.php')));
        require_once (implode(DS,array(__DIR__,'ddelivery','application','classes','DDelivery','DDeliveryUI.php')));
            
        $IntegratorShop = new IntegratorShop();
       	$ddeliveryUI = new \DDelivery\DDeliveryUI($IntegratorShop, true);
        $ddeliveryUI->createTables();
		return $this->onStoreInstallPluginTable ($jplugin_id);
	}

	/**
	 * @param VirtueMartCart $cart
	 * @return null
	 */
	public function plgVmOnSelectCheckShipment (VirtueMartCart &$cart) {
        
		return $this->OnSelectCheck ($cart);
	}

	/**
	 * plgVmDisplayListFE
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for example
	 *
	 * @param object  $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on success, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 */
	public function plgVmDisplayListFEShipment (VirtueMartCart $cart, $selected = 0, &$htmlIn) {
        
		$result = $this->displayListFE ($cart, $selected, $htmlIn);     
		
        return $result;
	}
    
    public function displayListFE (VirtueMartCart $cart, $selected = 0, &$htmlIn) {

		if ($this->getPluginMethods ($cart->vendorId) === 0) {
			if (empty($this->_name)) {
				vmAdminInfo ('displayListFE cartVendorId=' . $cart->vendorId);
				$app = JFactory::getApplication ();
				$app->enqueueMessage (JText::_ ('COM_VIRTUEMART_CART_NO_' . strtoupper ($this->_psType)));
				return FALSE;
			} else {
				return FALSE;
			}
		}

		$html = array();
		$method_name = $this->_psType . '_name';
		
        foreach ($this->methods as $method) {
			
            if ($this->checkConditions ($cart, $method, $cart->pricesUnformatted)) {
                
				//$methodSalesPrice = $this->calculateSalesPrice ($cart, $method, $cart->pricesUnformatted);
				/* Because of OPC: the price must not be overwritten directly in the cart */
				$pricesUnformatted= $cart->pricesUnformatted;
				$methodSalesPrice = $this->setCartPrices ($cart, $pricesUnformatted,$method);
				$method->$method_name = $this->renderPluginName ($method);
                
                /*$session = JFactory::getSession();
                $html_ddelivery = '<a href="javascript:DDeliveryIntegration.openPopup()">Указать способ доставки</a>';
                $html_ddelivery .= '<div id="dd_info">'.$session->get('comment','','ddelivery').'</div>';
                $html_ddelivery .= '<div id="dd_price">'.$session->get('cost','','ddelivery').'</div>';
                
				JFactory::getDocument()->addScriptDeclaration("var dd_shipment_id={$method->virtuemart_shipmentmethod_id}");
				JFactory::getDocument()->addScript(JURI::base().'plugins/vmshipment/ddelivery/ddelivery/assets/js/ddelivery.ready.js');
				JFactory::getDocument()->addScript(JURI::base().'plugins/vmshipment/ddelivery/ddelivery/assets/js/ddelivery.js');
				JFactory::getDocument()->addScript(JURI::base().'plugins/vmshipment/ddelivery/ddelivery/assets/js/ddelivery_include.js');*/
                $html [] = $this->getPluginHtml ($method, $selected, $methodSalesPrice); //. $html_ddelivery;
                
                //header('Content-Type: text/html; charset=utf-8');
                //jexit('<pre>'.htmlspecialchars(print_r($html,1)).'</pre>');
                //jexit('<pre>'.print_r($html,1).'</pre>');
			}
		}
		if (!empty($html)) {
			$htmlIn[] = $html;
			return TRUE;
		}

		return FALSE;
	}
    
    protected function renderPluginName ($plugin) {

		$return = '';
		$plugin_name = $this->_psType . '_name';
		$plugin_desc = $this->_psType . '_desc';
		$description = '';
		// 		$params = new JParameter($plugin->$plugin_params);
		// 		$logo = $params->get($this->_psType . '_logos');
		$logosFieldName = $this->_psType . '_logos';
        if (isset($plugin->$logosFieldName)){
    		$logos = $plugin->$logosFieldName;
            //print_r($plugin);
    		if (!empty($logos)) {
    			$return = $this->displayLogos ($logos) . ' ';
    		}
        }
		if (!empty($plugin->$plugin_desc)) {
			$description = '<span class="' . $this->_type . '_description">' . $plugin->$plugin_desc . '</span>';
		}
		$pluginName = $return . '<span class="' . $this->_type . '_name">' . $plugin->$plugin_name . '</span>' . $description;
        $session = JFactory::getSession();
        $html_ddelivery = '';
        if (strpos($_SERVER['SCRIPT_URI'],'administrator') === false){
            $html_ddelivery .= '<div><a id="select_way" href="javascript:DDeliveryIntegration.openPopup()">Указать способ доставки</a></div>';
            $html_ddelivery .= '<div id="dd_info">'.$session->get('comment','','ddelivery').'</div>';
            $html_ddelivery .= '<div id="dd_price">'.$session->get('cost','','ddelivery').'</div>';
        }        
		JFactory::getDocument()->addScriptDeclaration("var dd_shipment_id={$plugin->virtuemart_shipmentmethod_id}");
		JFactory::getDocument()->addScript(JURI::base().'plugins/vmshipment/ddelivery/ddelivery/assets/js/ddelivery.ready.js');
        JFactory::getDocument()->addScript(JURI::base().'plugins/vmshipment/ddelivery/ddelivery/assets/js/ddelivery.js');
		JFactory::getDocument()->addScript(JURI::base().'plugins/vmshipment/ddelivery/ddelivery/assets/js/ddelivery_include.js');
        $pluginName .= $html_ddelivery;        
		return $pluginName;
	}

	/**
	 * @param VirtueMartCart $cart
	 * @param array          $cart_prices
	 * @param                $cart_prices_name
	 * @return bool|null
	 */
	public function plgVmOnSelectedCalculatePriceShipment (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {

		return $this->onSelectedCalculatePrice ($cart, $cart_prices, $cart_prices_name);
	}

	/**
	 * plgVmOnCheckAutomaticSelected
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 *
	 * @author Valerie Isaksen
	 * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 *
	 */
	function plgVmOnCheckAutomaticSelectedShipment (VirtueMartCart $cart, array $cart_prices, &$shipCounter) {

		if ($shipCounter > 1) {
			return 0;
		}

		return $this->onCheckAutomaticSelected ($cart, $cart_prices, $shipCounter);
	}

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	function plgVmonShowOrderPrint ($order_number, $method_id) {
		return $this->onShowOrderPrint ($order_number, $method_id);
	}

	function plgVmDeclarePluginParamsShipment ($name, $id, &$dataOld) {
		return $this->declarePluginParams ('shipment', $name, $id, $dataOld);
	}

	function plgVmDeclarePluginParamsShipmentVM3 (&$data) {
		return $this->declarePluginParams ('shipment', $data);
	}


	/**
	 * @author Max Milbers
	 * @param $data
	 * @param $table
	 * @return bool
	 */
	function plgVmSetOnTablePluginShipment(&$data,&$table){

		$name = $data['shipment_element'];
		$id = $data['shipment_jplugin_id'];

		if (!empty($this->_psType) and !$this->selectedThis ($this->_psType, $name, $id)) {
			return FALSE;
		} else {
			$toConvert = array('weight_start','weight_stop','orderamount_start','orderamount_stop');
			foreach($toConvert as $field){

				if(!empty($data[$field])){
					$data[$field] = str_replace(array(',',' '),array('.',''),$data[$field]);
				} else {
					unset($data[$field]);
				}
			}

			$data['nbproducts_start'] = (int) $data['nbproducts_start'];
			$data['nbproducts_stop'] = (int) $data['nbproducts_stop'];
			//I dont see a reason for it
			/*$toConvert = array('zip_start','zip_stop','nbproducts_start' , 'nbproducts_stop');
			foreach($toConvert as $field){
				if(!empty($data[$field])){
					$data[$field] = str_replace( ' ','',$data[$field]);
				} else {
					unset($data[$field]);
				}
				if (preg_match ("/[^0-9]/", $data[$field])) {
					vmWarn( JText::sprintf('VMSHIPMENT_DDELIVERY_NUMERIC', JText::_('VMSHIPMENT_DDELIVERY_'.$field) ) );
				}
			}*/
			//Reasonable tests:
			if(!empty($data['zip_start']) and !empty($data['zip_stop']) and (int)$data['zip_start']>=(int)$data['zip_stop']){
				vmWarn('VMSHIPMENT_DDELIVERY_ZIP_CONDITION_WRONG');
			}
			if(!empty($data['weight_start']) and !empty($data['weight_stop']) and (float)$data['weight_start']>=(float)$data['weight_stop']){
				vmWarn('VMSHIPMENT_DDELIVERY_WEIGHT_CONDITION_WRONG');
			}

			if(!empty($data['orderamount_start']) and !empty($data['orderamount_stop']) and (float)$data['orderamount_start']>=(float)$data['orderamount_stop']){
				vmWarn('VMSHIPMENT_DDELIVERY_AMOUNT_CONDITION_WRONG');
			}

			if(!empty($data['nbproducts_start']) and !empty($data['nbproducts_stop']) and (float)$data['nbproducts_start']>=(float)$data['nbproducts_stop']){
				vmWarn('VMSHIPMENT_DDELIVERY_NBPRODUCTS_CONDITION_WRONG');
			}

			return $this->setOnTablePluginParams ($name, $id, $table);
		}
	}
    
    
    
    public function plgVmOnUpdateOrderShipment(&$data,$old_order_status){
        //JFactory::getApplication()->enqueueMessage('<pre>data: '.print_r($data,1).'</pre>');
        try{
            $this->bootstrap();
        
        	$IntegratorShop = new IntegratorShop();
        	$ddeliveryUI = new \DDelivery\DDeliveryUI($IntegratorShop, true);
        	$ddeliveryUI->onCmsChangeStatus($data->virtuemart_order_id, $data->order_status);
            //jexit(print_r($data,1));
        }
        catch(\DDelivery\DDeliveryException $e)
        {
            echo $e->getMessage();
            $ddeliveryUI->logMessage($e);
        }

        return null;
    }

    
    /**
	 * update the plugin cart_prices
	 *
	 * @author Valérie Isaksen
	 *
	 * @param $cart_prices: $cart_prices['salesPricePayment'] and $cart_prices['paymentTax'] updated. Displayed in the cart.
	 * @param $value :   fee
	 * @param $tax_id :  tax id
	 */

	function setCartPrices (VirtueMartCart $cart, &$cart_prices, $method, $progressive = true) {


		if (!class_exists ('calculationHelper')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'calculationh.php');
		}
		$_psType = ucfirst ($this->_psType);
		$calculator = calculationHelper::getInstance ();
        
		$cart_prices[$this->_psType . 'Value'] = $calculator->roundInternal ($this->getCosts ($cart, $method, $cart_prices), 'salesPrice');
		if(!isset($cart_prices[$this->_psType . 'Value'])) $cart_prices[$this->_psType . 'Value'] = 0.0;
		if(!isset($cart_prices[$this->_psType . 'Tax'])) $cart_prices[$this->_psType . 'Tax'] = 0.0;

		if($this->_psType=='payment'){
			$cartTotalAmountOrig=$this->getCartAmount($cart_prices);
			if(!$progressive){
				//Simple
				$cartTotalAmount=($cartTotalAmountOrig + $method->cost_per_transaction) * (1 +($method->cost_percent_total * 0.01));
				//vmdebug('Simple $cartTotalAmount = ('.$cartTotalAmountOrig.' + '.$method->cost_per_transaction.') * (1 + ('.$method->cost_percent_total.' * 0.01)) = '.$cartTotalAmount );
				//vmdebug('Simple $cartTotalAmount = '.($cartTotalAmountOrig + $method->cost_per_transaction).' * '. (1 + $method->cost_percent_total * 0.01) .' = '.$cartTotalAmount );
			} else {
				//progressive
				$cartTotalAmount = ($cartTotalAmountOrig + $method->cost_per_transaction) / (1 -($method->cost_percent_total * 0.01));
				//vmdebug('Progressive $cartTotalAmount = ('.$cartTotalAmountOrig.' + '.$method->cost_per_transaction.') / (1 - ('.$method->cost_percent_total.' * 0.01)) = '.$cartTotalAmount );
				//vmdebug('Progressive $cartTotalAmount = '.($cartTotalAmountOrig + $method->cost_per_transaction) .' / '. (1 - $method->cost_percent_total * 0.01) .' = '.$cartTotalAmount );
			}
			$cart_prices[$this->_psType . 'Value'] = $cartTotalAmount - $cartTotalAmountOrig;
		}


		$taxrules = array();
        $method->tax_id = -1;
		if(isset($method->tax_id) and (int)$method->tax_id === -1){

		} else if (!empty($method->tax_id)) {
			$cart_prices[$this->_psType . '_calc_id'] = $method->tax_id;

			$db = JFactory::getDBO ();
			$q = 'SELECT * FROM #__virtuemart_calcs WHERE `virtuemart_calc_id`="' . $method->tax_id . '" ';
			$db->setQuery ($q);
			$taxrules = $db->loadAssocList ();

			if(!empty($taxrules) ){
				foreach($taxrules as &$rule){
					if(!isset($rule['subTotal'])) $rule['subTotal'] = 0;
					if(!isset($rule['taxAmount'])) $rule['taxAmount'] = 0;
					$rule['subTotalOld'] = $rule['subTotal'];
					$rule['taxAmountOld'] = $rule['taxAmount'];
					$rule['taxAmount'] = 0;
					$rule['subTotal'] = $cart_prices[$this->_psType . 'Value'];
					$cart_prices[$this->_psType . 'TaxPerID'][$rule['virtuemart_calc_id']] = $calculator->roundInternal($calculator->roundInternal($calculator->interpreteMathOp($rule, $rule['subTotal'])) - $rule['subTotal'], 'salesPrice');
					$cart_prices[$this->_psType . 'Tax'] += $cart_prices[$this->_psType . 'TaxPerID'][$rule['virtuemart_calc_id']];
				}
			}
		} else {
			$taxrules = array_merge($calculator->_cartData['VatTax'],$calculator->_cartData['taxRulesBill']);

			if(!empty($taxrules) ){
				$denominator = 0.0;
				foreach($taxrules as &$rule){
					//$rule['numerator'] = $rule['calc_value']/100.0 * $rule['subTotal'];
					if(!isset($rule['subTotal'])) $rule['subTotal'] = 0;
					if(!isset($rule['taxAmount'])) $rule['taxAmount'] = 0;
					$denominator += ($rule['subTotal']-$rule['taxAmount']);
					$rule['subTotalOld'] = $rule['subTotal'];
					$rule['subTotal'] = 0;
					$rule['taxAmountOld'] = $rule['taxAmount'];
					$rule['taxAmount'] = 0;
					//$rule['subTotal'] = $cart_prices[$this->_psType . 'Value'];
				}
				if(empty($denominator)){
					$denominator = 1;
				}

				foreach($taxrules as &$rule){
					$frac = ($rule['subTotalOld']-$rule['taxAmountOld'])/$denominator;
					$rule['subTotal'] = $cart_prices[$this->_psType . 'Value'] * $frac;
					//vmdebug('Part $denominator '.$denominator.' $frac '.$frac,$rule['subTotal']);
					$cart_prices[$this->_psType . 'TaxPerID'][$rule['virtuemart_calc_id']] = $calculator->roundInternal($calculator->roundInternal($calculator->interpreteMathOp($rule, $rule['subTotal'])) - $rule['subTotal'], 'salesPrice');
					$cart_prices[$this->_psType . 'Tax'] += $cart_prices[$this->_psType . 'TaxPerID'][$rule['virtuemart_calc_id']];
				}
			}
		}


		if(empty($method->cost_per_transaction)) $method->cost_per_transaction = 0.0;
		if(empty($method->cost_percent_total)) $method->cost_percent_total = 0.0;

		if (count ($taxrules) > 0 ) {

			$cart_prices['salesPrice' . $_psType] = $calculator->roundInternal ($calculator->executeCalculation ($taxrules, $cart_prices[$this->_psType . 'Value'],true,false), 'salesPrice');
			//vmdebug('I am in '.get_class($this).' and have this rules now',$taxrules,$cart_prices[$this->_psType . 'Value'],$cart_prices['salesPrice' . $_psType]);
//			$cart_prices[$this->_psType . 'Tax'] = $calculator->roundInternal (($cart_prices['salesPrice' . $_psType] -  $cart_prices[$this->_psType . 'Value']), 'salesPrice');
			reset($taxrules);
//			$taxrule =  current($taxrules);
//			$cart_prices[$this->_psType . '_calc_id'] = $taxrule['virtuemart_calc_id'];

			foreach($taxrules as &$rule){
				if(!isset($cart_prices[$this->_psType . '_calc_id']) or !is_array($cart_prices[$this->_psType . '_calc_id'])) $cart_prices[$this->_psType . '_calc_id'] = array();
				$cart_prices[$this->_psType . '_calc_id'][] = $rule['virtuemart_calc_id'];
				if(isset($rule['subTotalOld'])) $rule['subTotal'] += $rule['subTotalOld'];
				if(isset($rule['taxAmountOld'])) $rule['taxAmount'] += $rule['taxAmountOld'];
			}

		} else {
			$cart_prices['salesPrice' . $_psType] = $cart_prices[$this->_psType . 'Value'];
			$cart_prices[$this->_psType . 'Tax'] = 0;
			$cart_prices[$this->_psType . '_calc_id'] = 0;
		}


		return $cart_prices['salesPrice' . $_psType];

	}

}

// No closing tag
