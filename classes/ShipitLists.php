<?php
/**
 * Integral logistics for eCommerce with pickup or fulfillment through Shipit
 *
 * @author    Rolige <www.rolige.com>
 * @copyright 2011-2018 Rolige - All Rights Reserved
 * @license   Proprietary and confidential
 */

class ShipitLists
{
    /**
     * List of services availables for Shipit
     *
     * @return array of services
     */
    public static function services()
    {
        $tracking_urls = array(
            'dhl' => 'http://www.dhl.com.mx/es/express/rastreo.html?AWB=@',
            'chilexpress' => 'http://chilexpress.cl/Views/ChilexpressCL/Resultado-busqueda.aspx?DATA=@',
            'starken' => 'http://www.starken.cl/seguimiento?codigo=@',
            'correos_de_chile' => 'http://www.correos.cl/SitePages/seguimiento/seguimiento.aspx?envio=@'
        );

        $couriers = array();
        $errors = false;
        $api = new ShipitAPI(Configuration::get('SHIPIT_EMAIL'), Configuration::get('SHIPIT_TOKEN'));

        if ($results = $api->getCouriersList($errors)) {
            $couriers[] = array(
                'code' => 'shipit',
                'desc' => 'Shipit',
                'active' => true,
                'image' => _PS_MODULE_DIR_.'rg_shipit/views/img/logo.png',
                'tracking_url' => ''
            );
            foreach ($results as $courier) {
                $couriers[] = array(
                    'code' => $courier['id'],
                    'desc' => $courier['name'],
                    'active' => $courier['active'],
                    'image' => $courier['image'],
                    'tracking_url' => (isset($tracking_urls[$courier['id']]) ? $tracking_urls[$courier['id']] : '')
                );
            }
        }

        return $couriers;
    }

    /**
     * List of cities availables for Chile
     *
     * @return array of cities
     */
    public static function cities()
    {
        $array = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'rg_shipit_commune`');

        return $array;
    }

    /**
     * Check if the city name is valid and get the id
     *
     * @param string $city
     */
    public static function searchCityId($city)
    {
        $id = Db::getInstance()->getValue(
            'SELECT `id` FROM `'._DB_PREFIX_.'rg_shipit_commune`
            WHERE `name` = "'.pSQL($city).'"'
        );

        return $id;
    }

    public static function normalizationCities()
    {
        $array = array(
            array('name' => 'Santiago Centro', 'alias' => array('santiago centro', 'santiago', 'stgo', 'santiago chile', 'santiago de chile')),
            array('name' => 'Antofagasta', 'alias' => array('antofagasta', 'antofagastas', 'antofas', 'antofasta')),
            array('name' => 'Concepcion', 'alias' => array('concepcion')),
            array('name' => 'Vina Del Mar', 'alias' => array('viña del mar', 'vina del mar', 'viña', 'vina')),
            array('name' => 'Las Condes', 'alias' => array('las condes', 'condes')),
            array('name' => 'Nunoa', 'alias' => array('ñuñoa', 'nunoa')),
            array('name' => 'Alto Biobio', 'alias' => array('alto bio bio', 'alto biobio', 'bio bio', 'biobio')),
            array('name' => 'Alto Del Carmen', 'alias' => array('alto del carmen', 'alto carmen')),
            array('name' => 'Alto El Canelo', 'alias' => array('alto el canelo', 'alto canelo')),
            array('name' => 'Alto Hospicio', 'alias' => array('alto hospicio', 'hospicio')),
            array('name' => 'Camina', 'alias' => array('camiña', 'camina')),
            array('name' => 'Canete', 'alias' => array('cañete', 'canete')),
            array('name' => 'Capitan Pastene', 'alias' => array('capitan pastene', 'pastene')),
            array('name' => 'Carrizal Bajo', 'alias' => array('carrizal bajo', 'carrizal')),
            array('name' => 'Cerrillos De Tamaya', 'alias' => array('cerrillos de tamaya', 'tamaya')),
            array('name' => 'Cerro Navia', 'alias' => array('cerro navia', 'navia')),
            array('name' => 'Cerro Sombrero', 'alias' => array('cerro sombrero', 'sombrero')),
            array('name' => 'Chanaral', 'alias' => array('chañaral', 'chanaral')),
            array('name' => 'Chanaral Alto', 'alias' => array('chañaral alto', 'chanaral alto')),
            array('name' => 'Chanaral De Caren', 'alias' => array('chañaral de caren', 'chanaral de caren', 'chanaral caren')),
            array('name' => 'Ciudad De Los Valles', 'alias' => array('ciudad de los valles', 'ciudad valles', 'valles')),
            array('name' => 'Codelco Radomiro Tomic', 'alias' => array('codelco radomiro tomic', 'codelco', 'radomiro', 'tomic', 'codelco radomiro')),
            array('name' => 'Coz Coz', 'alias' => array('coz coz', 'coz')),
            array('name' => 'Curaco De Velez', 'alias' => array('curaco de velez', 'curaco velez', 'curaco', 'velez')),
            array('name' => 'Diego De Almagro', 'alias' => array('diego de almagro', 'diego almagro', 'diego', 'almagro')),
            array('name' => 'Domeyko', 'alias' => array('domeyko', 'domeiko', 'domeico', 'domeyco')),
            array('name' => 'Donihue', 'alias' => array('doñihue', 'donihue')),
            array('name' => 'El Bosque', 'alias' => array('el bosque', 'bosque')),
            array('name' => 'El Carmen Chillan', 'alias' => array('el carmen chillan', 'carmen chillan')),
            array('name' => 'El Carmen Rengo', 'alias' => array('el carmen rengo', 'carmen rengo')),
            array('name' => 'El Colorado', 'alias' => array('el colorado', 'colorado')),
            array('name' => 'El Hinojal', 'alias' => array('el hinojal', 'hinojal')),
            array('name' => 'El Ingenio', 'alias' => array('el ingenio', 'ingenio')),
            array('name' => 'El Manzano', 'alias' => array('el manzano', 'manzano')),
            array('name' => 'El Melocoton', 'alias' => array('el melocoton', 'melocoton')),
            array('name' => 'El Melon', 'alias' => array('el melon', 'melon')),
            array('name' => 'El Membrillo', 'alias' => array('el membrillo', 'membrillo')),
            array('name' => 'El Penon', 'alias' => array('el penon', 'penon')),
            array('name' => 'El Quisco', 'alias' => array('el quisco', 'quisco')),
            array('name' => 'El Salvador', 'alias' => array('el salvador', 'salvador')),
            array('name' => 'El Tabito', 'alias' => array('el tabito', 'tabito')),
            array('name' => 'El Tabo', 'alias' => array('el tabo', 'tabo')),
            array('name' => 'El Tambo', 'alias' => array('el tambo', 'tambo')),
            array('name' => 'El Tangue', 'alias' => array('el tangue', 'tangue')),
            array('name' => 'Hacienda Los Andes', 'alias' => array('hacienda los andes', 'hacienda andes')),
            array('name' => 'Horcon Iv', 'alias' => array('horcon iv', 'horcon 4')),
            array('name' => 'Hualane', 'alias' => array('hualañe', 'hualane')),
            array('name' => 'Isla De Maipo', 'alias' => array('isla de maipo', 'isla maipo')),
            array('name' => 'Isla De Pascua', 'alias' => array('isla de pascua', 'isla pascua')),
            array('name' => 'Las Ramadas De Tulahuen', 'alias' => array('las ramadas de tulahuen', 'ramadas de tulahuen', 'tulahuen')),
            array('name' => 'Llanos De Guanta', 'alias' => array('llanos de guanta', 'llanos guanta', 'guanta')),
            array('name' => 'Llaillay', 'alias' => array('llay llay', 'llay', 'llaillay')),
            array('name' => 'Lomas De Lo Aguirre', 'alias' => array('lomas de lo aguirre', 'lomas de aguirre', 'lomas aguirre')),
            array('name' => 'Niquen', 'alias' => array('ñiquen', 'niquen')),
            array('name' => 'Ohiggins', 'alias' => array('ohiggins', 'ohigins')),
            array('name' => 'Penaflor', 'alias' => array('peñaflor', 'penaflor')),
            array('name' => 'Penalolen', 'alias' => array('peñalolen', 'penalolen')),
            array('name' => 'Quinta De Tilcoco', 'alias' => array('quinta de tilcoco', 'quinta tilcoco')),
            array('name' => 'Rinconada De Guzman', 'alias' => array('rinconada de guzman', 'rinconada guzman')),
            array('name' => 'Rinconada De Silva', 'alias' => array('rinconada de silva', 'rinconada silva')),
            array('name' => 'Rinihue', 'alias' => array('riñihue', 'rinihue')),
            array('name' => 'Rininahue', 'alias' => array('riñinahue', 'rininahue')),
            array('name' => 'Rio Ibanez', 'alias' => array('rio ibañez', 'rio ibanez', 'rio ibanes')),
            array('name' => 'Ruca Raqui (saavedra)', 'alias' => array('ruca raqui (saavedra)', 'ruca raqui')),
            array('name' => 'Sector La Pena', 'alias' => array('sector la peña', 'sector la pena', 'la pena')),
            array('name' => 'Vicuna', 'alias' => array('vicuña', 'vicuna')),
        );

        return $array;
    }
}
