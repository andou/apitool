<?php

namespace Andou;

/**
 * Your own personal Api Fetcher.
 * 
 * The MIT License (MIT)
 * 
 * Copyright (c) 2014 Antonio Pastorino <antonio.pastorino@gmail.com>
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 * 
 * 
 * @author Antonio Pastorino <antonio.pastorino@gmail.com>
 * @category apitool
 * @package andou/apitool
 * @copyright MIT License (http://opensource.org/licenses/MIT)
 */
class Api {

  /**
   * 
   * @return \Andou\Api
   */
  public static function getInstance() {
    $classname = __CLASS__;
    return new $classname;
  }

  /**
   * The API endpoint
   *
   * @var string 
   */
  protected $_api_address;

  /**
   * States if we have to resolve ipv4 to ipv6 addresses
   *
   * @var boolean 
   */
  protected $_resolve_ip_v4 = TRUE;

  /**
   * States if we should verify the remote peer
   *
   * @var boolean
   */
  protected $_do_not_verify_peer = TRUE;

  /**
   * States if we should verify the remote host
   *
   * @var boolean 
   */
  protected $_do_not_verify_host = TRUE;

  /**
   * Communication timeout delay expressed in seconds
   *
   * @var int 
   */
  protected $_timeout = 20;

  /**
   * States if you should use a proxy or not
   *
   * @var boolean 
   */
  protected $_use_proxy = FALSE;

  /**
   * The proxy port
   *
   * @var int
   */
  protected $_proxy_port = NULL;

  /**
   * The proxy address
   *
   * @var string 
   */
  protected $_proxy_address = NULL;

  /**
   * The proxy password
   *
   * @var string
   */
  protected $_proxy_userpassword = NULL;

  /**
   * Curl handler
   *
   * @var resource 
   */
  protected $_ch;

  /**
   * Underscore cache for magic method decamelize
   *
   * @var array 
   */
  protected static $_underscoreCache = array();

  /**
   * Determine if underscore the method or not
   *
   * @var boolean
   */
  protected $_use_underscore = TRUE;

  /**
   * Definition of HTTP method POST
   */

  const HTTP_METHOD_POST = "POST";
  /**
   * Definition of HTTP method GET
   */
  const HTTP_METHOD_GET = "GET";

  public function __construct() {
    if (defined('API_REMOTE_TIMEOUT')) {
      $this->_timeout = API_REMOTE_TIMEOUT;
    }
  }

  /**
   * Magic method for api call
   * 
   * ->apiCallYourMethod  performs a GET
   * ->apiPostYourMethod  performs a POST
   * ->apiGetYourMethod performs a GET
   * 
   * 
   * @param string $method
   * @param array $args
   * @return boolean | string
   */
  public function __call($method, $args) {
    switch (substr($method, 0, 7)) {
      case 'apiCall' :
        $method = substr($method, 7);
        if ($this->useUnderscores()) {
          $method = $this->_underscore($method);
        }
        return $this->_communicate($method, array_shift($args));
      case 'apiPost' :
        $method = substr($method, 7);
        if ($this->useUnderscores()) {
          $method = $this->_underscore($method);
        }
        return $this->_communicate($method, array_shift($args), self::HTTP_METHOD_POST);
    }

    switch (substr($method, 0, 6)) {
      case 'apiGet' :
        $method = substr($method, 6);
        if ($this->useUnderscores()) {
          $method = $this->_underscore($method);
        }
        return $this->_communicate($method, array_shift($args));
    }
  }

  /**
   * Performs an API call
   * 
   * @todo Add a cache layer
   * @param string $method
   * @return string
   */
  protected function _communicate($method, $params = array(), $http_method = self::HTTP_METHOD_GET) {
    $res = FALSE;
    if ($http_method === self::HTTP_METHOD_GET) {
      $address = $this->_prepareUrl($method, $params);
    } else {
      $address = $this->_prepareUrl($method);
      $this->setOpt(CURLOPT_POST, count($params))
              ->setOpt(CURLOPT_POSTFIELDS, $this->_buildQueryString($params));
    }
    if ($address) {
      $data = $this->setOpt(CURLOPT_URL, $address)
              ->setOpt(CURLOPT_RETURNTRANSFER, 1)
              ->setOpt(CURLOPT_TIMEOUT, $this->_timeout)
              ->_configureIpResolve()
              ->_configureVerifyHost()
              ->_configureProxy()
              ->_exec();

      if (!$this->hasErrors()) {
        $res = $data;
      }
    }
    $this->_closeCommHandler();
    return $res;
  }

  //////////////////////////////////////////////////////////////////////////////
  ////////////////// COMMUNICATION CONFIGURATION HELPERS ///////////////////////
  //////////////////////////////////////////////////////////////////////////////

  /**
   * Sets ip resolve to ipv4 if needed
   * 
   * @return \Andou\Api
   */
  protected function _configureIpResolve() {
    if ($this->_resolve_ip_v4) {
      $this->setOpt(CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    }
    return $this;
  }

  /**
   * Avoid peer and host verification if needed
   * 
   * @return \Andou\Api
   */
  protected function _configureVerifyHost() {
    if ($this->_do_not_verify_host) {
      $this->setOpt(CURLOPT_SSL_VERIFYHOST, FALSE);
    }

    if ($this->_do_not_verify_peer) {
      $this->setOpt(CURLOPT_SSL_VERIFYPEER, FALSE);
    }
    return $this;
  }

  /**
   * Configure proxy if needed
   * 
   * @return \Andou\Api
   */
  protected function _configureProxy() {
    if ($this->_use_proxy) {
      $this->setOpt(CURLOPT_HTTPPROXYTUNNEL, TRUE);
      if ($this->_proxy_port) {
        $this->setOpt(CURLOPT_PROXYPORT, $this->_proxy_port);
      }
      if ($this->_proxy_address) {
        $this->setOpt(CURLOPT_PROXY, $this->_proxy_address);
      }
      if ($this->_proxy_userpassword) {
        $this->setOpt(CURLOPT_PROXYUSERPWD, $this->_proxy_userpassword);
      }
    }
    return $this;
  }

  //////////////////////////////////////////////////////////////////////////////
  ////////////////////////// GETTER AND SETTER /////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////

  /**
   * Sets the address from which retrieve remote API information
   * 
   * @return \Andou\Api
   */
  public function setApiAddress($api_address) {
    $this->_api_address = $api_address;
    return $this;
  }

  /**
   * Returns the api address if specified, FALSE otherwise
   * 
   * @return string
   */
  public function getApiAddress() {
    return isset($this->_api_address) ?
            $this->_trimAddress($this->_api_address) : FALSE;
  }

  /**
   * States that the connnection should not use IP V4 ip resolving
   * 
   * @return \Andou\Api
   */
  public function doNotUseIpV4Resolve() {
    $this->_resolve_ip_v4 = FALSE;
    return $this;
  }

  /**
   * States that the connection should verify the peer
   * 
   * @return \Andou\Api
   */
  public function verifyPeer() {
    $this->_do_not_verify_peer = FALSE;
    return $this;
  }

  /**
   * States that the connection should verify the host
   * 
   * @return \Andou\Api
   */
  public function verifyHost() {
    $this->_do_not_verify_host = FALSE;
    return $this;
  }

  /**
   * Sets the timeout
   * 
   * @param string $timeout
   * @return \Andou\Api
   */
  public function setTimeout($timeout) {
    $this->_timeout = $timeout;
    return $this;
  }

  /**
   * Determine the use of a proxy
   * 
   * @return \Andou\Api
   */
  public function useProxy() {
    $this->_use_proxy = TRUE;
    return $this;
  }

  /**
   * Determine if this class should underscore the methods or not
   * 
   * @param boolean $use_underscore
   * @return \Andou\Api
   */
  public function setUnderscore($use_underscore = TRUE) {
    $this->_use_underscore = $use_underscore;
    return $this;
  }

  /**
   * Returns TRUE if method names are to be underscored or not
   * 
   * @return boolean
   */
  public function useUnderscores() {
    return $this->_use_underscore;
  }

  /**
   * Sets the user and password for the proxy
   * 
   * @param string $_proxy_userpassword
   * @return \Andou\Api
   */
  public function setProxyUserpassword($_proxy_userpassword) {
    $this->_proxy_userpassword = $_proxy_userpassword;
    return $this;
  }

  /**
   * Sets the proxy port
   * 
   * @param int $proxy_port
   * @return \Andou\Api
   */
  public function setProxyPort($proxy_port) {
    $this->_proxy_port = $proxy_port;
    return $this;
  }

  /**
   * Sets the proxy address
   * 
   * @param string $proxy_address
   * @return \Andou\Api
   */
  public function setProxyAddress($proxy_address) {
    $this->_proxy_address = $proxy_address;
    return $this;
  }

  //////////////////////////////////////////////////////////////////////////////
  ///////////////////////// URL TRANSFORMATION HELPERS /////////////////////////
  //////////////////////////////////////////////////////////////////////////////

  /**
   * Prepare an URL to be invoked
   * 
   * @param string $method
   * @param array $params
   * @return boolean
   */
  protected function _prepareUrl($method, $params = array()) {
    $api_address = $this->getApiAddress();
    if ($api_address && isset($method)) {
      return rtrim(sprintf("%s%s?%s", $api_address, $this->_trimMethod($method), $this->_buildQueryString($params)), "?");
    }
    return FALSE;
  }

  /**
   * Builds a query string
   * 
   * @param array $params
   * @return string
   */
  protected function _buildQueryString($params = array()) {
    if (!is_array($params)) {
      $params = array();
    }
    return count($params) ? http_build_query($params) : "";
  }

  /**
   * Trims an address removing the trailing slash
   * 
   * @param string $address
   * @return string
   */
  protected function _trimAddress($address) {
    return ltrim(rtrim(trim($address), "/"), "/");
  }

  /**
   * Trims a method name removing an eventual starting slash
   * 
   * @param string $method
   * @return string
   */
  protected function _trimMethod($method) {
    $_method = ltrim(trim(str_replace("_", "/", $method)), "/");
    return $_method != "" ? "/" . $_method : $_method;
  }

  //////////////////////////////////////////////////////////////////////////////
  ///////////////////////// CURL HANDLER HELPERS ///////////////////////////////
  //////////////////////////////////////////////////////////////////////////////

  /**
   * Initialize the curl Handle if not set
   * 
   * @return resource
   */
  protected function _getCommHandler() {
    if (!isset($this->_ch)) {
      $this->_ch = curl_init();
    }
    return $this->_ch;
  }

  /**
   * Cloae a curl communication
   */
  protected function _closeCommHandler() {
    curl_close($this->_ch);
    $this->_ch = NULL;
  }

  /**
   * Sets a Curl Option
   * 
   * @param string $opt_key
   * @param mixed $opt_value
   */
  public function setOpt($opt_key, $opt_value) {
    curl_setopt($this->_getCommHandler(), $opt_key, $opt_value);
    return $this;
  }

  /**
   * Checks if there are errors
   * 
   * @return boolean
   */
  public function hasErrors() {
    return (boolean) $this->_getErrorNumber();
  }

  /**
   * Return errors
   * 
   * @return string
   */
  public function getError() {
    return curl_error($this->_getCommHandler());
  }

  /**
   * Return error number if any
   * 
   * @return int
   */
  public function getErrorNumber() {
    return curl_errno($this->_getCommHandler());
  }

  /**
   * Exec a cUrl communication
   * 
   * @param boolean $trim
   * @return string
   */
  protected function _exec($trim = TRUE) {
    return $trim ?
            trim(curl_exec($this->_getCommHandler())) : curl_exec($this->_getCommHandler());
  }

  /**
   * Decamelize a string
   * 
   * @param string $name
   * @return string
   */
  protected function _underscore($name) {
    if (isset(self::$_underscoreCache[$name])) {
      return self::$_underscoreCache[$name];
    }
    $result = strtolower(preg_replace('/(.)([A-Z])/', "$1_$2", $name));
    self::$_underscoreCache[$name] = $result;
    return $result;
  }

}