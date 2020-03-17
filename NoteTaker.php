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

  public function redcap_save_record($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash, $response_id, $repeat_instance = 1 )
  {
    // Is a config defined for this instrument?
    // Take the current insturment and get all the fields.  Then check if the 'input field' is present in the instrument fields and is not empty.  If so, then add a log entry...


    $instances = $this->getSubSettings('instance');

    // Loop over all of them
    foreach ($instances as $i => $instance) {

      $i_event_id   = $instance['event-id'];
      $i_date_field = $instance['date-field'];
      $i_note_field = $instance['note-field'];


      // Only process this config if the form is in the same event id
      if ($instance["event-id"] == $event_id) {

        // Check if input_field is in $instrument
        $instrument_fields = REDCap::getFieldNames($instrument);
        $this->emDebug($instrument_fields);

        if (in_array($instance["input-field"], $instrument_fields)) {
          $this->emDebug($instance["input-field"] . " is on this form! ");

          // Check if the input-field is empty
          $fields = [
            $instance["input-field"],
            $instance["date-field"],
            $instance["note-field"]
          ];

          $data_json = REDCap::getData('json', $record, array($fields), $event_id);

          $data = json_decode($data_json, true);

          $this->emDebug($data, "Record $record Data");

          if (!empty($data[$instance["input-field"]])) {
            // Update
            $this->emDebug("Update Note");

            $data[$i_note_field] = "new";


            // Get the format of the date_field
            global $Proj;
            $date_format = $Proj->metadata[$i_date_field]['element_validation_type'];
            $this->emDebug($i_date_field . " is " . $date_format);
            switch ($date_format) {
              case "datetime_ymd":
                $new_date_format = "Y-m-d";
            }

            $data[$i_date_field] = Date($new_date_format);



          }
        }
      }
    }
  }


        // See if this config is valid
        // $sc = new SummarizeInstance($this, $instance);
        // $valid = $sc->validateConfig();

        // // If valid put together the summarize block and save it for this record
        // $config_num = $i++;
        // if ($valid) {
        //   $saved = $sc->saveSummarizeBlock($record, $instrument, $repeat_instance, $this->deleteAction);
        //   if ($saved) {
        //     $this->emDebug("Saved summarize block $config_num for record $record and instance $repeat_instance");
        //   } else {
        //                 $this->emLog($sc->getErrors());
        //             }
        //         } else {
        //             $this->emError("Skipping Summarize config $config_num for record $record because config is invalid" . json_encode($instance));
        //         }



}