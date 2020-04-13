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

        // Take the current instrument and get all the fields.
        $nested = $this->getProjectSetting('additional-field');

        // Loop over all instances
        foreach ($instances as $i => $instance) {
            // Get fields from current instance
            $i_event_id          = $instance['event-id'];
            $i_date_field        = $instance['date-field'];
            $i_note_field        = $instance['note-field'];
            $i_input_field       = $instance['input-field'];
            $i_include_delimiter = $instance['include-delimiter'];
            $i_include_new_line  = $instance['include-new-line'];
            $nested_field_names = !empty($nested) ? $nested[$i] : []; // Gather corresponding additional-field names for this instance if exists
            $instrument_fields = "";

            // Only process this config if the event just saved matches the instance event id
            if ($i_event_id !== $event_id) continue;

            // Load the fields on the instrument just saved
            if (empty($instrument_fields)) $instrument_fields = REDCap::getFieldNames($instrument);

            // If the input_field isn't on the form, then continue
            if (!in_array($i_input_field, $instrument_fields)) continue;
            $this->emDebug($i_input_field . " is on this form in event $event_id!");

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
            $fields = [ $i_input_field, $i_date_field, $i_note_field, REDCap::getRecordIdField()];
            $data_json = REDCap::getData('json', $record, $fields, $event_id);
            $data = json_decode($data_json, true)[0];

            $nested_data = ""; //Declared outside as empty to prevent undefined error when appending note
            if(!empty($nested_field_names)){ //Check if there are any sub_fields specified
                $additional_fields_json = REDCap::getData('json', $record, $nested_field_names, $event_id);
                $nested_data = json_decode($additional_fields_json, true)[0];

                foreach($nested_data as $label=>$value){ //Replace additional-field type with correct values (radio/dropdown/etc)
                    $check = parseEnum($Proj->metadata[$label]["element_enum"]);
                    if(!empty($check)){
                        $readable_value = $check[(int)$value];
                        $nested_data[$label] = $readable_value;
                    }
                }
            }

            // Check if there is any input to be prepended to notes. Only allow posting when initial input field has value
            if (empty($data[$i_input_field])) continue;

            // There is a new note entry
            $this->emDebug("Updating Note: {$data[$i_input_field]}");

            // Set date field to current time
            $new_date_format = $this->getNewDateFormat($i_date_field);
            $new_date = empty($new_date_format) ? "" : Date($new_date_format);
            $user = USERID;
            $new_note = $this->appendNote($data[$i_note_field], $data[$i_input_field], $new_date, USERID, $i_include_delimiter, $i_include_new_line,  $nested_data);

            //Set new data object to update record
            $data[$i_note_field] = $new_note;
            $data[$i_date_field] = $new_date;
            $data[$i_input_field] = "";

            //Set additional-fields to empty, merge to main data object for update
            foreach($nested_data as $k => $v)
                $nested_data[$k] = "";
            $data = array_merge($data, $nested_data);

            // Save
            $output_json = json_encode(array($data));
            $result      = REDCap::saveData('json', $output_json, 'overwrite');
            // $this->emDebug($data_json, $output_json);
            if (!empty($result['errors'])) $this->emError("Errors saving result: ", $data_json, $output_json, $result);
        }

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
    private function appendNote($current_note, $new_note, $date, $user, $use_delimiter, $add_newline, $additional_fields)
    {
        $delimiter = $use_delimiter ? self::DELIMITER : "\n\n";
        $entry = "[{$user} @ {$date}]" .
            ($add_newline ? "\n" : " ") .
            $new_note;

        if(!empty($additional_fields)){
            foreach($additional_fields as $field => $val)
                $entry.="\n({$field} => {$val})";
        }

        $result = empty($current_note) ? $entry : $entry . $delimiter . $current_note;
        return $result;
    }


}
