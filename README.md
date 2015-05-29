# nexmo-lib
## Overview
This is a PHP library that makes it easy to use Nexmo API for sending SMS Message, receiving delivery receipt etc.Currently, Only SMS features have been implemented.

Please go to Nexmo [API Documentation](https://docs.nexmo.com/) to find more API information

## Requirements
- PHP 5.3 or later
- Composer(optional)
    
## Composer
If you choose to use Composer autoload

###
    include 'vendor/autoload.php';

## Manually 

If you don't want to use Composer, then manually includes `init.php` file
###
    include 'init.php';
    
## Usage

Simply use it as following:
###
    use Nexmo\NexmoSMS;
    $nexmoSMS = new NexmoSMS('<nexmo-api-key>', '<nexmo-secret-key>');
    try{
        $response = $nexmoSMS->sendSMS('<from>', '<destination number>', array('text' => <text message>));
        // then dealing with response, json format by default.
    }catch (Exception $e) {
        var_dump($e->getMessage());
    }

## TODO
* Add more features of Nexmo API
* Add more documentation.

