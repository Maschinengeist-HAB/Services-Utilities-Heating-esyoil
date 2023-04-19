#! /usr/bin/env php
<?php
# ------------------------------------------------------------------------------------------ global
namespace Maschinengeist\Services\Utilities\Heating\esyoil;

use Exception;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\ConfigurationInvalidException;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\Exceptions\DataTransferException;
use PhpMqtt\Client\Exceptions\InvalidMessageException;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\Exceptions\ProtocolViolationException;
use PhpMqtt\Client\Exceptions\RepositoryException;
use PhpMqtt\Client\MqttClient;

error_reporting(E_ALL);
date_default_timezone_set($_ENV['TZ'] ?? 'Europe/Berlin');
define('SERVICE_NAME', 'maschinengeist-services-www-esyoil');
# ------------------------------------------------------------------------------------------ resolve dependencies
require_once __DIR__ . '/vendor/autoload.php';

spl_autoload_register(
    /**
         * @param $class_name
         * @return void
     */
    function ($class_name) {
        $class_name = str_replace('\\', DIRECTORY_SEPARATOR, $class_name);
        require '/opt/Library/' . $class_name . '.php';
    }
);

/**
 * @global string $MQTT_HOST Defines the used MQTT HOST, defaults to 'message broker' if not set from ENV
 */
define('MQTT_HOST', (string) $_ENV['MQTT_HOST'] ?? 'message-broker');

/**
 * @global int $MQTT_PORT Defines the port used for the MQTT connection, defaults to 1883
 */
define('MQTT_PORT', $_ENV['MQTT_PORT'] ?? 1883);

/**
 * @global int $MQTT_KEEP_ALIVE Keep alive packet sends every nth second, defaults to 10
 */
define('MQTT_KEEP_ALIVE', $_ENV['MQTT_KEEP_ALIVE'] ?? 10);

/**
 * @global string $MQTT_BASE_TOPIC The base topic, will be prepended to any other topic used
 */
define('MQTT_BASE_TOPIC', $_ENV['MQTT_BASE_TOPIC'] ?? 'maschinengeist/services/www/esyoil');

/**
 * @global string $MQTT_WRITE_TOPIC The topic, written to, $MQTT_BASE_TOPIC/results is the default
 */
define('MQTT_WRITE_TOPIC', MQTT_BASE_TOPIC . '/results');

/**
 * @global string $MQTT_WRITE_TOPIC The topic, commands accepted are, $MQTT_BASE_TOPIC/command is the default
 */
define('MQTT_COMMAND_TOPIC', MQTT_BASE_TOPIC . '/command');

/**
 * @global string $MQTT_WRITE_TOPIC The topic, data results are published to, $MQTT_BASE_TOPIC/quote is the default
 */
define('MQTT_QUOTE_TOPIC', MQTT_BASE_TOPIC . '/quote');

/**
 * @global string $MQTT_LWT_TOPIC LWT topic
 */
define('MQTT_LWT_TOPIC', MQTT_BASE_TOPIC . '/lwt');

/**
 * @global int $MIN_QUOTE_LITERS esyoil, and thus this service, will not accept requests below the USE_LITERS_THRESHOLD limit
 */
define('MIN_QUOTE_LITERS', 500);

/**
 * @global int $DEFAULT_REQUESTED_LITERS If no liters are requested, use this
 */
define('DEFAULT_REQUESTED_LITERS',  1000);

/**
 * @global int $DEFAULT_REQUESTED_LITERS If no zip cade is transmitted while requested, this is used
 */
define('DEFAULT_ZIPCODE', '33330');

if ( function_exists('pcntl_async_signals') ) {
    pcntl_async_signals(true);
}

/**
 * @param string $error_msg
 * @param MqttClient $mqtt
 * @param string $topic
 * @return void
 */
function log_errors(string $error_msg, MqttClient $mqtt, string $topic = MQTT_WRITE_TOPIC): void {
    error_log($error_msg);
    try {
        $mqtt->publish($topic, $error_msg, 1);
    } catch (DataTransferException|RepositoryException $e) {
        error_log('Publishing the error message via MQTT was not possible: ' . $e->getMessage());
    }
}

/**
 * @param MqttClient $mqttClient
 * @param $message_data
 */
function handle_quote_request(MqttClient $mqttClient, $message_data): void {
    error_log(print_r($message_data, true));

    $requested_liters   = (int) $message_data['quote']['requested-liters']   ?? DEFAULT_REQUESTED_LITERS ?? 1000;
    $zip_code           = $message_data['quote']['zip']                      ?? DEFAULT_ZIPCODE ?? '33330';

    if ($requested_liters < MIN_QUOTE_LITERS) {
        log_errors("$requested_liters is below the threshold of " . MIN_QUOTE_LITERS, $mqttClient);
        return;
    }

    try {
        $quote = new Quote($zip_code, $requested_liters);
        $seller = $quote->get();
    } catch (Exception $exception) {
        log_errors($exception->getMessage(), $mqttClient);
        return;
    }

    $client = new Publish(
        new MqttClient(
            MQTT_HOST,
            MQTT_PORT,
            'SERVICE_NAME',
        ),
        MQTT_QUOTE_TOPIC
    );

    error_log(sprintf(
        'Requested %d liters, got back %s by %s€ (%s€/100L). Deliverable in %d days (%s).',
        $requested_liters,
        $seller->name, $seller->total, $seller->l100,
        $seller->duration, $seller->date
    ));

    $client->publish($seller);
}

$mqtt = new MqttClient(MQTT_HOST, MQTT_PORT, SERVICE_NAME);

$mqttConnectionSettings = (new ConnectionSettings)
    ->setKeepAliveInterval(MQTT_KEEP_ALIVE)
    ->setLastWillTopic(MQTT_LWT_TOPIC)
    ->setRetainLastWill(true)
    ->setLastWillMessage('offline');

pcntl_signal(
    /**
     * @return void
     */
    SIGINT, function () use ($mqtt) {
        $mqtt->interrupt();
    }
);


try {
    $mqtt->connect($mqttConnectionSettings);
} catch (ConfigurationInvalidException|ConnectingToBrokerFailedException $e) {
    error_log(sprintf("Can't connect to %s (%s): %s. Aborting.", MQTT_HOST, MQTT_PORT, $e->getMessage()));
    exit(1);
}

try {
    $mqtt->publish(MQTT_LWT_TOPIC, 'online');
} catch (DataTransferException|RepositoryException $e) {
    error_log('Publishing first lwt message was not possible: ' . $e->getMessage());
}

try {
    $mqtt->subscribe(
    /**
     * @param $topic
     * @param $message
     * @return void
     * @throws DataTransferException
     * @throws RepositoryException
     */ MQTT_COMMAND_TOPIC, function ($topic, $message) use ($mqtt) {

        if ($message) {

            try {
                $message_data = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                log_errors($e->getMessage(), $mqtt);
                return;
            }

            switch ($message_data['command']) {

                case 'get-quote':
                    handle_quote_request($mqtt, $message_data);
                    break;

                default:
                    log_errors('Empty message on read topic received.', $mqtt);
                    return;

            }

        }
    }, 0);
} catch (DataTransferException|RepositoryException $e) {
    log_errors($e->getMessage(), $mqtt);
}

try {
    $mqtt->loop();
} catch (DataTransferException|InvalidMessageException|ProtocolViolationException|MqttClientException $e) {
    error_log($e->getMessage());
    exit(2);
}

try {
    $mqtt->disconnect();
} catch (DataTransferException $e) {
    error_log(sprintf("Can't disconnect from MQTT: %s", $e->getMessage()));
    exit(3);
}
