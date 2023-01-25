<?php

namespace Local\Queue;

use Nether\Object\Prototype;
use Nether\Object\Datastore;
use Nether\Object\Prototype\ConstructArgs;
use Nether\Object\Meta\PropertyObjectify;

class Message
extends Prototype {

	public string
	$Type = 'none';

	public array|Datastore
	$Payload = [];

	protected function
	OnReady(ConstructArgs $Args):
	void {

		$this->Payload = new Datastore(
			isset($this->Payload) && is_array($this->Payload)
			? $this->Payload : []
		);

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
