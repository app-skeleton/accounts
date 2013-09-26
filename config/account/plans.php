<?php defined('SYSPATH') OR die('No direct access allowed.');

return array(
    // Plans
    'trial' => array(
        'limits' => array(
            'projects' => 40
        ),

        // Time limit in days
        'time_limit' => 90 * 24 * 3600
    ),

    'starter' => array(
        'limits' => array(
            'projects' => 40
        )
    ),

    'pro' => array(
        'limits' => array(
            'projects' => 100
        )
    ),

    'unlimited' => array(
        'limits' => array(
            'projects' => INF
        )
    )
);
