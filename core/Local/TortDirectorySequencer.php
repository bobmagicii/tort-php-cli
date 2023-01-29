<?php

namespace Local;

use Nether\Common;

use SplFileInfo;
use FilesystemIterator;

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
	GetFiles():
	Common\Datastore {

		$Files = new Common\Datastore;
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
			$Files->Shove($Prefix, new Common\Datastore);

			$Files[$Prefix]->Push($Name);
		}

		return $Files;
	}

	public function
	CheckMaxSets(Common\Datastore $Files):
	int {

		// figure out the maximum number of full sets we can make by
		// seeing which line has the fewest files.

		$Max = $Files->Accumulate(PHP_INT_MAX, (
			fn(int $Carry, Common\Datastore $Line)
			=> min($Carry, $Line->Count())
		));

		return $Max;
	}

	public function
	Run():
	void {

		$Files = $this->GetFiles();
		$MaxSets = $this->CheckMaxSets($Files);
		$Seqs = new Common\Datastore;
		$Iter = NULL;
		$Dir = NULL;

		// prepare directories for all the full sets and index one file
		// from each complete set of lines for them.

		for($Iter = 0; $Iter < $MaxSets; $Iter++) {
			$Dir = Common\Filesystem\Util::Pathify(
				$this->Path,
				'seqs',
				sprintf('seq%\'03d', ($Iter + 1))
			);

			$Seqs->Shove($Dir, new Common\Datastore);

			foreach($Files as $Line)
			$Seqs[$Dir]->Push($Line[$Iter]);
		}

		// move all the files that were indexed into their final resting
		// places.

		$Seqs->Each(function(Common\Datastore $Files, string $SeqDir) {

			if(!is_dir($SeqDir))
			mkdir($SeqDir, 0777, TRUE);

			$Files->Shuffle();
			$File = NULL;

			foreach($Files as $File) {
				$Src = Common\Filesystem\Util::Pathify($this->Path, $File);
				$Dst = Common\Filesystem\Util::Pathify($SeqDir, $File);

				if($this->Copy)
				copy($Src, $Dst);
				else
				rename($Src, $Dst);
			}

			return;
		});

		return;
	}



}
