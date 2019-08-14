<?php
class productLocationZone
{
    public $productId = "";
    public $location = "";
    public $zone = "";
}


$_productIdList = "SKU1,SKU3";
$productIds = explode(",",$_productIdList);
$return = array();
        foreach($productIds as $productId) {
            $productLocation = $productId."LOC";
            $zone = $productId."ZONE";
            $productLocationZone = new productLocationZone();
            $productLocationZone->productId = $productId;
            $productLocationZone->location = $productLocation;
            $productLocationZone->zone = $zone;
            array_push($return, $productLocationZone);
            /*array_push($return, 
		array("ProductId" => $productId,
		      "Location" => $productLocation, 
   		      "Zone" => $zone));*/
        }
print_r($return);
?>
