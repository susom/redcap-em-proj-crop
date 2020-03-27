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

        //on save of training/certification form, trigger email to admin to verify the training
        if ($instrument == $this->getProjectSetting('training-survey-form')) {
            //$this->sendAdminVerifyEmail($record, $event_id, $repeat_instance);
        }

        //on save of recertification form, trigger email to admin to verify the training
        if ($instrument == $this->getProjectSetting('recertification-form')) {
            //$this->sendAdminVerifyRecertificationEmail($record, $event_id, $repeat_instance);
        }

        //on save of admin_review form, trigger email to admin to verify the training
        if ($instrument == $this->getProjectSetting('admin-review-form')) {
            $this->sendLearnerRetryEmail($record, $event_id, $repeat_instance);
        }

        //On save of exam date, set the dates for expiration and notifications
        //if form is admin_exam_dates_and_status
        if ($instrument == $this->getProjectSetting('exam-date-form')) {

            //test part
            //$this->sendNotification("TemplateSurvey", $record, $event_id, $instrument, $repeat_instance); exit;


            //get the final date of a PASSED exam
            $final_date = $this->getExamDate($record, $event_id,$repeat_instance);
            $this->emDebug("FINAL DATE Is ", $final_date);


            if ($final_date === false) {
                //no need to do anything, don't have passed exam yet.
                $this->emDebug("No update needed.");
                return;
            } else {
                //exam passed, set up the FUP survey s
                //Precreate the followup surveys now (in the main baseline survey event
                //Save the URLs of the survey in two fields in the current repeating event
                //$fup_data = $this->getFUPSurveyURLs($record, $repeat_instance);
            }

            //check if expiry passed?  If expiry still valid, no need update event
            //$final_date < 2 years away
            //return;

            //exam has passed and expiry not blank and not passed date (no need to create a new event)
            // 1. create new event instance
            // 2. update the Dates in the new event

            //get the latest certifying form
            $recertify_form = 'recertification_form';
            $rf = RepeatingForms::byEvent($project_id, $this->getProjectSetting('exam-event'));
            $next_id = $rf->getNextInstanceId($record, $this->getProjectSetting('exam-event'));
            $this->emDebug("next id is $next_id");

            //$rf->saveInstance($record, )
            $mode = 1; //recertifying  (0 = certify)
            $this->updateEndDate($record, $final_date, $event_id, $next_id, 1);

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
     * Send notification based on dates nad the template Alerts
     */
    public function cropNotifyCron() {
        $this->emDebug("Notify Cron started");

        //get all projects that are enabled for this module
        $enabled = ExternalModules::getEnabledProjects($this->PREFIX);

        //get the noAuth api endpoint for Cron job.
        $url = $this->getUrl('src/NotifyCron.php', true, true);

        //while ($proj = db_fetch_assoc($enabled)) {
        while($proj = $enabled->fetch_assoc()){

            $pid = $proj['project_id'];
            $this->emDebug("STARTING CROP NOTIFY CRON for pid " . $pid . ' notify url is '.$url);

            $this_url = $url . '&pid=' . $pid;

            //fire off the reset process
            $resp = http_get($this_url);

        }
    }

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
     * Check all the records to see if it is time for one of the notification alerts
     */
    public function checkNotification() {
        $alerts = new Alerts();



        //iterate through all the records and check that notifications are not due today.

        //Alert #3: Seminars not complete - send review email
        // -- Admin reviewed seminars and finds it needs corrections
        // -- Admin checks the checkbox 'resend_portal_review_1'
        $this->sendSeminarIncompleteEmail($alerts); //this passes by reference, i think?

        //
    }

    public function sendAdminVerifyEmail($record, $event_id, $repeat_instance) {
        $alert_title = "CheckRecertification";
        $ready_for_exam_field = 'ready_for_exam';
        $ts_ready_exam_notify_field = "ts_ready_exam_notify";
        $repeating_event_name = REDCap::getEventNames(true, false,$this->getProjectSetting('exam-event'));

        //check that the ready_for_exam field is checked
        $params = array(
            'return_format' => 'json',
            'records'       => array($record),
            'events'        =>  $event_id,
            'redcap_repeat_instance' => $repeat_instance,
            'fields'        => array( REDCap::getRecordIdField(), $ready_for_exam_field, $ts_ready_exam_notify_field)
            //'filterLogic'   => $filter    //TODO ask filter does not work with repeating events
        );

        $q = REDCap::getData($params);
        $records = json_decode($q, true);
        $target_data = $records[0];

        $this->emDebug($target_data);

        $today = new DateTime();
        $today_str = $today->format('Y-m-d');

        //if checkbox is checked then send email to admin
        //if ($target_data[$ready_for_exam_field . "___1"] == '1'  &&  ($target_data[$ts_ready_exam_notify_field] <> $today_str)) {
        if (($target_data[$ts_ready_exam_notify_field] <> $today_str)) {
            $alerts = new Alerts();
            //send the notification
            $this->emDebug("Sending the Notification to Admin that learner is ready for exam for record $record instance is $repeat_instance");
            $this->sendAlert( $record, $repeat_instance, $this->getProjectSetting('training-survey-form'), $alert_title, $alerts);

/*
            [record_id] => 4
            [redcap_event_name] => exam_arm_1
            [redcap_repeat_instrument] =>
            [redcap_repeat_instance] => 1
            [ready_for_exam___1] => 1
*/
            $save_data = array(
                'record_id'                               => $record,
                'redcap_event_name'                       => $repeating_event_name,
                'redcap_repeat_instance'                  => $repeat_instance,
                $ready_for_exam_field . "___1" => 0, //unset the checkbox
                $ts_ready_exam_notify_field             => $today_str, //set timestamp to today
                $last_alert_template_sent_field           => $alert_title //update the alertsent field
            );

            $status = REDCap::saveData('json', json_encode(array($save_data)));
            $this->emDebug("Saving this data and status", $save_data, $status);

            return;
        }
    }

    public function sendAdminVerifyRecertificationEmail($record, $event_id, $repeat_instance) {
        $alert_title = "CheckRecertification";

        //triggering conditions
        $rf_ready_for_verification_field = "rf_ready_for_verification";  //checkbox from recertification form
        $rf_recertification_form_field   = "recertification-form";       //form for recertification
        $repeating_event_name = REDCap::getEventNames(true, false,$this->getProjectSetting('exam-event'));

        //status fields to update
        $rf_ts_recertify_notify_field    = "rf_ts_recertify_notify";     //timestamp on email sent
        $last_alert_template_sent_field  = "last_alert_template_sent";  // field to hold last email template sent

        //check that the ready_for_exam field is checked
        $params = array(
            'return_format' => 'json',
            'records'       => array($record),
            'events'        =>  $event_id,
            'redcap_repeat_instance' => $repeat_instance,
            'fields'        => array( REDCap::getRecordIdField(), $rf_ready_for_verification_field, $rf_ts_recertify_notify_field)
            //'filterLogic'   => $filter    //TODO ask filter does not work with repeating events
        );

        $q = REDCap::getData($params);
        $records = json_decode($q, true);
        $target_data = $records[0];

        $this->emDebug($target_data);

        $today = new DateTime();
        $today_str = $today->format('Y-m-d');

        //if checkbox is checked then send email to admin
//        if ($target_data[$rf_ready_for_verification_field."___1"] == '1'  &&  ($target_data[$rf_ts_recertify_notify_field] <> $today_str)) {
        if (($target_data[$rf_ts_recertify_notify_field] <> $today_str)) {
            $alerts = new Alerts();
                //send the notification
                $this->emDebug("Sending the Notification to Admin that learner is ready for recertification for record $record instance is $repeat_instance");
                $this->sendAlert( $record, $repeat_instance, $rf_recertification_form_field, $alert_title, $alerts);

                /*
                            [record_id] => 4
                            [redcap_event_name] => exam_arm_1
                            [redcap_repeat_instrument] =>
                            [redcap_repeat_instance] => 1
                            [ready_for_exam___1] => 1
                */

                $save_data = array(
                    'record_id'                               => $record,
                    'redcap_event_name'                       => $repeating_event_name,
                    'redcap_repeat_instance'                  => $repeat_instance,
                    $rf_ready_for_verification_field . "___1" => 0, //unset the checkbox
                    $rf_ts_recertify_notify_field             => $today_str, //set timestamp to today
                    $last_alert_template_sent_field           => $alert_title //update the alertsent field
                );


                $status = REDCap::saveData('json', json_encode(array($save_data)));
                $this->emDebug("Saving this data and status", $save_data, $status);

                if ($status) {
                    return true;
                } else {
                    return false;
                }
        }
    }


    public function sendLearnerRetryEmail($record, $event_id, $repeat_instance) {
        $this->emDebug("sending learner to RETRY");

        //fields for recertification mode
        $mode_field = "mode";    //see which mode

        //fields to reverify test
        $last_alert_template_sent_field = "last_alert_template_sent";
        $resend_portal_review_field = "resend_portal_review_1";
        $resend_date_stamp_field = "resend_date_stamp";
        $ready_for_exam_field = "ready_for_exam";
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
                $ready_for_exam_field . "___1"  => 0, //unset the checkbox
                $needs_review_field             => '', //unset the checkbox
                $resend_date_stamp_field         => $today_str,
                $last_alert_template_sent_field  => 'SeminarIncomplete' //set timestamp to today
            );


            $status = REDCap::saveData('json', json_encode(array($save_data)));
            $this->emDebug("Saving this data",$save_data,  $status);
        }
    }

    /**
     * Admin checks that the seminar/training are verified
     *
     * @param $alerts
     * @throws \Exception
     */
    public function sendSeminarIncompleteEmail($alerts) {
        $resend_portal_review_field = "resend_portal_review_1";
        $resend_date_stamp_field = "resend_date_stamp";
        $ready_for_exam_field = "ready_for_exam";
        $needs_review_field = "needs_review_1";

        $repeating_event = $this->getProjectSetting('exam-event');

        //iterate through records with filter [exam_arm_1][resend_portal_review_1(1)]='1' is checked
        //check that the date in timestamp field  is not today
        //Send notification
        //Uncheck the checkbox: resend_portal_review_1
        //Clear out contents of resend_review_notes_1
        //Set timestamp to today

        //iterate through records with filter [exam_arm_1][resend_portal_review_1(1)]='1' is checked

        $event_filter_str =  "[" . REDCap::getEventNames(true, false, $repeating_event) . "]";

        //filter resend field is 1
        $filter = $event_filter_str . "[" . $resend_portal_review_field . "___1] = 1";

        //add filter to make sure that the date is not today
        $today = new DateTime();
        $today_str = $today->format('Y-m-d');
       // $filter .= " AND " . $event_filter_str . "[" . $resend_date_stamp_field . "] <> '$today_str'";

        $params = array(
            'return_format' => 'json',
            'events'        =>  $repeating_event,
            'fields'        => array( REDCap::getRecordIdField(), $resend_portal_review_field, $resend_date_stamp_field),
            //'filterLogic'   => $filter    //TODO ask filter does not work with repeating events/**/
        );


        $q = REDCap::getData($params);
        $records = json_decode($q, true);

        //$this->emDebug($filter, $params, $records, $repeating_forms); exit;

        //cannot get the filter to work with repeating events
        //so just iterate over the records and if the filter fits send notification
        foreach ($records as $k) {
            $record_id = $k['record_id'];
            //$this->emDebug("checking ",$k, $k[$resend_portal_review_field . "___1"]);

            if (($k[$resend_portal_review_field . "___1"] == '1') &&  ($k[$resend_date_stamp_field] <> $today_str)) {
                $this->emDebug("NEED TO SEND ONE:  ",$k, $k[$resend_portal_review_field . "___1"]);

                //send the notification
                $this->emDebug("Sending the notifictaion");
                $this->sendAlert( $record_id, $repeating_event, 'admin_review', "SeminarIncomplete", $alerts);

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

                //update the array to reset the form in the repeating event
                unset($k['redcap_repeat_instrument']);  // unsetting because its a repeat event not repeating form
                $k[$needs_review_field] = '';                  //unset needs review field
                $k[$resend_portal_review_field . "___1"] = 0;  //unset the checkbox
                $k[$ready_for_exam_field . "___1"] = 0;        //unset the checkbox
                $k[$resend_date_stamp_field] = $today_str;     //set timestamp to today
                $this->emDebug("Saving this data", $k);
                $status = REDCap::saveData('json', json_encode(array($k)));

            }


        }

    }

    /**
     * Check all the records to see if new exam_events instance need to be created.
     */
    public function checkExpiration() {
        //iterate through all the records and check that the expiration is not today
        $cert_status     = "cert_status";
        $cert_start      = "cert_start";
        $cert_end        = "cert_end";

        $event_filter_str =  "[" . REDCap::getEventNames(true, false, $this->getProjectSetting('application-event')) . "]";
        $main_event_expiry_field = 'cert_end';
        $main_event_certify_field = 'cert_status';
        $recertify_mode           = '1';

        $today = new DateTime();
        $today_str = $today->format('Y-m-d');

        $filter = $event_filter_str . "[" . $main_event_expiry_field . "] = '$today_str'";

        //add the filter to check that the cert_status is in recertification
        $filter .= " AND ". $event_filter_str . "[" . $main_event_certify_field . "] = '$recertify_mode'";

        $params = array(
            'return_format' => 'json',
            'events'        =>  $event_filter_str,
            'fields'        => array( REDCap::getRecordIdField(), $cert_status, $cert_start, $cert_end),
            'filterLogic'   => $filter
        );

        $q = REDCap::getData($params);
        $records = json_decode($q, true);

        $this->emDebug($filter, $params, $records, $q);

        //iterate through these records (with today as expiration and in recertification mode)
        foreach ($records as $record) {
            //if these
            $this->sendNotification("TemplateSurvey", $record);
            $this->resetInstance($record[REDCap::getRecordIdField()], $this->getProjectSetting('exam-event'));
        }



    }


    /**
     * Looks through all teh Alerts in projects and search for match
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
                $this->emDebug("Foudn alret title $alert_title sending notification");
                $id = $k['alert_id'];
                $foo = $alert->sendNotification($k['alert_id'],$project_id, $record, $event_id, $instrument);
                $this->emDebug("SENT: ", $foo);
                continue;
            }
        }

        /**

        $id = $alert->getKeyIdFromAlertId($project_id, $alert_id);
        $send_alert = $alerts[$alert_id];
        //$this->emDebug($send_alert); exit;

        $foo = $alert->sendNotification($send_alert['alert_id'],$project_id, $record, $event_id, $instrument);
        $this->emDebug("SENT: ", $foo);
            return;
*/
        return;
    }

    public function sendNotification($alert_type, $record, $event_id, $instrument, $instance,$modify = false, $new_link = null ) {
        $alert_type = 'TemplateSurvey';
        $modify = true;

        $project_id = $this->getProjectId();

        $alert = new Alerts();
        $alerts = $alert->getAlertSettings($project_id);

        $alert_id = 20;
        $id = $alert->getKeyIdFromAlertId($project_id, $alert_id);
        $this_alert = $alerts[$alert_id];


        if ($modify === true) {
            $alert->sendNotification($this_alert['alert_id'],$project_id, $record, $event_id, $instrument);
            return;
        }

        //this is copy of sendNotification
        $email_subject = $alert->getAlertSetting("email-subject", $project_id)[$id];
        $alert_message = $alert->getAlertSetting("alert-message", $project_id)[$id];
        $alert_type = $alert->getAlertSetting("alert-type", $project_id)[$id];
        $prevent_piping_identifiers = $alert->getAlertSetting("prevent-piping-identifiers", $project_id)[$id];


        $this->emDebug();
        $this->emDebug($id, $this_alert['alert_title'], $alert_message);

        // Set project and get data (if needed)
        $Proj = new \Project($project_id);
        $repeat_instrument = $Proj->isRepeatingForm($event_id, $instrument) ? $instrument : "";
        $isLongitudinal = $Proj->longitudinal;
        if (empty($data)) {
            $data = \Records::getData($project_id, 'array', $record);
        }
        $alertSentSuccesfully = false; // default
        $alertInstrument = $alert->getAlertSetting("form-name", $project_id)[$id];
        $alertEventId = $alert->getAlertSetting("form-name-event", $project_id)[$id];
        if (($alertInstrument == '' || $alertEventId == '') && is_numeric($event_id)) $alertEventId = $event_id;
        if ($alertEventId == '') $alertEventId = $Proj->firstEventId;
        $alertInstance = ($alertInstrument == '') ? 1 : $instance;

        //replace our key with the new link


        // Piping
        $alert_message = \Piping::replaceVariablesInLabel($alert_message, $record, $event_id, $instance, $data,false,
                                                         $project_id, false, $repeat_instrument, 1, false, false, $instrument, null, false, $prevent_piping_identifiers);
        $email_subject = \Piping::replaceVariablesInLabel($email_subject, $record, $event_id, $instance, $data,false,
                                                         $project_id, false, $repeat_instrument, 1, false, false, $instrument, null, false, $prevent_piping_identifiers);




    }

    public function setupMessage() {
// Initialize values (even if we aren't sending via EMAIL)
        $mail = new \Message();
        // Email Addresses
        $mail = $this->setEmailAddresses($mail, $project_id, $record, $event_id, $instrument, $instance, $id, $data);

        // Email From: Get the Reply-To and Display Name for this message
        $fromDisplayName = trim($this->getAlertSetting("email-from-display", $project_id)[$id]);
        $email_from = trim($this->getAlertSetting("email-from", $project_id)[$id]);
        if (!empty($email_from)) {
            if (!isEmail($email_from)) {
                $email_from = Piping::replaceVariablesInLabel($email_from, $record, $event_id, $instance, $data,false,
                                                              $project_id, false, $repeat_instrument, 1, false, false, $instrument);
            }
            if (isEmail($email_from)) {
                // Set From and From Name
                $mail->setFrom($email_from);
                $mail->setFromName($fromDisplayName);
            } else {
                $this->sendFailedEmailRecipient($this->getAlertSetting('email-failed', $project_id), $lang['alerts_55'], $lang['alerts_57']." ($email_from in Project: $project_id, Record: $record, Alert #{$id})");
            }
        } else {
            $this->sendFailedEmailRecipient($this->getAlertSetting('email-failed', $project_id), $lang['alerts_56'], $lang['alerts_58']." ($email_from in Project: $project_id, Record: $record, Alert #{$id})");
        }

        // Body and subject
        $mail->setBody($alert_message);
        $mail->setSubject($email_subject);
        // Embedded images
        $mail = $this->setEmbeddedImages($mail, $project_id);
        // Attachments
        $mail = $this->setAttachments($mail, $project_id, $id);
        // Attchment from field variable
        $mail = $this->setAttachmentsREDCapVar($mail, $project_id, $data, $record, $event_id, $instrument, $instance, $id, $isLongitudinal);
    }

    public function sendModifiedNotification($project_id, $id) {




    }

    public function resetInstance($record, $event) {



        $exam_event = $this->getProjectSetting('exam-event');
        $repeating_instrument = 'recertification_form';
        $recert_status = 'recertify_status';

        //get the latest seminar form
        $rf = new RepeatingForms($this->getProjectId(), $repeating_seminar_instrument);
        $rf->loadData($record, $exam_event, null);

        //get the last instance


        $last_instance_id = $rf->getLastInstanceId($record,$exam_event);
        $this->emDebug("record is $record last instance id is $last_instance_id / project id ".$this->getProjectId() . " event is $exam_event");
        $last_instance = $rf->getInstanceById($record, $last_instance_id, $exam_event);
        $this->emDebug("last instanced is $last_instance_id and $recert_status");

        //check that the instance is approved for certification
        if ($last_instance[$recert_status]  === '1') {
            $next_id = $rf->getNextInstanceId($record,$exam_event);
            $this->emDebug("next id is $next_id");

            //create new instance with today being the start date and all the offsets
            $mode = 1; //recertifying  (0 = certify)
            $today = new DateTime();
            $this->updateEndDate($record, $today->format('Y-m-d'), $exam_event, $next_id, 1);      //this wil
        }

    }



    /*******************************************************************************************************************/
    /* EM METHODS                                                                                                      */
    /***************************************************************************************************************** */

    /**
     * Get the Exam Date of a PASSED exam date.
     * If exam failed, then return FALSE
     *
     * @param $record
     * @param $event
     * @return array|bool
     */
    function getExamDate($record, $event,$repeat_instance) {

        $final_exam_date_field          = $this->getProjectSetting('final-exam-date-field');
        $date_exam_1_field              = $this->getProjectSetting('date-exam-1-field');
        $exam_status_1_field            = $this->getProjectSetting('exam-status-1-field');
        $date_exam_2_field              = $this->getProjectSetting('date-exam-2-field');
        $exam_status_2_field            = $this->getProjectSetting('exam-status-2-field');
        $date_exam_3_field              = $this->getProjectSetting('date-exam-3-field');
        $exam_status_3_field            = $this->getProjectSetting('exam-status-3-field');

        $params = array(
            'return_format'       => 'json',
            'records'             => $record,
            'fields'              => array($final_exam_date_field,$date_exam_1_field, $exam_status_1_field,$date_exam_2_field,$exam_status_2_field,
                $date_exam_3_field,$exam_status_3_field),
            'events'              => $event,
//                'redcap_repeat_instrument' => $instrument,       //this doesn't restrict
            'redcap_repeat_instance'   => $repeat_instance   //this doesn't seem to do anything!
        );

        $q = REDCap::getData($params);
        $results = json_decode($q, true);

        //$this->emDebug($params,$results, count(array_filter($results[0])));

        //nothing set, do nothing,  we are done return false
        if (count(array_filter($results[0])) < 1) {
            return false;
        }

        //if final_exam_date_field is populated, do nothing, return false
        if (!empty($results[0][$final_exam_date_field])) {
            $this->emDebug("Final exam date already set. Do nothing",$results[0][$final_exam_date_field]);
            return false;
        }

        //$this->emDebug($results[0][$exam_status_1_field],$results[0][$exam_status_2_field],$results[0][$exam_status_3_field]);
        //$this->emDebug($results[0][$date_exam_1_field],$results[0][$date_exam_2_field],$results[0][$date_exam_3_field]);

        $final_exam_date = '';
        if (($results[0][$exam_status_1_field]!=='1') && ($results[0][$exam_status_2_field]!=='1') && ($results[0][$exam_status_3_field]!=='1')) {
            $this->emDebug("Exam not passed. Do nothing");
            return false;
        } else {
            for($i=3; $i>0; $i--) {
                if ($results[0][${"exam_status_".$i."_field"}]=='1') {
                    $final_exam_date = $results[0][${"date_exam_".$i."_field"}];
                    break;
                }
            }

            $this->emDebug("FINAL Exam date is $final_exam_date");

        }

        return $final_exam_date;

    }

    /**
     * Pre-create the fup survey urls and store them in fields to be sent out in the emails
     * If the fields already exist, it will not recreate them.
     *
     * @param $record
     * @param $event
     * @param $repeat_instance
     */
    function getFUPSurveyURLs($record, $repeat_instance) {
        //check that the URLs aren't already populated
        $survey_one_field = $this->getProjectSetting('fup-survey-url-1-field');
        $survey_two_field = $this->getProjectSetting('fup-survey-url-2-field');
        $survey_fields = array($this->getProjectSetting('fup-survey-url-1-field'),$this->getProjectSetting('fup-survey-url-2-field'));

        $event = $this->getProjectSetting('application-event');

        $params  = array(
            'return_format' => 'json',
            'records'        => $record,
            'events'        =>  $event,
            'fields'        => array( $survey_one_field, $survey_two_field)
        );

        $q = REDCap::getData($params);
        $records = json_decode($q, true);
        //$this->emDebug("Record is $record", $params,$records, array_keys($records), $records[0], $records[0]['fup_survey_1_url']);

        foreach ($survey_fields as $survey_field) {
            $this->emDebug($survey_field, $records[$survey_field], empty($records[0][$survey_field]),isset($records[0][$survey_field]));

            if (empty($records[0][$survey_field])) {
                //$rf = new RepeatingForms($this->getProjectId(), $this->getProjectSetting('fup-survey-form'));
                $rf = RepeatingForms::byForm($this->getProjectId(), $this->getProjectSetting('fup-survey-form'));

                //pre create the survey
                $next_instance = $rf->getNextInstanceId($record, $this->getProjectSetting('application-event'));

                if ($rf->last_error_message) {
                    $this->emError("Record $record: There was an error: ", $rf->last_error_message);
                    return false;
                }

                $url =  $rf->getSurveyUrl($record,$next_instance);
                $this->emDebug("next _instance is $next_instance", $url);

                // Get the survey url for that instance
                $data[$this->getProjectSetting('fup-survey-url-1-field')] = $rf->getSurveyUrl($record,$next_instance);
            }
        }

        exit;
        //$rf = new RepeatingForms($this->getProjectId(), $this->getProjectSetting('fup-survey-form'));
        $rf = RepeatingForms::byForm($this->getProjectId(), $this->getProjectSetting('fup-survey-form'));
        if (!isset($rf)) {
            $this->emDebug("Is this project setting set for fup-survey-form?");
            return false;
        }

        //if $survey_one_field is empty, set it
        if (empty($records[0][$survey_one_field])) {
            //pre create the survey
            $next_instance = $rf->getNextInstanceIdForceReload($record, $this->getProjectSetting('application-event'));


            $this->emDebug($rf->last_error_message, $record, $next_instance);

            // Get the survey url for that instance
            $data[$this->getProjectSetting('fup-survey-url-1-field')] = $rf->getSurveyUrl($record,$next_instance);
        }

        //if $survey_two_field is empty, set it a year from exam date
        if (empty($records[0][$survey_two_field])) {
            //pre create the survey
            $next_instance = $rf->getNextInstanceIdForceReload($record, $this->getProjectSetting('application-event'));
            $this->emDebug($rf->last_error_message, $record, $next_instance);

            // Get the survey url for that instance
            $data[$this->getProjectSetting('fup-survey-url-2-field')] = $rf->getSurveyUrl($record,$next_instance);
        }

        $this->emDebug($data);
exit;
        return($data);

    }

    /**
     * Given a start_date and and offset, set the end_date into the target_field
     *
     * @param $record
     * @param $start_date
     * @param $offset
     * @param $target_field
     * @param $target_event
     */
    function updateEndDate($record, $exam_date, $target_event, $repeat_instance, $mode = 0) {

        $this->emDebug("Updating end date for $record / exama-date:  $exam_date / target_dtae:  $target_event" );

        if (!empty($exam_date)) {

            //save the date
            $data = array(
                REDCap::getRecordIdField()                             => $record,
                'redcap_event_name'                                    => REDCap::getEventNames(true, false,$target_event),
                'redcap_repeat_instance'                               => $repeat_instance,
                $this->getProjectSetting('final-exam-date-field') => $exam_date
            );

            $expiry_date = $this->getOffsetDate($exam_date,$this->getProjectSetting('final-exam-to-expiry-offset'));
            $data[$this->getProjectSetting('expiry-date-field')] =$expiry_date;
            $data[$this->getProjectSetting('fup-survey-6-mo-field')] = $this->getOffsetDate($exam_date,180);
            $data[$this->getProjectSetting('fup-survey-1-yr-field')] = $this->getOffsetDate($exam_date,365);

            $data[$this->getProjectSetting('rem-expiry-6-mo-field')] = $this->getOffsetDate($expiry_date,-180);
            $data[$this->getProjectSetting('rem-expiry-1-mo-field')] = $this->getOffsetDate($expiry_date,-30);
            $data[$this->getProjectSetting('grace-pd-30-day-field')] = $this->getOffsetDate($expiry_date,30);
            $data[$this->getProjectSetting('certify-recertify-mode-field')] = $mode;

            REDCap::saveData($data);
            $response = REDCap::saveData('json', json_encode(array($data)));

            if ($response['errors']) {
                $msg = "Error while trying to save dates to repeating event.";
                $this->emError($response['errors'], $data, $msg);
            } else {
                $this->emDebug("Successfully saved date data.");
            }

            //also save to the admin event to reflect the latest status
            $admin_data = array(
                REDCap::getRecordIdField()                             => $record,
                'redcap_event_name'                                    => REDCap::getEventNames(true, false,$this->getProjectSetting('application-event')),
                'cert_status' => $mode,
                'cert_start'  => $exam_date,
                'cert_end'  => $expiry_date
            );

            REDCap::saveData($admin_data);
            $response = REDCap::saveData('json', json_encode(array($admin_data)));

            if ($response['errors']) {
                $msg = "Error while trying to save dates to admin event.";
                $this->emError($response['errors'], $admin_data, $msg);
            } else {
                $this->emDebug("Successfully saved admin date data.");
            }
        }
    }

    function getOffsetDate($start_date, $offset) {
        $this->emDebug("Start date is $start_date with $offset");
        $end_date = new DateTime($start_date);
        $di = new DateInterval('P'.abs($offset).'D');

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
        $cert_status     = "cert_status";
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
            'fields'        => array( REDCap::getRecordIdField(), $firstname_field, $lastname_field, $cert_status, $cert_start, $cert_end),
            'filterLogic'   => $filter
        );

        $q = REDCap::getData($params);
        $records = json_decode($q, true);

        //$module->emDebug($filter, $params, $records, $q);

        //return ($records[0][REDCap::getRecordIdField()]);
        return ($records[0]);

    }

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



    public function scheduleExam($record) {
        //todo use config property
        $schedule_field  = 'ready_for_exam';

        $data[$schedule_field.'___1'] = '1';

        return $data;


    }

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