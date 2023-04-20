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
use PhpMqtt\Client\Exceptions\ProtocolNotSupportedException;
use PhpMqtt\Client\Exceptions\ProtocolViolationException;
use PhpMqtt\Client\Exceptions\RepositoryException;
use PhpMqtt\Client\MqttClient;

error_reporting(E_ALL);
date_default_timezone_set($_ENV['TZ'] ?? 'Europe/Berlin');
define('SERVICE_NAME', 'maschinengeist-services-utilities-heating-esyoil');
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

# ------------------------------------------------------------------------------------------ configuration
require_once 'Config.php';

# ------------------------------------------------------------------------------------------ helper
/**
 * @param string $error_msg
 * @param MqttClient $mqtt
 * @param string $topic
 * @return void
 */
function log_errors(string $error_msg, MqttClient $mqtt, string $topic = 'error/' . SERVICE_NAME): void {
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
 * @throws ProtocolNotSupportedException
 */
function handle_quote_request(MqttClient $mqttClient, $message_data): void {
    error_log(print_r($message_data, true));

    $requested_liters = (int) $message_data['quote']['requested-liters']
        ?? Config::getDefaultRequestLiters()
        ?? 1000;

    $zip_code = $message_data['quote']['zip']
        ?? Config::getDefaultZipCode()
        ?? '33330';

    if ($requested_liters < Config::getDefaultMinQuoteLiters()) {
        log_errors(
            "$requested_liters is below the threshold of "
            . Config::getDefaultMinQuoteLiters(),
            $mqttClient,
            Config::getMqttErrorTopic()
        );
        return;
    }

    try {
        $quote = new Quote($zip_code, $requested_liters);
        $seller = $quote->get();
    } catch (Exception $exception) {
        log_errors($exception->getMessage(), $mqttClient, Config::getMqttErrorTopic());
        return;
    }

    $client = new Publish(
        new MqttClient(
            Config::getMqttHost(),
            Config::getMqttPort(),
            'SERVICE_NAME',
        ),
        Config::getMqttResultTopic()
    );

    error_log(sprintf(
        'Requested %d liters, got back %s by %s€ (%s€/100L). Deliverable in %d days (%s).',
        $requested_liters,
        $seller->name, $seller->total, $seller->l100,
        $seller->duration, $seller->date
    ));

    $client->publish($seller);
}

# ------------------------------------------------------------------------------------------ main

if ( function_exists('pcntl_async_signals') ) {
    pcntl_async_signals(true);
}

try {
    $mqtt = new MqttClient(Config::getMqttHost(), Config::getMqttPort(), SERVICE_NAME);
} catch (Exception $e) {
    error_log($e->getMessage());
    # @Todo: Aboort, if not successful
}

$mqttConnectionSettings = (new ConnectionSettings)
    ->setKeepAliveInterval(Config::getMqttKeepAlive())
    ->setLastWillTopic(Config::getMqttLwtTopic())
    ->setRetainLastWill(true)
    ->setLastWillMessage('offline');

if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, function () use ($mqtt) {
        $mqtt->interrupt();
    });
}

try {
    $mqtt->connect($mqttConnectionSettings);
} catch (ConfigurationInvalidException|ConnectingToBrokerFailedException $e) {
    error_log(
        sprintf(
        "Can't connect to %s (%s): %s. Aborting.", Config::getMqttHost(), Config::getMqttPort(), $e->getMessage()
        )
    );
    exit(107);
}

try {
    $mqtt->publish(Config::getMqttLwtTopic(), 'online');
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
     */ Config::getMqttCommandTopic(), function ($topic, $message) use ($mqtt) {

        if ($message) {

            try {
                $message_data = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                log_errors($e->getMessage(), $mqtt, Config::getMqttErrorTopic());
                return;
            }

            switch ($message_data['command']) {

                case 'get-quote':
                    handle_quote_request($mqtt, $message_data);
                    break;

                default:
                    log_errors('Empty message on read topic received.', $mqtt, Config::getMqttErrorTopic());
                    return;
            }

        }
    }, 0);
} catch (DataTransferException|RepositoryException $e) {
    log_errors($e->getMessage(), $mqtt, Config::getMqttErrorTopic());
}

try {
    $mqtt->loop();
} catch (DataTransferException|InvalidMessageException|ProtocolViolationException|MqttClientException $e) {
    error_log($e->getMessage());
    exit(121);
}

try {
    $mqtt->disconnect();
} catch (DataTransferException $e) {
    error_log(sprintf("Can't disconnect from MQTT: %s", $e->getMessage()));
    exit(121);
}
