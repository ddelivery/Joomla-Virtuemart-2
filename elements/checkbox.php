<?php
defined('_JEXEC') or die();
 
class JElementCheckbox extends JElement
{
 
	function fetchElement($name, $value, &$node, $control_name)
	{	
		//echo '<pre>$name: '.print_r($name,1).'</pre>';
		//echo '<pre>$node: '.print_r($node,1).'</pre>';
		//echo '<pre>$value: '.print_r($value,1).'</pre>';
		//echo '<pre>$control_name: '.print_r($control_name,1).'</pre>';
		
        $checked = ($value == $node->attributes('value'))? ' checked="'.$node->attributes('checked').'"' : '' ;
		if (!$value) $checked = ' '. $node->attributes('default') .' ';
        
    	return "<input type='checkbox' name='$name' id='$name' 
                value='".$node->attributes('value')."' $checked />";
	}
}
?>