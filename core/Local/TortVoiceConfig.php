<?php

namespace Local;

use Exception;
use Nether\Object\Prototype;

class TortVoiceConfig
extends Prototype {

	// preset name that our settings start from.
	// ultra_fast, fast, standard, high_quality

	public string
	$Quality = 'high_quality';

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	// top-p seems to control the range of possible inflections.

	// top-p low things talk monotone regardless of temper.
	// top-p high things vary their pitch across wider range.

	public float
	$TopP = 0.8;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	// temperature seems to control the wildness of inflections as a
	// statement progresses. as if maybe how fast it might walk away
	// from its starting pitch and inflection.

	// top-p low, temper low, all samples sound the same. (negates seed?)
	// top-p low, temper high, all samples sound the same. (negates seed?)
	// top-p high, temper low, samples have variation.
	// top-p high, temper high, samples have variation.

	public float
	$Temper = 0.8;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	// docs claim low will make things sound slushy but i cannot tell a
	// difference between 0 or 1.

	public float
	$DiffTemper = 1.0;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	// turns on or off the conditioning.

	public bool
	$Cond = TRUE;

	// my best digestion hearing the difference between clips is that this
	// seems to affect loudness the most. the higher i push this the louder
	// already loud words get, without much difference on quieter words.
	// range 0 to inf

	public float
	$CondStr = 2.0;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	// controls some foolery regarding repeating of duplicate sounds
	// which also includes silence.

	public float
	$RepPen = 2.0;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	// controls how long things can be dragged out. documentation says
	// higher values will cause more brevity.

	public float
	$LenPen = 1.0;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	// how many iterations for the model to cook before rendering.

	public int
	$Iters = 256;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	TryToPreventDeath():
	void {

		// values it demands be above zero.

		if($this->TopP < 0.01)
		$this->TopP = 0.01;

		if($this->Temper < 0.01)
		$this->Temper = 0.01;

		if($this->RepPen < 0.01)
		$this->RepPen = 0.01;

		if($this->LenPen < 0.01)
		$this->LenPen = 0.01;

		return;
	}

	public function
	Write(string $File):
	void {

		if(!is_writable(dirname($File)))
		throw new Exception("unable to write {$File} (permission denied)");

		// get stickbugged.

		$JSON = json_encode($this, JSON_PRETTY_PRINT);
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

		$Data = json_decode(file_get_contents($File));

		if(!is_object($Data))
		return NULL;

		return new static($Data);
	}

}
