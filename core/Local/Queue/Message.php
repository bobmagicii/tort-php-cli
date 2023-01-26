<?php

namespace Local\Queue;

use Nether\Common;

class Message
extends Common\Prototype {

	public string
	$ID;

	public string
	$Type = 'none';

	public array|Common\Datastore
	$Payload = [];

	protected function
	OnReady(Common\Prototype\ConstructArgs $Args):
	void {

		if(!isset($this->ID))
		$this->ID = Common\UUID::V4();

		$this->Payload = match(TRUE) {
			isset($this->Payload) && is_array($this->Payload)
			=> new Common\Datastore($this->Payload),

			isset($this->Payload) && $this->Payload instanceof Common\Datastore
			=> $this->Payload,

			default
			=> new Common\Datastore([])
		};

		return;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	static public function
	FromJSON(string $JSON):
	?static {

		$Data = json_decode($JSON, TRUE);

		if(!is_array($Data))
		return NULL;

		return new static($Data);
	}

}
