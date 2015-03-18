<?php
defined('_JEXEC') or die();
 
class JElementStatus extends JElement
{
 
	function fetchElement($name, $value, &$node, $control_name)
	{	
		$db			=& JFactory::getDBO();
		
		$query = 'SELECT a.order_status_code as id, a.order_status_name AS text '
		. ' FROM #__virtuemart_orderstates AS a where a.published=1';
		
		$options = array();
		/*
		$k = new stdClass();
		$k->id = 1;
		$k->text = "Все способы";
		*/
		$db->setQuery( $query );
		$options = $db->loadObjectList( );
		echo $db->getErrorMsg();
		//print_r($options);
		//array_unshift($options,$k);
		return JHtmlSelect::genericlist($options, ''.$control_name.'['.$name.']', 'class="inputbox"', 'id', 'text', $value, $control_name.$name,true );
		
	}
}
?>