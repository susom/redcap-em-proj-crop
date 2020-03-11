<?php

namespace Stanford\ProjCROP;

use REDCap;

/** @var \Stanford\ProjCROP\ProjCROP $module */


$module->emLog("------- Starting PROJ CROP RESET Cron for  $project_id -------");

$module->checkExpiration();