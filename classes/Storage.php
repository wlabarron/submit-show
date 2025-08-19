<?php

abstract class Storage {
    /**
     * @var int File has just been uploaded and is awaiting processing.
     */
    public static int $LOCATION_HOLDING = 0;
    /**
     * @var int File is waiting to be moved to main storage.
     */
    public static int $LOCATION_WAITING = 1;
    /**
     * @var int File is stored in the main location (specified in config file).
     */
    public static int $LOCATION_MAIN = 2;

    /**
     * Storage constructor. This should create a connection to the storage system, as appropriate, so that subsequent
     * calls work correctly.
     * @throws Exception
     */
    abstract public function __construct();

    /**
     * Creates an appropriate instance of the Storage class for the storage type specified in the config file.
     * @return Storage Instance of an extension of the Storage class.
     * @throws Exception
     */
    public static function getProvider(): Storage {
        $config   = require __DIR__ . '/../config.php';
        $provider = strtolower($config["storageProvider"]);

        switch ($provider) {
            case "local":
                require __DIR__ . "/LocalStorage.php";
                return new LocalStorage();
            case "s3":
                require __DIR__ . "/S3Storage.php";
                return new S3Storage();
            default:
                throw new Exception("Unknown storage provider specified in config file.");
        }
    }

    /**
     * Takes a path to a file (such as {@code /var/storage/a/b/file.mp3}) and creates the holding directories
     * (such as {@code /var/storage/a/b/}) if they don't already exist. This can be used before attempting to write
     * a file, to ensure the required directories exist.
     * @param string $path The path to analyse.
     * @return bool true if the operation is successful or the directories already existed, false otherwise.
     */
    public static function createParentDirectories(string $path): bool {
        $split = explode("/", $path);
        // Take the last section off the array, since that will be the file name
        array_pop($split);
        $directory = implode("/", $split);

        if (is_dir($directory)) return true;
        else return mkdir($directory, 0775, true);
    }

    /**
     * Move a file from the holding location (specified in the config file) to a waiting location (again, specified in
     * the config file). This should be a quick, local operation, so that a file moves into semi-permanent storage and
     * is not somewhere it would be deleted and considered an abandoned upload.
     * @param string $file The path of the file to move, relative to the holding location in the config file.
     * @throws Exception
     */
    public function moveToWaiting(string $file) {
        if (empty($file)) throw new Exception("No file name provided.");

        $config          = require __DIR__ . '/../config.php';
        $holdingLocation = $config["holdingDirectory"] . "/" . $file;
        $targetLocation  = $config["waitingDirectory"] . "/" . $file;

        if (file_exists($holdingLocation)) {
            if (!Storage::createParentDirectories($targetLocation)) {
                throw new Exception("Couldn't make parent directories in target location.");
            }

            if (!rename($holdingLocation, $targetLocation)) {
                throw new Exception("Couldn't move file from holding to waiting.");
            }
        } else {
            throw new Exception("Couldn't find specified file in holding folder.");
        }
    }

    /**
     * Move the specified file to the main storage location. The provided path will be relative to the waiting directory
     * specified in the config file. This is the place to do more expensive and time-consuming network operations (like
     * moving files to an offsite storage location), since this happens in the background and not in the process of a
     * user uploading a recording.
     *
     * After successfully offloading the file, the copy in the waiting directory should be deleted.
     *
     * @param string $file The path of the file to offload, relative to the waiting directory specified in the config file.
     * @throws Exception
     */
    abstract public function offload(string $file);

    /**
     * Retrieve the file at a given location and return a path where it is accessible on the local file system. The file
     * path returned should be to a copy of the file in the temporary folder (as per the config file), which can be safely
     * deleted at any time.
     * @param string $file The path of the file requested, relative to the storage location.
     * @return string The path where the file has been placed in temporary storage (for example,
     *                {@code /tmp/Presenter Name-Show.m4a}.
     * @throws Exception
     */
    abstract public function retrieve(string $file): string;

    /**
     * Permanently delete the specified file.
     * @param string $file The path of the file to delete.
     * @throws Exception
     */
    abstract public function delete(string $file);
}