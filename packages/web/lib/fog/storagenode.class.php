<?php
/**
 * Storage node handler class.
 *
 * PHP version 5
 *
 * @category StorageNode
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Storage node handler class.
 *
 * @category StorageNode
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class StorageNode extends FOGController
{
    /**
     * The storage node table.
     *
     * @var string
     */
    protected $databaseTable = 'nfsGroupMembers';
    /**
     * The storage node fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'ngmID',
        'name' => 'ngmMemberName',
        'description' => 'ngmMemberDescription',
        'isMaster' => 'ngmIsMasterNode',
        'storagegroupID' => 'ngmGroupID',
        'isEnabled' => 'ngmIsEnabled',
        'isGraphEnabled' => 'ngmGraphEnabled',
        'path' => 'ngmRootPath',
        'ftppath' => 'ngmFTPPath',
        'bitrate' => 'ngmMaxBitrate',
        'snapinpath' => 'ngmSnapinPath',
        'sslpath' => 'ngmSSLPath',
        'ip' => 'ngmHostname',
        'maxClients' => 'ngmMaxClients',
        'user' => 'ngmUser',
        'pass' => 'ngmPass',
        'key' => 'ngmKey',
        'interface' => 'ngmInterface',
        'bandwidth' => 'ngmBandwidthLimit',
        'webroot' => 'ngmWebroot'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'ip',
        'path',
        'ftppath',
        'user',
        'pass'
    ];
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'images',
        'snapinfiles',
        'logfiles',
        'usedtasks',
        'storagegroup',
        'online'
    ];
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [
        'StorageGroup' => [
            'id',
            'storagegroupID',
            'storagegroup'
        ]
    ];
    protected $sqlQueryStr = "SELECT `%s`,`ngID`,`ngName`
        FROM `%s`
        LEFT OUTER JOIN `nfsGroups`
        ON `nfsGroupMembers`.`ngmGroupID` = `nfsGroups`.`ngID`
        %s
        %s
        %s";
    protected $sqlFilterStr = "SELECT COUNT(`%s`),`ngID`,`ngName`
        FROM `%s`
        LEFT OUTER JOIN `nfsGroups`
        ON `nfsGroupMembers`.`ngmGroupID` = `nfsGroups`.`ngID`
        %s";
    protected $sqlTotalStr = "SELECT COUNT(`%s`),`ngID`,`ngName`
        FROM `%s`
        LEFT OUTER JOIN `nfsGroups`
        ON `nfsGroupMembers`.`ngmGroupID` = `nfsGroups`.`ngID`";
    /**
     * Gets an item from the key sent, if no key all object data is returned.
     *
     * @param mixed $key the key to get
     *
     * @return object
     */
    public function get($key = '')
    {
        $pathvars = [
            'path',
            'ftppath',
            'snapinpath',
            'sslpath',
            'webroot'
        ];
        if (in_array($key, $pathvars)) {
            if (trim(parent::get($key)) === '/') {
                return parent::get($key);
            }
            return rtrim(parent::get($key), '/');
        }
        $loaders = [
            'snapinfiles' => 'getSnapinfiles',
            'images' => 'getImages',
            'logfiles' => 'getLogfiles'
        ];
        if (in_array($key, array_keys($loaders))
            && !array_key_exists($key, $this->data)
        ) {
            if (!$this->get('online')) {
                return parent::get($key);
            }
            $func = $loaders[$key];
            $this->{$func}();
        }

        return parent::get($key);
    }
    /**
     * Get the storage group of this node.
     *
     * @return object
     */
    public function getStorageGroup()
    {
        return $this->get('storagegroup');
    }
    /**
     * Loads the online status for us.
     *
     * @return void
     */
    public function loadOnline()
    {
        $test = self::$FOGURLRequests->isAvailable($this->get('ip'), 1, 21, 'tcp');
        $this->set('online', array_shift($test));
    }
    /**
     * Get the node failure.
     *
     * @param int $Host the host id
     *
     * @return object
     */
    public function getNodeFailure($Host)
    {
        Route::listem(
            'nodefailure',
            [
                'hostID' => $Host,
                'storagenodeID' => $this->get('id')
            ]
        );
        $Failures = json_decode(
            Route::getData()
        );
        foreach ($Failures->data as &$Failed) {
            $curr = self::niceDate();
            $prev = self::niceDate($Failed->failureTime);
            if ($curr < $prev) {
                return true;
            }
            unset($Failed);
        }
        return false;
    }
    /**
     * Loads the logfiles available on this node.
     *
     * @return void
     */
    public function getLogfiles()
    {
        if (!($this->get('isEnabled')
            && $this->get('isMaster')
            && $this->get('online'))
        ) {
            return $this->set('logfiles', []);
        }
        $paths = [
            '/var/log/nginx',
            '/var/log/httpd',
            '/var/log/apache2',
            '/var/log/fog',
            '/var/log/php7.0-fpm',
            '/var/log/php-fpm',
            '/var/log/php5-fpm',
            '/var/log/php5.6-fpm',
        ];
        natcasesort($paths);
        self::getIPAddress();
        $ip = self::resolveHostname($this->get('ip'));
        $files = [];
        if (in_array($ip, self::$ips)) {
            foreach ($paths as &$path) {
                if (!is_dir($path)) {
                    continue;
                }
                $path = str_replace(
                    ['\\', '/'],
                    DS,
                    $path
                );
                $path .= DS . '*';
                $files = self::fastmerge(
                    $files,
                    glob($path)
                );
                unset($path);
            }
        } else {
            $url = sprintf(
                '%s://%s/fog/status/getfiles.php?path=%s',
                self::$httpproto,
                $this->get('ip'),
                '%s'
            );
            $url = sprintf(
                $url,
                urlencode(implode(':', $paths))
            );
            $paths = self::$FOGURLRequests->process($url);
            $files = json_decode(array_shift($paths), true);
        }
        $this->set('logfiles', $files);
    }
    /**
     * Get's the storage node snapins, logfiles, and images
     * in a single multi call rather than three individual calls.
     *
     * @return void
     */
    private function _getData()
    {
        $url = sprintf(
            '%s://%s/fog/status/getfiles.php',
            self::$httpproto,
            $this->get('ip')
        );
        $keys = [
            'images' => urlencode($this->get('path')),
            'snapinfiles' => urlencode($this->get('snapinpath'))
        ];
        $urls = [];
        foreach ((array)$keys as $key => &$data) {
            $urls[] = sprintf(
                '%s?path=%s',
                $url,
                $data
            );
            unset($data);
        }
        $paths = self::$FOGURLRequests->process($urls);
        $pat = '#dev|postdownloadscripts|ssl#';
        $values = [];
        $index = 0;
        foreach ((array)$keys as $key => &$data) {
            $values = $paths[$index];
            unset($paths[$index]);
            $values = json_decode($values, true);
            $values = array_map('basename', (array)$values);
            $values = preg_replace(
                $pat,
                '',
                $values
            );
            $values = array_unique(
                (array)$values
            );
            $values = array_filter(
                (array)$values
            );
            if ($key === 'images') {
                $find = ['path' => $values];
                Route::ids(
                    'image',
                    $find
                );
                $values = json_decode(Route::getData(), true);
            }
            $this->set($key, $values);
            $index++;
            unset($data);
        }
        unset($values, $paths);
    }
    /**
     * Loads the snapins available on this node.
     *
     * @return void
     */
    public function getSnapinfiles()
    {
        $this->_getData();
    }
    /**
     * Loads the images available on this node.
     *
     * @return void
     */
    public function getImages()
    {
        $this->_getData();
    }
    /**
     * Gets this node's load of clients.
     *
     * @return float
     */
    public function getClientLoad()
    {
        if ($this->getUsedSlotCount() + $this->getQueuedSlotCount() < 0) {
            return 0;
        }
        if ($this->get('maxClients') < 1) {
            return 0;
        }
        return (float) (
            $this->getUsedSlotCount() + $this->getQueuedSlotCount()
        ) / $this->get('maxClients');
    }
    /**
     * Load used tasks.
     *
     * @return void
     */
    protected function loadUsedtasks()
    {
        $used = explode(',', self::getSetting('FOG_USED_TASKS'));
        if (count($used) < 1) {
            $used = [
                TaskType::DEPLOY,
                TaskType::DEPLOY_CAPTURE,
                TaskType::DEPLOY_NO_SNAPINS
            ];
        }
        $this->set('usedtasks', $used);
    }
    /**
     * Gets this node's used count.
     *
     * @return int
     */
    public function getUsedSlotCount()
    {
        $countTasks = 0;
        $usedtasks = $this->get('usedtasks');
        $findTasks = [
            'stateID' => self::getProgressState(),
            'storagenodeID' => $this->get('id'),
            'typeID' => $usedtasks,
        ];
        $countTasks = self::getClass('TaskManager')->count($findTasks);
        $index = array_search(8, $usedtasks);
        if ($index === false) {
            return $countTasks;
        }
        $find = [
            'stateID' => self::getProgressState(),
            'typeID' => TaskType::MULTICAST
        ];
        Route::ids(
            'task',
            $find
        );
        $taskids = json_decode(Route::getData(), true);
        $find = ['taskID' => $taskids];
        Route::ids(
            'multicastsessionassociation',
            $find,
            'msID'
        );
        $msids = json_decode(Route::getData(), true);
        $countTasks += count($msids);

        return $countTasks;
    }
    /**
     * Gets the queued hosts on this node.
     *
     * @return int
     */
    public function getQueuedSlotCount()
    {
        $countTasks = 0;
        $usedtasks = $this->get('usedtasks');
        $findTasks = [
            'stateID' => self::getQueuedStates(),
            'storagenodeID' => $this->get('id'),
            'typeID' => $usedtasks
        ];
        $countTasks = self::getClass('TaskManager')->count($findTasks);
        $index = array_search(8, $usedtasks);
        if ($index === false) {
            return $countTasks;
        }
        $find = [
            'stateID' => self::getQueuedStates(),
            'typeID' => TaskType::MULTICAST
        ];
        Route::ids(
            'task',
            $find
        );
        $taskids = json_decode(Route::getData(), true);
        $find = ['taskID' => $taskids];
        Route::ids(
            'multicastsessionassociation',
            $find,
            'msID'
        );
        $msids = json_decode(Route::getData(), true);
        $countTasks += count($msids);

        return $countTasks;
    }
}
