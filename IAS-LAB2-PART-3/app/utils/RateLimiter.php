<?php

class RateLimiter {
    private $cacheFile;

    public function __construct() {
        $this->cacheFile = __DIR__ . '/../../storage/rate_limits.json';
        if (!file_exists($this->cacheFile)) {
            file_put_contents($this->cacheFile, '{}');
        }
    }

    public function checkLimit($key, $maxAttempts, $timeWindow) {
        $limits = $this->getLimits();
        $now = time();

        if (!isset($limits[$key])) {
            $limits[$key] = [
                'attempts' => 0,
                'window_start' => $now
            ];
        }

        // Reset window if expired
        if ($now - $limits[$key]['window_start'] > $timeWindow) {
            $limits[$key] = [
                'attempts' => 0,
                'window_start' => $now
            ];
        }

        // Increment attempts
        $limits[$key]['attempts']++;
        $this->saveLimits($limits);

        return $limits[$key]['attempts'] <= $maxAttempts;
    }

    public function getRetryAfter($key) {
        $limits = $this->getLimits();
        if (!isset($limits[$key])) {
            return 0;
        }

        return max(0, 60 - (time() - $limits[$key]['window_start']));
    }

    private function getLimits() {
        return json_decode(file_get_contents($this->cacheFile), true) ?? [];
    }

    private function saveLimits($limits) {
        file_put_contents($this->cacheFile, json_encode($limits));
    }
} 