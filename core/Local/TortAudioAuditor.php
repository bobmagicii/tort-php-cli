<?php

namespace Local;

use Nether\Common;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class TortAudioAuditor {

	protected string
	$PlayerCmd;

	protected string
	$Path;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	__Construct(TortConfigPackage $Config, string $Path) {

		$this->PlayerCmd = $Config->App->PlayerCmd;
		$this->Path = $Path;

		// @todo 2023-02-06
		// inspect the PlayerCmd for known player executables and make sure
		// the arguments we know should exist for a good experience do
		// actually exist.

		return;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	GetFiles():
	Common\Datastore {

		$Scan = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
			$this->Path,
			(
				0
				| RecursiveDirectoryIterator::SKIP_DOTS
				| RecursiveDirectoryIterator::CURRENT_AS_PATHNAME
			)
		));

		$Files = [];
		$File = NULL;

		////////

		foreach($Scan as $File)
		if(preg_match('/\.(wav|mp3|flac)$/i', $File))
		$Files[] = trim(str_replace($this->Path, '', $File), '\\/');

		////////

		return new Common\Datastore($Files);
	}

	public function
	Play(string $File):
	void {

		$Path = Common\Filesystem\Util::Pathify($this->Path, $File);
		$CmdFmt = str_replace('{file}', '%1$s', $this->PlayerCmd);

		$Command = sprintf(
			$CmdFmt,
			escapeshellarg($Path)
		);

		system($Command);

		return;
	}

	public function
	Delete(string $File):
	void {

		$Path = Common\Filesystem\Util::Pathify($this->Path, $File);

		if(is_file($Path))
		unlink($Path);

		return;
	}

}