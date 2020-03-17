<?php
declare(strict_types=1);
namespace Stanford\NoteTaker;

require_once("emLoggerTrait.php");


class NoteTaker extends \ExternalModules\AbstractExternalModule {

  use emLoggerTrait;

  public function __construct() {
    parent::__construct();
    // Other code to run when object is instantiated
  }

  public function redcap_save_record( int $project_id, string $record, string $instrument, int $event_id, int $group_id, string $survey_hash, int $response_id, int $repeat_instance = 1 ) {

  }

}