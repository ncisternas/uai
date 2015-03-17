<?php

defined('MOODLE_INTERNAL') || die();

$tasks = array(
    array(
        'classname' => 'local_uai\task\notify_quiz_participation',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '17',
        'day' => '*',
        'dayofweek' => '0',
        'month' => '*'
    )
);