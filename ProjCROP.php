<?php

namespace Stanford\ProjCROP;

use DateInterval;
use DateTime;
use REDCap;
use ExternalModules\ExternalModules;
use Alerts;

require_once 'emLoggerTrait.php';
require_once 'src/RepeatingForms.php';

class ProjCROP extends \ExternalModules\AbstractExternalModule {

    use emLoggerTrait;

    var $portal_fields = array(
        "st_date_hipaa",
        "st_date_citi",
        "st_date_clin_trials",
        "st_date_ethics",
        "st_date_irb_report",
        "st_date_doc",
        "st_date_resources",
        "st_date_consent",
        "st_date_irb",
        array("st_budgeting","st_date_budgeting"),
        "st_date_billing",
        "st_date_regulatory",
        "st_date_startup",
        "st_date_roles",
        "st_date_rsrch_phase",
        array("st_oncore","st_date_oncore"),
        array("elective_1","elective_1_date"),
       array("elective_2", "elective_2_date"),
        array("elective_3","elective_3_date"),
       array("elective_4", "elective_4_date")
    );

    var $recert_fields = array(
        "rf_date_ethics",
        "rf_date_hipaa",
        array("rf_core_1_date", "rf_core_1_sponsor", "rf_core_1_title"),
        array("rf_core_2_date", "rf_core_2_sponsor", "rf_core_2_title"),
        array("rf_core_3_date", "rf_core_3_sponsor", "rf_core_3_title"),
        array("rf_class_1_date", "rf_class_1_sponsor", "rf_class_1_title"),
        array("rf_class_2_date", "rf_class_2_sponsor", "rf_class_2_title"),
        array("rf_class_3_date", "rf_class_3_sponsor", "rf_class_3_title"),
        array("rf_class_4_date", "rf_class_4_sponsor", "rf_class_4_title"),
        array("rf_class_5_date", "rf_class_5_sponsor", "rf_class_5_title"),
        array("rf_class_6_date", "rf_class_6_sponsor", "rf_class_6_title"),
        array("rf_class_7_date", "rf_class_7_sponsor", "rf_class_7_title")
    );

    var $alerts;

    /*******************************************************************************************************************/
    /* HOOK METHODS                                                                                                    */
    /***************************************************************************************************************** */

    public function redcap_save_record($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash = NULL, $response_id = NULL, $repeat_instance) {
        //
        $this->emDebug("Just saved $instrument in instance $repeat_instance");

        //on save of admin_review form, trigger email to admin to verify the training
        if ($instrument == $this->getProjectSetting('admin-review-form')) {
            $this->checkExamAndReset($record, $event_id, $repeat_instance);
            $this->sendLearnerStatusEmail($record, $event_id, $repeat_instance);
        }
    }

    /**
     *
     * Triggering form is admin-review-form
     * if MODE = certification
     *   && instance is the latest instance
     *   && exam passed
     * THEN RESET
     *   AND trigger first FUP survey (at certification exam pass)
     *
     *
     *
     * @param $record
     * @param $event_id
     * @param $repeat_instance
     */
    public function checkExamAndReset($record, $event_id, $repeat_instance) {
        $repeat_event = $this->getProjectSetting('exam-event');

        $rf = RepeatingForms::byEvent($this->getProjectId(), $repeat_event);
        $last_instance_id = $rf->getLastInstanceId($record, $repeat_event);
        $last_instance = $rf->getInstanceById($record, $last_instance_id, $repeat_event);
        $this->emDebug("last instance is $last_instance_id");

        //get the instance number of the last instance of the last repeating exam event
        $date_exam_field              = $this->getProjectSetting('date-exam-1-field');
        $exam_status_field            = $this->getProjectSetting('exam-status-1-field');
        $mode_field                   = $this->getProjectSetting('certify-recertify-mode-field');


        //$this->emDebug($last_instance, $mode_field, $last_instance[$mode_field]);

        $this->emDebug("Exam date is ".$last_instance[$date_exam_field]." / status is ".$last_instance[$exam_status_field]." / mode is ".$last_instance[$mode_field]);
        // IN latest instance
        //if mode is certification (empty or 0) && exam passed, then create a new instance
        if (($last_instance[$mode_field]!='1') && $last_instance[$exam_status_field] == '1') {
            $next_id = $rf->getNextInstanceId($record, $repeat_event);
            $this->emDebug("MODE IS certification and exam was passed!  Proceed to create new instance; next id is $next_id");

            $this->emDebug("MODE IS CERTIFICATION and EXAM STATUS was passed!  Proceed to create new instance; next id is $next_id");
            $this->resetInstanceToRecertify($record, $repeat_event, $next_id, $last_instance[$date_exam_field]);

        }
    }


    /**
     * Upon completion of
     *
     * @param $project_id
     * @param $record
     * @param $instrument
     * @param $event_id
     * @param $group_id
     * @param $survey_hash
     * @param $response_id
     * @param $repeat_instance
     */
    public function redcap_survey_complete($project_id, $record, $instrument, $event_id, $group_id,  $survey_hash,  $response_id,  $repeat_instance) {
        $this->emDebug("$instrument just completed as survey");

    }

    /*******************************************************************************************************************/
    /* CRON METHODS                                                                                                    */
    /***************************************************************************************************************** */


    /**
     * Reset the landing page to move to Recertification or Certification (from scratch) depending on date and status
     */
    public function cropResetCron() {
        $this->emDebug("Reset Cron started for ".$this->PREFIX);


        //get all projects that are enabled for this module
        $enabled = ExternalModules::getEnabledProjects($this->PREFIX);

        //get the noAuth api endpoint for Cron job.
        $url = $this->getUrl('src/ResetCron.php', true, true);

        //while ($proj = db_fetch_assoc($enabled)) {
        while($proj = $enabled->fetch_assoc()){

            $pid = $proj['project_id'];
            $this->emDebug("STARTING CROP RESET CRON for pid " . $pid . ' reset url is '.$url);

            $this_url = $url . '&pid=' . $pid;

            //fire off the reset process
            $resp = http_get($this_url);

        }

    }



    /**
     * Sending template alert when the learner requests Verification after completing the Recertification form
     *
     * @param $record
     * @param $event_id
     * @param $repeat_instance
     */
    public function sendAdminVerifyEmail($record, $event_id, $repeat_instance) {
        $this->emDebug("in sendAdminVerifyEmail");

        $alert_title = "ScheduleExam";     // alert template title
        $check_date_field = "ts_ready_exam_notify";  // field to log date when email sent

        //fields to update after send
        $log_update_array = array(
            'record_id'                               => $record,
            'redcap_event_name'                       => REDCap::getEventNames(true, false,$this->getProjectSetting('exam-event')),
            'redcap_repeat_instance'                  => $repeat_instance
            //$ts_ready_exam_notify_field               => $today_str, //set timestamp to today    //set in sendTemplateAlert
            //$last_alert_template_sent_field           => $alert_title //update the alertsent field  //set in sendTemplateAlert
        );

        //todo: unclear what instrument the Alerts method is requiring ? triggering instrument???
        $instrument = $this->getProjectSetting('training-survey-form');
        $this->sendTemplateAlert($record, $event_id, $repeat_instance, $instrument, $alert_title, $check_date_field, $log_update_array);
    }

    public function sendAdminVerifyRecertificationEmail($record, $event_id, $repeat_instance) {
        $alert_title      = "CheckRecertification";
        $check_date_field = "rf_ts_recertify_notify";     //timestamp on email sent

        //fields to update after send
        $log_update_array = array(
            'record_id'                               => $record,
            'redcap_event_name'                       => REDCap::getEventNames(true, false,$this->getProjectSetting('exam-event')),
            'redcap_repeat_instance'                  => $repeat_instance
        );

        //todo: unclear what instrument the Alerts method is requiring ? triggering instrument???
        $instrument = $this->getProjectSetting('recertification-form');
        $this->sendTemplateAlert($record, $event_id, $repeat_instance, $instrument, $alert_title, $check_date_field, $log_update_array);

    }

    public function sendLearnerStatusEmail($record, $event_id, $repeat_instance) {
        $this->emDebug("sending learner status email ");

        //fields for recertification mode
        $mode_field = "mode";    //see which mode

        //fields to reverify test
        $last_alert_template_sent_field = "last_alert_template_sent";
        $resend_portal_review_field = "resend_portal_review_1";
        $resend_date_stamp_field = "resend_date_stamp";
        //$ready_for_exam_field = "ready_for_exam";
        $needs_review_field = "needs_review_1";

        //fields for send exam date fields
        $date_exam_field = "date_exam";
        $send_exam_date_field = "send_exam_date";

        //fields for sending exam status
        $exam_status_field = "exam_status";
        $send_exam_status_field = "send_exam_status";

        $repeating_event_name = REDCap::getEventNames(true, false,$this->getProjectSetting('exam-event'));

        //check that [exam_arm_1][resend_portal_review_1(1)]='1' is checked
        //check that the date in timestamp field  is not today
        //Send notification
        //Uncheck the checkbox: resend_portal_review_1
        //Clear out contents of resend_review_notes_1
        //Set timestamp to today

        $params = array(
            'return_format' => 'json',
            'records'       => array($record),
            'events'        =>  $event_id,
            'redcap_repeat_instance' => $repeat_instance,  //this seems to do nothing!!
            'fields'        => array( REDCap::getRecordIdField(), $mode_field,
                $resend_portal_review_field, $resend_date_stamp_field,
                $date_exam_field, $send_exam_date_field,
                $exam_status_field,$send_exam_status_field ),
            //'filterLogic'   => $filter    //TODO ask filter does not work with repeating events/**/
        );

        $q = REDCap::getData($params);
        $records = json_decode($q, true);
        $k = $records[0];

        foreach ($records as $cand) {
            if ($cand['redcap_repeat_instance'] == $repeat_instance) {
                //$this->emDebug("Found instance ". $repeat_instance, $cand);
                $k= $cand;
                continue;
            }
        }

        /**
         * Checsk should be done in this sequence:
         *
         * 1. Check Exam PASSED
         *    a, checkbox:  'send_exam_status'  with ALERT
         * 2. Check if Exam Date set
         *    a. checkbox: send_exam_date_email
         *    b. text: date_exam
         * 3. Check if needs review
         *    a. checkbox : resend_portal_review_1
         *    b. checkbox: needs_review
         2.
         */

        $today = new DateTime();
        $today_str = $today->format('Y-m-d');
        $alerts = new Alerts();

        $this->emDebug("MODE IS  / ", $k);

        if ($k[$resend_date_stamp_field] == $today_str) {
            $this->emDebug("EMAIL already sent to user today.  Not sending any more emails");
            return false;
        }

        if ($k[$mode_field] == "1") {
            //if in recertification mode, just check that the
            if (($k[$resend_portal_review_field . "___1"] == '1') &&  ($k[$resend_date_stamp_field] <> $today_str)) {
                $this->emDebug("NEED TO SEND to learner to check recertification:  ",$k, $k[$resend_portal_review_field . "___1"]);

                //send the notification
                $this->sendAlert( $record, $event_id, $this->getProjectSetting('admin-review-form'), "RecertificationIncomplete", $alerts);

                //Uncheck the checkbox: resend_portal_review_1
                //Clear out contents of resend_review_notes_1
                //Set timestamp to today
                //Uncheck the checkbox: ready_for_exam
                //Reset the needs review radiobutton

                /**
                [record_id] => 6
                [redcap_event_name] => exam_arm_1
                [redcap_repeat_instrument] =>
                [redcap_repeat_instance] => 1
                [resend_portal_review_1___1] => 1
                [resend_date_stamp] =>
                 */

                $save_data = array(
                    'record_id'         => $record,
                    'redcap_event_name' => $repeating_event_name,
                    'redcap_repeat_instance'         => $repeat_instance,
                    $resend_portal_review_field . "___1"  => 0, //unset the checkbox
                    $needs_review_field             => '', //unset the checkbox
                    $resend_date_stamp_field         => $today_str,
                    $last_alert_template_sent_field  => 'RecertificationIncomplete' //set timestamp to today
                );

                $status = REDCap::saveData('json', json_encode(array($save_data)));
                $this->emDebug("Saving this data",$save_data, $status);
            }


        } else if (($k[$send_exam_status_field . "___1"] == '1') &&  ($k[$resend_date_stamp_field] <> $today_str)) {
            //check if exam has passed
            $this->emDebug("NEED TO SEND EXAM STATUS:  ",$k, $k[$send_exam_status_field . "___1"]);

            //send the notification
            $this->sendAlert( $record, $event_id, $this->getProjectSetting('admin-review-form'), "SendExamStatus", $alerts);

            $save_data = array(
                'record_id'         => $record,
                'redcap_event_name' => $repeating_event_name,
                'redcap_repeat_instance'         => $repeat_instance,
                $send_exam_status_field . "___1" => 0, //unset the checkbox
                $resend_date_stamp_field         => $today_str,
                $last_alert_template_sent_field  => 'SendExamStatus'//set timestamp to today
            );

            $status = REDCap::saveData('json', json_encode(array($save_data)));
            $this->emDebug("Saving this data", $save_data, $status);

        } else if (($k[$send_exam_date_field . "___1"] == '1') &&  ($k[$resend_date_stamp_field] <> $today_str)) {
            $this->emDebug("NEED TO SEND EXAM DATE STATUS:  ",$k, $k[$send_exam_date_field . "___1"]);

            //send the notification
            $this->sendAlert( $record, $event_id, $this->getProjectSetting('admin-review-form'), "SendExamDate", $alerts);

            $save_data = array(
                'record_id'         => $record,
                'redcap_event_name' => $repeating_event_name,
                'redcap_repeat_instance'         => $repeat_instance,
                $send_exam_date_field . "___1"  => 0, //unset the checkbox
                $resend_date_stamp_field         => $today_str,
                $last_alert_template_sent_field  => 'SendExamDate' //set timestamp to today
            );


            $status = REDCap::saveData('json', json_encode(array($save_data)));
            $this->emDebug("Saving this data", $save_data, $status);
        } else if (($k[$resend_portal_review_field . "___1"] == '1') &&  ($k[$resend_date_stamp_field] <> $today_str)) {
            $this->emDebug("SENDING ALERT SeminarIncomplete:  resend_portal_review: ". $k[$resend_portal_review_field . "___1"]);

            //send the notification
            $this->sendAlert( $record, $event_id, $this->getProjectSetting('admin-review-form'), "SeminarIncomplete", $alerts);

            //Uncheck the checkbox: resend_portal_review_1
            //Clear out contents of resend_review_notes_1
            //Set timestamp to today
            //Uncheck the checkbox: ready_for_exam
            //Reset the needs review radiobutton

            /**
            [record_id] => 6
            [redcap_event_name] => exam_arm_1
            [redcap_repeat_instrument] =>
            [redcap_repeat_instance] => 1
            [resend_portal_review_1___1] => 1
            [resend_date_stamp] =>
             */

            $save_data = array(
                'record_id'         => $record,
                'redcap_event_name' => $repeating_event_name,
                'redcap_repeat_instance'         => $repeat_instance,
                $resend_portal_review_field . "___1"  => 0, //unset the checkbox
                //$ready_for_exam_field . "___1"  => 0, //unset the checkbox
                $needs_review_field             => '', //unset the radiobutton todo: how to reset a radiobutton?
                $resend_date_stamp_field         => $today_str,
                $last_alert_template_sent_field  => 'SeminarIncomplete' //set timestamp to today
            );


            $status = REDCap::saveData('json', json_encode(array($save_data)));
            $this->emDebug("Saving this data",$save_data,  $status);
        }

    }

    /**
     *
     *
     * @param $record
     * @param $event_id - event_id where the check_date_field is located
     * @param $repeat_instance - repeat_instance number where the check_date_field
     * @param $instrument
     * @param $alert_title
     * @param $alerts
     * @param $check_date_field - check field is empty to prevent multiple sends on same day (if populated, don't send)
     * @param $log_update_array   - array of fields to log with send updates
     */
    public function sendTemplateAlert($record, $event_id, $repeat_instance, $instrument, $alert_title, $check_date_field, $log_update_array) {
        $this->emDebug("SEnding $alert_title for Record $record check_date_field is $check_date_field");

        //check that the email not already sent
        //check that the ready_for_exam field is checked
        $params = array(
            'return_format' => 'json',
            'records'       => array($record),
            'events'        =>  $event_id,
            'redcap_repeat_instance' => $repeat_instance,     //Adding parameter here does NOT seem to limit the getData to this instance
            'fields'        => array( REDCap::getRecordIdField(), $check_date_field)
            //'filterLogic'   => $filter    //TODO filter does not work with repeating events??
        );

        $q = REDCap::getData($params);
        $records = json_decode($q, true);

        $target_data = $records[0]; //for the cases where repeat_instance is not set (instance = 0 sometimes is not set)?

        //Adding redcap_repeat_instance in getData  does NOT seem to limit the getData to this instance
        foreach ($records as $cand) {
            if ($cand['redcap_repeat_instance'] == $repeat_instance) {
                $this->emDebug("Found instance ". $repeat_instance, $cand);
                $target_data = $cand;
                continue;
            }
        }

        $this->emDebug("Repeat instance is $repeat_instance", $target_data);

        //get today's date
        $today = new DateTime();
        $today_str = $today->format('Y-m-d');

        if (($target_data[$check_date_field] <> $today_str)) {
            $alerts = new Alerts();
            //send the notification
            $this->emDebug("Sending the $alert_title to learner $record instance is $repeat_instance");
            $this->sendAlert( $record, $repeat_instance, $instrument, $alert_title, $alerts);
            /*
                        [record_id] => 4
                        [redcap_event_name] => exam_arm_1
                        [redcap_repeat_instrument] =>
                        [redcap_repeat_instance] => 1
                        [ready_for_exam___1] => 1
            */
            //add the timestamp field the logUpdateArray
            $log_update_array[$check_date_field] = $today_str;
            $log_update_array[$this->getProjectSetting('last-alert-template-sent-field')] = $alert_title;

            $status = REDCap::saveData('json', json_encode(array($log_update_array)));
            $this->emDebug("Saving this data and status", $log_update_array, $status);

            return;
        }

    }

    /**
     * Called by cron job
     * Check all the records to see if new exam_events instance need to be created.
     */
    public function checkExpirationGracePeriod() {
        //iterate through all the records and check that the expiration is not today
        $cert_mode         = "cert_mode";
        $cert_start        = "cert_start";
        $cert_end          = "cert_end";
        $cert_grace_pd_end = "cert_grace_pd_end";
        $recertify_mode    = '1';
        $status_field      = 'recertify_status';

        $event_filter_str =  "[" . REDCap::getEventNames(true, false, $this->getProjectSetting('application-event')) . "]";

        $today = new DateTime();
        $today_str = $today->format('Y-m-d');

        //find records where the 30 day grace period ends today
        $filter = $event_filter_str . "[" . $cert_grace_pd_end . "] = '$today_str'";

        //add the filter to check that the $cert_mode is in recertification
        $filter .= " AND ". $event_filter_str . "[" . $cert_mode . "] = '$recertify_mode'";

        $params = array(
            'return_format' => 'json',
            'events'        =>  $event_filter_str,
            'fields'        => array( REDCap::getRecordIdField(), $cert_mode, $cert_start, $cert_end, $cert_grace_pd_end),
            'filterLogic'   => $filter
        );

        $q = REDCap::getData($params);
        $records = json_decode($q, true);

        $this->emDebug($filter, $params, $records, $q);

        //alert->sendNotification needs an event (of triggering event??)
        $repeat_event = $this->getProjectSetting('exam-event');
        $trigger_form = $this->getProjectSetting('admin-review-form');
        $date_exam_field = $this->getProjectSetting('date-exam-1-field');
        $mode_field      = $this->getProjectSetting('certify-recertify-mode-field');

        //create the REDCap Alerts class to pass on sendAlert method
        $alerts = new Alerts();

        $rf = RepeatingForms::byEvent($this->getProjectId(), $repeat_event);

        //iterate through these records (with today as grace period expiration and in recertification mode)
        foreach ($records as $record) {
            $record_id = $record['record_id'];

            //send them the notification for "ExpirationLetter"
            $this->sendAlert( $record[REDCap::getRecordIdField()], $repeat_event, $trigger_form, "ExpirationLetter", $alerts);

            //create a new instance
            $last_instance_id = $rf->getLastInstanceId($record_id, $repeat_event);
            $last_instance = $rf->getInstanceById($record_id, $last_instance_id, $repeat_event);

            //check that the recertify_status is PASS : ]'recertify_status'] == '1'
            //if mode is certification (empty or 0) && exam passed, then create a new instance

            $this->emDebug($last_instance_id, $mode_field, $last_instance[$mode_field]);
            //   mode = recertify (not blank, not 0)        status  = PASS  ('1')
            if ($last_instance[$mode_field] == '1') {
                $next_id = $rf->getNextInstanceId($record_id, $repeat_event);

                if ($last_instance[$status_field] == '1') {
                    //STATUS IS PASS

                    /**   RESET TO RECERTIFY */
                    $this->emDebug("MODE IS RECERTIFICATION and STATUS was passed!  Proceed to create new instance; next id is $next_id");
                    $this->resetInstanceToRecertify($record_id, $repeat_event, $next_id, $last_instance[$date_exam_field]);
                } else {
                    //STATUS IS FAIL or NOT SET

                    /**   RESET TO CERTIFY - START FROM SCRATCH*/
                    $this->emDebug("MODE IS CERTIFICATION and STATUS was passed!  Proceed to create new instance; next id is $next_id");
                    $this->resetInstanceToCertify($record_id, $repeat_event, $next_id);
                }

            }

        }

    }

    /**
     * Check records where today is the date of expiry and mode is  Recertifications
     * If recertify Verification status ('recertify_status') IS PASS, then create a new instance to Recertification
     *
     * @throws \Exception
     */
    public function checkExpiration() {
        //iterate through all the records and check that the expiration is not today
        $cert_mode        = "cert_mode";
        $cert_start        = "cert_start";
        $cert_end          = "cert_end";
        $cert_grace_pd_end = "cert_grace_pd_end";
        $date_certified_field = 'date_certification';
        $recertify_mode    = '1';

        $event_filter_str =  "[" . REDCap::getEventNames(true, false, $this->getProjectSetting('application-event')) . "]";

        $today = new DateTime();
        $today_str = $today->format('Y-m-d');

        //find records where the 30 day grace period ends today
        $filter = $event_filter_str . "[" . $cert_end . "] = '$today_str'";

        //add the filter to check that the $cert_mode is in recertification
        $filter .= " AND ". $event_filter_str . "[" . $cert_mode . "] = '$recertify_mode'";

        $params = array(
            'return_format' => 'json',
            'events'        =>  $event_filter_str,
            'fields'        => array( REDCap::getRecordIdField(), $cert_mode, $cert_start, $cert_end, $cert_grace_pd_end),
            'filterLogic'   => $filter
        );

        $q = REDCap::getData($params);
        $records = json_decode($q, true);

        $this->emDebug($filter, $params, $records, $q);

        $repeat_event = $this->getProjectSetting('exam-event');
        $trigger_form = $this->getProjectSetting('admin-review-form');

        $rf = RepeatingForms::byEvent($this->getProjectId(), $repeat_event);

        //iterate through these records (with today as grace period expiration and in recertification mode)
        foreach ($records as $record) {

            $record_id = $record['record_id'];

            $last_instance_id = $rf->getLastInstanceId($record_id, $repeat_event);
            $next_id = $rf->getNextInstanceId($record_id, $repeat_event);
            $last_instance = $rf->getInstanceById($record_id, $last_instance_id, $repeat_event);

            //$this->emDebug($record_id, $next_id, $last_instance[$date_certified_field], $last_instance, $date_certified_field); exit;
            $this->resetInstanceToRecertify($record_id, $repeat_event, $next_id, $last_instance[$date_certified_field]);
        }
    }


    /**
     * Helper method that looks through all the Alerts in projects and search for match
     * ex; "Seminars not complete - send review email"
     * And triggers the notification
     *
     * @param $record
     * @param $event_id
     * @param $instrument
     * @param $alert_title
     * $param $alert - Alerts object already created
     */
    public function sendAlert($record, $event_id, $instrument, $alert_title, $alert) {
        $project_id = $this->getProjectId();

        //$alert = new Alerts();
        $alerts = $alert->getAlertSettings($project_id);

        //iterate through and find the one that matches the $alert title
        foreach ($alerts as $k) {
            if ($k['alert_title'] == $alert_title) {
                $this->emDebug("Found alert title $alert_title sending notification");
                $id = $k['alert_id'];
                $foo = $alert->sendNotification($k['alert_id'],$project_id, $record, $event_id, $instrument);
                $this->emDebug("SENT: ", $foo);
                continue;
            }
        }

        return;
    }


        /*******************************************************************************************************************/
        /* EM HELPER METHODS                                                                                                      */
        /***************************************************************************************************************** */

    /**
     * Helper method to create a new instance in Recertification mode
     * Offset dates added
     * Admin form ('admin') fields  reset to new dates
     *
     * @param $record
     * @param $repeat_event
     * @param $next_id
     * @param $exam_date
     */
    function resetInstanceToRecertify($record, $repeat_event, $next_id, $exam_date) {
        //create a new instance with mode = 0 (certification) or mode = 1 (recertification)

        $expiry_date = $this->getOffsetDate($exam_date, $this->getProjectSetting('final-exam-to-expiry-offset'));
        $grace_pd_date = $this->getOffsetDate($expiry_date, 30);

        $data = array(
            REDCap::getRecordIdField()                                    => $record,
            'redcap_event_name'                                           => REDCap::getEventNames(true, false, $repeat_event),
            'redcap_repeat_instance'                                      => $next_id,
            $this->getProjectSetting('certify-recertify-mode-field') => 1, //recertify mode
            $this->getProjectSetting('final-exam-date-field')        => $exam_date,
            $this->getProjectSetting('expiry-date-field')     => $expiry_date,
            $this->getProjectSetting('fup-survey-6-mo-field') => $this->getOffsetDate($exam_date, 180),
            $this->getProjectSetting('fup-survey-1-yr-field') => $this->getOffsetDate($exam_date, 365),
            $this->getProjectSetting('rem-expiry-6-mo-field') => $this->getOffsetDate($expiry_date, -180),
            $this->getProjectSetting('rem-expiry-1-mo-field') => $this->getOffsetDate($expiry_date, -30),
            $this->getProjectSetting('grace-pd-30-day-field') => $grace_pd_date
        );

        $response = REDCap::saveData('json', json_encode(array($data)));

        if ($response['errors']) {
            $msg = "Error while trying to save dates to repeating event.";
            $this->emError($response['errors'], $data, $msg);
        } else {
            $this->emDebug("Successfully saved date data.");
        }

        //also save to the admin event to reflect the latest status
        $admin_data = array(
            REDCap::getRecordIdField() => $record,
            'redcap_event_name' => REDCap::getEventNames(true, false, $this->getProjectSetting('application-event')),
            'cert_mode'  => 1,
            'cert_start' => $exam_date,
            'cert_end'   => $expiry_date,
            'cert_grace_pd_end' => $grace_pd_date
        );

        $response = REDCap::saveData('json', json_encode(array($admin_data)));

        if ($response['errors']) {
            $msg = "Error while trying to save dates to admin event.";
            $this->emError($response['errors'], $admin_data, $msg);
        } else {
            $this->emDebug("Successfully saved admin date data.");
        }
    }

    /**
     * Helper method to create a new instance in Certification mode
     * Admin form ('admin') fields  reset to NO dates
     *
     * @param $record
     * @param $repeat_event
     * @param $next_id
     */
    function resetInstanceToCertify($record, $repeat_event, $next_id) {
        //create a new instance with mode = 0 (certification) or mode = 1 (recertification)
        $data = array(
            REDCap::getRecordIdField() => $record,
            'redcap_event_name' => REDCap::getEventNames(true, false, $repeat_event),
            'redcap_repeat_instance' => $next_id,
            $this->getProjectSetting('certify-recertify-mode-field') => 0 //certify mode
        );

        //save the data
        $response = REDCap::saveData('json', json_encode(array($data)));

        if ($response['errors']) {
            $msg = "Error while trying to save dates to repeating event.";
            $this->emError($response['errors'], $data, $msg);
        } else {
            $this->emDebug("Successfully saved date data.");
            $this->emDebug($response, $data);
        }

        //also save to the admin event to reflect the latest status
        $admin_data = array(
            REDCap::getRecordIdField() => $record,
            'redcap_event_name' => REDCap::getEventNames(true, false, $this->getProjectSetting('application-event')),
            'cert_mode' => 0,
            'cert_start' => '',
            'cert_end' => '',
            'cert_grace_pd_end' => ''
        );

        $response = REDCap::saveData('json', json_encode(array($admin_data)), 'overwrite');

        if ($response['errors']) {
            $msg = "Error while trying to save dates to admin event.";
            $this->emError($response['errors'], $admin_data, $msg);
        } else {
            $this->emDebug("Successfully saved admin date data.");
            $this->emDebug($response, $admin_data);
        }
    }


    /**
     * Helper method to calculate dates from offset
     * @param $start_date
     * @param $offset
     * @return string
     * @throws \Exception
     */
    public function getOffsetDate($start_date, $offset)
        {
            $this->emDebug("Start date is $start_date with $offset");
            $end_date = new DateTime($start_date);
            $di = new DateInterval('P' . abs($offset) . 'D');

            if ($offset < 0) {
                $di->invert = 1; // Proper negative date interval
            }
            $end_date->add($di);
            //$this->emDebug("Start date is $start_date with $offset . and end date ".$end_date->format('Y-m-d'));
            return $end_date->format('Y-m-d');
        }


    public function findRecordFromSUNet($id, $target_event = NULL) {
        global $module;

        //should this be parametrized?
        $target_id_field = "webauth_user";
        $firstname_field = "first_name";
        $lastname_field  = "last_name";
        $cert_mode       = "cert_mode";
        $cert_start      = "cert_start";
        $cert_end        = "cert_end";
        $date_start_field = "st_date_seminar_start";

        if (empty($id)) {
            $module->emDebug("No id passed.");
            return array(false, "SUNet is blank. PLease webauth and try again!");
        }

        //punt on this until it becomes longitudinal
        $event_filter_str = "";
        if (REDCap::isLongitudinal()) {
            $event_filter_str =  "[" . REDCap::getEventNames(true, false, $target_event) . "]";
        }


        $filter = $event_filter_str . "[" . $target_id_field . "] = '$id'";

        // Use alternative passing of parameters as an associate array
        $params = array(
            'return_format' => 'json',
            'events'        =>  $target_event,
            'fields'        => array( REDCap::getRecordIdField(), $firstname_field, $lastname_field, $cert_mode, $cert_start, $cert_end),
            'filterLogic'   => $filter
        );

        $q = REDCap::getData($params);
        $records = json_decode($q, true);

        //$module->emDebug($filter, $params, $records, $q);

        //return ($records[0][REDCap::getRecordIdField()]);
        return ($records[0]);

    }

    /**
     * Get the data from the seminar / trainings and render as table
     *
     * @param $instance
     * @return string
     * @throws \Exception
     */
    public function getLatestSeminars($instance) {
        $instrument = 'seminars_trainings';
        $htm  = '';

        $dict = REDCap::getDataDictionary($this->getProjectId(),'array', false, null, $instrument);
        //$this->emDebug($instance);

        foreach ($this->portal_fields as $field) {


            if (!is_array($field)) {
                $field_label = $dict[$field]['field_label'];
                $field_value = $instance[$field];
                $field_id = $field;
            } else {

                $field_label = $dict[$field[0]]['field_label'];

                $field_value = $instance[$field[1]];
                $field_id = $field[1];

                if ($dict[$field[0]]['field_type'] === 'dropdown') {

                    $selected = trim($instance[$field[0]]);

                    $default_selected_display = $selected == '' ? 'selected disabled' : '';
                    //$this->emDebug("SELECTED? $field[0] :  " . $selected . " : " . ($selected == ''). " :display=> ". $default_selected_display);

                    $field_choices = "<option value='' {$default_selected_display}>{$field_label}</option>";
                    foreach (explode("|", $dict[$field[0]]['select_choices_or_calculations']) as $choice) {
                        $choice_parts = explode(",", $choice);
                        $choice_selected_display = ($selected == $choice_parts[0]) ? 'selected' : '';
                        //$this->emDebug("SELECTED?  :  " . $choice_parts[0] . " : " . ($selected == $choice_parts[0]). " :display=> ". $choice_selected_display);
                        $field_choices .= "<option value='{$choice_parts[0]}'  {$choice_selected_display}>{$choice_parts[1]}</option>";
                    }

                    $field_choices .= "</select></div>";
                    $field_label = "<div class='form-group col-md-8'>
                          <select id='{$field[0]}' class='form-control select'>
                                {$field_choices}
                          </select>
                      </div>";
                }

                //handle free text fields that aren't dates
                //if (($dict[$field]['field_type'] == 'text') && ($dict[$field]['text_validation_type_or_show_slider_number'] !== 'date_ymd')) {
                if (($dict[$field[0]]['field_type'] == 'text') && (strpos($field[0], 'elective_') === 0)) {

                    //$field_elective_label = substr($field[0], 0, -5);
                    $field_elective_value = $instance[$field[0]];
                    $field_label = "<input id='{$field[0]}' type='text' class='form-control elective' value='{$field_elective_value}' placeholder='Please enter {$field_label}'/> ";
                }
            }

            $htm .= '<tr><td>'
                .$field_label.
                "</td>
                  <td>
                      <div class='input-group date'  >
                          <input id='{$field_id}' type='text' class='form-control dt' value='{$field_value}' placeholder='yyyy-mm-dd'/>
                          <span class='input-group-addon'>
                               <span class='glyphicon glyphicon-calendar'></span>
                           </span>
                      </div>
                  </td>
              </tr>";
        }

        return $htm;
    }


    /**
     * Get the data from the recertification and render as table
     *
     * @param $instance
     * @return string
     * @throws \Exception
     */
    public function getRecertification($instance) {
        $instrument = 'recertification_form';
        $htm  = '';

        $dict = REDCap::getDataDictionary($this->getProjectId(),'array', false, null, $instrument);
        //$this->emDebug($instance);

        foreach ($this->recert_fields as $field) {

            //if array, then it is date - sponsor - class
            if (!is_array($field)) {
                $field_date_value = $instance[$field];
                $field_date_id = $field;
                $field_sponsor = (strpos($field, 'ethics') !== false) ? 'RCO' :'Privacy';
                $field_class = $dict[$field]['field_label'];
            } else {
                $field_date_value = $instance[$field[0]];
                $field_date_id = $field[0];
                $field_sponsor = "<input id='{$field[1]}' type='text' class='form-control elective' value='{$instance[$field[1]]}' placeholder='Please enter Sponsor'/> ";
                $class_label = (strpos($field[2], 'core') !== false) ? 'CORE TITLE' :'CLASS TITLE';

                $field_class = "<input id='{$field[2]}' type='text' class='form-control elective' value='{$instance[$field[2]]}' placeholder='{$class_label}'/> ";
            }

            //date - sponsor - class
            $htm .= "<tr>".
                "<td>
                      <div class='input-group date'  >
                          <input id='{$field_date_id}' type='text' class='form-control dt' value='{$field_date_value}' placeholder='yyyy-mm-dd'/>
                          <span class='input-group-addon'>
                               <span class='glyphicon glyphicon-calendar'></span>
                           </span>
                      </div>
                  </td>".
                "<td>"
                .$field_sponsor.
                "</td>".
                "<td>"
                .$field_class.
                "</td>".
              "</tr>";
        }

        return $htm;
    }


    /**
     *
     * @param $date
     * @param $text
     * @param $coded
     * @return array
     * @throws \Exception
     */
    public function setupSaveData($date, $text, $coded) {


        $codedArray = array();
        foreach ($coded as $field_name => $field_value) {
            $codedArray[$field_name] = $field_value;
        }

        $textArray = array();
        foreach ($text as $field_name => $field_value) {
            $textArray[$field_name] = $field_value;
        }

        $dateArray = array();
        foreach ($date as $field_name => $field_value) {
            if (!empty($field_value)) {


                if (strpos($field_name,'_date') !== false) {
                    $date_cand = new \DateTime($field_value);
                    $date_str = $date_cand->format('Y-m-d');

                    $dateArray[$field_name] = $date_str;
                } else {
                    $textArray[$field_name] = $field_value;
                }
            }
        }

        $data = array_merge(
            $codedArray,
            $textArray,
            $dateArray
        );

        return $data;
    }


}