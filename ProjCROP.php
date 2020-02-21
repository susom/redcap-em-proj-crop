<?php

namespace Stanford\ProjCROP;

use ExternalModules\ExternalModules;
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
    "st_budgeting",
    "st_date_budgeting",
    "st_date_billing",
    "st_date_regulatory",
    "st_date_startup",
    "st_date_roles",
    "st_date_rsrch_phase",
    "st_oncore",
    "st_date_oncore",
    "elective_1_date",
    "elective_2_date",
    "elective_3_date",
    "elective_4_date"
    );

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

    public function findRecordFromSUNet($id) {
        global $module;

        //should this be parametrized?
        $target_id_field = "webauth_user";
        $target_event = null;
        $firstname_field = "first_name";
        $lastname_field = "last_name";
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
        //    'events'        =>  $target_event,
            'fields'        => array( REDCap::getRecordIdField(), $firstname_field, $lastname_field),
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
            $field_label = $dict[$field]['field_label'];
            $field_value = $instance[$field];

            if ($dict[$field]['field_type'] === 'dropdown') {
                $field_choices = "<option value='' selected disabled>{$field_label}</option>";
                foreach (explode("|",$dict[$field]['select_choices_or_calculations']) as $choice) {
                    $choice_parts = explode(",", $choice);
                    $field_choices .= "<option value='{$choice_parts[0]}'>{$choice_parts[1]}</option>";
                }

                $field_choices .= "</select></div>";
                $field_label = "<div class='form-group col-md-8'>
                          <select id='{$field}' class='form-control'>
                                {$field_choices}
                          </select>
                      </div>";
            }

            //handle free text fields that aren't dates
            //if (($dict[$field]['field_type'] == 'text') && ($dict[$field]['text_validation_type_or_show_slider_number'] !== 'date_ymd')) {
            if (($dict[$field]['field_type'] == 'text') && (strpos( $field, 'elective_' ) === 0)) {
                $field_elective_label = substr($field, 0, -5);
                $field_elective_value = $instance[$field_elective_label];
                $field_label = "<input id='{$field_elective_label}' type='text' class='form-control dt' value='{$field_elective_value}' placeholder='Please enter {$field_label}'/> ";
            }

             $htm .= '<tr><td>'
            .$field_label.
            "</td>
                  <td>
                      <div class='input-group date'  >
                          <input id='{$field}' type='text' class='form-control dt' value='{$field_value}' placeholder='yyyy-mm-dd'/>
                          <span class='input-group-addon'>
                               <span class='glyphicon glyphicon-calendar'></span>
                           </span>
                      </div>
                  </td>
              </tr>";
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
            $codedArray[$field_name] = db_escape($field_value);
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