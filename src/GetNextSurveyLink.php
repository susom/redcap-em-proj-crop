<?php

namespace Stanford\ProjCROP;
/** @var \Stanford\ProjCROP\ProjCROP $module */

require_once("RepeatingForms.php");

use REDCap;
use DateTime;

/**
 * There is a followup survey (Annual Survey) that  is to be sent out annually using the dates already being calculated
 * for the reminders and recertifications.

The Annual Surveys are stored in the first event. The form is REPEATING.

Two alerts are used to send two surveys every event
1 year mark
Date of expiry - 2 year mark


 * In order to avoid using two different forms for every event, I chose to make this form repeating and then use an EM
 * method to create a new instance of the form in the baseline event.


 * There are two Alert and Notifications used. The survey link embedded in the alert will actually be a call to the EM
 * with the relevant parameters embedded in the url.  The creation of the new instance of the Annual Surveys is handled by the EM method GetNextSurveyLink.  This method will generate the next instance of the AnnualSurvey and return the survey link for the next instance. This link will be embedded in the email body.  (See localhost 315 for test scenarios).
 * Alert #6: FUPAnnualSurvey
 *    TRIGGERING LOGIC: datediff([followup_survey_1_yr], 'today', 'd') = 0
 *    SURVEYLINK: [redcap-version-url]/ExternalModules/?prefix=proj_crop&page=src%2FGetNextSurveyLink&pid=[project-id]&recid=[record-name]&instance=[current-instance]&event=[event-name]&num=1


 * Alert #15: FUPAnnualSurvey2
 *    TRIGGERING LOGIC: datediff([date_expiry], 'today', 'd') = 0
 *    SURVEY LINK: [redcap-version-url]/ExternalModules/?prefix=proj_crop&page=src%2FGetNextSurveyLink&pid=[project-id]&recid=[record-name]&instance=[current-instance]&event=[event-name]&num=2

 */

$record = isset($_REQUEST['recid']) ? $_REQUEST['recid'] : "";
$instance = isset($_REQUEST['instance']) ? $_REQUEST['instance'] : "";
$event_name = isset($_REQUEST['event']) ? $_REQUEST['event'] : "";
$num = isset($_REQUEST['num']) ? $_REQUEST['num'] : "";

$module->emDebug("Starting CROP GetNextSurveyLink for project $pid and record $record and instance $instance");

if (empty($record)) {
    $module->emDebug("Record id not passed through EM link.");
    die("Unable to locate your followup survey. Please notify your admin");
}

$url = $module->getAnnualUrl($record, $event_name, $instance, $num);

//redirect to the annual survey
header("Location: " . $url);
exit;
