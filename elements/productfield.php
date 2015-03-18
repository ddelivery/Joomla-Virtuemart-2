<?php
defined('_JEXEC') or die();
 
class JElementProductfield extends JElement
{
 
	function fetchElement($name, $value, &$node, $control_name)
	{	
		$db			=& JFactory::getDBO();
		
		$query = "SHOW COLUMNS  
					FROM #__virtuemart_products";
		$db->setQuery( $query );
		$options = $db->loadObjectList( );
		
		return JHTML::_('select.genericlist',  $options, ''.$control_name.'['.$name.']', 'class="inputbox"', 'Field', 'Field', $value, $control_name.$name );
		
	}
}
?>