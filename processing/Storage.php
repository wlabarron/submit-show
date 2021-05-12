<?php

abstract class Storage {
    /**
     * @var int File has just been uploaded and is awaiting processing.
     */
    public static int $LOCATION_HOLDING = 0;
    /**
     * @var int File is stored locally.
     */
    public static int $LOCATION_LOCAL = 1;
    /**
     * @var int File is waiting to be moved to offsite storage.
     */
    public static int $LOCATION_WAITING = 2;
    /**
     * @var int File is stored in an offsite location (specified in config file).
     */
    public static int $LOCATION_OFFSITE = 3;

    /**
     * Storage constructor. This should create a connection to the storage system, as appropriate, so that subsequent
     * calls work correctly.
     */
    abstract public function __construct();

    /**
     * Move a file from the holding location (specified in the config file) to a waiting location (again, specified in
     * the config file). This should be a quick, local operation, so that a file moves into semi-permanent storage and
     * is not somewhere it would be deleted and considered an abandoned upload.
     * @param string $file The path of the file to move, relative to the holding location in the config file.
     * @throws Exception
     */
    public function moveToWaiting(string $file) {
        if (is_null($file)) throw new Exception("No file name provided.");

        $config = require __DIR__ . '/config.php';

        if (file_exists($config["holdingDirectory"] . "/" . $file)) {
            if (!rename($config["holdingDirectory"] . "/" . $file, $config["waitingUploadsFolder"] . "/" . $file)) {
                throw new Exception("Couldn't move file from holding to waiting.");
            }
        } else {
            throw new Exception("Couldn't find specified file in holding folder.");
        }
    }

    /**
     * Move all files currently in the waiting location (specified in the config file) to the main storage location.
     * This is the place to do more expensive and time-consuming network operations (like moving files to an offsite
     * storage location), since this happens in the background and not in the process of a user uploading a recording.
     * @throws Exception
     */
    abstract public function offloadFiles();

    /**
     * Retrieve the file at a given location and store it in the temporary directory specified in the config file.
     * Return the path the file was written to, including the part taken from the config file.
     * @param string $file The path of the file requested, relative to the storage location.
     * @return string The path where the file has been placed in temporary storage (for example,
     *                {@code /tmp/Presenter Name-Show.m4a}.
     */
    abstract public function retrieve(string $file): string;
}