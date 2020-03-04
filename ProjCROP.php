<?php

namespace Stanford\ProjCROP;

use DateInterval;
use DateTime;
use REDCap;

require_once 'emLoggerTrait.php';

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

    public function redcap_save_record($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash = NULL, $response_id = NULL, $repeat_instance) {
        //On save of exam date, set the dates for expiration and notifications
        //if form is admin_exam_dates_and_status
        if ($instrument == $this->getProjectSetting('exam-date-form')) {
            //getData for populated fields
            $final_date = $this->getExamData($record, $event_id,$repeat_instance);

            if ($final_date === false) {
                $this->emDebug("No update needed.");
                return;
            }

            $this->updateEndDate($record, $final_date, $event_id, $repeat_instance);

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

    public function cropNotifyCron() {
        $this->emDebug("Notify Cron started");

    }

    /******************************************/


    /**
     * Retrieve the fields entered in the admin_expiry_and followup page to be copied over to the expiry form
     *
     * @param $record
     * @param $event
     * @return array|bool
     */
    function getExamData($record, $event,$repeat_instance) {

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
     * Given a start_date and and offset, set the end_date into the target_field
     *
     * @param $record
     * @param $start_date
     * @param $offset
     * @param $target_field
     * @param $target_event
     */
    function updateEndDate($record, $exam_date, $target_event, $repeat_instance) {

        $this->emDebug("Updating end date for $record ", $exam_date);

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

            REDCap::saveData($data);
            $response = REDCap::saveData('json', json_encode(array($data)));

            if ($response['errors']) {
                $msg = "Error while trying to save dates.";
                $this->emError($response['errors'], $data, $msg);
            } else {
                $this->emDebug("Successfully saved date data.");
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

        $module->emDebug($filter, $params, $records, $q);

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