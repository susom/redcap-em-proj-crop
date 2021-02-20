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


        $exam_event = $this->framework->getProjectSetting('exam-event');
        $last_instance = $repeat_instance;

        //on save of seminar form form, trigger email to admin to verify the training
        //or Reset to new event instance
        if ($instrument == $this->framework->getProjectSetting('training-survey-form')) {
            $this->sendAdminVerifyEmail($record, $exam_event, $last_instance);

            //change request: Also send the learner notification that exam is about to be scheduled
            $this->sendLearnerVerifyEmail($record, $exam_event, $last_instance);
        }

        //on save of recertify form form, trigger email to admin to verify the recertification
        //or Reset to new event instance
        if ($instrument == $this->framework->getProjectSetting('recertification-form')) {
            //if verification checkbox is checked, then send Admin form to verify

            $this->sendAdminVerifyRecertificationEmail($record, $exam_event, $last_instance);

            //TODO: Also send the learner notification that recertification is about to verified
            //$this->sendLearnerVerifyEmail($record, $exam_event, $last_instance);
        }
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
        /* EM HELPER METHODS                                                                                               */
        /***************************************************************************************************************** */

    /**
     * Rediret to this url
     *
     * @param $url
     */
    function redirect($url) {
        header("Location: " . $url);
    }

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
            $this->getProjectSetting('recertify-start-date-field')   => $exam_date,
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

}