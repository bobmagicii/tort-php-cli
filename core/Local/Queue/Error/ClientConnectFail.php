<?php

namespace Local\Queue\Error;

use Exception;

class ClientConnectFail
extends Exception {

	public function
	__Construct(string $Host, int $Port) {
		parent::__Construct("unable to connect to {$Host}:{$Port}");
		return;
	}

}
