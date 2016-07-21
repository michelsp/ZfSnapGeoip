<?php
namespace ZfSnapGeoip\Service;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Http\PhpEnvironment\Request as HttpRequest;
<<<<<<< HEAD
=======

>>>>>>> 2a9a4ba49931d5d5bcee8156178f417da7f2340e
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
<<<<<<< HEAD
=======

>>>>>>> 2a9a4ba49931d5d5bcee8156178f417da7f2340e
        return new Geoip($record, $hydrator, $config, $request);
    }
}