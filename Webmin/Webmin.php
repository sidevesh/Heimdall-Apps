<?php

namespace App\SupportedApps\Webmin;

use Illuminate\Support\Facades\Log;

class Webmin extends \App\SupportedApps implements \App\EnhancedApps // phpcs:ignore
{
    public $config;
    private $attrs;
    private $vars;

    public function __construct()
    {
    }

    public function test()
    {
        $response_data = $this->getXMLRPCData("show_webmin_notifications");
        if (
            !isset($response_data) ||
            $data == "Err" ||
            $response_data == null ||
            !is_object($response_data)
        ) {
            echo 'There is an issue connecting to "' .
                $this->url("xmlrpc.cgi") .
                '". Please check your Webmin URL and credentials.' . "\n" . $this->outputResponse($response_data);
        } else {
            echo "Connection successful!";
        }
    }

    public function livestats()
    {
        $status = "inactive";
        $data = [];

        // Retrieve Webmin notification count (including updates)
        $response_data = $this->getXMLRPCData('get_webmin_notifications');
        if (
          !isset($response_data) ||
          $response_data == "Err" ||
          $response_data == null ||
          !is_object($data)
        ) {
            $status = "active";
            $data["notification_count"] = $this->formatNotificationCount($response_data);
        }

        return parent::getLiveStats($status, $response_data);
    }

    public function url($endpoint)
    {
        return parent::normaliseurl($this->config->url) . $endpoint;
    }

    public function getXMLRPCData($method, $params = [])
    {
        $value = "";
        $body = "<methodCall><methodName>" . $method . "</methodName>";

        if (!empty($params)) {
            $body .= "<params>";
            foreach ($params as $param) {
                $body .= "<param><value>" . $this->encodeValue($param) . "</value></param>";
            }
            $body .= "</params>";
        }

        $body .= "</methodCall>";

        $this->vars = ["http_errors" => false, "timeout" => 5, "body" => $body];
        $this->attrs = [];
        $this->attrs["headers"] = ["Content-Type" => "text/xml"];

        // Set the authentication credentials in the URL
        $url = "https://" . $this->config->username . ":" . $this->config->password . "@" . $this->url("xmlrpc.cgi");

        try {
            $res = parent::execute($url, $this->attrs, $this->vars);
            // Log the response status code
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $value = "Failed to connect to Webmin: " . $e->getMessage();
        }

        if ($res !== null && $res->getStatusCode() === 200) {
            if (function_exists("simplexml_load_string")) {
                try {
                    $value = simplexml_load_string($res->getBody()->getContents());
                } catch (\ErrorException $e) {
                    $value = "Unexpected response. Are credentials correct?";
                }
            } else {
                $value = 'simplexml_load_string doesn\'t exist.';
            }
        } else {
            $value = "Failed to retrieve notifications: " . ($res !== null ? $res->getReasonPhrase() : $res) . "\nResponse: " . $this->outputResponse($res);
        }

        return $value;
    }

    private function encodeValue($value)
    {
        $encoded = '';
        if (is_string($value)) {
            $encoded = '<string>' . htmlspecialchars($value) . '</string>';
        } elseif (is_bool($value)) {
            $encoded = '<boolean>' . ($value ? '1' : '0') . '</boolean>';
        } elseif (is_numeric($value)) {
            $encoded = '<double>' . $value . '</double>';
        } elseif (is_null($value)) {
            $encoded = '<nil/>';
        } else {
            $encoded = '<string>' . htmlspecialchars(print_r($value, true)) . '</string>';
        }
        return $encoded;
    }

    private function formatNotificationCount($notifications)
    {
        // Count the number of notifications (including updates)
        $notificationCount = 0;
        if (isset($notifications->params->param->value->i8)) {
            $notificationCount = $notifications->params->param->value->i8;
        }

        return $notificationCount;
    }
}
