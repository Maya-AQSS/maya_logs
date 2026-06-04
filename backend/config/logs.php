<?php

return [
    /*
     * Error Spike Detection Configuration
     */
    'error_spike_window_seconds' => (int) env('LOGS_ERROR_SPIKE_WINDOW_SECONDS', 60),
    'error_spike_threshold' => (int) env('LOGS_ERROR_SPIKE_THRESHOLD', 10),
];
