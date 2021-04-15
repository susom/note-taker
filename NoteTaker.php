<?php

namespace Stanford\NoteTaker;

require_once("emLoggerTrait.php");

use \REDCap;

class NoteTaker extends \ExternalModules\AbstractExternalModule
{

    use emLoggerTrait;

    const LF = "\r\n";
    const DELIMITER = self::LF . "------------------------------------------------------------" . self::LF;

    /** Prepends an entry into notebox specified by user upon record save
     * @param      $project_id
     * @param null $record
     * @param      $instrument
     * @param      $event_id
     * @param null $group_id
     * @param      $survey_hash
     * @param      $response_id
     * @param int  $repeat_instance
     * @return bool
     */
    public function redcap_save_record($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash, $response_id, $repeat_instance = 1)
    {
        $instances = $this->getSubSettings('instance'); // Take the current instrument and get all the fields.  Then check if the 'input field' is present in the instrument fields and is not empty.  If so, then add a log entry...

        global $Proj;
        $RepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();
        $event_name = REDCap::getEventNames(true,true,$event_id);

        // Loop over all instances
        foreach ($instances as $i => $instance) {
            // Get fields from current instance
            $i_event_id          = $instance['event-id'];
            $i_date_field        = $instance['date-field'];
            $i_note_field        = $instance['note-field'];
            $i_input_fields      = $instance['input-field'];
            $i_include_delimiter = $instance['include-delimiter'];

            // Only process this config if the event just saved matches the instance event id
            if ($i_event_id !== $event_id) continue;

            // If the input_fields aren't all on the form, then continue
            $instrument_fields = REDCap::getFieldNames($instrument); // Load the fields on the instrument just saved
            $overlap = array_intersect($i_input_fields, $instrument_fields);
            if (empty($overlap)) continue;

            // Do not permit repeating forms
            if($RepeatingFormsEvents) {
                if (!empty($Proj->RepeatingFormsEvents[$event_id][$instrument])) {
                    REDCap::logEvent("NOTETAKER EM ERROR", "$instrument in $event_name cannot be used with Notetaker because it is repeating", "", $record, $event_id, $project_id);
                    return "";
                }
                if (isset($Proj->RepeatingFormsEvents[$event_id]) && $RepeatingFormsEvents[$event_id] == "WHOLE") {
                    REDCap::logEvent("NOTETAKER EM ERROR", "$event_name cannot be used with NoteTaker because it is repeating", "", $record, $event_id, $project_id);
                    return "";
                }
            }

            // Pull data to check if the input-field had an entry
            $fields = array_merge($i_input_fields, array($i_date_field, $i_note_field, REDCap::getRecordIdField()));
            $data_json = REDCap::getData('json', $record, $fields, $event_id);
            $data = json_decode($data_json, true)[0];
            $data_labels = array();

            //check if any inputs are radio/dropdown, if so replace with labels
            foreach($i_input_fields as $fieldname){
                $check = parseEnum($Proj->metadata[$fieldname]["element_enum"]);
                if(!empty($check)){ //array of all enum options
                    if($Proj->metadata[$fieldname]["element_type"] == 'checkbox'){ //Checkbox has special alternative
                        foreach($check as $key => $val){
                            if(!empty($data[$fieldname."___".$key])){ //if checkbox build each key as val
                                $data[$fieldname."___".$key] = "0";
                                $data_labels[$fieldname."___".$key] = $val; //Create field representation
                            }
                        }
                    } else { //if radio button or dropdown select
                        if(!empty($data[$fieldname])){
                            $data_labels[$fieldname] =  $check[$data[$fieldname]];
                            $data[$fieldname] = "";
                        }
                    }
                }
                //input field or note field
                if(!empty($data[$fieldname])){
                    $data_labels[$fieldname] = $data[$fieldname];
                    $data[$fieldname] = "";
                }

            }

            if(!$this->checkValidity($data_labels)) continue;

            // There is a new note entry
            $this->emDebug("Updating Note: {$data[$i_note_field]}");

            // Set date field to current time
            $new_date_format = $this->getNewDateFormat($i_date_field);
            $new_date = empty($new_date_format) ? "" : Date($new_date_format);
            $new_note = $this->appendNote($data_labels, $data[$i_note_field], $i_input_fields, $new_date, USERID, $i_include_delimiter);

            //Set new data object to update record
            $data[$i_note_field] = $new_note;
            $data[$i_date_field] = $new_date;

            // Save
            $output_json = json_encode(array($data));
            $this->emDebug($data, $output_json);
            $result      = REDCap::saveData('json', $output_json, 'overwrite');
            if (!empty($result['errors'])) $this->emError("Errors saving result: ", $data_json, $output_json, $result);
            if (!empty($result['errors'])) REDCap::logEvent("NOTETAKER EM ERROR", "Errors saving result, this is likely because a field-type specified is not supported", "", $data_json, $output_json, $result);

        }

    }

    /** Function that determines if record save is valid. Returns false only when all input fields are empty
     * @param $data_labels {Array} field names & data of getData
     * @return bool
     */
    public function checkValidity($data_labels)
    {
        if(!isset($data_labels)){
            $this->emError("Error in passing arguments into checkValidity");
            return false;
        }

        if(empty($data_labels))
            return false;
        else
            return true;

    }

    /** Returns the new date format as a String based on set validation type specified in designer
     * This format will be used for all dates in the prepended header
     * Example Call -
     *  $i_date_field = "testDateField"
     *  $date_format = "datetime_seconds"
     *  $tokenized = [datetime, seconds]
     *  returns "Y-m-d H:i:s"
     *
     * @param $i_date_field
     * @return String : representation of new date format
     */
    private function getNewDateFormat($i_date_field)
    {
        global $Proj;
        $date_format = $Proj->metadata[$i_date_field]['element_validation_type'];
        // $this->emDebug($i_date_field . " is " . $date_format);
        if (empty($date_format)) REDCap::logEvent("NOTETAKER EM ERROR", "Error saving result, no date format specified on `Date Field last modified` field", "", "", "", "");

        $tokenized = explode("_", $date_format);

        if (in_array("datetime", $tokenized) && in_array("seconds", $tokenized)) { //dateTime seconds
            $new_date_format = "Y-m-d H:i:s";
        } else if (in_array("datetime", $tokenized)) { //dateTime without seconds
            $new_date_format = "Y-m-d H:i";
        } else if (in_array("date", $tokenized)) { //Regular date
            $new_date_format = "Y-m-d";
        } else {
            $this->emError("Specified validation type is not supported for field $i_date_field -- date not chosen");
            $new_date_format = '';
        }

        return $new_date_format;
    }


    /**
     * Take the current note text and append in the new values
     * @param string $data_labels
     * @param string $existing_note
     * @param string $i_input_field
     * @param string $date
     * @param string $user
     * @param bool $use_delimiter
     * @return string
     */
    private function appendNote($data_labels, $existing_note, $i_input_field, $date, $user, $use_delimiter)
    {
        $delimiter = $use_delimiter ? self::DELIMITER : self::LF . self::LF;
        $entry = "[{$user} @ {$date}]";

        if(count($i_input_field) === 1){ //If only one note field, label is not necessary
            $entry .= " " . $data_labels[$i_input_field[0]];
        } else { //Else provide label distinction
            foreach($data_labels as $field => $val){
                    $entry .= self::LF ." ({$field}) " . $val;
            }
        }

        $result = empty($existing_note) ? $entry : $entry . $delimiter . $existing_note;
        return $result;
    }


}
