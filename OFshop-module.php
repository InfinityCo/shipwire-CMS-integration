<?php
class OrderProcessingVisualization {
        
    const CANVAS_WIDTH = 800;
    const CANVAS_LR_PADDING = 5;
    const CANVAS_HEIGHT = 600;
    
    private $orders = array();
    
    public function __construct($orders) {
        
        
        $this->orders = $orders;
        foreach($this->orders as &$order) {
            $order['collisions'] = array();
        }
        usort($this->orders, array($this, '_sortByStartTime'));
    }
    
    private function _sortByStartTime($a, $b) {
        return strnatcmp($a['packingStart'], $b['packingStart']);
    }

    public function getOrders($json = false) {
        
        $orders = $this->orders;
        
        if($json === true) $orders = json_encode($orders);
        
        return $orders;
    }

    private function _setCollisions() {
        
        if(count($this->orders) == 0) return false;
        
        foreach ($this->orders as $key=>$order) {
            $this->_checkForCollision($key, $order);
        }
        
        return true;
        
    }
    

    private function _checkForCollision($currentOrder, $checkOrder) {
        
        foreach ($this->orders as $key=>&$order) {
            if(($checkOrder['packingStart'] + $checkOrder['duration']) < $order['packingStart']) continue; # no collision
            if(($order['packingStart'] + $order['duration']) < $checkOrder['packingStart']) continue; # no collision
                
            $order['collisions'][] = $currentOrder;
        }
        return true;
    }
    

    public function addMissingDataPoints() {
        
        if(count($this->orders) == 0) return false;
        
        # find which orders collide
        $this->_setCollisions();
        
        foreach ($this->orders as $key=>&$order) {
            # calculate how many times an order has collided
            $order['maxCollisions'] = count($order['collisions']);
            foreach($order['collisions'] as $collision) {
                $prevCollisions = count($this->orders[$collision]['collisions']);
                if($prevCollisions >= $order['maxCollisions']) { #make sure we have the max amount of collisions
                    $order['collisions'] = $this->orders[$collision]['collisions']; #udpate to correct collisions
                    $order['maxCollisions'] = $prevCollisions;
                    $order['collisionNumber'] = array_search($key, $order['collisions']); #how many collisions before this order (used for left)
                }
            }
            $collisions = count($order['collisions']);
            $order['width'] = ($collisions > 0) ? self::CANVAS_WIDTH / $collisions : self::CANVAS_WIDTH;
            $order['left'] = $order['width'] * $order['collisionNumber'];
            
        }
        
        # remove unwated keys
        foreach ($this->orders as &$order) {
            unset($order['collisions']);
            unset($order['maxCollisions']);
            unset($order['collisionNumber']);
        }

        return true;
        
    }
    
    public static function getVisualization($ordersJson, $style='css') {
        
        $orders = json_decode($ordersJson, true);
        
        switch($style) {
            case 'css' :
                $out = self::_getCSS($orders);
                break;
            
        }
        
        return $out;
    }
    

    private static function _getCSS($orders) {
        
        ob_start(); ?>
        <style type="text/css">
            body { font-size: 11px; font-family: monospace;}
            .dash, .time { height: 30px; display: inline-block; width: 60px; vertical-align: text-top; }
            .dash { color: #ccc; }
            #time-frame { float:left; width:60px; text-align: right; margin-top: -6px; padding-right: 10px }
            #canvas { float:left; background: #dfd; height:<?php echo self::CANVAS_HEIGHT; ?>px; width:<?php echo self::CANVAS_WIDTH; ?>px; padding: 0 <?php echo self::CANVAS_LR_PADDING; ?>px; position:relative }
            .order { background: #fff; padding: 5px; border-left:2px solid #0C0; border-right:2px solid #0C0; position:absolute; }
        </style>
        <div id="time-frame">
            <span class="time">0:00</span>
            <?php for($i = 1; $i <= 10; ++$i) : ?>
            <span class="dash">-</span>
            <span class="time"><?php echo $i; ?>:00</span>
            <?php endfor; ?>
        </div>
        <div id="canvas">
            <?php foreach ($orders as $order) : ?>
                <div class="order" style="top: <?php echo $order['packingStart']; ?>; left: <?php echo $order['left'] + self::CANVAS_LR_PADDING; ?>; height: <?php echo $order['duration']; ?>px; width: <?php echo $order['width'] - 14; ?>px ">
                    #<?php echo $order['orderId']; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php 
        $out = ob_get_contents();
        ob_end_clean();
        
        return $out;
    }
    
}


$ordersJson = '[{"orderId":1,"packingStart":224,"duration":69},{"orderId":2,"packingStart":335,"duration":91},{"orderId":3,"packingStart":23,"duration":47},{"orderId":4,"packingStart":130,"duration":52},{"orderId":5,"packingStart":5,"duration":183},{"orderId":6,"packingStart":253,"duration":71},{"orderId":7,"packingStart":41,"duration":68}]';
$orders = json_decode($ordersJson, true);

if((!isset($orders[0]['width'])) && (!isset($orders[0]['left']))) {
    $OrderProcessingVisualization = new OrderProcessingVisualization($orders);
    $OrderProcessingVisualization->addMissingDataPoints();
    $newOrders = $OrderProcessingVisualization->getOrders(true);
}
else {
    $newOrders = $ordersJson;
}

echo OrderProcessingVisualization::getVisualization($newOrders);
