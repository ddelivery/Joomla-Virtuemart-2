<?php
defined('JPATH_BASE') or die();
 
class JElementMulti extends JElement
{
  var  $_name = 'Multi';
 
  function fetchElement($name, $value, &$node, $control_name)
  {
    $ctrl = $control_name . '[' . $name . ']';
    $attribs = '';
 
    if ($v = $node->attributes('class')) {
      $attribs .= ' class="'.$v.'"';
    } else {
      $attribs .= ' class="inputbox"';
    }
 
    if ($v = $node->attributes('size')) {
      $attribs .= ' size="'.$v.'"';
    }
 
    if ($m = $node->attributes('multiple')) {
      //$attribs .= ' multiple="multiple"';
      //$ctrl .= '[]';
    }
  
    $options = array ();
    $db			=& JFactory::getDBO();
    $query = 'SELECT a.virtuemart_paymentmethod_id as id, b.payment_name AS text '
		. ' FROM #__virtuemart_paymentmethods as a left join #__virtuemart_paymentmethods_ru_ru AS b on a.virtuemart_paymentmethod_id=b.virtuemart_paymentmethod_id'
		. ' WHERE a.published = 1'
		
		;
	$db->setQuery( $query );
	$op = $db->loadObjectList( );
    foreach ($op as $option)
    {
      $val  = $option->id;
      $text  = $option->text;
      $options[] = JHTML::_('select.option', $val, JText::_($text));
    }
 	//$attribs .= ' size="'.count($op).'"';
 	//$attribs .= ' multiple="multiple"';
    //$ctrl .= '[]';
		
		
    return JHTML::_('select.genericlist',  $options, $ctrl, trim($attribs), 'value', 'text', $value, $control_name.$name);
  }
}