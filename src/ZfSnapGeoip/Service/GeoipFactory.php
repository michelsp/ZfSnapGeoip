<?php

namespace ZfSnapGeoip\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Http\PhpEnvironment\Request as HttpRequest;

/**
 * Factory of Geoip
 *
 * @author Michel Soares Pintor <michel@michelsp.com.br>
 */
class GeoipFactory implements FactoryInterface
{

    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $record = $serviceLocator->get('geoip_record');
        $hydrator = $serviceLocator->get('geoip_hydrator');
        $config = $serviceLocator->get('ZfSnapGeoip\DatabaseConfig');
        $request = new HttpRequest();

        return new Geoip($record, $hydrator, $config, $request);
    }

}