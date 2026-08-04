<?php

namespace App\SupportedApps\BootLeg;

class BootLeg extends \App\SupportedApps implements \App\EnhancedApps
{
    public $config;

    // Only 3 stat tiles fit the livestats layout cleanly, so selection is
    // capped both here and in config.blade.php's helper text.
    private const MAX_STATS = 3;

    public function __construct()
    {
        $this->jar = new \GuzzleHttp\Cookie\CookieJar();
    }

    // Bootleg's API is entirely session-cookie based (no API keys) — every
    // /api/* route except /api/auth/* requires the cookie this sets on the
    // shared jar, so every other request in this class goes through it.
    // Public because the parent class declares login() public — PHP forbids
    // narrowing visibility on override.
    public function login()
    {
        $attrs = [
            "body" => json_encode([
                "email" => $this->config->email,
                "password" => $this->config->password,
            ]),
            "cookies" => $this->jar,
            "headers" => [
                "Content-Type" => "application/json",
                "Accept" => "application/json",
            ],
        ];
        return parent::execute($this->url("api/auth/login"), $attrs, null, "POST");
    }

    public function test()
    {
        $res = $this->login();
        if ($res === null) {
            echo $this->error;
            return;
        }
        if ($res->getStatusCode() === 401) {
            echo "Failed: Invalid credentials";
            return;
        }
        if ($res->getStatusCode() >= 300) {
            echo "Something went wrong... Code: " . $res->getStatusCode();
            return;
        }
        echo "Successfully communicated with the API";
    }

    public function livestats()
    {
        $status = "inactive";
        $data = ["visiblestats" => []];

        $login = $this->login();
        if ($login === null || $login->getStatusCode() >= 300) {
            return parent::getLiveStats($status, $data);
        }

        $stats = array_slice($this->config->availablestats ?? ["total", "playing"], 0, self::MAX_STATS);
        $attrs = [
            "cookies" => $this->jar,
            "headers" => ["Accept" => "application/json"],
        ];

        foreach ($stats as $stat) {
            $query = self::getStatQuery($stat);
            if ($query === null) {
                continue;
            }

            $res = parent::execute($this->url("api/collection?limit=1" . $query), $attrs);
            $details = $res ? json_decode($res->getBody()) : null;
            if (!isset($details->total)) {
                continue;
            }

            $status = "active";

            $newstat = new \stdClass();
            $newstat->title = self::getAvailableStats()[$stat];
            $newstat->value = number_format($details->total);
            $data["visiblestats"][] = $newstat;
        }

        return parent::getLiveStats($status, $data);
    }

    public function url($endpoint)
    {
        $api_url = parent::normaliseurl($this->config->url) . $endpoint;
        return $api_url;
    }

    // Keys and labels match Bootleg's own status vocabulary (see
    // STATUS_OPTIONS in its frontend) rather than inventing new terms here.
    public static function getAvailableStats()
    {
        return [
            "total" => "Total",
            "want" => "Want",
            "playing" => "Playing",
            "beaten" => "Beaten",
            "dropped" => "Dropped",
            "owned" => "Owned",
        ];
    }

    // Query string appended to /api/collection for each stat — `total` in
    // the response is always the full count matching these filters
    // (computed by a dedicated COUNT query server-side), so `limit=1` above
    // keeps the response body cheap without affecting the count itself.
    private static function getStatQuery(string $stat)
    {
        switch ($stat) {
            case "total":
                return "";
            case "want":
                return "&status=want";
            case "playing":
                return "&status=playing";
            case "beaten":
                return "&status=beaten";
            case "dropped":
                return "&status=dropped";
            case "owned":
                return "&owned=true";
            default:
                return null;
        }
    }
}
