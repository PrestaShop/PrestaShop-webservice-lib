<?php
/*
* 2007-2022 PrestaShop SA and Contributors
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* https://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to https://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2022 PrestaShop SA
*  @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
* PrestaShop Webservice Library
* @package PrestaShopWebservice
*/

/**
 * @package PrestaShopWebservice
 */
class PrestaShopWebservice
{

    /** @var string Shop URL */
    protected $url;

    /** @var string Authentication key */
    protected $key;

    /** @var boolean is debug activated */
    protected $debug;

    /** @var string PS version */
    protected $version;

    /** @var string Minimal version of PrestaShop to use with this library */
    const psCompatibleVersionsMin = '1.4.0.0';
    /** @var string Maximal version of PrestaShop to use with this library */
    const psCompatibleVersionsMax = '8.1.1';

    /**
     * PrestaShopWebservice constructor. Throw an exception when CURL is not installed/activated
     * <code>
     * <?php
     * require_once('./PrestaShopWebservice.php');
     * try
     * {
     *    $ws = new PrestaShopWebservice('https://mystore.com/', 'ZQ88PRJX5VWQHCWE4EE7SQ7HPNX00RAJ', false);
     *    // Now we have a webservice object to play with
     * }
     * catch (PrestaShopWebserviceException $ex)
     * {
     *    echo 'Error : '.$ex->getMessage();
     * }
     * ?>
     * </code>
     *
     * @param string $url Root URL for the shop
     * @param string $key Authentication key
     * @param mixed $debug Debug mode Activated (true) or deactivated (false)
     *
     * @throws PrestaShopWebserviceException if curl is not loaded
     */
    function __construct($url, $key, $debug = true)
    {
        if (!extension_loaded('curl')) {
            throw PrestaShopWebserviceMissingPreconditionException::missingExtension('curl');
        }
        $this->url = $url;
        $this->key = $key;
        $this->debug = $debug;
        $this->version = 'unknown';
    }

    /**
     * Take the status code and throw an exception if the server didn't return 200 or 201 code
     * <p>Unique parameter must take : <br><br>
     * 'status_code' => Status code of an HTTP return<br>
     * 'response' => CURL response
     * </p>
     *
     * @param array{status_code: string, response: string} $request Response elements of CURL request
     *
     * @throws PrestaShopWebserviceException if HTTP status code is not 200 or 201
     */
    protected function checkStatusCode($request)
    {
        $this->assertStatusCode($request['status_code']);
    }

    private function assertStatusCode($statusCode)
    {
        switch ($statusCode) {
            case 100:
                throw PrestaShopWebserviceStatusException::shouldNotReceive100ContinueStatus();
            case 101:
                throw PrestaShopWebserviceStatusException::shouldNotReceive101SwitchingProtocolsStatus();
            case 102:
                throw PrestaShopWebserviceStatusException::shouldNotReceive102ProcessingStatus();
            case 103:
                throw PrestaShopWebserviceStatusException::shouldNotReceive103EarlyHintsStatus();
            case 204:
                throw new PrestaShopWebserviceNoContentException();
            case 300:
                throw PrestaShopWebserviceStatusException::shouldNotReceive300MultipleChoicesStatus();
            case 301:
                throw PrestaShopWebserviceStatusException::shouldNotReceive301MovedPermanentlyStatus();
            case 302:
                throw PrestaShopWebserviceStatusException::shouldNotReceive302FoundStatus();
            case 303:
                throw PrestaShopWebserviceStatusException::shouldNotReceive303SeeOtherStatus();
            case 304:
                throw PrestaShopWebserviceStatusException::shouldNotReceive304NotModifiedStatus();
            case 307:
                throw PrestaShopWebserviceStatusException::shouldNotReceive307TemporaryRedirectStatus();
            case 308:
                throw PrestaShopWebserviceStatusException::shouldNotReceive308PermanentRedirectStatus();
            case 400:
                throw new PrestaShopWebserviceBadRequestException();
            case 401:
                throw new PrestaShopWebserviceUnauthorizedException();
            case 403:
                throw new PrestaShopWebserviceForbiddenException();
            case 404:
                throw new PrestaShopWebserviceNotFoundException();
            case 405:
                throw new PrestaShopWebserviceMethodNotAllowedException();
            case 429:
                throw new PrestaShopWebserviceTooManyRequestsException();
        }
        if ($statusCode >= 100 && $statusCode < 200
            || $statusCode >= 202 && $statusCode < 300
            || $statusCode >= 300 && $statusCode < 500
            || $statusCode >= 600
            || $statusCode < 100
        ) {
            throw new PrestaShopWebserviceClientException('This call to PrestaShop Web Services responded with a non-standard code or a code this client could not handle properly. This can come from your web server or reverse proxy configuration.', $statusCode);
        }

        if ($statusCode >= 500 && $statusCode < 600) {
            throw new PrestaShopWebserviceServerException('This call to PrestaShop Web Services responded with a status code indicating it is not available or under a too heavy load to process your request.', $statusCode);
        }
    }

    /**
     * Provides default parameters for the curl connection(s)
     * @return array Default parameters for curl connection(s)
     */
    protected function getCurlDefaultParams()
    {
        $defaultParams = array(
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->key . ':',
            CURLOPT_HTTPHEADER => array('Expect:'),
            //CURLOPT_SSL_VERIFYPEER => false, // reminder, in dev environment sometimes self-signed certificates are used
            //CURLOPT_CAINFO => "PATH2CAINFO", // ssl certificate chain checking
            //CURLOPT_CAPATH => "PATH2CAPATH",
        );
        return $defaultParams;
    }

    /**
     * Handles a CURL request to PrestaShop Webservice. Can throw exception.
     *
     * @param string $url Resource name
     * @param mixed $curl_params CURL parameters (sent to curl_set_opt)
     *
     * @return array status_code, response, header
     *
     * @throws PrestaShopWebserviceException
     */
    protected function executeRequest($url, $curl_params = array())
    {
        $defaultParams = $this->getCurlDefaultParams();

        $session = curl_init($url);

        $curl_options = array();
        foreach ($defaultParams as $defkey => $defval) {
            if (isset($curl_params[$defkey])) {
                $curl_options[$defkey] = $curl_params[$defkey];
            } else {
                $curl_options[$defkey] = $defaultParams[$defkey];
            }
        }
        foreach ($curl_params as $defkey => $defval) {
            if (!isset($curl_options[$defkey])) {
                $curl_options[$defkey] = $curl_params[$defkey];
            }
        }

        curl_setopt_array($session, $curl_options);
        $response = curl_exec($session);

        $index = strpos($response, "\r\n\r\n");
        if ($index === false && $curl_params[CURLOPT_CUSTOMREQUEST] != 'HEAD') {
            throw new PrestaShopWebserviceServerException('Bad HTTP response ' . $response . curl_error($session));
        }

        $header = substr($response, 0, $index);
        $body = substr($response, $index + 4);

        $headerArrayTmp = explode("\n", $header);

        $headerArray = array();
        foreach ($headerArrayTmp as &$headerItem) {
            $tmp = explode(':', $headerItem);
            $tmp = array_map('trim', $tmp);
            if (count($tmp) == 2) {
                $headerArray[$tmp[0]] = $tmp[1];
            }
        }

        if (array_key_exists('PSWS-Version', $headerArray)) {
            $this->version = $headerArray['PSWS-Version'];
            if (
                version_compare(PrestaShopWebservice::psCompatibleVersionsMin, $headerArray['PSWS-Version']) == 1 ||
                version_compare(PrestaShopWebservice::psCompatibleVersionsMax, $headerArray['PSWS-Version']) == -1
            ) {
                throw new PrestaShopWebserviceMissingPreconditionException(
                    'This library is not compatible with this version of PrestaShop. Please upgrade/downgrade this library'
                );
            }
        }

        if ($this->debug) {
            $this->printDebug('HTTP REQUEST HEADER', curl_getinfo($session, CURLINFO_HEADER_OUT));
            $this->printDebug('HTTP RESPONSE HEADER', $header);
        }
        $status_code = curl_getinfo($session, CURLINFO_HTTP_CODE);
        if ($status_code === 0) {
            throw new PrestaShopWebserviceServerException('CURL Error: ' . curl_error($session));
        }
        curl_close($session);
        if ($this->debug) {
            if ($curl_params[CURLOPT_CUSTOMREQUEST] == 'PUT' || $curl_params[CURLOPT_CUSTOMREQUEST] == 'POST') {
                $this->printDebug('XML SENT', urldecode($curl_params[CURLOPT_POSTFIELDS]));
            }
            if ($curl_params[CURLOPT_CUSTOMREQUEST] != 'DELETE' && $curl_params[CURLOPT_CUSTOMREQUEST] != 'HEAD') {
                $this->printDebug('RETURN HTTP BODY', $body);
            }
        }
        return array('status_code' => $status_code, 'response' => $body, 'header' => $header);
    }

    public function printDebug($title, $content)
    {
        if (php_sapi_name() == 'cli') {
            echo $title . PHP_EOL . $content;
        } else {
            echo '<div style="display:table;background:#CCC;font-size:8pt;padding:7px"><h6 style="font-size:9pt;margin:0">'
                . $title
                . '</h6><pre>'
                . htmlentities($content)
                . '</pre></div>';
        }
    }

    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Load XML from string. Can throw exception
     *
     * @param string $response String from a CURL response
     *
     * @return SimpleXMLElement status_code, response
     * @throws PrestaShopWebserviceException
     */
    protected function parseXML($response)
    {
        if ($response != '') {
            libxml_clear_errors();
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string(trim($response), 'SimpleXMLElement', LIBXML_NOCDATA);
            if (libxml_get_errors()) {
                $msg = var_export(libxml_get_errors(), true);
                libxml_clear_errors();
                throw new PrestaShopWebserviceServerException('HTTP XML response is not parsable: ' . $msg);
            }
            return $xml;
        } else {
            throw new PrestaShopWebserviceServerException('HTTP response is empty');
        }
    }

    /**
     * Add (POST) a resource
     * <p>Unique parameter must take : <br><br>
     * 'resource' => Resource name<br>
     * 'postXml' => Full XML string to add resource<br><br>
     * Examples are given in the tutorial</p>
     *
     * @param array $options
     *
     * @return SimpleXMLElement status_code, response
     * @throws PrestaShopWebserviceException
     */
    public function add($options)
    {
        $xml = '';

        if (isset($options['resource'], $options['postXml']) || isset($options['url'], $options['postXml'])) {
            $url = (isset($options['resource']) ? $this->url . '/api/' . $options['resource'] : $options['url']);
            $xml = $options['postXml'];
            if (isset($options['id_shop'])) {
                $url .= '&id_shop=' . $options['id_shop'];
            }
            if (isset($options['id_group_shop'])) {
                $url .= '&id_group_shop=' . $options['id_group_shop'];
            }
        } else {
            throw PrestaShopWebserviceBadParametersException::badParameters();
        }
        $request = $this->executeRequest($url, array(CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_POSTFIELDS => $xml));

        $this->checkStatusCode($request);
        return $this->parseXML($request['response']);
    }

    /**
     * Retrieve (GET) a resource
     * <p>Unique parameter must take : <br><br>
     * 'url' => Full URL for a GET request of Webservice (ex: https://mystore.com/api/customers/1/)<br>
     * OR<br>
     * 'resource' => Resource name,<br>
     * 'id' => ID of a resource you want to get<br><br>
     * </p>
     * <code>
     * <?php
     * require_once('./PrestaShopWebservice.php');
     * try
     * {
     * $ws = new PrestaShopWebservice('https://mystore.com/', 'ZQ88PRJX5VWQHCWE4EE7SQ7HPNX00RAJ', false);
     * $xml = $ws->get(array('resource' => 'orders', 'id' => 1));
     *    // Here in $xml, a SimpleXMLElement object you can parse
     * foreach ($xml->children()->children() as $attName => $attValue)
     *    echo $attName.' = '.$attValue.'<br />';
     * }
     * catch (PrestaShopWebserviceException $ex)
     * {
     *    echo 'Error : '.$ex->getMessage();
     * }
     * ?>
     * </code>
     *
     * @param array $options Array representing resource to get.
     *
     * @return SimpleXMLElement status_code, response
     * @throws PrestaShopWebserviceException
     */
    public function get($options)
    {
        if (isset($options['url'])) {
            $url = $options['url'];
        } elseif (isset($options['resource'])) {
            $url = $this->url . '/api/' . $options['resource'];
            $url_params = array();
            if (isset($options['id'])) {
                $url .= '/' . $options['id'];
            }

            $params = array('filter', 'display', 'sort', 'limit', 'id_shop', 'id_group_shop', 'schema', 'language', 'date', 'price');
            foreach ($params as $p) {
                foreach ($options as $k => $o) {
                    if (strpos($k, $p) !== false) {
                        $url_params[$k] = $options[$k];
                    }
                }
            }
            if (count($url_params) > 0) {
                $url .= '?' . http_build_query($url_params);
            }
        } else {
            throw PrestaShopWebserviceBadParametersException::badParameters();
        }

        $request = $this->executeRequest($url, array(CURLOPT_CUSTOMREQUEST => 'GET'));

        $this->checkStatusCode($request);// check the response validity

        return $this->parseXML($request['response']);
    }

    /**
     * Head method (HEAD) a resource
     *
     * @param array $options Array representing resource for head request.
     *
     * @return SimpleXMLElement status_code, response
     * @throws PrestaShopWebserviceException
     */
    public function head($options)
    {
        if (isset($options['url'])) {
            $url = $options['url'];
        } elseif (isset($options['resource'])) {
            $url = $this->url . '/api/' . $options['resource'];
            $url_params = array();
            if (isset($options['id'])) {
                $url .= '/' . $options['id'];
            }

            $params = array('filter', 'display', 'sort', 'limit');
            foreach ($params as $p) {
                foreach ($options as $k => $o) {
                    if (strpos($k, $p) !== false) {
                        $url_params[$k] = $options[$k];
                    }
                }
            }
            if (count($url_params) > 0) {
                $url .= '?' . http_build_query($url_params);
            }
        } else {
            throw PrestaShopWebserviceBadParametersException::badParameters();
        }
        $request = $this->executeRequest($url, array(CURLOPT_CUSTOMREQUEST => 'HEAD', CURLOPT_NOBODY => true));
        $this->checkStatusCode($request);// check the response validity
        return $request['header'];
    }

    /**
     * Edit (PUT) a resource
     * <p>Unique parameter must take : <br><br>
     * 'resource' => Resource name ,<br>
     * 'id' => ID of a resource you want to edit,<br>
     * 'putXml' => Modified XML string of a resource<br><br>
     * Examples are given in the tutorial</p>
     *
     * @param array $options Array representing resource to edit.
     *
     * @return SimpleXMLElement
     * @throws PrestaShopWebserviceException
     */
    public function edit($options)
    {
        $xml = '';
        if (isset($options['url'])) {
            $url = $options['url'];
        } elseif ((isset($options['resource'], $options['id']) || isset($options['url'])) && $options['putXml']) {
            $url = (isset($options['url']) ? $options['url'] :
                $this->url . '/api/' . $options['resource'] . '/' . $options['id']);
            $xml = $options['putXml'];
            if (isset($options['id_shop'])) {
                $url .= '&id_shop=' . $options['id_shop'];
            }
            if (isset($options['id_group_shop'])) {
                $url .= '&id_group_shop=' . $options['id_group_shop'];
            }
        } else {
            throw PrestaShopWebserviceBadParametersException::badParameters();
        }

        $request = $this->executeRequest($url, array(CURLOPT_CUSTOMREQUEST => 'PUT', CURLOPT_POSTFIELDS => $xml));
        $this->checkStatusCode($request);// check the response validity
        return $this->parseXML($request['response']);
    }

    /**
     * Delete (DELETE) a resource.
     * Unique parameter must take : <br><br>
     * 'resource' => Resource name<br>
     * 'id' => ID or array which contains IDs of a resource(s) you want to delete<br><br>
     * <code>
     * <?php
     * require_once('./PrestaShopWebservice.php');
     * try
     * {
     * $ws = new PrestaShopWebservice('https://mystore.com/', 'ZQ88PRJX5VWQHCWE4EE7SQ7HPNX00RAJ', false);
     * $xml = $ws->delete(array('resource' => 'orders', 'id' => 1));
     *    // Following code will not be executed if an exception is thrown.
     *    echo 'Successfully deleted.';
     * }
     * catch (PrestaShopWebserviceException $ex)
     * {
     *    echo 'Error : '.$ex->getMessage();
     * }
     * ?>
     * </code>
     *
     * @param array $options Array representing resource to delete.
     *
     * @return bool
     * @throws PrestaShopWebserviceException
     */
    public function delete($options)
    {
        if (isset($options['url'])) {
            $url = $options['url'];
        } elseif (isset($options['resource']) && isset($options['id'])) {
            $url = (is_array($options['id']))
                ? $this->url . '/api/' . $options['resource'] . '/?id=[' . implode(',', $options['id']) . ']'
                : $this->url . '/api/' . $options['resource'] . '/' . $options['id'];
        } else {
            throw PrestaShopWebserviceBadParametersException::badParameters();
        }

        if (isset($options['id_shop'])) {
            $url .= '&id_shop=' . $options['id_shop'];
        }
        if (isset($options['id_group_shop'])) {
            $url .= '&id_group_shop=' . $options['id_group_shop'];
        }

        $request = $this->executeRequest($url, array(CURLOPT_CUSTOMREQUEST => 'DELETE'));
        $this->checkStatusCode($request);// check the response validity
        return true;
    }

}

// Polyfill for PHP 5
if (!interface_exists('Throwable')) {
    interface Throwable
    {
    }
}

/**
 * @package PrestaShopWebservice
 */
interface PrestaShopWebserviceException extends \Throwable
{
}

/**
 * @package PrestaShopWebservice
 */
class PrestaShopWebserviceBadParametersException extends \RuntimeException implements PrestaShopWebserviceException {
    public static function badParameters($response, $previous = null)
    {
        return new self('Bad parameters given', $previous);
    }
}

/**
 * @package PrestaShopWebservice
 */
class PrestaShopWebserviceMissingPreconditionException extends \BadFunctionCallException implements PrestaShopWebserviceException {
    public static function missingExtension($extension, $previous = null)
    {
        return new self(
            strtr(
                'Please activate the PHP extension \'%extension%\' to allow use of PrestaShop webservice library',
                array(
                    '%extension%' => $extension,
                )
            ),
            $previous
        );
    }
}

/**
 * @package PrestaShopWebservice
 */
class PrestaShopWebserviceBadRequestException extends \RuntimeException implements PrestaShopWebserviceException {
    public function __construct($previous = null)
    {
        parent::__construct('Bad Request', 400, $previous);
    }
}

/**
 * @package PrestaShopWebservice
 */
class PrestaShopWebserviceUnauthorizedException extends \RuntimeException implements PrestaShopWebserviceException {
    public function __construct($previous = null)
    {
        parent::__construct('Unauthorized', 401, $previous);
    }
}

/**
 * @package PrestaShopWebservice
 */
class PrestaShopWebserviceForbiddenException extends \RuntimeException implements PrestaShopWebserviceException {
    public function __construct($previous = null)
    {
        parent::__construct('Forbidden', 403, $previous);
    }
}

/**
 * @package PrestaShopWebservice
 */
class PrestaShopWebserviceNotFoundException extends \RuntimeException implements PrestaShopWebserviceException {
    public  function __construct($previous = null)
    {
        parent::__construct('Not Found', 404, $previous);
    }
}

/**
 * @package PrestaShopWebservice
 */
class PrestaShopWebserviceMethodNotAllowedException extends \RuntimeException implements PrestaShopWebserviceException {
    public function __construct($previous = null)
    {
        parent::__construct('Method Not Allowed', 405, $previous);
    }
}

/**
 * @package PrestaShopWebservice
 */
class PrestaShopWebserviceTooManyRequestsException extends \RuntimeException implements PrestaShopWebserviceException {
    public function __construct($previous = null)
    {
        parent::__construct('Too Many Requests', 429, $previous);
    }
}

/**
 * @package PrestaShopWebservice
 */
class PrestaShopWebserviceNoContentException extends \RuntimeException implements PrestaShopWebserviceException {
    public function __construct($previous = null)
    {
        parent::__construct('No Content', 204, $previous);
    }
}

/**
 * @package PrestaShopWebservice
 */
class PrestaShopWebserviceStatusException extends \RuntimeException implements PrestaShopWebserviceException {
    /**
     * @param int $code
     * @param string $status
     * @param ?\Throwable $previous
     * @return self
     */
    private static function shouldNotReceiveStatus($code, $status, $previous = null)
    {
        return new self(
            strtr(
                'This call to PrestaShop Web Services responded with status `%code% %status%`, this client was not designed to process this kind of response. This can come from your web server or reverse proxy configuration.',
                array(
                    '%code%' => $code,
                    '%status%' => $status,
                )
            ),
            $code,
            $previous
        );
    }

    /**
     * @param ?\Throwable $previous
     * @return self
     */
    public static function shouldNotReceive100ContinueStatus($previous = null)
    {
        return self::shouldNotReceiveStatus(100, 'Continue', $previous);
    }
    
    /**
     * @param ?\Throwable $previous
     * @return self
     */
    public static function shouldNotReceive101SwitchingProtocolsStatus($previous = null)
    {
        return self::shouldNotReceiveStatus(101, 'Switching Protocols', $previous);
    }
    
    /**
     * @param ?\Throwable $previous
     * @return self
     */
    public static function shouldNotReceive102ProcessingStatus($previous = null)
    {
        return self::shouldNotReceiveStatus(102, 'Processing', $previous);
    }

    /**
     * @param ?\Throwable $previous
     * @return self
     */
    public static function shouldNotReceive103EarlyHintsStatus($previous = null)
    {
        return self::shouldNotReceiveStatus(103, 'Early Hints', $previous);
    }

    /**
     * @param ?\Throwable $previous
     * @return self
     */
    public static function shouldNotReceive300MultipleChoicesStatus($previous = null)
    {
        return self::shouldNotReceiveStatus(300, 'Multiple Choices', $previous);
    }

    /**
     * @param ?\Throwable $previous
     * @return self
     */
    public static function shouldNotReceive301MovedPermanentlyStatus($previous = null)
    {
        return self::shouldNotReceiveStatus(301, 'Moved Permanently', $previous);
    }

    /**
     * @param ?\Throwable $previous
     * @return self
     */
    public static function shouldNotReceive302FoundStatus($previous = null)
    {
        return self::shouldNotReceiveStatus(302, 'Found', $previous);
    }

    /**
     * @param ?\Throwable $previous
     * @return self
     */
    public static function shouldNotReceive303SeeOtherStatus($previous = null)
    {
        return self::shouldNotReceiveStatus(303, 'See Other', $previous);
    }

    /**
     * @param ?\Throwable $previous
     * @return self
     */
    public static function shouldNotReceive304NotModifiedStatus($previous = null)
    {
        return self::shouldNotReceiveStatus(304, 'Not Modified', $previous);
    }

    /**
     * @param ?\Throwable $previous
     * @return self
     */
    public static function shouldNotReceive307TemporaryRedirectStatus($previous = null)
    {
        return self::shouldNotReceiveStatus(307, 'Temporary Redirect', $previous);
    }

    /**
     * @param ?\Throwable $previous
     * @return self
     */
    public static function shouldNotReceive308PermanentRedirectStatus($previous = null)
    {
        return self::shouldNotReceiveStatus(308, 'Permanent Redirect', $previous);
    }
}

/**
 * @package PrestaShopWebservice
 */
class PrestaShopWebserviceClientException extends \RuntimeException implements PrestaShopWebserviceException {}

/**
 * @package PrestaShopWebservice
 */
class PrestaShopWebserviceServerException extends \RuntimeException implements PrestaShopWebserviceException {}
