<?php

return [
    /*
     * How many days into the future to materialize planned transactions for active recurring plans.
     */
    'horizon_days' => (int) env('RECURRING_HORIZON_DAYS', 90),
];
