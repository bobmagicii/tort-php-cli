<?php

namespace Local;

use Nether\Common;

class TortConsoleConfig
extends Common\Prototype {

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
