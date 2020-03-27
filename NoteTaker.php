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
        // Take the current instrument and get all the fields.
        $instances = $this->getSubSettings('instance');

        // Loop over all instances
        foreach ($instances as $i => $instance) {
            // Get fields from current instance
            $i_event_id          = $instance['event-id'];
            $i_date_field        = $instance['date-field'];
            $i_note_field        = $instance['note-field'];
            $i_input_field       = $instance['input-field'];
            $i_include_delimiter = $instance['include-delimiter'];
            $i_include_new_line  = $instance['include-new-line'];

            $instrument_fields = "";

            // Only process this config if the event just saved matches the instance event id
            if ($i_event_id !== $event_id) continue;

            // Load the fields on the instrument just saved
            if (empty($instrument_fields)) $instrument_fields = REDCap::getFieldNames($instrument);

            // If the input_field isn't on the form, then continue
            if (!in_array($i_input_field, $instrument_fields)) continue;
            $this->emDebug($i_input_field . " is on this form in event $event_id!");

            // Pull data to check if the input-field had an entry
            $fields = [ $i_input_field, $i_date_field, $i_note_field ];
            $data_json = REDCap::getData('json', $record, array($fields), $event_id);
            $data = json_decode($data_json, true);
            $data = $data[0];
            // $this->emDebug("Record $record note data", $data_data);

            // Check if there is any input to be prepended to notes
            if (empty($data[$i_input_field])) continue;

            // There is a new note entry
            $this->emDebug("Updating Note: {$data[$i_input_field]}");

            // Set date field to current time
            $new_date_format = $this->getNewDateFormat($i_date_field);
            $new_date = empty($new_date_format) ? "" : Date($new_date_format);
            $user = USERID;
            $new_note = $this->appendNote($data[$i_note_field], $data[$i_input_field], $new_date, USERID, $i_include_delimiter, $i_include_new_line);

            $data[$i_note_field] = $new_note;
            $data[$i_date_field] = $new_date;
            $data[$i_input_field] = "";

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
    private function appendNote($current_note, $new_note, $date, $user, $use_delimiter, $add_newline)
    {
        $delimiter = $use_delimiter ? self::DELIMITER : "\n\n";
        $entry = "[{$user} @ {$date}]" .
            ($add_newline ? "\n" : " ") .
            $new_note;
        $result = empty($current_note) ? $entry : $entry . $delimiter . $current_note;
        return $result;
    }


}
