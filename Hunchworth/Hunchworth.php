<?php

namespace App\SupportedApps\Hunchworth;

class Hunchworth extends \App\SupportedApps implements \App\EnhancedApps
{
    public $config;
    private const MAX_STATS = 3;

    public function __construct()
    {
        $this->jar = new \GuzzleHttp\Cookie\CookieJar();
    }

    public function test()
    {
        try {
            $this->statistics();
            if (
                !empty($this->config->email) ||
                !empty($this->config->password)
            ) {
                $this->login();
                try {
                    $this->countValue($this->request("api/auth/me"), "credits");
                } finally {
                    $this->request("api/auth/logout", [], "POST");
                }
            }
            echo "Successfully communicated with the API";
        } catch (\Throwable $e) {
            echo $e->getMessage();
        }
    }

    public function livestats()
    {
        $data = ["visiblestats" => []];
        try {
            $values = $this->statistics();
            foreach ($values as $key => $value) {
                $data["visiblestats"][] = (object) [
                    "title" => self::getAvailableStats()[$key],
                    "value" => number_format($value),
                ];
            }
            return parent::getLiveStats("active", $data);
        } catch (\Throwable $e) {
            return parent::getLiveStats("inactive", $data);
        }
    }

    private function statistics()
    {
        $selected = $this->config->availablestats ?? [];
        if (!is_array($selected) || !$selected) {
            $selected = ["generated", "pending"];
        }
        $selected = array_slice(
            array_values(
                array_intersect(
                    $selected,
                    array_keys(self::getAvailableStats())
                )
            ),
            0,
            self::MAX_STATS
        );
        if (!$selected) {
            throw new \RuntimeException(
                "Failed: Select at least one statistic"
            );
        }
        $authenticated = false;
        try {
            if (array_intersect($selected, ["reports", "credits"])) {
                $this->login();
                $authenticated = true;
            }
            $values = [];
            if (array_intersect($selected, ["seeds", "generated", "pending"])) {
                $seeds = $this->request("api/ideas/seeds");
                $total = $this->countValue($seeds, "total");
                $generated = $this->countValue($seeds, "generated");
                if ($generated > $total) {
                    throw new \RuntimeException("Failed: Invalid seed counts");
                }
                $values = [
                    "seeds" => $total,
                    "generated" => $generated,
                    "pending" => $total - $generated,
                ];
            }
            if (in_array("reports", $selected, true)) {
                $values["reports"] = $this->countValue(
                    $this->request("api/ideas/count"),
                    "count"
                );
            }
            if (in_array("credits", $selected, true)) {
                $values["credits"] = $this->countValue(
                    $this->request("api/auth/me"),
                    "credits"
                );
            }
            $result = [];
            foreach ($selected as $key) {
                $result[$key] = $values[$key];
            }
            return $result;
        } finally {
            if ($authenticated) {
                try {
                    $this->request("api/auth/logout", [], "POST");
                } catch (\Throwable $e) {
                    // Preserve the statistics result if session cleanup fails.
                }
            }
        }
    }

    public function login()
    {
        if (empty($this->config->email) || empty($this->config->password)) {
            throw new \RuntimeException(
                "Failed: Reports and Credits require a Hunchworth email and password"
            );
        }
        $result = $this->request(
            "api/auth/login",
            [
                "email" => $this->config->email,
                "password" => $this->config->password,
            ],
            "POST"
        );
        if (($result->ok ?? false) !== true) {
            throw new \RuntimeException(
                "Failed: Invalid Hunchworth credentials"
            );
        }
    }

    private function countValue($result, $key)
    {
        if (
            !isset($result->$key) ||
            !is_int($result->$key) ||
            $result->$key < 0
        ) {
            throw new \RuntimeException(
                "Failed: Invalid Hunchworth statistics"
            );
        }
        return $result->$key;
    }

    private function request($endpoint, $body = null, $method = "GET")
    {
        $attrs = [
            "cookies" => $this->jar,
            "headers" => ["Accept" => "application/json"],
            "timeout" => 5,
            "connect_timeout" => 3,
            "allow_redirects" => false,
        ];
        if ($body !== null) {
            $attrs["json"] = (object) $body;
        }
        try {
            $res = parent::execute(
                $this->url($endpoint),
                $attrs,
                null,
                $method
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed: Could not connect to Hunchworth"
            );
        }
        if ($res === null || $res->getStatusCode() !== 200) {
            throw new \RuntimeException(
                "Failed: Could not communicate with Hunchworth"
            );
        }
        $result = json_decode((string) $res->getBody());
        if (!is_object($result)) {
            throw new \RuntimeException("Failed: Invalid Hunchworth response");
        }
        if (isset($result->error) || !empty($result->auth_required)) {
            throw new \RuntimeException(
                "Failed: Hunchworth rejected the request; check your credentials and URL"
            );
        }
        return $result;
    }

    public function url($endpoint)
    {
        return parent::normaliseurl($this->config->url) . $endpoint;
    }

    public static function getAvailableStats()
    {
        return [
            "seeds" => "Seeds",
            "generated" => "Done",
            "pending" => "Pending",
            "reports" => "Reports",
            "credits" => "Credits",
        ];
    }
}
