<?php

namespace Local;

use Exception;
use Nether\Object\Prototype;

class TortExecConfig
extends Prototype {

	public ?string
	$TortoiseTTS = 'tortoise-tts/scripts/tortoise_tts.py';

	public ?string
	$VoiceDir = 'voices';

	public ?string
	$OutputDir = NULL;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public ?string
	$Text = NULL;

	public ?string
	$File = NULL;

	public string|bool
	$Lines = FALSE;

	public ?int
	$Seed = NULL;

	public string
	$Voice = 'train_atkins';

	public int
	$GenCount = 3;

	public ?int
	$TextLenMin = 300;

	public ?int
	$TextLenMax = 400;

	public bool
	$KeepCombined = FALSE;

	public bool
	$DontRename = FALSE;

	public ?string
	$Label = NULL;

	public ?string
	$PromptPre = NULL;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public bool
	$DryRun = FALSE;

	public bool
	$Derp = FALSE;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	GetVoiceConfRelated():
	TortVoiceConfig {

		return TortVoiceConfig::New($this);
	}

	public function
	TryToPreventDeath():
	void {

		if($this->TextLenMax < $this->TextLenMin)
		$this->TextLenMax = $this->TextLenMin + 100;

		if($this->TextLenMin >= 450)
		throw new Error\TextLengthWarning(450);

		return;
	}

	public function
	Write(string $File):
	void {

		if(!is_dir(dirname($File)))
		throw new Exception("Unable to write {$File} (permission denied)");

		// some properties don't make sense to write to disk.

		$Dataset = [];
		$Skip = [ 'DryRun', 'Derp' ];
		$Prop = NULL;
		$Val = NULL;

		foreach($this as $Prop => $Val) {
			if(array_key_exists($Prop, $Skip))
			continue;

			$Dataset[$Prop] = $Val;
		}

		// get stickbugged.

		$JSON = json_encode($Dataset, JSON_PRETTY_PRINT);
		$JSON = preg_replace('/[\h]{4}/', "\t", $JSON);

		file_put_contents($File, $JSON);

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
