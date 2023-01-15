<?php

namespace Local;

use Nether\Object\Prototype;

class TortConsoleConfig
extends Prototype {

	public string
	$OutputDatestamp = 'Ymd-His-v';

	public ?string
	$OutputDir = 'output/{hostname}.{label}.{voice}.{file}.{datestamp}';

	public bool
	$CheckForConda = FALSE;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	static public function
	FromFile(string $File):
	?static {

		if(!file_exists($File))
		return NULL;

		$Data = json_decode(
			file_get_contents($File),
			TRUE
		);

		if(!is_array($Data))
		return NULL;

		return new static($Data);
	}

}
