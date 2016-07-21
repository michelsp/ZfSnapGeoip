<?php

namespace ZfSnapGeoip\Service;

use ZfSnapGeoip\DatabaseConfig;
use ZfSnapGeoip\Entity\RecordInterface;
use ZfSnapGeoip\Exception\DomainException;
use ZfSnapGeoip\IpAwareInterface;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\EventManager;
use Zend\Http\PhpEnvironment\Request as HttpRequest;
use geoiprecord as GeoipCoreRecord;
use ZfSnapGeoip\Entity\Record;
use \Zend\Stdlib\Hydrator\HydratorInterface;


/**
 * Geoip Service
 *
 * @author Witold Wasiczko <witold@wasiczko.pl>
 * @author Michel Soares Pintor <michel@michelsp.com.br>
 */
class Geoip implements EventManagerAwareInterface
{
    /**
     * @var \GeoIP
     */
    private $geoip;

    /**
     * @var EventManagerInterface
     */
    private $eventManager;

    /**
     * @var GeoipCoreRecord[]
     */
    private $records;

    /**
     * @var string|bool
     */
    private $defaultIp;

    /**
     * @var DatabaseConfig
     */
    private $config;

    /**
     * @var array
     */
    private $regions;

    /**
     * @var RecordInterface
     */
    private $record;

    /**
     * @var \Zend\Stdlib\Hydrator\HydratorInterface
     */
    private $hydrator;

    /**
     * @var HttpRequest
     */
    private $request;


    /**
     * Geoip constructor.
     * @param Record|RecordInterface $record
     * @param HydratorInterface $hydrator
     * @param DatabaseConfig $config
     * @param HttpRequest $request
     */
    public function __construct(RecordInterface $record, HydratorInterface $hydrator, DatabaseConfig $config, HttpRequest $request)
    {
        $this->record = $record;
        $this->hydrator = $hydrator;
        $this->config = $config;
        $this->request = $request;
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->closeGeoip();
    }

    /**
     * @return \GeoIP
     */
    public function getGeoip()
    {
        if (!$this->geoip) {
            $database = $this->getConfig()->getDatabasePath();
            if (file_exists($database)) {
                $this->geoip = geoip_open($database, $this->getConfig()->getFlag());
            } else {
                throw new DomainException('You need to download Maxmind database. You can use ZFTool or composer.json for that :)');
            }
        }
        return $this->geoip;
    }

    /**
     * @param string $ipAddress
     * @return GeoipCoreRecord
     */
    public function getGeoipRecord($ipAddress)
    {
        $ipAddress = $this->getIp($ipAddress);

        if (!isset($this->records[$ipAddress])) {
            $this->records[$ipAddress] = GeoIP_record_by_addr($this->getGeoip(), $ipAddress);
        }

        return $this->records[$ipAddress];
    }

    /**
     * @param string $ipAddress
     * @return string
     */
    private function getIp($ipAddress)
    {
        if ($ipAddress === null) {
            $ipAddress = $this->getDefaultIp();
        }

        if ($ipAddress instanceof IpAwareInterface) {
            $ipAddress = $ipAddress->getIpAddress();
        }
        $this->getEventManager()->trigger(__FUNCTION__, $this, array(
            'ip' => $ipAddress,
        ));

        return $ipAddress;
    }

    /**
     * @param string $ipAdress
     * @return RecordInterface
     *
     */
    public function getRecord($ipAdress = null)
    {
        /* @var $record RecordInterface */
        if (!$this->record instanceof RecordInterface) {
            throw new DomainException('Incorrect record implementation');
        }

        $geoipRecord = $this->getGeoipRecord($ipAdress);

        if (!$geoipRecord instanceof GeoipCoreRecord) {
            return $this->record;
        }

        $data = get_object_vars($geoipRecord);
        $data['region_name'] = $this->getRegionName($data);

        /* @var $hydrator \Zend\Stdlib\Hydrator\HydratorInterface */
        $this->hydrator->hydrate($data, $this->record);

        $this->getEventManager()->trigger(__FUNCTION__, $this, array(
            'record' => $this->record,
        ));

        return $this->record;
    }

    /**
     * @param string $ipAddress
     * @return RecordInterface
     */
    public function lookup($ipAddress = null)
    {
        return $this->getRecord($ipAddress);
    }

    /**
     * @return self
     */
    private function closeGeoip()
    {
        if ($this->geoip) {
            geoip_close($this->geoip);
            $this->geoip = null;
        }
        return $this;
    }

    /**
     * @return array
     */
    private function getRegions()
    {
        if ($this->regions === null) {
            $regionVarPath = $this->getConfig()->getRegionVarsPath();
            include($regionVarPath);

            if (!isset($GEOIP_REGION_NAME)) {
                throw new DomainException(sprintf('Missing region names data in path %s', $regionVarPath));
            }

            $this->regions = $GEOIP_REGION_NAME;

            $this->getEventManager()->trigger(__FUNCTION__, $this, array(
                'regions' => $this->regions,
            ));
        }
        return $this->regions;
    }

    /**
     * @return DatabaseConfig
     */
    private function getConfig()
    {
        return $this->config;
    }

    /**
     * @return string|null
     */
    private function getDefaultIp()
    {
        if ($this->defaultIp === null) {

            if ($this->request instanceof HttpRequest) {
                $ipAddress = $this->request->getServer('REMOTE_ADDR', false);
                $this->defaultIp = $ipAddress;
            } else {
                $this->defaultIp = false;
                return null;
            }
        }
        return $this->defaultIp;
    }

    /**
     * @param array $data
     * @return string
     */
    private function getRegionName(array $data = array())
    {
        $regions = $this->getRegions();
        $countryCode = isset($data['country_code']) ? $data['country_code'] : null;

        if (isset($regions[$countryCode])) {
            $regionCodes = $regions[$countryCode];
            $regionCode = isset($data['region']) ? $data['region'] : null;

            if (isset($regionCodes[$regionCode])) {
                return $regionCodes[$regionCode];
            }
        }
        return null;
    }

    /**
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        if ($this->eventManager === null) {
            $this->eventManager = new EventManager();
        }
        return $this->eventManager;
    }

    /**
     * @param EventManagerInterface $eventManager
     * @return self
     */
    public function setEventManager(EventManagerInterface $eventManager)
    {
        $eventManager->setIdentifiers(array(
            __CLASS__,
            get_called_class(),
        ));
        $this->eventManager = $eventManager;

        return $this;
    }

}