<?php

namespace Stanford\ProjCROP;

use REDCap;

/** @var \Stanford\ProjCROP\ProjCROP $module */


$module->emLog("------- Starting PROJ CROP RESET Cron for  $project_id -------");

//check records where today is the date of expiry
//Dont check until after grace period
//$module->checkExpiration();

//check records where today is the date of grace period end
$module->checkExpirationGracePeriod();