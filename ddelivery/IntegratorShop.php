<?php
/**
 * Created by PhpStorm.
 * User: mrozk
 * Date: 15.05.14
 * Time: 23:14
 */
error_reporting(E_ALL);
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
                $price      = (double)$prod->product_override_price;
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
        //print_r($addr);
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
     *
     * Перед возвратом точек самовывоза фильтровать их по определенным правилам
     *
     * @param $companyArray
     * @param DDeliveryOrder $order
     * @return mixed
     */
    public function finalFilterSelfCompanies( $companyArray, DDeliveryOrder $order ){
        return $companyArray;
    }

    /**
     *
     *  Перед возвратом компаний курьерок фильтровать их по определенным правилам
     *
     * @param $companyArray
     * @param DDeliveryOrder $order
     * @return mixed
     */
    public function finalFilterCourierCompanies( $companyArray, DDeliveryOrder $order ){
        return $companyArray;
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
        //echo '<pre>'.print_r($result,1).'</pre>';
        
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
    

}