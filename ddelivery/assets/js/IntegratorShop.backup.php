<?php
/**
 * Created by PhpStorm.
 * User: mrozk
 * Date: 15.05.14
 * Time: 23:14
 */
//error_reporting(E_ALL);
if (!defined('_JEXEC'))
    define( '_JEXEC', 1 );
if (!defined('JPATH_BASE')){
    define('JPATH_BASE', __DIR__.'/../../../../');
    require ( JPATH_BASE .'includes/defines.php' );
    require ( JPATH_BASE .'includes/framework.php' );
    require ( JPATH_BASE .'libraries/joomla/factory.php' );
    require ( JPATH_BASE .'libraries/import.php');
    $app = JFactory::getApplication('site')->initialise();
}
if (!class_exists( 'VmConfig' )) require(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_virtuemart'.DS.'helpers'.DS.'config.php');
/* Create the Application */
$app = JFactory::getApplication('site');

use DDelivery\Order\DDeliveryOrder;
use DDelivery\Order\DDeliveryProduct;
use DDelivery\Order\DDStatusProvider;

class IntegratorShop extends \DDelivery\Adapter\PluginFilters
{
    protected $registry;
    protected $address_correct = false;
    protected $default_currency_code;
    /**
     * Синхронизация локальных статусов и статусов дделивери
     * @var array
     */
    protected  $cmsOrderStatus ;
                                        
    
                                        
    public function __construct(){
        $cmsOrderStatus = array( DDStatusProvider::ORDER_IN_PROGRESS => $this->getConfig('status_in_progress'),
                                 DDStatusProvider::ORDER_CONFIRMED => $this->getConfig('status_confirmed'),
                                 DDStatusProvider::ORDER_IN_STOCK => $this->getConfig('status_in_stock'),
                                 DDStatusProvider::ORDER_IN_WAY => $this->getConfig('status_in_way'),
                                 DDStatusProvider::ORDER_DELIVERED => $this->getConfig('status_delivered'),
                                 DDStatusProvider::ORDER_RECEIVED => $this->getConfig('status_received'),
                                 DDStatusProvider::ORDER_RETURN => $this->getConfig('status_return'),
                                 DDStatusProvider::ORDER_CUSTOMER_RETURNED => $this->getConfig('status_customer_returned'),
                                 DDStatusProvider::ORDER_PARTIAL_REFUND => $this->getConfig('status_refund'),
                                 DDStatusProvider::ORDER_RETURNED_MI => $this->getConfig('status_returned_mi'),
                                 DDStatusProvider::ORDER_WAITING => $this->getConfig('status_waiting'),
                                 DDStatusProvider::ORDER_CANCEL => $this->getConfig('status_cancel') );
        
    }
    
    /**
     * Возвращает товары находящиеся в корзине пользователя, будет вызван один раз, затем закеширован
     * @return DDeliveryProduct[]
     */
    protected function _getProductsFromCart(){
        
        $products = array();
        if (!class_exists('VirtueMartCart'))
			require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
		$cart = VirtueMartCart::getCart();
        $cart->setCartIntoSession();
        
        $cart->prepareCartViewData();
        
        $prods =  $cart->products;
        $currencyModel = VmModel::getModel('currency');
        $toCurrencyId = ShopFunctions::getCurrencyIDByName('RUB');
        $currencyDisplay = CurrencyDisplay::getInstance($cart->pricesCurrency);
        if (is_array($prods) && count($prods)){
            foreach ($prods as $prod){
                //echo '<pre>'. print_r($prod,1).'</pre>';
                $width      = ((double)ShopFunctions::convertDimensionUnit((double)$prod->product_width,$prod->product_lwh_uom, 'CM') > 1)? ShopFunctions::convertDimensionUnit((double)$prod->product_width,$prod->product_lwh_uom, 'CM'):(double)$this->getConfig('default_width',10);
                $height     = ((double)ShopFunctions::convertDimensionUnit((double)$prod->product_height,$prod->product_lwh_uom,'CM')>= 1)? ShopFunctions::convertDimensionUnit((double)$prod->product_height,$prod->product_lwh_uom,'CM'):(double)$this->getConfig('default_height',10);
                $length     = ((double)ShopFunctions::convertDimensionUnit((double)$prod->product_length,$prod->product_lwh_uom,'CM') >= 1)?ShopFunctions::convertDimensionUnit((double)$prod->product_length,$prod->product_lwh_uom,'CM'):(double)$this->getConfig('default_length',10);
                $weight     = ((double)ShopFunctions::convertWeigthUnit((double)$prod->product_weight,$prod->product_weight_uom,'KG') > 0)?ShopFunctions::convertWeigthUnit((double)$prod->product_weight,$prod->product_weight_uom,'KG'):(double)$this->getConfig('default_weight',1);
                //$price      = $currencyDisplay->convertCurrencyTo($currencyModel->getCurrency($toCurrencyId),$cart->pricesUnformatted['salesPrice']);
                $price      = (double)$prod->product_price;
                $sku        = ($prod->product_sku)? $prod->product_sku :$prod->virtuemart_product_id;
                $products[] = new DDeliveryProduct(
                        $prod->virtuemart_product_id,	//	int $id id товара в системе и-нет магазина
                        $width,	 //	float $width длинна
                        $height, //	float $height высота
                        $length, //	float $length ширина
                        $weight, //	float $weight вес кг
                        $price,	 //	float $price стоимостьв рублях
                        $prod->quantity,
                        $prod->product_name,	//	string $name Название вещи
                        $sku
                    );        
            }
        }
        //echo '<pre>'. print_r($products,1).'</pre>';
        return $products;
    }
    
    /**
     * Возвращает API ключ, вы можете получить его для Вашего приложения в личном кабинете
     * @return string
     */
    public function getApiKey(){
           return $this->getConfig('api');
    }
    
    
                                        
    /**
     * Верните true если нужно использовать тестовый(stage) сервер
     * @return bool
     */
    public function isTestMode(){
        return  ($this->getConfig('workmode') !== 'work');
    }
    
    /**
     * Какой процент от стоимости страхуется
     * @return float
     */
    public function getDeclaredPercent(){
        return (double)$this->getConfig('insur');
    }
    
    /**
     * Если true, то не учитывает цену забора
     * @return bool
     */
    public function isPayPickup(){
        return ((int)$this->getConfig('ispaypickup') == 1);
    }
        
     /**
     * Если вы знаете имя покупателя, сделайте чтобы оно вернулось в этом методе
     * @return string|null
     */
    public function getClientFirstName() {
        return $this->getUserField($this->getConfig('last_name')) .' '.
            $this->getUserField($this->getConfig('first_name'));
    }

    /**
     * Если вы знаете фамилию покупателя, сделайте чтобы оно вернулось в этом методе
     * @return string|null
     */
    public function getClientLastName() {
        return $this->getUserField($this->getConfig('last_name'));
    }

    /**
     * Если вы знаете телефон покупателя, сделайте чтобы оно вернулось в этом методе. 11 символов, например 79211234567
     * @return string|null
     */
    public function getClientPhone() {
        $phone = $this->getUserField($this->getConfig('phone'));
        $phone = str_replace(array('+','-','(',')',' '),'',$phone);
        $phone = '7'.substr($phone,-10);
        if (strlen($phone) !== 11) $this->address_correct = false;
        return $phone;
    }
    
    
    public function getClientEmail(){
        return $this->getUserField($this->getConfig('email'));
    }

    /**
     * Верни массив Адрес, Дом, Корпус, Квартира. Если не можешь можно вернуть все в одном поле и настроить через get*RequiredFields
     * @return string[]
     */
    public function getClientAddress() {
        $field_types = array('address','house','corpus','flat');
        
        $addr = $this->getUserField($this->getConfig('address'));
        if ($this->getConfig('house') !== 'Не выбрано')
            $addr .= ', д. '. $this->getUserField($this->getConfig('house'));
        if ($this->getConfig('corpus') !== 'Не выбрано')
            $addr .= ', корп. '. $this->getUserField($this->getConfig('corpus'));
        if ($this->getConfig('flat') !== 'Не выбрано')
            $addr .= ', кв. '. $this->getUserField($this->getConfig('flat'));
        $pat_street = "/^(.+?)(?:д[\.ом])?\s+(\d+)\s+/is";
        if (strpos($addr,',') === false) {
            
            if (preg_match($pat_street,$addr, $matches)){
                //print_r($matches);
                if (isset($matches[1]))
                    $addr = str_replace($matches[1],$matches[1].',',$addr);
            }
        } 
        if ($addr) $ar = explode(',',$addr);
        else $ar = array();
        //print_r($ar);
        $return = array();
        $street = '';
        $house  = '';
        $corp  = '';
        $flat  = '';
        if (count($ar)){
            foreach($ar as $k => $v){
                $ar[$k] = trim($v);
                if ($k >0 && strpos($v,'корп'))
                    $corp = trim($v);
                if ($k >0 && (strpos($v,'кв') || strpos($v,'оф')))
                    $flat = trim($v);
                }
        }else return $addr;
        if (preg_match($pat_street,$ar[0], $matches)){
            //print_r($matches);
            if (isset($matches[1]))
                $street = $matches[1];
            if (isset($matches[2]))
                $house = $matches[2];
        }
        else {  
            if (isset($ar[0]))
                $street = $ar[0];
            if (isset($ar[1]))
                $house = $ar[1];
            }
        if (trim($street))
            $street = trim(str_replace(array('улица','ул.'),'',$street));
        else $street = '--';
        $street = trim($street);
        if ($house)
            $house = trim(str_replace(array('дом','д.','д'),'',$house));
        else $house = '--';
        if ($corp)
            $corp = trim(str_replace(array('корпус','корп.','корп'),'',$corp));
        //else $corp = '--';
        if ($flat)
            $flat = trim(str_replace(array('квартира','кв.','кв',),'',$flat));
        else $flat = '--';    
        
        $house = preg_replace(array('/корп[.ус]\s+(\d+)/','/кв[.атира]\s+(\d+)/'),'',$house);
        $corp = preg_replace(array('/д[.ом]\s+(\d+)/','/кв[.атира]\s+(\d+)/'),'',$corp);
        $flat = preg_replace(array('/корп[.ус]\s+(\d+)/','/д[.ом]\s+(\d+)/'),'',$flat);
        //if (strlen($street)>0 && strlen($house)>0 && strlen($flat)>0) 
          //  $this->address_correct = true;
            
        $return[] = $street;
        $return[] = $house;
        $return[] = $corp;
        $return[] = $flat;
        
        return $return;
    }

    /**
     * Верните id города в системе DDelivery
     * @return int
     */
    public function getClientCityId(){
        if ($this->getUserField($this->getConfig('city')) !== ''){
            $cityRes = $this->ddeliveryUI->sdk->getAutoCompleteCity($this->getUserField($this->getConfig('city')));
            //print_r($cityRes->response);
            return $cityRes->response[0]['_id'];    
        }
        // Если нет информации о городе, оставьте вызов родительского метода.
        return parent::getClientCityId();
    }
    

    /**
     * Меняет статус внутреннего заказа cms
     *
     * @param $cmsOrderID - id заказа
     * @param $status - статус заказа для обновления
     *
     * @return bool
     */
    public function setCmsOrderStatus($cmsOrderID, $status){
        //$q = "UPDATE ". DB_PREFIX."orders set order_status_id='$status' where order_id='$cmsOrderId'";
        //$this->db->query($q);
    }

    /**
     * Метод будет вызван когда пользователь закончит выбор способа доставки
     *
     * @param \DDelivery\Order\DDeliveryOrder $order
     * @return void
     */
    public function onFinishChange($order){
        $point = $order->getPoint();
        $session = JFactory::getSession();
        $session->set('order_id',(int)$order->localId,'ddelivery');
    }
    
    /**
     * Возможность что - нибудь добавить к информации
     * при окончании оформления заказа
     *
     * @param $order DDeliveryOrder
     * @param $resultArray
     */
    public function onFinishResultReturn( $order, $resultArray ){
        $session = JFactory::getSession();
        $session->set('cost',$resultArray['clientPrice'] .' руб.','ddelivery');
        //if VirtueMartCart
        $session->set('comment',$resultArray['comment'],'ddelivery');
        
        return $resultArray;
    }


    /**
     * Должен вернуть те компании которые  показываются в курьерке
     *
     * @return int[]
     */
    public function filterCompanyPointCourier(){
        $return = array();
        for ($i = 0; $i< 100; $i++)
            if ((int)$this->getConfig("courier_$i") == $i)
                $return[] = $i;
        return $return;
        return array	(4,21,29,23,27,28,20,30,31,11,16,22,17,3,14,1,13,18,6,
                         26,25,24,7,35,36,37,39,40,42,43,44,45,46,47,48,49);
        //$return = $this->getConfig('ddelivery_cur_companies');
        //if (!is_array($return)) $return = array();
        //return $return;
        
    }

    /**
     * Должен вернуть те компании которые  показываются в самовывозе
     *
     * @return int[]
     */
    public function filterCompanyPointSelf(){
        $return = array();
        for ($i = 0; $i< 100; $i++)
            if ((int)$this->getConfig("pvz_$i") == $i)
                $return[] = $i;
        $return[] = 1002901;
        $return[] = 1002902;
        $return[] = 1002903;
        $return[] = 1002904;
        $return[] = 1002905;
        $return[] = 1002906;
        $return[] = 1002907;
        $return[] = 1002908;
        $return[] = 1002909;
        $return[] = 1002910;
        return $return;
        return array	(4,21,29,23,27,28,20,30,31,11,16,22,17,3,14,1,13,18,6,
                         26,25,24,7,35,36,37,39,40,42,43,44,45,46,47,48,49);
        $return = $this->getConfig('ddelivery_pvz_companies');
        if (!is_array($return)) $return = array();
        return $return;
    }

    /**
     * Возвращаем способ оплаты  c наложенным платежем для курьера
     *
     * либо константа \DDelivery\Adapter\PluginFilters::PAYMENT_PREPAYMENT - если способ облаты - предоплата,
     * либо константа \DDelivery\Adapter\PluginFilters::PAYMENT_POST_PAYMENT -  если способ оплаты оплата при получении
     *
     * @param $order DDeliveryOrder
     *
     * @return int
     */
    public function filterPointByPaymentTypeCourier( $order ){
        if ((int)$order->paymentVariant == (int)$this->getConfig('pvz_payment'))
            return \DDelivery\Adapter\PluginFilters::PAYMENT_POST_PAYMENT;
        else return \DDelivery\Adapter\PluginFilters::PAYMENT_PREPAYMENT;
    }

    /**
     * Возвращаем способ оплаты  c наложенным платежем для самовывоза
     *
     * либо константа \DDelivery\Adapter\PluginFilters::PAYMENT_PREPAYMENT - если способ облаты - предоплата,
     * либо константа \DDelivery\Adapter\PluginFilters::PAYMENT_POST_PAYMENT -  если способ оплаты оплата при получении
     *
     * @param $order DDeliveryOrder
     *
     * @return int
     */
    public function filterPointByPaymentTypeSelf( $order ){
        if ((int)$order->paymentVariant == (int)$this->getConfig('curier_payment'))
            return \DDelivery\Adapter\PluginFilters::PAYMENT_POST_PAYMENT;
        else return \DDelivery\Adapter\PluginFilters::PAYMENT_PREPAYMENT;
    }

    

    /**
     * Метод возвращает настройки оплаты фильтра которые должны быть собраны из админки
     *
     * @return array
     */
    public function getIntervalsByPoint(){
        $return = array();
        $pay_type = array(
            "client" => self::INTERVAL_RULES_CLIENT_ALL,
            "allmag" => self::INTERVAL_RULES_MARKET_ALL,
            "magpercent" => self::INTERVAL_RULES_MARKET_PERCENT,
            "fix" => self::INTERVAL_RULES_MARKET_AMOUNT,
        );
        $avaible_types = array(
            self::INTERVAL_RULES_CLIENT_ALL,
            self::INTERVAL_RULES_MARKET_ALL,
            self::INTERVAL_RULES_MARKET_PERCENT,
            self::INTERVAL_RULES_MARKET_AMOUNT,
        );
       
        for ($i = 1; $i<4; $i++){
            $return[] = array(
                'min' => (double)$this->getConfig("cond$i"), 
                'max'=>  (double)$this->getConfig("cond{$i}_2"), 
                'type'=> (in_array($pay_type[$this->getConfig("condres{$i}")], $avaible_types))?$pay_type[$this->getConfig("condres{$i}")]:self::INTERVAL_RULES_CLIENT_ALL, 
                'amount'=>(double)$this->getConfig("condperbymag{$i}"));
        }
        return $return;
        
    }

    /**
     * Тип округления
     * @return int
     */
    public function aroundPriceType(){
        switch ($this->getConfig('aroundpricetype')){
            case 'round': return self::AROUND_ROUND;
            case 'floor': return self::AROUND_FLOOR;
            case 'ceil': return self::AROUND_CEIL;
            default: return self::AROUND_ROUND;
        }
    }

    /**
     * Шаг округления
     * @return float
     */
    public function aroundPriceStep(){
        return $this->getConfig('aroundpricestep'); // До 50 копеек
    }

    /**
     * описание собственных служб доставки
     * @return string
     */
    public function getCustomPointsString(){
        return '';
    }

   

    /**
     * Возвращает поддерживаемые магазином способы доставки
     * @return array
     */
    public function getSupportedType(){
        switch ($this->getConfig('services')){
            case 'selfandcurier': return array(
                                   \DDelivery\Sdk\DDeliverySDK::TYPE_COURIER,
                                    \DDelivery\Sdk\DDeliverySDK::TYPE_SELF
                                ); 
            break;
            case 'self': return array(
                                    \DDelivery\Sdk\DDeliverySDK::TYPE_SELF
                                ); 
            break;
            case 'curier': return array(
                                   \DDelivery\Sdk\DDeliverySDK::TYPE_COURIER,
                                ); 
            break;
            default: return array(
                                   \DDelivery\Sdk\DDeliverySDK::TYPE_COURIER,
                                    \DDelivery\Sdk\DDeliverySDK::TYPE_SELF
                                ); 
        }
        
    }

    

    /**
     * Получить доступные способы оплаты для Самовывоза ( можно анализировать содержимое order )
     * @param $order DDeliveryOrder
     * @return array
     */
    public function getSelfPaymentVariants( $order ){
        return array();
    }

    /**
     * Получить доступные способы оплаты для курьера ( можно анализировать содержимое order )
     * @param $order DDeliveryOrder
     * @return array
     */
    public function getCourierPaymentVariants( $order ){
        return array();
    }

    /**
     *
     * Используется при отправке заявки на сервер DD для указания стартового статуса
     *
     * Если true то заявка в сервисе DDelivery будет выставлена в статус "Подтверждена",
     * если false то то заявка в сервисе DDelivery будет выставлена в статус "В обработке"
     *
     * @param mixed $localStatus
     *
     * @return bool
     */
    public function isConfirmedStatus( $localStatus ){
        return ($localStatus == $this->getConfig('status_confirmed'));
    }
    
    /**
     * При отправке заказа на сервер дделивери идет
     * проверка  статуса  выставленого в настройках
     *
     * @param mixed $cmsStatus
     * @return bool|void
     */
    public function isStatusToSendOrder( $cmsStatus ){
        return ($cmsStatus == $this->getConfig('status_confirmed') || 
                    $cmsStatus == $this->getConfig('status_in_progress'));
    }


    /**
     * Возвращает бинарную маску обязательных полей для курьера
     * Если редактирование не включено, но есть обязательность то поле появится
     * Если редактируемых полей не будет то пропустим шаг
     * @return int
     */
    public function getCourierRequiredFields(){
        // ВВести все обязательно, кроме корпуса
        //if ((int)$this->getConfig('ddelivery_show_contact_form') == 0 && $this->address_correct) return false;
        return self::FIELD_EDIT_FIRST_NAME | self::FIELD_REQUIRED_FIRST_NAME
        | self::FIELD_EDIT_PHONE | self::FIELD_REQUIRED_PHONE
        | self::FIELD_EDIT_ADDRESS | self::FIELD_REQUIRED_ADDRESS
        | self::FIELD_EDIT_ADDRESS_HOUSE | self::FIELD_REQUIRED_ADDRESS_HOUSE
        | self::FIELD_EDIT_ADDRESS_HOUSING
        | self::FIELD_EDIT_ADDRESS_FLAT | self::FIELD_REQUIRED_ADDRESS_FLAT | self::FIELD_EDIT_EMAIL;
    }

    /**
     * Возвращает бинарную маску обязательных полей для пунктов самовывоза
     * Если редактирование не включено, но есть обязательность то поле появится
     * Если редактируемых полей не будет то пропустим шаг
     * @return int
     */
    public function getSelfRequiredFields(){
        //if ((int)$this->getConfig('ddelivery_show_contact_form') == 0 && $this->address_correct) return false;
        return self::FIELD_EDIT_FIRST_NAME | self::FIELD_REQUIRED_FIRST_NAME
        | self::FIELD_EDIT_PHONE | self::FIELD_REQUIRED_PHONE | self::FIELD_EDIT_EMAIL;
    }

    /**
     * Получить название шаблона для сдк ( разные цветовые схемы )
     *
     * @return string
     */
    public function getTemplate(){
        return $this->getConfig('theme','default');
    }
    
    /**
     * Должен вернуть url до каталога с статикой
     * @return string
     */
    public function getStaticPath(){
        return 'assets/';
    }

    /**
     * Возвращает путь до файла базы данных, положите его в место не доступное по прямой ссылке
     * @return string
     */
    public function getPathByDB(){
        return __DIR__.'db/db.sqlite';
    }
    
    /**
     * Настройки базы данных
     * @return array
     */
    public function getDbConfig(){
        $host = JFactory::getConfig()->get('host');
        $dbname = JFactory::getConfig()->get('db');
        $user = JFactory::getConfig()->get('user');
        $password = JFactory::getConfig()->get('password');
        $pref = JFactory::getConfig()->get('dbprefix');
        return array( 
            'pdo' => new \PDO('mysql:host='.$host.';dbname='.$dbname, $user, $password, array(\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")),
            'prefix' => $pref.'ps_dd_',
        );

        return array(
            'type' => self::DB_SQLITE,
            'dbPath' => $this->getPathByDB(),
            'prefix' => '',
        );

        return array(
            'type' => self::DB_MYSQL,
            'dsn' => 'mysql:host=localhost;dbname=ddelivery',
            'user' => 'root',
            'pass' => '0',
            'prefix' => '',
        );
    }
    
    public function getConfig($param, $default = null){
        $db = JFactory::getDbo();
        $db->setQuery("select shipment_params from #__virtuemart_shipmentmethods where shipment_element = 'ddelivery'");
        $result = $db->loadResult(); 
        $params = explode('|',$result);
        $result = array();
        if (count($params))
            foreach($params as $k=> $v){
                if (strpos($v,'=')!==false){
                    $t = explode('=',$v);
                    if (count($t) == 2)
                        $result[$t[0]] = json_decode($t[1],true);
                    }
                
            }
        return (isset($result[$param]))?$result[$param]:$default;
    }
    
    public function getPhpScriptURL(){
        $url = array();
        $vmcart = unserialize(JFactory::getSession()->get('vmcart','','vm')) ;     
        /*if (is_array($vmcart->BT)){
            JFactory::getSession()->set('firstname',$vmcart->BT['fio'],'dd_user');
            JFactory::getSession()->set('address',$vmcart->BT['address_1'],'dd_user');
            JFactory::getSession()->set('email',$vmcart->BT['email'],'dd_user');
            JFactory::getSession()->set('telephone',$vmcart->BT['phone_2'],'dd_user');
        }*/
        
        $last_name = $this->getConfig('last_name');
        $first_name = $this->getConfig('first_name');
        $email = $this->getConfig('email');
        $phone = $this->getConfig('phone');
        $city = $this->getConfig('city');
        $address = $this->getConfig('address');
        $house = $this->getConfig('house');
        $corpus = $this->getConfig('corpus');
        $flat = $this->getConfig('flat');
        if ($last_name !== 'Не выбрано')
            $url[$last_name] = JRequest::getString($last_name);
        if ($first_name !== 'Не выбрано')
            $url[$first_name] = JRequest::getString($first_name);
        if ($email !== 'Не выбрано')
            $url[$email] = JRequest::getString($email);
        if ($phone !== 'Не выбрано')
            $url[$phone] = JRequest::getString($phone);
        if ($city !== 'Не выбрано')
            $url[$city] = JRequest::getString($city);
        if ($address !== 'Не выбрано')
            $url[$address] = JRequest::getString($address);
        if ($house !== 'Не выбрано')
            $url[$house] = JRequest::getString($house);
        if ($corpus !== 'Не выбрано')
            $url[$corpus] = JRequest::getString($corpus);
        if ($flat !== 'Не выбрано')
            $url[$flat] = JRequest::getString($flat);
            
        return 'ajax.php?'.http_build_query($url);
    }
    
    public function getUserField($field){
        $last_name = $this->getConfig('last_name');
        $first_name = $this->getConfig('first_name');
        $email = $this->getConfig('email');
        $phone = $this->getConfig('phone');
        $city = $this->getConfig('city');
        $address = $this->getConfig('address');
        $house = $this->getConfig('house');
        $corpus = $this->getConfig('corpus');
        $flat = $this->getConfig('flat');
        if ($last_name !== 'Не выбрано')
            $return[$last_name] = JRequest::getString($last_name);
        if ($first_name !== 'Не выбрано')
            $return[$first_name] = JRequest::getString($first_name);
        if ($email !== 'Не выбрано')
            $return[$email] = JRequest::getString($email);
        if ($phone !== 'Не выбрано')
            $return[$phone] = JRequest::getString($phone);
        if ($city !== 'Не выбрано')
            $return[$city] = JRequest::getString($city);
        if ($address !== 'Не выбрано')
            $return[$address] = JRequest::getString($address);
        if ($house !== 'Не выбрано')
            $return[$house] = JRequest::getString($house);
        if ($corpus !== 'Не выбрано')
            $return[$corpus] = JRequest::getString($corpus);
        if ($flat !== 'Не выбрано')
            $return[$flat] = JRequest::getString($flat);
        /*$return = array(
            'last_name' => '',
            'first_name' => JFactory::getSession()->get('firstname','','dd_user'),
            'email' => JFactory::getSession()->get('email','','dd_user'),
            'phone' => JFactory::getSession()->get('telephone','','dd_user'),
            'address' => JFactory::getSession()->get('address','','dd_user'),
        );*/
        //print_r($this->getConfig('phone'));
        if (isset($return[$field]) && $return[$field])    
            return $return[$field];
        
        if (!class_exists('VirtueMartCart'))
			require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
		$cart = VirtueMartCart::getCart();
        $cart->setCartIntoSession();
        if (isset($cart->ST) && is_array($cart->ST) && isset($cart->ST[$field]))
            return $cart->ST[$field];
        elseif (isset($cart->BT) && is_array($cart->BT) && isset($cart->BT[$field]))
            return $cart->BT[$field];
        //print_r($cart);
        //$cart->prepareCartViewData();
        $userModel = VmModel::getModel('user');
        $user = $userModel->getUser();
        if (count($user->userInfo)){
            foreach($user->userInfo as $v){
                //echo '<pre>$user: '.print_r($v->$field,1).'</pre>';
                return $v->$field;     
                
            }
        }
        
        
    }

    /**
     *
     * Получить массив с кастомными курьерскими компаниями
     *
     * @return array
     */
    public function getCustomCourierCompanies(){
        //return array();
        return array(
            9008101 => array(
                'city' => 151185,
                'delivery_company' => 9008101,
                'delivery_company_name' => 'SMOLA20',
                'pickup_price' => 300,
                'delivery_price' => 300,
                'delivery_price_fee' => 0,
                'declared_price_fee' => 0,
                'delivery_time_min' => 1,
                'delivery_time_max' => 5,
                'delivery_time_avg' => 3,
                'return_price' => 0,
                'return_client_price' => 0,
                'return_partial_price' => 0,
                'total_price' => 300
            )
        );
    }

    /**
     *
     * Получить массив с кастомными компаниями самовывоза
     *
     * @return array
     */
    public function getCustomSelfCompanies(){
        //return array();
        return array(
            1002901 => array(
                'city' => 151185,
                'delivery_company' => 1002901,
                'delivery_company_name' => 'Офис SMOLA20',
                'pickup_price' => 0,
                'delivery_price' => 0,
                'delivery_price_fee' => 0,
                'declared_price_fee' => 0,
                'delivery_time_min' => 2,
                'delivery_time_max' => 2,
                'delivery_time_avg' => 2,
                'return_price' => 0,
                'return_client_price' => 0,
                'return_partial_price' => 0,
                'total_price' => 0
            ),
            1002902 => array(
                'city' => 151185,
                'delivery_company' => 1002902,
                'delivery_company_name' => 'ГЛАВПУНКТ м. Московская, ул. Алтайская, 16',
                'pickup_price' => 0,
                'delivery_price' => 100,
                'delivery_price_fee' => 0,
                'declared_price_fee' => 0,
                'delivery_time_min' => 1,
                'delivery_time_max' => 1,
                'delivery_time_avg' => 1,
                'return_price' => 0,
                'return_client_price' => 0,
                'return_partial_price' => 0,
                'total_price' => 100
            ),
            1002903 => array(
                'city' => 151185,
                'delivery_company' => 1002903,
                'delivery_company_name' => 'ГЛАВПУНКТ м. Купчино, Балканская пл., д. 5, Лит О, ТЦ Балканский 6',
                'pickup_price' => 0,
                'delivery_price' => 100,
                'delivery_price_fee' => 0,
                'declared_price_fee' => 0,
                'delivery_time_min' => 1,
                'delivery_time_max' => 1,
                'delivery_time_avg' => 1,
                'return_price' => 0,
                'return_client_price' => 0,
                'return_partial_price' => 0,
                'total_price' => 100
            ),
            1002904 => array(
                'city' => 151185,
                'delivery_company' => 1002904,
                'delivery_company_name' => 'ГЛАВПУНКТ м. Проспект Просвещения, проспект Энгельса, дом 133 в магазине Лайм',
                'pickup_price' => 0,
                'delivery_price' => 100,
                'delivery_price_fee' => 0,
                'declared_price_fee' => 0,
                'delivery_time_min' => 1,
                'delivery_time_max' => 1,
                'delivery_time_avg' => 1,
                'return_price' => 0,
                'return_client_price' => 0,
                'return_partial_price' => 0,
                'total_price' => 100
            ),
            1002905 => array(
                'city' => 151185,
                'delivery_company' => 1002905,
                'delivery_company_name' => 'ГЛАВПУНКТ м. Пионерская, Коломяжский пр., д.15, к.2',
                'pickup_price' => 0,
                'delivery_price' => 100,
                'delivery_price_fee' => 0,
                'declared_price_fee' => 0,
                'delivery_time_min' => 0,
                'delivery_time_max' => 0,
                'delivery_time_avg' => 0,
                'return_price' => 0,
                'return_client_price' => 0,
                'return_partial_price' => 0,
                'total_price' => 100
            ),
            1002906 => array(
                'city' => 151185,
                'delivery_company' => 1002906,
                'delivery_company_name' => 'ГЛАВПУНКТ м. Чернышевская, Фурштатская улица, дом 25',
                'pickup_price' => 0,
                'delivery_price' => 100,
                'delivery_price_fee' => 0,
                'declared_price_fee' => 0,
                'delivery_time_min' => 1,
                'delivery_time_max' => 1,
                'delivery_time_avg' => 1,
                'return_price' => 0,
                'return_client_price' => 0,
                'return_partial_price' => 0,
                'total_price' => 100
            ),
            1002907 => array(
                'city' => 151185,
                'delivery_company' => 1002907,
                'delivery_company_name' => 'ГЛАВПУНКТ м. Проспект Ветеранов, бульвар Новаторов, дом 67 корпус 2',
                'pickup_price' => 0,
                'delivery_price' => 100,
                'delivery_price_fee' => 0,
                'declared_price_fee' => 0,
                'delivery_time_min' => 1,
                'delivery_time_max' => 1,
                'delivery_time_avg' => 1,
                'return_price' => 0,
                'return_client_price' => 0,
                'return_partial_price' => 0,
                'total_price' => 100
            ),
            1002908 => array(
                'city' => 151185,
                'delivery_company' => 1002908,
                'delivery_company_name' => 'ГЛАВПУНКТ м. Академическая проспект Науки, д.17, к.2',
                'pickup_price' => 0,
                'delivery_price' => 100,
                'delivery_price_fee' => 0,
                'declared_price_fee' => 0,
                'delivery_time_min' => 1,
                'delivery_time_max' => 1,
                'delivery_time_avg' => 1,
                'return_price' => 0,
                'return_client_price' => 0,
                'return_partial_price' => 0,
                'total_price' => 100
            ),
            1002909 => array(
                'city' => 151185,
                'delivery_company' => 1002909,
                'delivery_company_name' => 'ГЛАВПУНКТ м. Ладожская Заневский проспект, дом 65, к.5, лит.А ТК "Платформа"',
                'pickup_price' => 0,
                'delivery_price' => 100,
                'delivery_price_fee' => 0,
                'declared_price_fee' => 0,
                'delivery_time_min' => 1,
                'delivery_time_max' => 1,
                'delivery_time_avg' => 1,
                'return_price' => 0,
                'return_client_price' => 0,
                'return_partial_price' => 0,
                'total_price' => 100
            ),
            1002910 => array(
                'city' => 151185,
                'delivery_company' => 1002910,
                'delivery_company_name' => 'ГЛАВПУНКТ м. Василеостровская, 6я линия, д. 25, под арку',
                'pickup_price' => 0,
                'delivery_price' => 100,
                'delivery_price_fee' => 0,
                'declared_price_fee' => 0,
                'delivery_time_min' => 1,
                'delivery_time_max' => 1,
                'delivery_time_avg' => 1,
                'return_price' => 0,
                'return_client_price' => 0,
                'return_partial_price' => 0,
                'total_price' => 100
            )
        );
    }

    /**
     *
     * Получить массив с кастомными точками самовывоза
     *
     * @return array
     */
    public function getCustomSelfPoints(){
        //return array();
        return array(
            1002901 => array(
                '_id' => 1002901,
                'name' => 'Офис SMOLA20',
                'city_id' => 151185,
                'city' => 'Санкт-Петербург',
                'region' => 'г. Санкт-Петербург',
                'region_id' => '78',
                'city_type' => 'г',
                'postal_code' => '190000',
                'area' =>'',
                'kladr' => '78000000000',
                'company' => 'Smola 2.0',
                'company_id' => 1002901,
                'company_code' => 1002901,
                'metro' => '',
                'description_in' =>'',
                'description_out' =>'',
                'indoor_place' =>'БЦ "Гепард" офис 525',
                'address' => 'Московские ворота ул. Коли Томчака 28А',
                'schedule' => 'Пн.-Пт. 12.00 - 18.00',
                'longitude' => '30.3251982',
                'latitude' => '59.8863184',
                'type' => 2,
                'status' => 2,
                'has_fitting_room' => '',
                'is_cash' => 1,
                'is_card' => ''
            ),
            1002902 => array(
                '_id' => 1002902,
                'name' => 'ГЛАВПУНКТ м. Московская, ул. Алтайская, 16',
                'city_id' => 151185,
                'city' => 'Санкт-Петербург',
                'region' => 'г. Санкт-Петербург',
                'region_id' => '78',
                'city_type' => 'г',
                'postal_code' => '190000',
                'area' =>'',
                'kladr' => '78000000000',
                'company' => 'Smola 2.0',
                'company_id' => 1002902,
                'company_code' => 1002902,
                'metro' => 'м. Московская',
                'description_in' =>'',
                'description_out' =>'',
                'indoor_place' =>'',
                'address' => 'ул. Алтайская, 16',
                'schedule' => 'Ежедневно 10.00 - 21.00',
                'longitude' => '30.327309',
                'latitude' => '59.849718',
                'type' => 2,
                'status' => 2,
                'has_fitting_room' => '',
                'is_cash' => 1,
                'is_card' => ''
            ),
            1002903 => array(
                '_id' => 1002903,
                'name' => 'ГЛАВПУНКТ м. Купчино, Балканская пл., д. 5, Лит О, ТЦ Балканский 6',
                'city_id' => 151185,
                'city' => 'Санкт-Петербург',
                'region' => 'г. Санкт-Петербург',
                'region_id' => '78',
                'city_type' => 'г',
                'postal_code' => '190000',
                'area' =>'',
                'kladr' => '78000000000',
                'company' => 'Smola 2.0',
                'company_id' => 1002903,
                'company_code' => 1002903,
                'metro' => 'м. Купчино',
                'description_in' =>'',
                'description_out' =>'',
                'indoor_place' =>'ТЦ Балканский 6',
                'address' => 'Балканская пл., д. 5, Лит О',
                'schedule' => 'Ежедневно 10.00 - 21.00',
                'longitude' => '30.379',
                'latitude' => '59.8275',
                'type' => 2,
                'status' => 2,
                'has_fitting_room' => '',
                'is_cash' => 1,
                'is_card' => ''
            ),
            1002904 => array(
                '_id' => 1002904,
                'name' => 'ГЛАВПУНКТ м. Проспект Просвещения, проспект Энгельса, дом 133 в магазине Лайм',
                'city_id' => 151185,
                'city' => 'Санкт-Петербург',
                'region' => 'г. Санкт-Петербург',
                'region_id' => '78',
                'city_type' => 'г',
                'postal_code' => '190000',
                'area' =>'',
                'kladr' => '78000000000',
                'company' => 'Smola 2.0',
                'company_id' => 1002904,
                'company_code' => 1002904,
                'metro' => 'м. Проспект Просвещения',
                'description_in' =>'',
                'description_out' =>'',
                'indoor_place' =>'в магазине Лайм',
                'address' => 'проспект Энгельса, дом 133',
                'schedule' => 'Ежедневно 10.00 - 21.00',
                'longitude' => '30.333276',
                'latitude' => '60.048802',
                'type' => 2,
                'status' => 2,
                'has_fitting_room' => '',
                'is_cash' => 1,
                'is_card' => ''
            ),
            1002905 => array(
                '_id' => 1002905,
                'name' => 'ГЛАВПУНКТ м. Пионерская, Коломяжский пр., д.15, к.2',
                'city_id' => 151185,
                'city' => 'Санкт-Петербург',
                'region' => 'г. Санкт-Петербург',
                'region_id' => '78',
                'city_type' => 'г',
                'postal_code' => '190000',
                'area' =>'',
                'kladr' => '78000000000',
                'company' => 'Smola 2.0',
                'company_id' => 1002905,
                'company_code' => 1002905,
                'metro' => 'м. Пионерская',
                'description_in' =>'',
                'description_out' =>'',
                'indoor_place' =>'',
                'address' => 'Коломяжский пр., д.15, к.2',
                'schedule' => 'Ежедневно 10.00 - 21.00',
                'longitude' => '30.2988653',
                'latitude' => '60.0017122',
                'type' => 2,
                'status' => 2,
                'has_fitting_room' => '',
                'is_cash' => 1,
                'is_card' => ''
            ),
            1002906 => array(
                '_id' => 1002906,
                'name' => 'ГЛАВПУНКТ м. Чернышевская, Фурштатская улица, дом 25',
                'city_id' => 151185,
                'city' => 'Санкт-Петербург',
                'region' => 'г. Санкт-Петербург',
                'region_id' => '78',
                'city_type' => 'г',
                'postal_code' => '190000',
                'area' =>'',
                'kladr' => '78000000000',
                'company' => 'Smola 2.0',
                'company_id' => 1002906,
                'company_code' => 1002906,
                'metro' => 'м. Чернышевская',
                'description_in' =>'',
                'description_out' =>'',
                'indoor_place' =>'',
                'address' => 'Фурштатская улица, дом 25',
                'schedule' => 'Ежедневно 10.00 - 21.00',
                'longitude' => '30.3572376',
                'latitude' => '59.9450066',
                'type' => 2,
                'status' => 2,
                'has_fitting_room' => '',
                'is_cash' => 1,
                'is_card' => ''
            ),
            1002907 => array(
                '_id' => 1002907,
                'name' => 'ГЛАВПУНКТ м. Проспект Ветеранов, бульвар Новаторов, дом 67 корпус 2',
                'city_id' => 151185,
                'city' => 'Санкт-Петербург',
                'region' => 'г. Санкт-Петербург',
                'region_id' => '78',
                'city_type' => 'г',
                'postal_code' => '190000',
                'area' =>'',
                'kladr' => '78000000000',
                'company' => 'Smola 2.0',
                'company_id' => 1002907,
                'company_code' => 1002907,
                'metro' => 'м. Проспект Ветеранов',
                'description_in' =>'',
                'description_out' =>'',
                'indoor_place' =>'',
                'address' => 'бульвар Новаторов, дом 67 корпус 2',
                'schedule' => 'Ежедневно 10.00 - 21.00',
                'longitude' => '30.2605087',
                'latitude' => '59.84303',
                'type' => 2,
                'status' => 2,
                'has_fitting_room' => '',
                'is_cash' => 1,
                'is_card' => ''
            ),
            1002908 => array(
                '_id' => 1002908,
                'name' => 'ГЛАВПУНКТ м. Академическая проспект Науки, д.17, к.2',
                'city_id' => 151185,
                'city' => 'Санкт-Петербург',
                'region' => 'г. Санкт-Петербург',
                'region_id' => '78',
                'city_type' => 'г',
                'postal_code' => '190000',
                'area' =>'',
                'kladr' => '78000000000',
                'company' => 'Smola 2.0',
                'company_id' => 1002908,
                'company_code' => 1002908,
                'metro' => 'м. Академическая',
                'description_in' =>'',
                'description_out' =>'',
                'indoor_place' =>'',
                'address' => 'м. Академическая проспект Науки, д.17, к.2',
                'schedule' => 'Ежедневно 10.00 - 21.00',
                'longitude' => '30.386878',
                'latitude' => '60.014675',
                'type' => 2,
                'status' => 2,
                'has_fitting_room' => '',
                'is_cash' => 1,
                'is_card' => ''
            ),
            1002909 => array(
                '_id' => 1002909,
                'name' => 'ГЛАВПУНКТ м. Ладожская Заневский проспект, дом 65, к.5, лит.А ТК "Платформа"',
                'city_id' => 151185,
                'city' => 'Санкт-Петербург',
                'region' => 'г. Санкт-Петербург',
                'region_id' => '78',
                'city_type' => 'г',
                'postal_code' => '190000',
                'area' =>'',
                'kladr' => '78000000000',
                'company' => 'Smola 2.0',
                'company_id' => 1002909,
                'company_code' => 1002909,
                'metro' => 'м. Ладожская',
                'description_in' =>'',
                'description_out' =>'',
                'indoor_place' =>'лит.А ТК "Платформа"',
                'address' => 'Заневский проспект, дом 65, к.5',
                'schedule' => 'Ежедневно 10.00 - 21.00',
                'longitude' => '30.4340913',
                'latitude' => '59.9324998',
                'type' => 2,
                'status' => 2,
                'has_fitting_room' => '',
                'is_cash' => 1,
                'is_card' => ''
            ),
            1002910 => array(
                '_id' => 1002910,
                'name' => 'ГЛАВПУНКТ м. Василеостровская, 6я линия, д. 25, под арку',
                'city_id' => 151185,
                'city' => 'Санкт-Петербург',
                'region' => 'г. Санкт-Петербург',
                'region_id' => '78',
                'city_type' => 'г',
                'postal_code' => '190000',
                'area' =>'',
                'kladr' => '78000000000',
                'company' => 'Smola 2.0',
                'company_id' => 1002910,
                'company_code' => 1002910,
                'metro' => 'м. Василеостровская',
                'description_in' =>'',
                'description_out' =>'',
                'indoor_place' =>'под арку',
                'address' => '6я линия, д. 25',
                'schedule' => 'Ежедневно 10.00 - 21.00',
                'longitude' => '30.2802853',
                'latitude' => '59.9420267',
                'type' => 2,
                'status' => 2,
                'has_fitting_room' => '',
                'is_cash' => 1,
                'is_card' => ''
            ),

        );
    }
    
    public function setDDeliveryUI($ddeliveryUI){
        $this->ddeliveryUI = $ddeliveryUI;
    }

}