<?php
namespace Stanford\NoteTaker;

class NoteTaker extends \ExternalModules\AbstractExternalModule {
	public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}
	
	public function redcap_save_record( int $project_id, string $record = NULL, string $instrument, int $event_id, int $group_id, string $survey_hash = NULL, int $response_id, int $repeat_instance = 1 ) {
		
	}

	
}