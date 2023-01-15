#!/usr/bin/env php
<?php

$PharPath = Phar::Running() ?: '.';
require("{$PharPath}/vendor/autoload.php");

exit((new Local\TortConsoleApp)->Run());
