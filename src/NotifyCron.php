<?php

namespace Stanford\ProjCROP;

use REDCap;

/** @var \Stanford\ProjCROP\ProjCROP $module */


$module->emLog("------- Starting PROJ CROP NOTIFY Cron for  $project_id -------");

$module->checkNotification();