#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Application;
use want100cookies\PhoneGallerySort\SortCommand;

$application = new Application("Phone Gallery Sort", "0.1.1");

$command = new SortCommand();

$application->add($command);

$application->setDefaultCommand($command->getName(), true);
$application->run();