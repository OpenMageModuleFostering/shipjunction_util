<?php
class productLocationZone
{
    public $productId = "";
    public $location = "";
    public $zone = "";
}
class Shipjunction_Utilities_Model_Objectmodel_Api extends Mage_Api_Model_Resource_Abstract
{
     /**
     * sendShipmentEmail
     *
     * @param string $shipmentIncrementId
     * @return string 1 = sucess, 0 = failure
     */
    
    public function sendShipmentEmail( $shipmentIncrementId )
    {
        $return = 1;
        Mage::log("Shipjunction_Utilites_Model_Objectmodel_Api: sendShipmentEmail called");
        $shipment = Mage::getModel('sales/order_shipment')->loadByIncrementId($shipmentIncrementId);
        if($shipment)
        {
            $shipment->sendEmail(true);
            $shipment->setEmailSent(true);
            $shipment->save();                          
        }
	    else {
	      Mage::log("Shipjunction_Utilites_Model_Objectmodel_Api: shipment NOT found : ". $shipmentIncrementId);
          $result = 0;
        }
	    return $return;
    }
	
  	/**
  	 * getClearpathOrderNumber
  	 *
  	 * @param string $_orderIncrementId
  	 * @return string clearpathOrderNumber, boolean false
  	 */
  	public function getClearpathOrderNumber( $_orderIncrementId )
    {
      Mage::log("Shipjunction_Utilites_Model_Objectmodel_Api: getClearpathOrderNumber called");

  		$_clearpath_tablename = Mage::getStoreConfig('clearpath/general/clearpath_tablename');
  		$_db = Mage::getSingleton('core/resource')->getConnection('core_read');
  		$_db_query = "SELECT cp_order_number FROM {$_clearpath_tablename} WHERE mage_order_number = '{$_orderIncrementId}'";
  		$_db_result = $_db->fetchAll($_db_query);
  		
  		if ($_db_result) {
  			return $_db_result[0]['cp_order_number'];
  		} else {
  			return false;
  		}
    }

    /**
     * getEmbeddedErpFullStockOrders
     *
     * @return string comma delimited list of increment_ids which are fully stocked
     */
    public function getEmbeddedErpFullStockOrders()
    {
        Mage::log("Shipjunction_Utilites_Model_Objectmodel_Api: getEmbeddedErpFullStockOrders called");
        $result = "";
        $resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');
        $sales_order_table = $resource->getTableName('sales/order');

        $completeOrders = Mage::getModel('Orderpreparation/ordertoprepare')->getFullStockOrdersFromCache();
        foreach($completeOrders as $completeOrder)
        {
            $completeOrderIds[] = $completeOrder->getopp_order_id();
        }

        $sql = "SELECT `increment_id` FROM `". $sales_order_table ."` WHERE `entity_id` in ('". implode("', '", $completeOrderIds) ."')";
        $db_result = $readConnection->fetchAll($sql);
        if ($db_result) {
            foreach($db_result as $row){
                $result = $result.$row['increment_id'].",";
            }
        }

        return $result;
    }

    /**
     * getEmbeddedErpZonesAndBins
     *
     * @param string $_productIdList comma delimited list of productIds to retrieve zone & bin information
     * @return string pipe delimited set of productId|binLocation|zone for $_productIdList
     */
    public function getEmbeddedErpZonesAndBins($_productIdList)
    {
        Mage::log("Shipjunction_Utilites_Model_Objectmodel_Api: getEmbeddedErpZonesAndBins called");

        $productToBin = array();
        $binToZone = array();
        $locationList = "";
        $result = "";

        $warehouse = Mage::getModel('AdvancedStock/Warehouse')->load(1);
        $productIds = explode(",",$_productIdList);

        // Map each productId to a bin_num        
        foreach($productIds as $productId) {
            $location = $warehouse->getProductLocation($productId);    
            $productToBin[$productId] = $location;
            $locationList = $locationList."'".$location."',";
        }

        $resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');
        // Map each bin_num to a zone
        $sql = "SELECT `bin_num`,`zone` FROM `bin_list` WHERE `bin_num` in (". rtrim($locationList, ',') .");";
        /*Mage::log("Shipjunction_Utilites_Model_Objectmodel_Api: getEmbeddedErpZonesAndBins sql : ".$sql);*/

        $db_result = $readConnection->fetchAll($sql);
        if ($db_result) {
            foreach($db_result as $row){
                if (strlen($row['bin_num']) > 0) {
                    $binToZone[strtoupper($row['bin_num'])] = $row['zone'];
                }
            }
        }
        // put the results together
        foreach(array_keys($productToBin) as $productId) {
            $location = $productToBin[$productId];
            $zone = $binToZone[strtoupper($location)];
            $result = $result."|".$productId."|".$location."|".$zone;
        }
        // remove starting pipe
        $result = ltrim($result, '|');

        return $result;
    }

    /**
     * Create new invoice for order
     *
     * @param string $invoiceIncrementId
     * @param array $itemsQty
     * @param string $comment
     * @param bool $email
     * @param bool $includeComment
     * @param bool $capture
     * @return string
     */
    public function createInvoice($orderIncrementId, 
                                $itemsQty, 
                                $comment = null, 
                                $email = false, 
                                $includeComment = false, 
                                $capture = false)
    {
        Mage::log("Shipjunction_Utilites_Model_Objectmodel_Api: createInvoice called with id:".$orderIncrementId.",email=".$email.",capture=".$capture.",itemsQty=".var_dump($itemsQty));
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        /**
          * Check order existing
          */
        if (!$order->getId()) {
             return "Invalid order incrementId";
        }

        if ($order->getBaseTotalDue() == 0) {
             return "Invoiced";
        }
        /**
         * Check invoice create availability
         */
        if (!$order->canInvoice()) {
             return "Cannot do invoice for order.";
        }

        $invoice = $order->prepareInvoice(); // removing $itemsQty for now

        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);

        $invoice->register();

        if ($capture && $invoice->canCapture()) {
            $invoice->capture();
            Mage::log("Shipjunction_Utilites_Model_Objectmodel_Api: createInvoice attempted to capture invoice");
        }

        if ($comment !== null) {
            $invoice->addComment($comment, $email);
        }

        if ($email) {
            $invoice->setEmailSent(true);
        }

        $invoice->getOrder()->setIsInProcess(true);

        try {
            Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();
            $invoice->sendEmail($email, ($includeComment ? $comment : ''));
        } catch (Mage_Core_Exception $e) {
            return "Data invalid : ".$e->getMessage();
        }

        return $invoice->getIncrementId();
    }
    /**
     * Create new shipment for order
     *
     * @param string $orderIncrementId
     * @param array $itemsQty
     * @param string $carrierName
     * @param string $title
     * @param string $trackingNumbers
     * @return string
     */
    public function createShipment($orderIncrementId,
                                $itemsQty,
                                $carrierName,
                                $title,
                                $trackingNumbers)
    {
      Mage::log("Shipjunction_Utilites_Model_Objectmodel_Api: createShipment called with id:".$orderIncrementId.",carrierName=".$carrierName.",title=".$title.",itemsQty=".var_dump($itemsQty));
      $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);

      if (!$order->getId()) {
           return "Invalid order incrementId";
      }

      $shipment = null;

      if ($order->hasShipments()) {
        $shipment = $order->getShipmentsCollection()->getFirstItem();
        Mage::log("Shipjunction_Utilites_Model_Objectmodel_Api: has Shipments");
      }
      else {
        /**
         * Check shipment create availability
         */
        if (!$order->canShip()) {
             return "Cannot do shipment for order.";
        }

        /* @var $shipment Mage_Sales_Model_Order_Shipment */
        $shipment = $order->prepareShipment($itemsQty);
        if ($shipment) {
            $shipment->register();
            //$shipment->addComment($comment, $email && $includeComment);
            $shipment->getOrder()->setIsInProcess(true);
            try {
                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($shipment)
                    ->addObject($shipment->getOrder())
                    ->save();
            } catch (Mage_Core_Exception $e) {
                return $e->getMessage();
            }
        }
      }

      if (!$shipment) {
        return "Unable to find or create a shipment.";
      }

      $trackingNumbersOnShipment = array();
      foreach($shipment->getAllTracks() as $trackingNumberOnShipment) {
        $trackingNumbersOnShipment[]=$trackingNumberOnShipment->getNumber();
      }
      $numbersToUpdate = array_diff($trackingNumbers, $trackingNumbersOnShipment);
      foreach($numbersToUpdate as $trackingNumberToUpdate)
      {
        $trackId = Mage::getModel('sales/order_shipment_api')->addTrack($shipment->increment_id, $carrierName, $title, $trackingNumberToUpdate);
      }

      return $shipment->increment_id;
    }
    /**
     * Create new invoice for order
     *
     * @param string $orderIncrementId
     * @param array $itemsQty
     * @param string $comment
     * @param bool $email
     * @param bool $includeComment
     * @param bool $capture
     * @param string $carrierName
     * @param string $title
     * @param string $trackingNumbers
     * @return string
     */
    public function createInvoiceAndShipment($orderIncrementId, 
                                $itemsQty, 
                                $comment = null, 
                                $email = false, 
                                $includeComment = false, 
                                $capture = false,
                                $carrierName,
                                $title,
                                $trackingNumbers) {
      $invoiceResult = $this->createInvoice($orderIncrementId, $itemsQty, $comment, $email, $includeComment, $capture);
      if (is_numeric($invoiceResult) || $invoiceResult == "Invoiced") {
        $shipmentResult = $this->createShipment($orderIncrementId, $itemsQty, $carrierName, $title, $trackingNumbers);
        return $shipmentResult;
      }
      return $invoiceResult;
    }
}
?>
