<?php
class Location extends FOGController {
	protected $databaseTable = 'location';
	protected $databaseFields = array(
		'id' => 'lID',
		'name' => 'lName',
		'description' => 'lDesc',
		'createdBy' => 'lCreatedBy',
		'createdTime' => 'lCreatedTime',
		'storageGroupID' => 'lStorageGroupID',
		'storageNodeID' => 'lStorageNodeID',
		'tftp' => 'lTftpEnabled',
	);
	protected $databaseFieldsRequired = array(
		'name',
		'storageGroupID',
	);
    protected $additionalFields = array(
        'hosts',
        'hostsnotinme',
    );
    public function get($key = '') {
        $key = $this->key($key);
        if (!$this->isLoaded($key)) $this->loadItem($key);
        return parent::get($key);
    }
    public function set($key,$value) {
        $key = $this->key($key);
        if (!$this->isLoaded($key)) $this->loadItem($key);
        return parent::set($key,$value);
    }
    public function add($key,$value) {
        $key = $this->key($key);
        if (!$this->isLoaded($key)) $this->loadItem($key);
        return parent::add($key,$value);
    }
    public function remove($key,$value) {
        $key = $this->key($key);
        if (!$this->isLoaded($key)) $this->loadItem($key);
        return parent::remove($key,$value);
    }
    public function destroy($field = 'id') {
        $this->getClass('LocationAssociationManager')->destroy(array('locationID'=>$this->get('id')));
        return parent::destroy($field);
    }
    public function save() {
        parent::save();
        if ($this->isLoaded('hosts')) {
            $DBHostIDs = $this->getSubObjectIDs('LocationAssociation',array('locationID'=>$this->get('id'),'hostID'));
            $RemoveHostIDs = array_diff((array)$DBHostIDs,(array)$this->get('hosts'));
            if (count($RemoveHostIDs)) {
                $this->getClass('LocationAssociationManager')->destroy(array('locationID'=>$this->get('id'),'hostID'=>$RemoveHostIDs));
                $DBHostIDs = $this->getSubObjectIDs('LocationAssociation',array('locationID'=>$this->get('id'),'hostID'));
                unset($RemoveHostIDs);
            }
            $Hosts = array_diff((array)$this->get('hosts'),(array)$DBHostIDs);
            foreach ((array)$Hosts AS $i => &$Host) {
                $this->getClass('LocationAssociation')
                    ->set('hostID',$Host)
                    ->set('locationID',$this->get('id'))
                    ->save();
            }
            unset($Host);
        }
        return $this;
    }
    public function addHost($addArray) {
        $Hosts = array_unique(array_diff((array)$addArray,(array)$this->get('hosts')));
        if (count($Hosts)) {
            $Hosts = array_merge((array)$this->get('hosts'),(array)$Hosts);
            $this->set('hosts',$Hosts);
        }
        return $this;
    }
    public function removeHost($removeArray) {
        $this->set('hosts',array_unique(array_diff((array)$this->get('hosts'),(array)$removeArray)));
        return $this;
    }
	public function getStorageGroup() {
		return $this->getClass('StorageGroup',$this->get('storageGroupID'));
	}
	public function getStorageNode() {
		return $this->getClass('StorageNode',$this->get('storageNodeID'));
	}
    protected function loadHosts() {
        if ($this->get('id')) $this->set('hosts',$this->getSubObjectIDs('LocationAssociation',array('locationID'=>$this->get('id')),'hostID'));
    }
    protected function loadHostsnotinme() {
        if ($this->get('id')) {
            $find = array('id'=>$this->get('hosts'));
            $this->set('hostsnotinme',$this->getSubObjectIDs('Host',$find,'',true));
            unset($find);
        }
    }
}
