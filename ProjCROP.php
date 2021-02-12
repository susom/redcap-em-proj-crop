<?php

namespace Stanford\ProjCROP;

use DateInterval;
use DateTime;
use REDCap;
use ExternalModules\ExternalModules;
use Alerts;
use Files;
use Project;

require_once 'emLoggerTrait.php';
require_once 'src/RepeatingForms.php';

class ProjCROP extends \ExternalModules\AbstractExternalModule {

    use emLoggerTrait;

    //These are the fields to be displayed for the certification form
    //TODO: convert this to use the EM config
    var $portal_fields = array(
        "st_date_hipaa",
        "st_date_citi",
        "st_citi_file",
        "st_date_clin_trials",
        "st_date_ethics",
        "st_date_irb_report",
        "st_date_doc",
        "st_date_resources",
        "st_date_consent",
        "st_date_irb",
        "st_date_budgeting",
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

    private $proj;

    /*******************************************************************************************************************/
    /* HOOK METHODS                                                                                                    */
    /***************************************************************************************************************** */

    public function redcap_save_record($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash = NULL, $response_id = NULL, $repeat_instance) {
        //
        $this->emDebug("Just saved $instrument in instance $repeat_instance");

        //on save of admin_review form, trigger email to admin to verify the training
        //or Reset to new event instance
        if ($instrument == $this->framework->getProjectSetting('admin-review-form')) {
            $this->checkExamAndReset($record, $event_id, $repeat_instance);
            $this->sendLearnerStatusEmail($record, $event_id, $repeat_instance);
        }
    }

    /**
     *
     * Triggering form is admin-review-form
     * mode = 1 (Recertification) / 0 (Certification)
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
        $repeat_event = $this->framework->getProjectSetting('exam-event');

        $rf = RepeatingForms::byEvent($this->getProjectId(), $repeat_event);
        $last_instance_id = $rf->getLastInstanceId($record, $repeat_event);
        $last_instance = $rf->getInstanceById($record, $last_instance_id, $repeat_event);
        $this->emDebug("Last instance for record $record is $last_instance_id");

        //get the instance number of the last instance of the last repeating exam event
        $date_exam_field              = $this->getProjectSetting('date-exam-1-field');
        $exam_status_field            = $this->getProjectSetting('exam-status-1-field');
        $mode_field                   = $this->getProjectSetting('certify-recertify-mode-field');



        //GEt the MODE (cert/recert) and exam_status
        $mode                         = $last_instance[$mode_field];
        $exam_status                  = $last_instance[$exam_status_field];

        // IN latest instance
        //if mode is certification (empty or 0) && exam passed, then create a new instance
        if (($last_instance[$mode_field]!='1') && $last_instance[$exam_status_field] == '1') {
            $next_id = $rf->getNextInstanceId($record, $repeat_event);
            $this->emDebug("Record=$record : MODE IS CERTIFICATION and EXAM STATUS was passed!  Proceed to create new instance; next id is $next_id");
            $this->resetInstanceToRecertify($record, $repeat_event, $next_id, $last_instance[$date_exam_field]);

        }

        //TODO convert to config?
        $recertify_status_field      = 'recertify_status';

        //IF MODE IS RECERTIFICATION (1) &&
        if (($last_instance[$mode_field]=='1') && $last_instance[$recertify_status_field] == '1') {
            $recertify_date_field = 'recertify_date';
            //
            $next_id = $rf->getNextInstanceId($record, $repeat_event);
            $this->emDebug("Record=$record : MODE IS RECERTIFICATION and EXAM STATUS was passed!  Proceed to create new instance; next id is $next_id");
            $this->resetInstanceToRecertify($record, $repeat_event, $next_id, $last_instance[$recertify_date_field]);

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
    /* TEMPLATE EMAIL METHODS  - SENT BY LEARNER                                                                       */
    /***************************************************************************************************************** */



    /**
 * Email triggered by the learner from the portal (verification request for Certification form)
 * Sending template alert when the learner requests Verification after completing the Recertification form
 *
 * @param $record
 * @param $event_id
 * @param $repeat_instance
 */
    public function sendAdminVerifyEmail($record, $event_id, $repeat_instance) {
        $this->emDebug("in sendAdminVerifyEmail");

        $alert_title = $this->framework->getProjectSetting("template-schedule-exam"); //"ScheduleExam";     // alert template title
        $check_date_field =  $this->framework->getProjectSetting("certify-request-sent-timestamp-field"); //"ts_ready_exam_notify");  // field to log date when email sent

        //fields to update after send
        $log_update_array = array(
            'record_id'                               => $record,
            'redcap_event_name'                       => REDCap::getEventNames(true, false,$this->framework->getProjectSetting('exam-event')),
            'redcap_repeat_instance'                  => $repeat_instance
            //$ts_ready_exam_notify_field               => $today_str, //set timestamp to today    //set in sendTemplateAlert
            //$last_alert_template_sent_field           => $alert_title //update the alertsent field  //set in sendTemplateAlert
        );

        //todo: unclear what instrument the Alerts method is requiring ? triggering instrument???
        $instrument = $this->framework->getProjectSetting('training-survey-form');
        $this->sendTemplateAlert($record, $event_id, $repeat_instance, $instrument, $alert_title, $check_date_field, $log_update_array);
    }

    /**
     * Email triggered by the learner from the portal (verification request for Certification form)
     * Sending template alert when the learner requests Verification after completing the Recertification form
     * CHANGEREQUEST: Sept 2020: Learner gets an email too
     *
     * @param $record
     * @param $event_id
     * @param $repeat_instance
     */
    public function sendLearnerVerifyEmail($record, $event_id, $repeat_instance) {
        $this->emDebug("in sendLearnerVerifyEmail");

        $alert_title = $this->framework->getProjectSetting("template-schedule-exam-learner"); //"ScheduleExamLearner";     // alert template title

        //For learner IGNORE the multiple send for current
        //$check_date_field =  $this->framework->getProjectSetting("certify-request-sent-timestamp-field"); //"ts_ready_exam_notify");  // field to log date when email sent
        $check_date_field = '';


        //fields to update after send
        $log_update_array = array(
            'record_id'                               => $record,
            'redcap_event_name'                       => REDCap::getEventNames(true, false,$this->framework->getProjectSetting('exam-event')),
            'redcap_repeat_instance'                  => $repeat_instance
            //$ts_ready_exam_notify_field               => $today_str, //set timestamp to today    //set in sendTemplateAlert
            //$last_alert_template_sent_field           => $alert_title //update the alertsent field  //set in sendTemplateAlert
        );

        //todo: unclear what instrument the Alerts method is requiring ? triggering instrument???
        $instrument = $this->framework->getProjectSetting('training-survey-form');
        $this->sendTemplateAlert($record, $event_id, $repeat_instance, $instrument, $alert_title, $check_date_field, $log_update_array);
    }

    /**
     * Email triggered by the learner from the portal (verification request for Certification form)
     *
     * @param $record
     * @param $event_id
     * @param $repeat_instance
     */
    public function sendAdminVerifyRecertificationEmail($record, $event_id, $repeat_instance) {
        $alert_title = $this->framework->getProjectSetting("template-check-recertification"); //"CheckRecertification";     // alert template title
        $check_date_field = $this->framework->getProjectSetting("rf_ts_recertify_notify");     //timestamp on email sent

        //fields to update after send
        $log_update_array = array(
            'record_id'                               => $record,
            'redcap_event_name'                       => REDCap::getEventNames(true, false,$this->framework->getProjectSetting('exam-event')),
            'redcap_repeat_instance'                  => $repeat_instance
        );

        //todo: unclear what instrument the Alerts method is requiring ? triggering instrument???
        $instrument = $this->framework->getProjectSetting('recertification-form');
        $this->sendTemplateAlert($record, $event_id, $repeat_instance, $instrument, $alert_title, $check_date_field, $log_update_array);

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

                $target_data = $cand;
                continue;
            }
        }

        //get today's date
        $today = new DateTime();
        $today_str = $today->format('Y-m-d');

        if (($target_data[$check_date_field] == $today_str)) {
            $multiple = $this->framework->getProjectSetting("allow-multiple-emails");
            if ($multiple != "true") {
                $this->emDebug("EMAIL already sent to admin from learner today.  Not sending any more emails");
                return false;
            }
        }

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

        return;

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

                continue;
            }
        }

        return;
    }

    /*******************************************************************************************************************/
    /* TEMPLATE EMAIL METHODS  - SENT BY ADMIN                                                                       */
    /***************************************************************************************************************** */


    /**
     * There are several template emails that have been defined in Alerts and Notification.
     * Do a lookup for those templates among the Alerts and Notification
     *
     * @param $record
     * @param $event_id
     * @param $repeat_instance
     * @return bool
     * @throws \Exception
     */
    public function sendLearnerStatusEmail($record, $event_id, $repeat_instance) {
        $this->emDebug("Checking whether status email should be sent  to  learner... ");

        //fields for recertification mode
        $mode_field = $this->framework->getProjectSetting("certify-recertify-mode-field");    //see which mode

        //fields to reverify test
        $resend_portal_review_field = "resend_portal_review_1";
        $resend_date_stamp_field = $this->framework->getProjectSetting("alert-sent-timestamp-field");

        $needs_review_field = "needs_review_1";

        //fields for send exam date fields
        $send_exam_date_field = "send_exam_date";

        //fields for sending exam status
        $send_exam_status_field = "send_exam_status";

        $exam_event           = $this->framework->getProjectSetting('exam-event');
        $repeating_event_name = REDCap::getEventNames(true, false, $exam_event);

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
            'fields'        => array( REDCap::getRecordIdField(),
                $mode_field,
                $resend_portal_review_field,
                $resend_date_stamp_field,
                $send_exam_date_field,
                $send_exam_status_field ),
            //'filterLogic'   => $filter    //TODO ask filter does not work with repeating events/**/
        );

        $q = REDCap::getData($params);
        $records = json_decode($q, true);
        $k = $records[0];

        //get the correct repeat_instance
        foreach ($records as $cand) {
            if ($cand['redcap_repeat_instance'] == $repeat_instance) {
                //$this->emDebug("Found instance ". $repeat_instance, $cand);
                $k= $cand;
                continue;
            }
        }

        /**
         * Checks should be done in this sequence:
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

        //$this->emDebug("MODE IS  / ", $k);

        if ($k[$resend_date_stamp_field] == $today_str )  {

            $multiple = $this->framework->getProjectSetting("allow-multiple-emails");
            if ($multiple != "true") {
                $this->emDebug("EMAIL already sent to user today.  Not sending any more emails");
                return false;
            }
        }

        //MDOE : 0 or Blank = CERTIFICAITON / 1 = RECERTIFICATION
        if ($k[$mode_field] == "1") {
            //RECERTIFICATION

            //if send recert if checkbox is checked
            if ($k[$resend_portal_review_field . "___1"] == '1') {

                //extra data to be set
                $extra_data = array($resend_portal_review_field . "___1"  => 0, //unset the checkbox
                    $needs_review_field                  => '', //unset the checkbox
                );

                $this->sendEmail($record,
                                 $event_id,
                                 $this->framework->getProjectSetting('admin-review-form'),
                                 $this->framework->getProjectSetting("template-recertification-incomplete"), //"TEMPLATE_RecertificationIncomplete"),
                                 $repeating_event_name,
                                 $repeat_instance,
                                 $extra_data);
            }

        } else {
            //CERTIFICATION

            // Send exam status to learner if send checkbox is check
            if ($k[$send_exam_status_field . "___1"] == '1') {
                $this->emDebug("NEED TO SEND EXAM STATUS:  ".$k[$send_exam_status_field . "___1"]);

                //extra data to be set
                $extra_data = array($send_exam_status_field . "___1" => 0); //unset the checkbox);
                $template   = $this->framework->getProjectSetting("template-send-exam-status"); //"TEMPLATE_SendExamStatus");


            } else if ($k[$send_exam_date_field . "___1"] == '1') {
              // Send Learner email with  exam date notification

                //extra data to be set
                $extra_data = array($send_exam_date_field . "___1"  => 0); //unset the checkbox
                $template   = $this->framework->getProjectSetting("template-send-exam-date"); //"TEMPLATE_SendExamDate");
            } else if ($k[$resend_portal_review_field . "___1"] == '1')  {
                // Send Learner email that seminar form needs review

                //extra data to be set
                $extra_data = array($resend_portal_review_field . "___1"  => 0, //unset the checkbox
                    //$ready_for_exam_field . "___1"  => 0, //unset the checkbox
                                    $needs_review_field             => ''); //unset the radiobutton todo: how to reset a radiobutton?
                $template   = $this->framework->getProjectSetting("template-seminar-incomplete"); //"TEMPLATE_SeminarIncomplete");
            }

            $this->sendEmail($record,
                             $event_id,
                             $this->framework->getProjectSetting('admin-review-form'),
                             $template,
                             $repeating_event_name,
                             $repeat_instance,
                             $extra_data);
        }


    }


    /**
     * Helper wrapper method that sets up the sending of emails using a template alerts
     *
     * @param $record
     * @param $event_id
     * @param $form
     * @param $template
     * @param $repeating_event_name
     * @param $repeat_instance
     * @param $extra_data
     * @throws \Exception
     */
    public function sendEmail($record, $event_id, $form, $template, $repeating_event_name, $repeat_instance, $extra_data) {
        $today = new DateTime();
        $today_str = $today->format('Y-m-d');
        $alerts = new Alerts();

        $resend_date_stamp_field = $this->framework->getProjectSetting("alert-sent-timestamp-field"); //resend_date_stamp");
        $last_alert_template_sent_field = $this->framework->getProjectSetting("last-alert-template-sent-field"); //last_alert_template_sent");

        //send the notification
        $this->sendAlert( $record, $event_id, $form, $template, $alerts);

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
            'record_id'                           => $record,
            'redcap_event_name'                   => $repeating_event_name,
            'redcap_repeat_instance'              => $repeat_instance,
            $resend_date_stamp_field              => $today_str,
            $last_alert_template_sent_field       => $template //set timestamp to today
        );

        $data = array_merge($save_data, $extra_data);

        $status = REDCap::saveData('json', json_encode(array($data)));
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

        //alert->sendNotification needs an event (of triggering event??)
        $repeat_event    = $this->framework->getProjectSetting('exam-event');
        $trigger_form    = $this->framework->getProjectSetting('admin-review-form');
        $mode_field      = $this->framework->getProjectSetting('certify-recertify-mode-field');

        //create the REDCap Alerts class to pass on sendAlert method
        $alerts = new Alerts();

        $rf = RepeatingForms::byEvent($this->getProjectId(), $repeat_event);

        //iterate through these records (with today as grace period expiration and in recertification mode)
        foreach ($records as $record) {
            $record_id = $record['record_id'];

            //send them the notification for "ExpirationLetter"
            $this->sendAlert($record[REDCap::getRecordIdField()], $repeat_event, $trigger_form, "TEMPLATE_ExpirationLetter", $alerts);

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

                    //this is unlikely transition.  This step is mostly likely handled with
                    //change triggered in Admin Review Form

                    $recertify_status_field      = 'recertify_status';

                    /**   RESET TO RECERTIFY */
                    $this->emDebug("EXPIRY CHECK: MODE IS RECERTIFICATION and STATUS was passed!  Proceed to create new recertify instance; next instance id is $next_id");
                    $this->resetInstanceToRecertify($record_id, $repeat_event, $next_id, $last_instance[$recertify_status_field]);
                } else {
                    //STATUS IS FAIL or NOT SET
                    /**   RESET TO CERTIFY - START FROM SCRATCH*/
                    $this->emDebug("EXPIRY CHECK: MODE IS RECERTIFICATION and STATUS was failed!  Proceed to create new certify instance; next instance id is $next_id");
                    $this->resetInstanceToCertify($record_id, $repeat_event, $next_id);
                }

            }

        }

    }

    /**
     *
     * REPLACED BY checkExpirationGracePeriod since no state change till after grace period
     *
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

        //find records where certification ends today
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

        //iterate through these records (with today as expiration and in recertification mode)
        foreach ($records as $record) {

            $record_id = $record['record_id'];

            $last_instance_id = $rf->getLastInstanceId($record_id, $repeat_event);
            $next_id = $rf->getNextInstanceId($record_id, $repeat_event);
            $last_instance = $rf->getInstanceById($record_id, $last_instance_id, $repeat_event);

            //$this->emDebug($record_id, $next_id, $last_instance[$date_certified_field], $last_instance, $date_certified_field); exit;
            $this->resetInstanceToRecertify($record_id, $repeat_event, $next_id, $last_instance[$date_certified_field]);
        }
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
     * @param $current_mode
     */
    function resetInstanceToRecertify($record, $repeat_event, $next_id, $exam_date) {
        //create a new instance with mode = 0 (certification) or mode = 1 (recertification)

        $expiry_date = $this->getOffsetDate($exam_date, $this->getProjectSetting('final-exam-to-expiry-offset'));
        $grace_pd_date = $this->getOffsetDate($expiry_date, 30);


        //depending on whether the current mode is certify or recertiy, the start of certification date will either be
        //certify: exam_date  or recertify: recertify+date


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


    /**
     * This method will be called not in project context so need to make it standalone
     * @param $pid
     * @param $id
     * @param null $target_event
     * @return array|mixed
     * @throws \Exception
     */
    public function findRecordFromSUNet($pid, $id, $target_event = NULL) {
        global $module, $Proj;

        if ($Proj->project_id == $pid) {
            $this->proj = $Proj;
        } else {
            if ($this->proj->project_id != $pid) {
                $this->proj =  new Project($pid);
                $_GET['pid'] = $pid;
            }
        }

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

        //can't use this since not in project context
//        if (REDCap::isLongitudinal()) {
//            $event_filter_str =  "[" . REDCap::getEventNames(true, false, $target_event) . "]";
//        }

        if ($this->proj->longitudinal == true) {
            $first_event_label   = $this->proj->firstEventName;
            $first_event_name = lower(str_replace(' ', '_',$first_event_label)) . "_arm_1";
            $event_filter_str =  "[" . $first_event_name . "]";
        }

        $filter = $event_filter_str . "[" . $target_id_field . "] = '$id'";

        // Use alternative passing of parameters as an associate array
        $params = array(
            'project_id'    => $pid,
            'return_format' => 'json',
            'events'        =>  $target_event,
            'fields'        => array( $this->proj->table_pk, $firstname_field, $lastname_field, $cert_mode, $cert_start, $cert_end),
            'filterLogic'   => $filter
        );

        $q = REDCap::getData($params);
        $records = json_decode($q, true);

        //$module->emDebug($filter, $params, $records, $q);



        //return ($records[0][REDCap::getRecordIdField()]);
        return ($records[0]);

    }

    function getAnnualUrl($record, $event_name, $repeat_instance, $year) {
        //check if the url has been started before
        if ($year == '2') {
            $url_field = $this->getProjectSetting('ann-survey-url-2-field');
            $date_stamp_field = $this->getProjectSetting('ann-survey-timestamp-2-field');
        } else {
            $url_field = $this->getProjectSetting('ann-survey-url-1-field');
            $date_stamp_field = $this->getProjectSetting('ann-survey-timestamp-1-field');
        }

        $params = array(
            'return_format'          => 'json',
            'records'                => array($record),
            'events'                 =>  $event_name,
            'redcap_repeat_instance' => $repeat_instance,     //Adding parameter here does NOT seem to limit the getData to this instance
            'fields'                 => array( REDCap::getRecordIdField(), $url_field)
        );

        $q = REDCap::getData($params);
        $records = json_decode($q, true);

        $target_url = null;

        //Adding redcap_repeat_instance in getData  does NOT seem to limit the getData to this instance
        foreach ($records as $cand) {
            if ($cand['redcap_repeat_instance'] == $repeat_instance) {

                $target_url = $cand[$url_field];
                continue;
            }
        }

        //no url saved so get the url
        if (empty($target_url)) {

            $target_form = $this->getProjectSetting('annual-survey-form');
            $main_event = $this->getProjectSetting('application-event');
            $rf = RepeatingForms::byForm($this->getProjectId(), $target_form, $main_event);

            //get the next instance of the annual survey form
            $next_instance = $rf->getNextInstanceIdForForm($record, $target_form,$main_event);

            $target_url =  $rf->getSurveyUrl($record,$next_instance);
            $this->emDebug("next _instance is $next_instance", $target_url);

//save the url and timestamp in the admin form for the current instance
//alert will add to logging
//get today's date
            $today = new DateTime();
            $today_str = $today->format('Y-m-d H:i:s');

            $save_data = array(
                'record_id'                           => $record,
                'redcap_event_name'                   => $event_name,
                'redcap_repeat_instance'              => $repeat_instance,
                $url_field                            => $target_url,
                $date_stamp_field                     => $today_str
            );

            $status = REDCap::saveData('json', json_encode(array($save_data)));

        }

        return $target_url;
    }

    /*******************************************************************************************************************/
    /* LEARNER PORTAL  METHODS                                                                                                      */
    /***************************************************************************************************************** */


    /**
     * Cribbed from file_upload.php
     *
     * @param $record_id
     * @param $event_id
     * @param $field_name
     * @param $instance_id
     * @param $file
     */
    public function uploadFile($project_id, $record_id, $event_id, $field_name, $instance_id, $file) {
        //$project_id = $this->framework->getProjectId();

        //Uplolad file into edocs folder, return edoc_id, and unlinks tmp file
        $doc_id = Files::uploadFile($file,$project_id);

        $doc_name = trim(strip_tags(str_replace("'", "", html_entity_decode(stripslashes($file['name']), ENT_QUOTES))));
        if ($doc_name == "") {
            $doc_id = 0;
        }

        // Update data table with $doc_id value

        //EXAMPLE: select 1 from redcap_data WHERE record = '16' and project_id = 263 and event_id = '1821' and instance is null limit 1

        $sql_1 = sprintf("select 1 from redcap_data WHERE record = '%s' and project_id = $project_id 
					   and event_id = '%d' and field_name = '%s' and instance ".($instance_id == '1' ? "is null" : "= '%d'")." limit 1",
                         db_escape($record_id),
                         db_escape($event_id),
                         db_escape($field_name),
                         db_escape($instance_id)
        );

        //$this->emDebug("SQL1: ".$sql_1);
        $q = db_query($sql_1);

        $status = false;

        // Record exists. Now see if field has had a previous value. If so, update; if not, insert.
        $fileFieldValueExists = (db_num_rows($q) > 0);
        if ($fileFieldValueExists) {

            //EXAMPLE: UPDATE redcap_data SET value = '1534' WHERE record = '16' AND field_name = 'st_citi_file' AND project_id = 263 and instance is null

            $sql_2 = sprintf("UPDATE redcap_data SET value = '$doc_id' WHERE record = '%s' AND field_name = '%s' 
					  AND project_id = $project_id and instance ".($instance_id == '1' ? "is null" : "= '%d'"),
                             db_escape($record_id),
                             db_escape($field_name),
                             db_escape($event_id),
                             db_escape($instance_id)
            );

            //$this->emDebug("SQL2: ".$sql_2);
            $q2 = db_query($sql_2);
            if ($q2== true) $status = true;

            if (db_affected_rows($q2) == 0) {
                // Insert since update failed
                //EXAMPLE: SQL3: INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance) VALUES (263, 1821, '16', 'st_citi_file', 1534,NULL)

                $sql_3 = sprintf("INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance) 
						  VALUES ($project_id, %d, '%s', '%s', %d,".($instance_id == '1' ? "NULL" : '%d').")",
                                 db_escape($event_id),
                                 db_escape($record_id),
                                 db_escape($field_name),
                                 db_escape($doc_id),
                                 db_escape($instance_id)
                );

                //$this->emDebug("SQL3: ".$sql_3);
                $q3 = db_query($sql_3);
                if ($q3== true) $status = true;
            }

            //return true;
        } else {
            //fieldValue does not exist yet
            $sql_3 = sprintf("INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance) 
						  VALUES ($project_id, %d, '%s', '%s', %d,".($instance_id == '1' ? "NULL" : '%d').")",
                             db_escape($event_id),
                             db_escape($record_id),
                             db_escape($field_name),
                             db_escape($doc_id),
                             db_escape($instance_id)
            );

            //$this->emDebug("SQL3: ".$sql_3);
            $q3 = db_query($sql_3);
            if ($q3 == true) $status = true;
            //return true;  //return error message??
        }

        if ($status) {
            //log to REDCap logging
            REDCap::logEvent(
                "File uploaded from learner portal by CROP EM",  //action
                "$doc_name uplaoded to record $record_id in instance $instance_id",  //changes
                NULL, //sql optional
                $record_id, //record optional
                $event_id, //event optional
                $project_id //project ID optional
            );

            return true;
        }
        //TODO elseif (!$fileFieldValueExists && !$auto_inc_set): record is not saved yet. This should never happen; instance should always be saved first.

    }


    public function getUploadedFileName($edoc_id) {
        //lookup the edoc file name in the redcap_edocs_metadata table
        $q = db_query("select doc_name from redcap_edocs_metadata where doc_id = ".db_escape($edoc_id));
        return db_result($q,0);

    }

    /**
     * Change request: 7/22
     * They want to be able to change the exam dates
     * @param $instance
     */
    public function getExamDates($instance) {
        $instrument = 'seminars_trainings';
        $htm = '';

        $dict = REDCap::getDataDictionary($this->getProjectId(),'array', false, null, $instrument);
        $selections_str = $dict['st_requested_exam']['select_choices_or_calculations'];
        $selections = explode("|", $selections_str);
        foreach ($selections as $str) {
            $val = explode(",", $str);
            $htm .= "<option value='{$val[0]}'> {$val[1]} </option>";
        }
        return $htm;
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
            $field_type = null;


            if (!is_array($field)) {
                $field_label = $dict[$field]['field_label'];
                $field_type = $dict[$field]['field_type'];
                $field_value = $instance[$field];
                $field_id = $field;
            } else {

                $field_label = $dict[$field[0]]['field_label'];
                $field_type = $dict[$field[0]]['field_type'];

                $field_value = $instance[$field[1]];
                $field_id = $field[1];

                if ($field_type === 'dropdown') {

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
                    $field_label = "
                          <select id='{$field[0]}' class='form-control select'>
                                {$field_choices}
                          </select>
                      ";
                }

                //handle free text fields that aren't dates
                //if (($dict[$field]['field_type'] == 'text') && ($dict[$field]['text_validation_type_or_show_slider_number'] !== 'date_ymd')) {
                if (($field_type == 'text') && (strpos($field[0], 'elective_') === 0)) {

                    //$field_elective_label = substr($field[0], 0, -5);
                    $field_elective_value = $instance[$field[0]];
                    $field_label = "<input id='{$field[0]}' type='text' class='form-control elective' value='{$field_elective_value}' placeholder='Please enter {$field_label}'/> ";
                }

            }

            //change request: 7/22: add an file upload
            if ($field == "st_citi_file") {
                if (!empty($field_value)) {
                    //a file has already been uploaded
                    //get the file name
                    $file_name = $this->getUploadedFileName($field_value);
                    $upload_inst = "'$file_name' is already uploaded. You can replace this by doing another upload.";
                } else {
                    $upload_inst = "File not yet uploaded.";
                }

                $htm .= '<tr><td>' . $field_label .
                    "</td>
                  <td>

                    <span class='file_uploaded_status'>{$upload_inst}</span>
                  <div class='form-control upload'>                                      
                  <input type='file'   name='{$field}' id='{$field}' placeholder='{$upload_inst}'>
                  <input type='submit' name='upload_file' id='upload_file' data_field='{$field}' value='Upload File'>
                  </div>
                 
                  </td>
                  </tr>";

            } else {
                $htm .= '<tr><td>'
                    . $field_label .
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
        }

        return $htm;
    }



    public function getRecertificationGenInfo($instance) {
        $htm = '<div class="form-row col-md-5 row">
            <div class="form-group col-md-8"><h6>Have you changed positions since certification?</h6>
            </div>
            <div class="form-group col-md-4">';
        $htm .= '<label class="d-block mb-0"><input name="rf_position_change" id="rf_position_change" type="radio" value="1" ';
        $htm .= $instance['rf_position_change'] == '1' ? 'checked="true" ' : '';
        $htm .= '> Yes</label> ';
        $htm .= '<label><input name="rf_position_change" id="rf_position_change" type="radio" value="0" ';
        $htm .= $instance['rf_position_change'] == '0' ? 'checked="true" ' : '';
        $htm .= '> No</label>
        </div>
        </div>';

        $htm .= '<div class="form-row  col-md-5 row">
            <div class="form-group col-md-9"><h6 class="pl-2">If yes, what was your title/department at initial certification date?</h6>
            </div>
            <div class="form-group col-md-3">
                <input name="rf_title_at_cert" id="rf_title_at_cert" type="text" class="form-control elective" value="'.$instance['rf_title_at_cert'].'" autocomplete="off"/>
            </div>
        </div>';

        return $htm;
    }

    /**
     *
     * Formats the learner's record for display on the learner portal for the recertification stage
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