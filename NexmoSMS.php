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
    private $nexmoDefaults = array();

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
                $this->nexmoDefaults = array_merge_recursive($this->nexmoDefaults, $name);
            } else {
                $this->nexmoDefaults = array_merge($this->nexmoDefaults, $name);
            }
        } else {
            $settings = $this->nexmoDefaults;
            $settings[$name] = $value;
            $this->nexmoDefaults = $settings;
        }
    }

    /**
     * Get a default array of nexmo options
     *
     * @return array
     */
    protected function getDefaults()
    {
        $nexmoDefaultSettings = array(
            "endpoint_type" => "json"
        );

        return $nexmoDefaultSettings;
    }

    /**
     * Configure the default options
     *
     * @param array $config
     */
    private function configureDefaults($config)
    {
        if (!isset($config['defaults'])) {
            $this->nexmoDefaults = $this->getDefaults();
        } else {
            $this->nexmoDefaults = array_merge(
                $this->getDefaults(),
                $config['defaults']
            );
        }
    }

    /**
     * Build Nexmo REST endpoint based on configuration of response type (json/xml)
     *
     * @return string
     */
    protected function buildUrl()
    {
        return $this->baseUrl . '/sms/' . $this->nexmoDefaults['endpoint_type'];
    }

    /**
     * @param string|mixed $value The string that needs to be UTF-8 encoded.
     *
     * @return string|mixed UTF-8 encoded string or the original object if it is not a string
     */
    public function utf8($value)
    {
        if (is_string($value) && "UTF-8" != mb_detect_encoding($value, "UTF-8", true)) {
            return utf8_encode($value);
        } else {
            return $value;
        }
    }



    /*
     * *******************************************
     *      Sending Outbound Message
     * *******************************************
     * */



    public function sendSMS($from, $to, $message, $type = 'text')
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

    private function sendText($params)
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

        return $this->request($filteredParams);
    }

    private function sendBinary($params)
    {
        return $this->request($params);
    }

    private function sendWapPush($params)
    {
        return $this->request($params);
    }

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

    protected function validateResponse(array $response)
    {
        return '';
    }

    private function request($params)
    {
        return $params;
    }

    public function json($response)
    {

    }

    public function xml($response)
    {
        throw new BadMethodCallException("xml request not implemented yet");
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