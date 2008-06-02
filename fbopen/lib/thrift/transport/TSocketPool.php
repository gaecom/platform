<?php

/******BEGIN LICENSE BLOCK*******
* 
* Common Public Attribution License Version 1.0.
*
* The contents of this file are subject to the Common Public Attribution 
* License Version 1.0 (the "License") you may not use this file except in 
* compliance with the License. You may obtain a copy of the License at
* http://developers.facebook.com/fbopen/cpal.html. The License is based 
* on the Mozilla Public License Version 1.1 but Sections 14 and 15 have 
* been added to cover use of software over a computer network and provide 
* for limited attribution for the Original Developer. In addition, Exhibit A 
* has been modified to be consistent with Exhibit B.
* Software distributed under the License is distributed on an "AS IS" basis, 
* WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License 
* for the specific language governing rights and limitations under the License.
* The Original Code is Facebook Open Platform.
* The Original Developer is the Initial Developer.
* The Initial Developer of the Original Code is Facebook, Inc.  All portions 
* of the code written by Facebook, Inc are 
* Copyright 2006-2008 Facebook, Inc. All Rights Reserved.
*
*
********END LICENSE BLOCK*********/


/**
 * Copyright (c) 2006- Facebook
 * Distributed under the Thrift Software License
 *
 * See accompanying file LICENSE or visit the Thrift site at:
 * http://developers.facebook.com/thrift/
 *
 * @package thrift.transport
 * @author Mark Slee <mcslee@facebook.com>
 */

/** Inherits from Socket */
include_once $GLOBALS['THRIFT_ROOT'].'/transport/TSocket.php';

/**
 * This library makes use of APC cache to make hosts as down in a web
 * environment. If you are running from the CLI or on a system without APC
 * installed, then these null functions will step in and act like cache
 * misses.
 */
if (!function_exists('apc_fetch')) {
  function apc_fetch($key) { return FALSE; }
  function apc_store($key, $var, $ttl=0) { return FALSE; }
}

/**
 * Sockets implementation of the TTransport interface that allows connection
 * to a pool of servers.
 *
 * @package thrift.transport
 * @author Mark Slee <mcslee@facebook.com>
 */
class TSocketPool extends TSocket {

  /**
   * Remote servers. Array of associative arrays with 'host' and 'port' keys
   */
  private $servers_ = array();

  /**
   * How many times to retry each host in connect
   *
   * @var int
   */
  private $numRetries_ = 1;

  /**
   * Retry interval in seconds, how long to not try a host if it has been
   * marked as down.
   *
   * @var int
   */
  private $retryInterval_ = 60;

  /**
   * Max consecutive failures before marking a host down.
   *
   * @var int
   */
  private $maxConsecutiveFailures_ = 1;

  /**
   * Try hosts in order? or Randomized?
   *
   * @var bool
   */
  private $randomize_ = TRUE;

  /**
   * Always try last host, even if marked down?
   *
   * @var bool
   */
  private $alwaysTryLast_ = TRUE;

  /**
   * Socket pool constructor
   *
   * @param array  $hosts        List of remote hostnames
   * @param mixed  $ports        Array of remote ports, or a single common port
   * @param bool   $persist      Whether to use a persistent socket
   * @param mixed  $debugHandler Function for error logging
   */
  public function __construct($hosts=array('localhost'),
                              $ports=array(9090),
                              $persist=FALSE,
                              $debugHandler=null) {
    parent::__construct(null, 0, $persist, $debugHandler);

    if (!is_array($ports)) {
      $port = $ports;
      $ports = array();
      foreach ($hosts as $key => $val) {
        $ports[$key] = $port;
      }
    }

    foreach ($hosts as $key => $host) {
      $this->servers_ []= array('host' => $host,
                                'port' => $ports[$key]);
    }
  }

  /**
   * Add a server to the pool
   *
   * This function does not prevent you from adding a duplicate server entry.
   *
   * @param string $host hostname or IP
   * @param int $port port
   */
  public function addServer($host, $port) {
    $this->servers_[] = array('host' => $host, 'port' => $port);
  }

  /**
   * Sets how many time to keep retrying a host in the connect function.
   *
   * @param int $numRetries
   */
  public function setNumRetries($numRetries) {
    $this->numRetries_ = $numRetries;
  }

  /**
   * Sets how long to wait until retrying a host if it was marked down
   *
   * @param int $numRetries
   */
  public function setRetryInterval($retryInterval) {
    $this->retryInterval_ = $retryInterval;
  }

  /**
   * Sets how many time to keep retrying a host before marking it as down.
   *
   * @param int $numRetries
   */
  public function setMaxConsecutiveFailures($maxConsecutiveFailures) {
    $this->maxConsecutiveFailures_ = $maxConsecutiveFailures;
  }

  /**
   * Turns randomization in connect order on or off.
   *
   * @param bool $randomize
   */
  public function setRandomize($randomize) {
    $this->randomize_ = $randomize;
  }

  /**
   * Whether to always try the last server.
   *
   * @param bool $alwaysTryLast
   */
  public function setAlwaysTryLast($alwaysTryLast) {
    $this->alwaysTryLast_ = $alwaysTryLast;
  }


  /**
   * Connects the socket by iterating through all the servers in the pool
   * and trying to find one that works.
   */
  public function open() {
    // Check if we want order randomization
    if ($this->randomize_) {
      shuffle($this->servers_);
    }

    // Count servers to identify the "last" one
    $numServers = count($this->servers_);

    for ($i = 0; $i < $numServers; ++$i) {

      // This extracts the $host and $port variables
      extract($this->servers_[$i]);

      // Check APC cache for a record of this server being down
      $failtimeKey = 'thrift_failtime:'.$host.':'.$port.'~';

      // Cache miss? Assume it's OK
      $lastFailtime = apc_fetch($failtimeKey);
      if ($lastFailtime === FALSE) {
        $lastFailtime = 0;
      }

      $retryIntervalPassed = FALSE;

      // Cache hit...make sure enough the retry interval has elapsed
      if ($lastFailtime > 0) {
        $elapsed = time() - $lastFailtime;
        if ($elapsed > $this->retryInterval_) {
          $retryIntervalPassed = TRUE;
          if ($this->debug_) {
            call_user_func($this->debugHandler_,
                           'TSocketPool: retryInterval '.
                           '('.$this->retryInterval_.') '.
                           'has passed for host '.$host.':'.$port);
          }
        }
      }

      // Only connect if not in the middle of a fail interval, OR if this
      // is the LAST server we are trying, just hammer away on it
      $isLastServer = FALSE;
      if ($this->alwaysTryLast_) {
        $isLastServer = ($i == ($numServers - 1));
      }

      if (($lastFailtime === 0) ||
          ($isLastServer) ||
          ($lastFailtime > 0 && $retryIntervalPassed)) {

        // Set underlying TSocket params to this one
        $this->host_ = $host;
        $this->port_ = $port;

        // Try up to numRetries_ connections per server
        for ($attempt = 0; $attempt < $this->numRetries_; $attempt++) {
          try {
            // Use the underlying TSocket open function
            parent::open();

            // Only clear the failure counts if required to do so
            if ($lastFailtime > 0) {
              apc_store($failtimeKey, 0);
            }

            // Successful connection, return now
            return;

          } catch (TException $tx) {
            // Connection failed
          }
        }

        // Mark failure of this host in the cache
        $consecfailsKey = 'thrift_consecfails:'.$host.':'.$port.'~';

        // Ignore cache misses
        $consecfails = apc_fetch($consecfailsKey);
        if ($consecfails === FALSE) {
          $consecfails = 0;
        }

        // Increment by one
        $consecfails++;

        // Log and cache this failure
        if ($consecfails >= $this->maxConsecutiveFailures_) {
          if ($this->debug_) {
            call_user_func($this->debugHandler_,
                           'TSocketPool: marking '.$host.':'.$port.
                           ' as down for '.$this->retryInterval_.' secs '.
                           'after '.$consecfails.' failed attempts.');
          }
          // Store the failure time
          apc_store($failtimeKey, time());

          // Clear the count of consecutive failures
          apc_store($consecfailsKey, 0);
        } else {
          apc_store($consecfailsKey, $consecfails);
        }
      }
    }

    // We failed them all. The system is totally ill!
    $error = 'TSocketPool: All hosts in pool are down. ';
    $hosts = array();
    foreach ($this->servers_ as $server) {
      $hosts []= $server['host'].':'.$server['port'];
    }
    $hostlist = implode(',', $hosts);
    $error .= '('.$hostlist.')';
    if ($this->debug_) {
      call_user_func($this->debugHandler_, $error);
    }
    throw new TException($error);
  }
}

?>
