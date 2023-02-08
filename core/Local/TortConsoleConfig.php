<?php

namespace Local;

use Nether\Common;

class TortConsoleConfig
extends Common\Prototype {

	public string
	$OutputDatestamp = 'Ymd-His-v';

	public ?string
	$OutputDir = NULL;

	public ?string
	$DefaultVoice = NULL;

	public bool|string
	$CheckForConda = FALSE;

	public ?string
	$PlayerCmd = NULL;

	public string
	$DefaultBind = '127.0.0.1';

	public int
	$DefaultPort = 42001;

	public ?string
	$DefaultHost = NULL;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected function
	OnReady(Common\Prototype\ConstructArgs $Args):
	void {

		if(!$this->OutputDir)
		$this->OutputDir = 'output/{hostname}.{label}.{voice}.{file}.{datestamp}';

		if(!$this->DefaultVoice)
		$this->DefaultVoice = 'train_atkins';

		if(is_string($this->CheckForConda))
		if(!$this->CheckForConda)
		$this->CheckForConda = FALSE;

		return;
	}

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
