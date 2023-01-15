# PHP-CLI Wrapper for TorToiSe TTS

This is a CLI app that wraps the CLI app for TorToiSe TTS to be... more.

* Support for a `voice.json` file for voices so you can save your tunings for
  your custom voices and forget half the CLI settings.

* Process a single line, a text file as one paragraph, or a text file with
  each line being run separately pre-processed so TorToiSe can do work in
  nicer smaller batches - which helps with low VRAM hardware and avoiding some
  pitfalls where it will start stuttering, repeating itself, or skipping entire
  statements in longer texts.

* Writes a `job.json` file with each output so it can be re-ran as it was.

* Interception and rewriting of the progress output that did not work well on
  Windows, or if you ever resized your terminal window, or looked at it too
  hard on accident.

* Some pre-emptive error checking. Mostly prevention of shooting yourself in
  the foot with settings that end up crashing with a Python stack dump.

* Prevent you from just overwriting the same files over and over when you
  wanted to bulk process 100 voice lines, but with 3 samples each, such you
  would have woke up the next morning to only 3 files.

* If you use conda there is a reminder if you failed to remember to activate
  the conda environment. This can be disabled in `tort.json`.

* Dry-Run mode where it just shows you what is going to happen without doing
  any of it.

* A confirmation before actually doing it, showing you what it is going to
  do, which can be bypassed with a flag.

This is all done just with wrapping and checking things before commiting
commands to TorToiSe. No way they'd been happy with the amount of reformatting
I would have done with a pull request.

Tested on Windows 10 and Mint Linux 21. Both using `conda` hosting Python 3.9
environments to escape the Python minor version and package backward
compatiblity breakage hell. I am certain an actual Python person would be able
to navigate it without it though just fine.



# Requirements

* TorToiSe TTS (https://github.com/neonbjb/tortoise-tts)

* PHP 8.1
* Composer for PHP (if not using PHAR)



# Installation (Preferred Method)

Grab the latest PHAR from Releases and extract it.

From within the `tort` directory install TorToiSe the way they instruct.
You should spend time to get it to work there with the `do_tts.py` to prove it
works or else none of this is going to work either. Do not skip over the bits
about PyTorch, double so if you want GPU processing to actually work.

* `cd tort`
* `git clone https://github.com/neonbjb/tortoise-tts`
* `cd tortoise-tts`
	* [ Get PyTorch working like the TorToiSe instructions say.]
	* `python setup.py install`
	* `cd ..`

Next copy the `voices` folder out of `tortoise-tts/tortoise/voices` into the
root of this project so you can edit and add configuration files to them. That
way you can update TorToiSe at-will without messing up your progress.

* `cp -r tortoise-tts/tortoise/voices .`

The final directory structure should look like this:

* `tort`
	- `tortoise-tts`
	- `voices`
	- `tort.phar`
	- `tort.json`

If you are not using `conda` or do not want to be reminded when you failed to
activate an environment, change the appropriate setting in `tort.json`.



# Installation (Source)

Start by checking out this repo.

* `git clone https://github.com/bobmagicii/tort-php-cli`

Install the dependencies.

* `cd tort-php-cli`
* `composer install`

Then follow the exact same instructions as the Preferred Method skipping the
first line.



# Usage

This will dump its help command.

* `php tort.phar`

This will dump the help for the generation command.

* `php tort.phar help gen`


# Examples

List all the voices that we were able to find. Probably important to check that
first before going nuts.

```sh
php tort.phar voices
```

---

Run a single line of text on the default voice which I decided should
be `train_atkins`.

```bash
php tort.phar gen "this is a test."
```

---

Run a single line of text with a custom voice.

```bash
php tort.phar gen "this is a test." --voice=bob
```

---

Run a text file as the input. This has TorToiSe handle the entire text file in
the manner it decides to. This generally means it will break it into smaller
chunks and run them in sequence.

```bash
php tort.phar gen --file=path\to\textfile.txt
```

---

Run a text file as the input, but have Tort pre-process it breaking it up by
line and having each line run by clean invocations of TorToiSe. This helps a
a lot with voices that start suttering, repeating, or skipping. It also helps
with VRAM usage.

```bash
php tort.phar gen --file=path\to\textfile.txt --lines
```

---

Run with more Iterations. This actually defaults to four and if I am being
honest I can rarely tell a difference between four and ninty-six with clean
sound sources, other than a difference of like 10 minutes of life.

However each voice dataset is unique and often some do better with fewer or
moreser you just gotta play around to get your stuff be good. I got one voice
that sounds best at 62 iters, but then my Master Chief voice sounds better
at 4 with him getting more and more southern the more iters added.

```sh
php tort.phar gen "this is a test." --iters=24
```

---

Run with a specific seed so you can repeat the same generations. I have
observed that the same seed will not produce the same outputs on across
different hardware. My NVIDIA 3060 and 980 both produced different outputs from
the same seed.

```sh
php tort.phar gen "this is a test." --seed=69
```

---

Generate a new config file for your voice so you can forget all the tuning
values that are listed by the `tort help gen` command later on. It will create
a `voice.json` file in that voice folder with the tuning values that have the
most effects and from that point on if you do not provide any CLI overrides,
those setting will get used with that voice.

```sh
php tort.phar voiceconf <voice>
```


# Final Notes

Thanks to the TorToiSe TTS person for all the work of making it work.

Hope they continue with making more compat models or release more info on how,
so we can give the voices better diveristy. Like right now if your goal is to
do an old-timey radio transatlantic person, or any city accents, gonna have a
hard time.

But it still pretty cool.


