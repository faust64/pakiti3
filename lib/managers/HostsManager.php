<?php
# Copyright (c) 2011, CESNET. All rights reserved.
# 
# Redistribution and use in source and binary forms, with or
# without modification, are permitted provided that the following
# conditions are met:
# 
#   o Redistributions of source code must retain the above
#     copyright notice, this list of conditions and the following
#     disclaimer.
#   o Redistributions in binary form must reproduce the above
#     copyright notice, this list of conditions and the following
#     disclaimer in the documentation and/or other materials
#     provided with the distribution.
# 
# THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND
# CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
# INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
# MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
# DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS
# BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
# EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
# TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
# DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
# ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
# OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
# OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
# POSSIBILITY OF SUCH DAMAGE. 

class HostsManager extends DefaultManager {

  /**
  * Create if not exist, else update it
  * @return false if already exist
  */
  public function storeHost(Host &$host) {
    Utils::log(LOG_DEBUG, "Storing the host", __FILE__, __LINE__);
    if ($host == null) {
      Utils::log(LOG_ERR, "Exception", __FILE__, __LINE__);
      throw new Exception("Host object is not valid or Host.id is not set");
    }

    # Get the osId
    $os = new Os();
    $os->setName($host->getOsName());
    $this->getPakiti()->getManager("OsesManager")->storeOs($os);
    $host->setOsId($os->getId());
    $host->setOs($os);

    # Get the archId
    $arch = new Arch();
    $arch->setName($host->getArchName());
    $this->getPakiti()->getManager("ArchsManager")->storeArch($arch);
    $host->setArchId($arch->getId());
    $host->setArch($arch);

    # Get the domainId
    $domain = new Domain();
    $domain->setName($host->getDomainName());
    $this->getPakiti()->getManager("DomainsManager")->storeDomain($domain);
    $host->setDomainId($domain->getId());
    $host->setDomain($domain);

    $new = false;
    $dao = $this->getPakiti()->getDao("Host");
    $hostId = $this->getHostId($host);
    if ($hostId != -1) {
      $host->setId($hostId);
      # Host exist, so update it
      $this->getPakiti()->getDao("Host")->update($host);
    } else {
      # Host is missing, so store it
      $this->getPakiti()->getDao("Host")->create($host);
      $new = true;
    }
    return $new;
  }

  /*
   * Try to find host using hostname, reporterHostnem, ip and reporterIp
   */
  public function getHostId(Host &$host) {
    if ($host == null) {
      Utils::log(LOG_ERR, "Exception", __FILE__, __LINE__);
      throw new Exception("Host object is not valid.");
    }

    Utils::log(LOG_DEBUG, "Getting the host ID", __FILE__, __LINE__);
    return $this->getPakiti()->getDao("Host")->getId($host);
  }
  
  /*
   * Get the host by its ID
   */
  public function getHostById($id) {
    Utils::log(LOG_DEBUG, "Getting the host by its ID [id=$id]", __FILE__, __LINE__);
    $host =& $this->getPakiti()->getDao("Host")->getById($id);
    if (is_object($host)) {
      $host->setArch($this->getPakiti()->getDao("Arch")->getById($host->getArchId()));
      $host->setOs($this->getPakiti()->getDao("Os")->getById($host->getOsId()));
      $host->setDomain($this->getPakiti()->getDao("Domain")->getById($host->getDomainId()));
    } else return null;
    
    return $host;
  }
  
/*
   * Get the host by its hostname
   */
  public function getHostByHostname($hostname) {
    Utils::log(LOG_DEBUG, "Getting the host by its hostname [hostname=$hostname]", __FILE__, __LINE__);
    return $this->getPakiti()->getDao("Host")->getByHostname($hostname);  
  }
  
  /*
   * Get all hosts
   */
  public function getHosts($orderBy, $pageSize = -1, $pageNum = -1) {
    Utils::log(LOG_DEBUG, "Getting all hosts", __FILE__, __LINE__);
    $hostsIds =& $this->getPakiti()->getDao("Host")->getHostsIds($orderBy, $pageSize, $pageNum);
    
    $hosts = array();
    foreach ($hostsIds as $hostId) {
      array_push($hosts, $this->getHostById($hostId));
    }
    
    return $hosts;
  }

  /*
   * Get hosts by theirs first letter
   */
  public function getHostsByFirstLetter($firstLetter) {
    Utils::log(LOG_DEBUG, "Getting hosts by firstLetter", __FILE__, __LINE__);
    $hostsIds =& $this->getPakiti()->getDao("Host")->getHostsIdsByFirstLetter($firstLetter);
    
    $hosts = array();
    foreach ($hostsIds as $hostId) {
      array_push($hosts, $this->getHostById($hostId));
    }
    
    return $hosts;
  }

  /*
   * Get Host by tag name
   */
  public function getHostsByTagName($tagName)
  {
    $tagId = $this->getPakiti()->getManager("TagsManager")->getTagIdByName($tagName);
    if ($tagId == -1) {
      Utils::log(LOG_ERR, "Exception", __FILE__, __LINE__);
      throw new Exception("The tag $tagName does not exist");
    }


    $sql = "select Host.id from Host join HostTag on Host.id=HostTag.hostId where
          HostTag.tagId={$tagId}";

    $hostIdsDb =& $this->getPakiti()->getManager("DbManager")->queryToMultiRow($sql);
    $hosts = array();
    foreach ($hostIdsDb as $hostIdDb) {
      $host = $this->getHostById($hostIdDb["id"]);
      array_push($hosts, $host);
    }
    return $hosts;
  }

  /*
   * Get hosts count
   */
  public function getHostsCount() {
    Utils::log(LOG_DEBUG, "Getting hosts count", __FILE__, __LINE__);
    return $this->getPakiti()->getDao("Host")->getHostsIdsCount();
  }
  
  /*
   * Delete the host
   */
  public function deleteHost(Host &$host) {
    if ($host == null || $host->getId() == -1) {
      Utils::log(LOG_ERR, "Exception", __FILE__, __LINE__);
      throw new Exception("Host object is not valid or Host.id is not set");
    }
    // Related data in the db is delete using delete on cascade
    return $this->getPakiti()->getDao("Host")->delete($host);
  }

  public function setLastReportId(Host &$host, Report &$report) {
    if ($host == null || $host->getId() == -1 || $report == null || $report->getId() == -1) {
      Utils::log(LOG_ERR, "Exception", __FILE__, __LINE__);
      throw new Exception("Host or Report pobject is not valid or Host.id or Report.id is not set");
    }
     return $this->getPakiti()->getDao("Host")->setLastReportId($host->getId(), $report->getId()); 
  }
 
  /* 
   * Get list of all oses
   */
  public function getOses($orderBy, $pageSize = -1, $pageNum = -1) {
    Utils::log(LOG_DEBUG, "Getting all oses", __FILE__, __LINE__);
    $osesIds =& $this->getPakiti()->getDao("Os")->getOsesIds($orderBy, $pageSize, $pageNum);

    $oses = array();
    foreach ($osesIds as $osId) {
      array_push($oses, $this->getPakiti()->getDao("Os")->getById($osId));
    }

    return $oses;
  }



  /* 
   * Get list of all archs
   */
  public function getArchs($orderBy, $pageSize = -1, $pageNum = -1) {
    Utils::log(LOG_DEBUG, "Getting all archs", __FILE__, __LINE__);
    $archsIds =& $this->getPakiti()->getDao("Arch")->getArchsIds($orderBy, $pageSize, $pageNum);

    $archs = array();
    foreach ($archsIds as $archId) {
      array_push($archs, $this->getPakiti()->getDao("Arch")->getById($archId));
    }

    return $archs;
  }

  /* 
   * Get arch id
   */
  public function getArchId($name) {
    Utils::log(LOG_DEBUG, "Getting arch Id by Name", __FILE__, __LINE__);
    $arch =& $this->getPakiti()->getDao("Arch")->getByName($name);

    return $arch->getId();
  }

  /* 
   * Get arch
   */
  public function getArch($name) {
    Utils::log(LOG_DEBUG, "Getting arch by Name", __FILE__, __LINE__);
    return $this->getPakiti()->getDao("Arch")->getByName($name);
  }


  /* 
   * Create arch
   */
  public function createArch($name) {
    Utils::log(LOG_DEBUG, "Creating arch $name", __FILE__, __LINE__);
    $arch = new Arch();
    $arch->setName($name);
    $this->getPakiti()->getDao("Arch")->create($arch);

    return $arch;
  }



}
?>
