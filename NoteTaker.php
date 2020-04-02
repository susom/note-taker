<?php
namespace Stanford\NoteTaker;

require_once("emLoggerTrait.php");

use \REDCap;

class NoteTaker extends \ExternalModules\AbstractExternalModule {

  use emLoggerTrait;

  public function __construct() {
    parent::__construct();
    // Other code to run when object is instantiated
  }


  /** Prepends an entry into notebox specified by user upon record save
   * @param $project_id
   * @param null $record
   * @param $instrument
   * @param $event_id
   * @param null $group_id
   * @param $survey_hash
   * @param $response_id
   * @param int $repeat_instance
   * @return bool
   */
  public function redcap_save_record($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash, $response_id, $repeat_instance = 1 ){
    global $Proj;
    $instances = $this->getSubSettings('instance'); // Take the current insturment and get all the fields.  Then check if the 'input field' is present in the instrument fields and is not empty.  If so, then add a log entry...
    $RepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();
    $event_name = REDCap::getEventNames(true,true,$event_id);

    // Loop over all instances
    foreach ($instances as $i => $instance) {
      //grab all names of instances within the instrument
      $i_event_id = $instance['event-id'];
      $i_date_field = $instance['date-field'];
      $i_note_field = $instance['note-field'];
      $i_input_field = $instance['input-field'];
      $i_include_delimiter = $instance['include-delimiter'];

      $instrument_fields = "";

      if ($i_event_id == $event_id) {

        if (empty($instrument_fields)) $instrument_fields = REDCap::getFieldNames($instrument);

        // Check if input_field is in $instrument
        if (in_array($i_input_field, $instrument_fields)) {
          $this->emDebug($i_input_field . " is on this form! ");

          if($RepeatingFormsEvents){
            if (!empty($Proj->RepeatingFormsEvents[$event_id][$instrument])) {
              REDCap::logEvent("NOTETAKER EM ERROR", "$instrument in $event_name cannot be used with Notetaker because it is repeating", "", $record, $event_id, $project_id);
              return "";
            }
            if (isset($Proj->RepeatingFormsEvents[$event_id]) && $RepeatingFormsEvents[$event_id] == "WHOLE") {
              REDCap::logEvent("NOTETAKER EM ERROR", "$event_name cannot be used with NoteTaker because it is repeating", "", $record, $event_id, $project_id);
              return "";
            }
          }

          // Check if the input-field is empty
          $fields = [
            $i_input_field,
            $i_date_field,
            $i_note_field
          ];

          $data_json = REDCap::getData('json', $record, array($fields), $event_id);

          $data = json_decode($data_json, true);
          $data = $data[0];

          $this->emDebug($data, "Record $record Data");

          //Check if there is any input to be prepended to notes
          if (!empty($data[$i_input_field])) {
            // Update
            $this->emDebug("Update Note");

            //set date field to current time
            $new_date_format = $this->getNewDateFormat($i_date_field);

            if (!empty($new_date_format)) {
              $data[$i_date_field] = Date($new_date_format);
            } else {
              $this->emError("Specified validation type is not supported, date not chosen");
            }

            //Update note box
            $data = $this->prependInput($data, $i_note_field, $i_input_field, $i_date_field, $i_include_delimiter);
            $this->emDebug("Data after update", $data);

            // Save
            $data2_json = json_encode(array($data));
            $result = REDCap::saveData('json', $data2_json, 'overwrite');
            $this->emDebug($data_json, $data2_json, $result, "Save Result");
          }
        }
      }
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
  private function getNewDateFormat($i_date_field){
    global $Proj;
    $date_format = $Proj->metadata[$i_date_field]['element_validation_type'];
    $this->emDebug($i_date_field . " is " . $date_format);

    $tokenized = explode("_", $date_format);

    if(in_array("datetime", $tokenized) && in_array("seconds", $tokenized)) { //dateTime seconds
      $new_date_format = "Y-m-d H:i:s";
    } else if(in_array("datetime", $tokenized)){ //dateTime without seconds
      $new_date_format = "Y-m-d H:i";
    } else if(in_array("date", $tokenized)){ //Regular date
      $new_date_format = "Y-m-d";
    } else {
      return '';
    }

    return $new_date_format;
  }


  /** Prepends Header String and input field value to previous note field within the data object
   *  Example call -
   *  $data = array(), $i_note_field = "notebox1" , $i_input_field = "Append Me", $i_date_field = "dateTime1", $i_include_delimiter = True
   *  return -
   *  data[notebox1] = "[<username> @ <dateNow> ] \n ---------------------- \n <previous note> \n
   *
   * @param $data : data object containing the values of instrument instances
   * @param $i_note_field : note field name
   * @param $i_input_field : input field name
   * @param $i_date_field : date field name
   * @param $i_include_delimiter : T/F, whether to delimit note by a line
   * @return $data : updated data object
   **/
  private function prependInput($data, $i_note_field, $i_input_field, $i_date_field, $i_include_delimiter){
    if($data){
      $user = USERID;
      if(!empty($data[$i_note_field]) && $i_include_delimiter){ //If notes field already has been prepended to
        $data[$i_note_field] =  "[{$user} @ {$data[$i_date_field]}]\n" . $data[$i_input_field] . "\n------------------------------------------------------------\n" . $data[$i_note_field] . "\n";
      } else { //New note, no need for delimiter
        $data[$i_note_field] =  "[{$user} @ {$data[$i_date_field]}]\n" . $data[$i_input_field]. "\n" . $data[$i_note_field] . "\n";
//        $data[$i_note_field] = $data[$i_note_field] . "\n[{$user} @ {$data[$i_date_field]}]\n" . $data[$i_input_field];
      }

      // Erase input field
      $data[$i_input_field] = "";
      return $data;
    }
    $this->emError('Data object supplied is empty');
  }

}
