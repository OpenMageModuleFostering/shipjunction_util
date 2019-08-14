<?php
       
	$completeOrderIds = array(1,3);
 
        $s = "SELECT `increment_id` FROM `". $sales_order_table ."` WHERE `entity_id` in ('". implode("', '", $completeOrderIds) ."')";
	print_r($s);
?>
