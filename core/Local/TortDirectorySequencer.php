<?php

namespace Local;

use SplFileInfo;
use FilesystemIterator;
use Nether\Object\Datastore;

class TortDirectorySequencer {

	protected string
	$Path;

	protected bool
	$Copy;

	public function
	__Construct(string $Path, bool $Copy) {

		$this->Path = $Path;
		$this->Copy = $Copy;

		return;
	}

	public function
	Run():
	void {

		$Files = $this->GetFiles();
		$Seqs = new Datastore;
		$Min = NULL;
		$Iter = NULL;
		$Dir = NULL;

		// figure out the maximum number of full sets we can make by
		// seeing which line has the fewest files.

		$Min = $Files->Accumulate(PHP_INT_MAX, (
			fn(int $Carry, Datastore $Line)
			=> min($Carry, $Line->Count())
		));

		// prepare directories for all the full sets and index one file
		// from each complete set of lines for them.

		for($Iter = 0; $Iter < $Min; $Iter++) {
			$Dir = sprintf(
				'%s%s%s%sseq%\'03d',
				$this->Path,
				DIRECTORY_SEPARATOR,
				'seqs',
				DIRECTORY_SEPARATOR,
				($Iter + 1)
			);

			$Seqs->Shove($Dir, new Datastore);

			foreach($Files as $Line)
			$Seqs[$Dir]->Push($Line[$Iter]);
		}

		// move all the files that were indexed into their final resting
		// places.

		$Seqs->Each(function(Datastore $Files, string $SeqDir) {

			if(!is_dir($SeqDir))
			mkdir($SeqDir, 0777, TRUE);

			foreach($Files as $File) {
				$Src = sprintf('%s%s%s', $this->Path, DIRECTORY_SEPARATOR, $File);
				$Dst = sprintf('%s%s%s', $SeqDir, DIRECTORY_SEPARATOR, $File);

				if($this->Copy)
				copy($Src, $Dst);
				else
				rename($Src, $Dst);
			}

			return;
		});

		return;
	}

	protected function
	GetFiles():
	Datastore {

		$Files = new Datastore;
		$Iter = new FilesystemIterator($this->Path, (
			0
			| FilesystemIterator::KEY_AS_PATHNAME
			| FilesystemIterator::SKIP_DOTS
		));

		$File = NULL;
		$Prefix = NULL;
		$Discard = NULL;
		$Name = NULL;

		////////

		foreach($Iter as $File) {
			/** @var SplFileInfo $File */

			$Name = $File->GetFilename();

			if(!str_starts_with($Name, 'line'))
			continue;

			list($Prefix, $Discard) = explode('_', $Name, 2);

			if(!$Files->HasKey($Prefix))
			$Files->Shove($Prefix, new Datastore);

			$Files[$Prefix]->Push($Name);
		}

		return $Files;
	}

}
