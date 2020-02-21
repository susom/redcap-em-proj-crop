<?php

namespace Stanford\ProjCROP;

use REDCap;

/** @var \Stanford\ProjCROP\ProjCROP $module */

$url = $module->getUrl('src/landing.php', true, false);
echo "<br><br>This is the landing Link: <br>".$url;

