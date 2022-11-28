<?php

namespace cmi5;

require_once($CFG->libdir . '/filelib.php');

    $curl = new \curl();

    $curl->setopt(array('CURLOPT_FOLLOWLOCATION' => 1, 'CURLOPT_MAXREDIRS' => 5));

    $calendar = $curl->get($url);