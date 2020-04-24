<?php

namespace Stanford\NoteTaker;

require_once("emLoggerTrait.php");

use \REDCap;

class NoteTaker extends \ExternalModules\AbstractExternalModule
{

    use emLoggerTrait;

    const DELIMITER = "\n------------------------------------------------------------\n";

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
        global $Proj;
        $instances = $this->getSubSettings('instance'); // Take the current insturment and get all the fields.  Then check if the 'input field' is present in the instrument fields and is not empty.  If so, then add a log entry...
        $RepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();
        $event_name = REDCap::getEventNames(true,true,$event_id);

        // Loop over all instances
        foreach ($instances as $i => $instance) {
            // Get fields from current instance
            $i_event_id          = $instance['event-id'];
            $i_date_field        = $instance['date-field'];
            $i_note_field        = $instance['note-field'];
            $i_input_field       = $instance['input-field'];
            $i_include_delimiter = $instance['include-delimiter'];
            $instrument_fields = REDCap::getFieldNames($instrument); // Load the fields on the instrument just saved

            // Only process this config if the event just saved matches the instance event id
            if ($i_event_id !== $event_id) continue;

            // If the input_fields aren't all on the form, then continue
            foreach($i_input_field as $field)
                if(!in_array($field, $instrument_fields)) continue;

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
            $fields = array_merge($i_input_field, array($i_date_field, $i_note_field, REDCap::getRecordIdField()));
            $data_json = REDCap::getData('json', $record, $fields, $event_id);
            $data = json_decode($data_json, true)[0];

            if(!$this->checkValidity($i_input_field, $data)) continue;

            //check if any inputs are radio/dropdown, if so replace with labels
            foreach($i_input_field as $label){
                $check = parseEnum($Proj->metadata[$label]["element_enum"]);
                    if(!empty($check)){
                        $numerical_value = (int)$data[$label];
                        $readable_value = $check[$numerical_value];
                        $data[$label] = $readable_value;  //set data value
                    }
            }

            // There is a new note entry
            $this->emDebug("Updating Note: {$data[$i_input_field]}");

            // Set date field to current time
            $new_date_format = $this->getNewDateFormat($i_date_field);
            $new_date = empty($new_date_format) ? "" : Date($new_date_format);
            $new_note = $this->appendNote($data, $i_note_field, $i_input_field, $new_date, USERID, $i_include_delimiter);

            //Set new data object to update record
            $data[$i_note_field] = $new_note;
            $data[$i_date_field] = $new_date;

            //Clear fields
            foreach($i_input_field as $field)
                $data[$field] = "";

            // Save
            $output_json = json_encode(array($data));
            $result      = REDCap::saveData('json', $output_json, 'overwrite');
            if (!empty($result['errors'])) $this->emError("Errors saving result: ", $data_json, $output_json, $result);
        }

    }

    /** Function that determines if record save is valid. Returns false only when all input fields are empty
     * @param $input_fields {Array} field names of inputs to pull from
     * @param $data : data object to check
     * @return bool
     */
    public function checkValidity($input_fields, $data)
    {
        if(!isset($input_fields) || !isset($data)){
            $this->emError("Error in passing arguments into checkValidity");
            return false;
        }

        foreach($input_fields as $field){
            if(empty($data[$field]))
                continue;
            else //if one field is populated return true
                return true;
        }

        return false;
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
     * @param string $current_note
     * @param string $new_note
     * @param string $date
     * @param string $user
     * @param bool $use_delimiter
     * @param bool $add_newline
     * @return string
     */
    private function appendNote($data, $i_note_field, $i_input_field, $date, $user, $use_delimiter)
    {
        $delimiter = $use_delimiter ? self::DELIMITER : "\n\n";
        $entry = "[{$user} @ {$date}]";

        if(count($i_input_field) === 1){ //If only one note field, label is not necessary
            $entry .= "\n" . $data[$i_input_field[0]];
        } else { //Else provide label distinction
            foreach($i_input_field as $field){
                if(!empty($data[$field]))
                    $entry .= "\n" ."({$field}) " . $data[$field];
            }
        }

        $result = empty($data[$i_note_field]) ? $entry : $entry . $delimiter . $data[$i_note_field];
        return $result;
    }


}
