<?php

/**
 * Implementation of Nexmo SMS API including sending sms message,
 *  receiving inbound message and handling delivery receipt
 */
class NexmoSMS
{
    /**
     * Nexmo API Key
     *
     * @var string
     */
    private $nexmoApiKey;

    /**
     * Nexmo API Secret
     *
     * @var string
     */
    private $nexmoApiSecret;

    /**
     * The optional parameters
     *
     * @var array
     */
    private $nexmoConfig = array();

    /**
     * The base url of Nexmo API endpoint
     *
     * @var string
     */
    public $baseUrl = 'https://rest.nexmo.com';


    /**
     * The default required configuration
     *
     * @var array
     */
    private static $defaultRequired = array(
        'api_key',
        'api_secret'
    );

    /**
     * Create an instance of NexmoSMS class
     *
     * @param null|string $nexmoApiKey
     * @param null|string $nexmoApiSecret
     */
    public function __construct(
        $nexmoApiKey = null,
        $nexmoApiSecret = null,
        array $nexmoOptions = array()
    )
    {
        $this->configureDefaults($nexmoOptions);
        $this->nexmoApiKey = $nexmoApiKey;
        $this->nexmoApiSecret = $nexmoApiSecret;
    }

    /**
     * Set Nexmo API key
     *
     * @param string $nexmoApiKey
     */
    public function setNexmoApiKey($nexmoApiKey)
    {
        $this->nexmoApiKey = $nexmoApiKey;
    }

    /**
     * Get Nexmo API key
     *
     * @return string
     */
    public function getNexmoApiKey()
    {
        return $this->nexmoApiKey;
    }

    /**
     * Set Nexmo API secret
     *
     * @param string $nexmoApiSecret
     */
    public function setNexmoApiSecret($nexmoApiSecret)
    {
        $this->setNexmoApiSecret($nexmoApiSecret);
    }

    /**
     * Get Nexmo API secret
     *
     * @return string
     */
    public function getNexmoApiSecret()
    {
        return $this->nexmoApiSecret;
    }

    /**
     * Configure Nexmo SMS settings
     *
     * If only one arg is set and it is an array, this array will be merged into the default settings.
     *
     * If two args are set, the first arg is the name of Nexmo settings to be modified and the second arg is the value
     *
     * @param string|array $name   string: the name of Nexmo config to be set,
     *                             array: an array of Nexmo settings to be set with names and values
     * @param mixed        $value If $name is a string, then the value of the Nexmo settings
     *                             will be identified by $name
     */
    public function config($name, $value = null)
    {
        if(is_array($name)) {
            if (true === $value) {
                $this->nexmoConfig = array_merge_recursive($this->nexmoConfig, $name);
            } else {
                $this->nexmoConfig = array_merge($this->nexmoConfig, $name);
            }
        } else {
            $settings = $this->nexmoConfig;
            $settings[$name] = $value;
            $this->nexmoConfig = $settings;
        }
    }

    /**
     * Configure the default options
     *
     * @param array $config
     */
    private function configureDefaults(array $config)
    {
        $nexmoDefaultSettings = array(
            "endpoint_type" => "json"
        );

        $this->nexmoConfig = $config + $nexmoDefaultSettings;
    }

    /**
     * Build Nexmo REST endpoint based on configuration of response type (json/xml)
     *
     * @return string
     */
    public function buildUrl()
    {
        return $this->baseUrl . '/sms/' . $this->nexmoConfig['endpoint_type'];
    }

    /**
     * @param string|mixed $value The string that needs to be UTF-8 encoded.
     *
     * @return string|mixed UTF-8 encoded string or the original object if it is not a string
     */
    public static function utf8($value)
    {
        if (is_string($value) && 'UTF-8' != mb_detect_encoding($value, 'UTF-8', true)) {
            return utf8_encode($value);
        } else {
            return $value;
        }
    }


    /**
     * Send SMS request by using curl (default)
     *
     * @param string    $method     The request method, get and post supported currently
     * @param string    $url        The url used to send request
     * @param array     $params     The parameters that needs to be
     *                              set for sending request
     * @param array     $options    The optional settings (timeout etc.)
     *
     * @return array
     * @throws Exception
     */
    public function request($method, $url, $params, array $options = array())
    {
        $defaultHeaders = array(
            "Content-Type" => 'application/x-www-form-urlencoded'
        );
        if (isset($options['headers'])) {
            $defaultHeaders = array_merge($defaultHeaders, $options['headers']);
        }
        $rawHeaders = array();
        foreach ($defaultHeaders as $headerName => $headerValue) {
            $rawHeaders[] = $headerName . ': ' . $headerValue;
        }

        return $this->curlRequest($method, $url, $params, $defaultHeaders);
    }


    /**
     * Send curl request
     *
     * @param string     $method    @see NexmoSMS::request()
     * @param string     $url       @see NexmoSMS::request()
     * @param array      $params    @see NexmoSMS::request()
     * @param array      $headers   The headers settings for sending curl request
     *
     * @return array
     * @throws Exception
     */
    private function curlRequest($method, $url, $params, $headers)
    {
        $method = strtolower($method);
        $curlOpts = array();

        $params = !empty($params) ? http_build_query($params) : '';
        if ('get' == $method) {
            $curlOpts[CURLOPT_HTTPGET] = 1;
            $url = "$url?$params";
        } elseif ('post' == $method) {
            $curlOpts[CURLOPT_POST] = 1;
            $curlOpts[CURLOPT_POSTFIELDS] = $params;
        } else {
            throw new Exception("Unknown request method $method");
        }

        $url = self::utf8($url);
        $curlOpts[CURLOPT_URL] = $url;
        $curlOpts[CURLOPT_RETURNTRANSFER] = true;
        $curlOpts[CURLOPT_SSL_VERIFYHOST] = false;
        $curlOpts[CURLOPT_HTTPHEADER] = $headers;
        $curlOpts[CURLOPT_TIMEOUT] = isset($options['timeout']) ? (int) $options['timeout'] : 60;
        $curlOpts[CURLOPT_SSL_VERIFYPEER] = isset($options['ssl_verify_peer']) ? (int) $options['ssl_verify_peer'] : 0;

        $curl = curl_init();
        curl_setopt_array($curl, $curlOpts);
        if (!$responseBody = curl_exec($curl)) {
            $errno = curl_errno($curl);
            $errorMessage = curl_error($curl);
            curl_close($curl);
            $this->handleCurlError($url, $errno, $errorMessage);
        }
        $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return array($responseBody, $responseCode);
    }


    /**
     * Handle curl errors
     *
     * @param string   $url            The url used to send curl request
     * @param int      $errno          The error number
     * @param string   $errorMessage   The error message
     *
     * @throws Exception
     */
    private function handleCurlError($url, $errno, $errorMessage)
    {
        switch ($errno) {
            case CURLE_COULDNT_CONNECT:
            case CURLE_COULDNT_RESOLVE_HOST:
            case CURLE_OPERATION_TIMEOUTED:
                $msg = "Could not connect to Microgaming server ($url)."
                    . "Please check internet connection and try again.";
                break;
            case CURLE_SSL_CACERT:
            case CURLE_SSL_PEER_CERTIFICATE:
                $msg = "Could not verify ssl certificate.";
                break;
            default:
                $msg = "Unexpected curl error happended while connecting Microgaming server";
                break;
        }

        $msg .= "\n\n\n (Error Tracker: [$errno]: $errorMessage)";

        throw new Exception($msg);
    }



    /*
     * *******************************************
     *      Sending Outbound Message
     * *******************************************
     * */


    /**
     * Send a SMS message
     * 
     * @param string    $from     The sender address that may be alphanumeric
     * @param string    $to       The recipient mobile number in *International format*
     * @param array     $message  The message array that contains other parameters
     * @param string    $type     The message type (text, unicode, wappush and binary)
     *
     * @return mixed
     */
    public function sendSMS($from, $to, array $message, $type = 'text')
    {
        $type = strtolower($type);
        $params = array_merge($message, array("from" => $from, "to" => $to, "type" => $type));
        switch ($type) {
            case 'text':
            case 'unicode':
                $response = $this->sendText($params);
                break;
            case 'binary':
                $response = $this->sendBinary($params);
                break;
            case 'wappush':
                $response = $this->sendWapPush($params);
                break;
            default:
                throw new InvalidArgumentException("Unknown type ($type) for sending SMS Message");
                break;
        }

        return $this->validateResponse($response);
    }

    /**
     * Send a text message
     *
     * @param array $params The text message parameters
     *
     * @return mixed
     */
    private function sendText(array $params)
    {
        $required = array(
            'from',
            'to',
            'text',
            'type',
        );
        $filteredParams = $this->filterParams($params, $required);

        if (!is_numeric($filteredParams['from'])
            && !mb_check_encoding($filteredParams['from'], 'UTF-8')
        ){
            throw new InvalidArgumentException('from parameter must be a valid UTF-8 encoded string');
        }
        if (!mb_check_encoding($filteredParams['text'], 'UTF-8')) {
            throw new InvalidArgumentException('SMS message must be a valid UTF-8 encoded string');
        }

        if ('text' == $filteredParams['type'] && !$this->isGSM0338($filteredParams['text'])) {
            $filteredParams['type'] = 'unicode';
        }

        $filteredParams['from'] = urlencode($filteredParams['from']);
        $filteredParams['text'] = urlencode($this->utf8($filteredParams['text']));

        $textUrl = $this->buildUrl();
        list($responseBody, $responseCode)  = $this->request('post', $textUrl, $filteredParams);
        return $responseBody;
    }

    /**
     * Send a binary data message
     *
     * @param array $params The binary message parameters
     *
     * @return mixed
     */
    private function sendBinary(array $params)
    {
        $required = array(
            'from',
            'to',
            'type',
            'body',
            'udh'
        );
        $filteredParams = $this->filterParams($params, $required);
        $filteredParams['body'] = bin2hex($filteredParams['body']);
        $filteredParams['udh'] = bin2hex($filteredParams['udh']);
        $binaryUrl = $this->buildUrl();
        list($responseBody, $responseCode) = $this->request('post', $binaryUrl, $filteredParams);
        return $responseBody;
    }

    /**
     * Send a WAP Push message
     *
     * @param array $params The WAP Push message parameters
     *
     * @return mixed
     */
    private function sendWapPush(array $params)
    {
        $required = array(
            'from',
            'to',
            'type',
            'title',
            'url'
        );
        $filteredParams = $this->filterParams($params, $required);

        if ( !mb_check_encoding($filteredParams['title'], 'UTF-8') ||
            !mb_check_encoding($filteredParams['url'], 'UTF-8')
        ){
            throw new InvalidArgumentException('title and url parameters must be valid UTF-8 encoded strings');
        }

        $filteredParams['title'] = self::utf8($filteredParams['title']);
        $filteredParams['url'] = urlencode(self::utf8($filteredParams['url']));
        $wappushUrl = $this->buildUrl();
        list($responseBody, $responseCode) = $this->request('post', $wappushUrl, $filteredParams);
        return $responseBody;
    }

    /**
     * Filter parameters that will be sent to Nexmo API
     *
     * @param array $params     The parameters sent to Nexmo
     * @param array $required   The required parameter array for checking purpose
     *
     * @return array  The filtered parameters(remove unused blank parameters)
     */
    private function filterParams(array $params, array $required = array())
    {
        $required = array_merge(self::$defaultRequired, $required);
        $params = array_merge($params,
            array(
                "api_key" => $this->nexmoApiKey,
                "api_secret" => $this->nexmoApiSecret)
        );
        $filteredParams = array_filter($params);
        if ($missingParam = array_diff($required, array_keys($filteredParams))) {
            throw new InvalidArgumentException(
                'Parameters with the following keys are missing: ' .
                implode(', ', $missingParam)
            );
        }

        return $filteredParams;
    }

    private function validateResponse(array $response)
    {
        switch ($this->nexmoConfig['endpoint_type']) {
            case 'xml':
                throw new BadMethodCallException("xml request not implemented yet");
                break;
            case 'json':
            default:
                $newResponse = $this->json($response);
                break;
        }
        return $newResponse;
    }

    public function json($response)
    {
        return json_decode($response, true);
    }


    /**
     * Check if the message contains words that don't belongs to GSM0338
     *
     * Thanks: http://stackoverflow.com/questions/27599/reliable-sms-unicode-gsm-encoding-in-php
     *
     * @param string $message The message that needs to be detected
     *
     * @return bool false: is unicode, true: otherwise
     */
    public function isGSM0338($message)
    {
        $gsm0338 = array(
            '@','Δ',' ','0','¡','P','¿','p',
            '£','_','!','1','A','Q','a','q',
            '$','Φ','"','2','B','R','b','r',
            '¥','Γ','#','3','C','S','c','s',
            'è','Λ','¤','4','D','T','d','t',
            'é','Ω','%','5','E','U','e','u',
            'ù','Π','&','6','F','V','f','v',
            'ì','Ψ','\'','7','G','W','g','w',
            'ò','Σ','(','8','H','X','h','x',
            'Ç','Θ',')','9','I','Y','i','y',
            "\n",'Ξ','*',':','J','Z','j','z',
            'Ø',"\x1B",'+',';','K','Ä','k','ä',
            'ø','Æ',',','<','L','Ö','l','ö',
            "\r",'æ','-','=','M','Ñ','m','ñ',
            'Å','ß','.','>','N','Ü','n','ü',
            'å','É','/','?','O','§','o','à'
        );
        $len = mb_strlen( $message, 'UTF-8');

        for( $i=0; $i < $len; $i++) {
            if (!in_array(mb_substr($message, $i, 1, 'UTF-8'), $gsm0338)) {
                return false;
            }
        }

        return true;
    }



    /*
     * ******************************************************
     *      Receiving Delivery Receipt
     * ******************************************************
     * */


    /*
     * ******************************************************
     *      Receiving Inbound Message
     * ******************************************************
     * */

}