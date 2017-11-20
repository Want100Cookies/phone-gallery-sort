<?php

namespace want100cookies\PhoneGallerySort;

use Carbon\Carbon;
use PHPExif\Reader\Reader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class SortCommand extends Command
{
    private $exifReader;

    public function __construct($name = null)
    {
        parent::__construct($name);

        $this->exifReader = Reader::factory(Reader::TYPE_NATIVE);
    }

    protected function configure()
    {
        $this
            ->setName("sort")
            ->setDescription("Sort all files provided through the arguments")
            ->addArgument(
                "destination",
                InputArgument::REQUIRED,
                "Set the destination path. The sorted files are placed here"
            )
            ->addOption(
                "source",
                "s",
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                "Set the source directory from which to get the files to sort",
                ["./"]
            )
            ->addOption(
                "event-threshold",
                null,
                InputOption::VALUE_REQUIRED,
                "How many files on same date are required for event folder",
                7
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("Phone Gallery Sort");

        if (!extension_loaded('exif')) {
            $io->error("php_exif module not loaded!");
            die(0);
        }

        $finder = new Finder();
        $finder->files()->in($input->getOption('source'));

        $io->comment("Getting dates...");
        list($dates, $unsorted) = $this->getDates($finder);

        $io->comment("Sorting based on dates...");
        $sorted = $this->sort($dates, $input->getOption("event-threshold"));

        $io->comment("Copying files to destination...");
        $this->copy($sorted, $unsorted, $input->getArgument("destination"), $io);

        $io->success("Done");
    }

    protected function copy(array $sorted, array $unsorted, string $destination, SymfonyStyle $io)
    {
        $fs = new Filesystem();

        $io->progressStart(count($sorted, COUNT_RECURSIVE) + count($unsorted));

        foreach ($sorted as $folder => $files) {
            foreach ($files as $file) {
                $fs->copy($file->getRealPath(), $destination . '/' . $folder . '/' . $file->getFilename());
                $io->progressAdvance();
            }
        }

        foreach ($unsorted as $file) {
            $fs->copy($file->getRealPath(), $destination . '/unsorted/' . $file->getFilename());
            $io->progressAdvance();
        }

        $io->progressFinish();
    }

    protected function sort(array $dates, $eventThreshold)
    {
        $sorted = [];

        foreach ($dates as $timestamp => $date) {
            $carbon = Carbon::createFromTimestamp($timestamp);
            if (count($date) > $eventThreshold) {
                $sorted[$carbon->format("Y-m-d")] = $date;
            } else {
                $format = "Y-m";
                if (!array_key_exists($carbon->format($format), $sorted)) {
                    $sorted[$carbon->format($format)] = [];
                }

                array_merge($sorted[$carbon->format($format)], $date);
            }
        }

        return $sorted;
    }

    protected function getDates(Finder $finder)
    {
        $dates = [];
        $unsorted = [];

        foreach ($finder as $file) {
            $date = $this->getDateTimeForFile($file);
            if ($date !== null) {
                $index = $date->getTimestamp();

                if (!array_key_exists($index, $dates)) {
                    $dates[$index] = [];
                }

                $dates[$index][] = $file;
            } else {
                $unsorted[] = $file;
            }
        }

        return [$dates, $unsorted];
    }

    protected function getDateTimeForFile(SplFileInfo $fileInfo)
    {
        $filename = $fileInfo->getFilename();

        if (preg_match("/(20[0-9][0-9])(0[1-9]|1[012])(0[1-9]|[12][0-9]|3[01])/", $filename, $match)) {
            return Carbon::parse($match[0]);
        }

        $exif = $this->exifReader->read($fileInfo->getRealPath());
        if ($exif !== false) {
            $creationDate = $exif->getCreationDate();
            if ($creationDate !== false) {
                return Carbon::createFromTimestamp($creationDate->getTimeStamp());
            }
        }

        return null;
    }
}