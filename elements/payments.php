<?php
defined('_JEXEC') or die();
 
class JElementPayments extends JElement
{
 
	function fetchElement($name, $value, &$node, $control_name)
	{	
		$db			=& JFactory::getDBO();
		$doc 		=& JFactory::getDocument();
		
		
		$query = 'SELECT a.virtuemart_paymentmethod_id as id, b.payment_name AS text '
		. ' FROM #__virtuemart_paymentmethods as a left join #__virtuemart_paymentmethods_ru_ru AS b on a.virtuemart_paymentmethod_id=b.virtuemart_paymentmethod_id'
		. ' WHERE a.published = 1'
		
		;
		$options = array();
		$k = new stdClass();
		$k->id = 1;
		$k->text = "Все способы";
		
		$db->setQuery( $query );
		$options = $db->loadObjectList( );
		//print_r($options);
		array_unshift($options,$k);
		return JHTML::_('select.genericlist',  $options, ''.$control_name.'['.$name.']', 'class="inputbox"', 'id', 'text', $value, $control_name.$name );
		
	}
}
?>