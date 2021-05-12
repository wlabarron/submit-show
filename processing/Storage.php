<?php

abstract class Storage {
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
        // TODO
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