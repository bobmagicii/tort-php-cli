<?php

namespace Local\Error;

use Exception;

class CondaNotActive
extends Exception {

	public function
	__Construct() {
		parent::__Construct('No CONDA environment is active.');
		return;
	}

}
