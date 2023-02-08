<?php

namespace Local\Error;

use Exception;

class CondaWrongEnv
extends Exception {

	public function
	__Construct(string $Env) {
		parent::__Construct("Wrong CONDA environment is active: {$Env}");
		return;
	}

}
