<?php

namespace Local\Error;

use Exception;

class TextLengthWarning
extends Exception {

	public function
	__Construct(int $Len) {
		parent::__Construct("min-len > {$Len} - tortoise may begin complaining about various things and experience processing errors if this is too large.");
		return;
	}

}
