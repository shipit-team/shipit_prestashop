<?php
/**
 * Integral logistics for eCommerce with pickup or fulfillment through Shipit
 *
 * @author    Rolige <www.rolige.com>
 * @copyright 2011-2018 Rolige - All Rights Reserved
 * @license   Proprietary and confidential
 */

require(dirname(__FILE__).'/../libraries/ShipitAPI.php');
require(dirname(__FILE__).'/ShipitLists.php');
require(dirname(__FILE__).'/ShipitTools.php');
require(dirname(__FILE__).'/ShipitCache.php');
require(dirname(__FILE__).'/ShipitServices.php');
require(dirname(__FILE__).'/ShipitShipment.php');
require_once(dirname(__FILE__).'/../libraries/ShipitLAFFPack.php');

class ShipitCore extends CarrierModule
{
    const EST_MODE_WEIGHT = 1;
    const EST_MODE_CUBIC = 2;
    const EST_MODE_3D = 3;

    public function getOrderShippingCost($params, $shipping_cost) {}

    public function getOrderShippingCostExternal($params) {}

    protected function updateCache(Cart $cart, $hash_cart)
    {
        $cache = new ShipitCache((int)$cart->id);

        // Initialize cache values.
        $cache->hash_cart = $hash_cart;
        $cache->package = null;
        $cache->carriers = null;

        $products = $cart->getProducts();
        $normalized = $this->config['SHIPIT_NORMALIZED'];
        $estimation_mode = (int)$this->config['SHIPIT_ESTIMATION_MODE'];
        $services_list = ShipitServices::getAll(true);
        $address = new Address((int)$cart->id_address_delivery);

        $weight = 0;

        if ($products) {
            switch ($estimation_mode) {
                /* case self::EST_MODE_WEIGHT: */
                case self::EST_MODE_CUBIC:
                    $volume = ShipitTools::getProductsVolume($this->config, $cart);
                    $weight = ShipitTools::getProductsWeight($this->config, $cart);

                    if (!$volume || !$weight) {
                        ShipitTools::log('updateCache: products without dimensions and/or weight (volume => '.$volume.', weight => '.$weight.').');

                        return false;
                    }

                    $pow = pow($volume, 1 / 3);
                    $width = $pow;
                    $height = $pow;
                    $depth = $pow;

                    $width = ShipitTools::convertToCm($width);
                    $height = ShipitTools::convertToCm($height);
                    $depth = ShipitTools::convertToCm($depth);
                    $weight = ShipitTools::convertToKg($weight);

                    break;

                case self::EST_MODE_3D:
                    $boxes = array();

                    foreach ($products as $product) {
                        $product_boxes = $this->getProductBoxes($product);
                        $boxes = !$boxes ? $product_boxes : array_merge($boxes, $product_boxes);
                    }

                    $weight = ShipitTools::getProductsWeight($this->config, $cart);

                    // Initialize ShipitLAFFPack.
                    $lap = new ShipitLAFFPack();

                    // Start packing our nice boxes.
                    $lap->pack($boxes);
                    // Collect our container details.
                    $c_size = $lap->get_container_dimensions();

                    $width = ShipitTools::convertToCm($c_size['width']);
                    $height = ShipitTools::convertToCm($c_size['height']);
                    $depth = ShipitTools::convertToCm($c_size['length']);
                    $weight = ShipitTools::convertToKg($weight);

                    // Shipit does not allow empty fields.
                    if (!$width || !$height || !$depth || !$weight) {
                        ShipitTools::log('updateCache: products without dimensions and/or weight (width => '.$width.', height => '.$height.', depth => '.$depth.', weight => '.$weight.').');

                        return false;
                    }

                    break;
            }
        }

        // Check if there is a valid weight.
        if (($weight > 0) &&
            // Check if there is a services list available.
            $services_list &&
            // Check if currency of Chile exists.
            $this->config['chile_id_currency'] &&
            // Check if the country of Chile exists.
            $this->config['chile_id_country'] &&
            // Check if the addresses were normalized.
            $normalized &&
            // Check if the id_address in the delivery address correspond to Chile.
            ($this->config['chile_id_country'] == $address->id_country)
        ) {
            $cache->package = array(
                'width' => $width,
                'height' => $height,
                'depth' => $depth,
                'weight' => $weight
            );

            $dest_code = ShipitLists::searchCityId($address->city);

            if ($dest_code) {
                // Get the shipping costs available.
                $services_cost = $this->getServicesCost($dest_code, $weight, $height, $width, $depth);

                // If there is a services cost available.
                if ($services_cost) {
                    foreach ($services_cost as $service_reference => $service_cost) {
                        $service = ShipitServices::getByCode($service_reference);
                        if ($service) {
                            $carrier = Carrier::getCarrierByReference($service->id_reference);
                            if ($carrier) {
                                if ($service_cost) {
                                    $cost = $service_cost;

                                    if ($impact_price = $this->config['SHIPIT_IMPACT_PRICE']) {
                                        $impact_price_amount = $this->config['SHIPIT_IMPACT_PRICE_AMOUNT'];

                                        switch ($impact_price) {
                                            case 1: // Increase percent.
                                                $cost += ($impact_price_amount / 100) * $cost;
                                                break;
                                            case 2: // Increase amount.
                                                $cost += $impact_price_amount;
                                                break;
                                            case 3: // Reduction percent.
                                                $cost -= ($impact_price_amount / 100) * $cost;
                                                break;
                                            case 4: // Reduction amount.
                                                $cost -= $impact_price_amount;
                                                break;
                                        }
                                    }
                                } else {
                                    $cost = false;
                                }

                                $cache->carriers[$carrier->id] = $cost;
                            }
                        }
                    }
                }
            }
        }

        return $cache->save();
    }

    public function getProductBoxes($product)
    {
        $boxes = array();

        for ($i = 0; $i < $product['cart_quantity']; $i++) {
            $s_width = (float)$this->config['SHIPIT_SET_VALUE_WIDTH'];
            $s_height = (float)$this->config['SHIPIT_SET_VALUE_HEIGHT'];
            $s_depth = (float)$this->config['SHIPIT_SET_VALUE_DEPTH'];
            $product_width = (float)$product['width'];
            $product_height = (float)$product['height'];
            $product_depth = (float)$product['depth'];

            switch ($this->config['SHIPIT_SET_DIMENSIONS']) {
                case 1: // Set the specified dimensions.
                    $values = array(
                        ($s_width ? $s_width : $product_width),
                        ($s_height ? $s_height : $product_height),
                        ($s_depth ? $s_depth : $product_depth)
                    );

                    break;

                case 2: // When the product dimension is missing or not set.
                    $values = array(
                        ($s_width && !$product_width ? $s_width : $product_width),
                        ($s_height && !$product_height ? $s_height : $product_height),
                        ($s_depth && !$product_depth ? $s_depth : $product_depth)
                    );

                    break;

                case 3: // When the product dimension is less than specified.
                    $values = array(
                        ($s_width && ($product_width < $s_width) ? $s_width : $product_width),
                        ($s_height && ($product_height < $s_height) ? $s_height : $product_height),
                        ($s_depth && ($product_depth < $s_depth) ? $s_depth : $product_depth)
                    );

                    break;

                case 4: // When the product dimension is greater than specified.
                    $values = array(
                        ($s_width && ($product_width > $s_width) ? $s_width : $product_width),
                        ($s_height && ($product_height > $s_height) ? $s_height : $product_height),
                        ($s_depth && ($product_depth > $s_depth) ? $s_depth : $product_depth)
                    );

                    break;

                default:
                    $values = array(
                        $product_width,
                        $product_height,
                        $product_depth
                    );

                    break;
            }

            sort($values);

            $boxes[] = array_combine(array('height', 'width', 'length'), $values);
        }

        return $boxes;
    }

    /**
     * Get the cost of the available services
     * @param  [string] $destination The city code destination
     * @param  [float]  $weight      The weight of the product in Kg
     * @param  [float]  $height      The height of the product in cm
     * @param  [float]  $width       The width of the product in cm
     * @param  [float]  $depth       The depth of the product in cm
     * @return [array]  Return empty if no services
     */
    protected function getServicesCost($destination, $weight, $height, $width, $depth)
    {
        $variables = array(
            'destination' => $destination,
            'weight' => $weight,
            'height' => $height,
            'width' => $width,
            'depth' => $depth
        );

        $services = $this->calculatePricing($variables);

        return $services;
    }

    /**
     * Calculate the pricing for the shipping.
     *
     *  The information to calculate the pricing. The structure:
     *    - destination: The city code destination.
     *    - weight: The weight of the product in Kg.
     *    - height: The height of the product in cm.
     *    - width: The width of the product in cm.
     *    - depth: The depth of the product in cm.
     *  If fails FALSE is return or an array of services available.
     * @param  array    $variables
     * @return mixed
     */
    private function calculatePricing(array $variables)
    {
        $params = array(
            'package' => array(
                'to_commune_id' => $variables['destination'],
                'height' => $variables['height'],
                'length' => $variables['depth'],
                'width' => $variables['width'],
                'weight' => $variables['weight'],
                'destiny' => 'Domicilio'
            )
        );

        $errors = false;
        $api = new ShipitAPI($this->config['SHIPIT_EMAIL'], $this->config['SHIPIT_TOKEN']);
        $results = $api->getEstimateCost($params, (int)$this->config['SHIPIT_DISPATCH_ALGORITHM'], $errors);

        if (!$results) {
            ShipitTools::log('calculatePricing: '.print_r($errors, true));

            return false;
        }

        return $results;
    }
}
