<?php


class LocalStorage extends Storage {

    /**
     * @inheritDoc
     */
    public function __construct() {
        // No particular construction is required.
    }

    /**
     * @inheritDoc
     */
    public function offloadFiles() {
        $config = require __DIR__ . '/config.php';

        $waitingFiles = glob($config["waitingUploadsFolder"] . "/*");

        foreach ($waitingFiles as $path) {
            $name = preg_replace("/^" . $config["waitingUploadsFolder"] . "/", "", $path);

            if (!rename($path, $config["uploadFolder"] . $name)) {
                throw new Exception("Couldn't move file from waiting to local storage.");
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function retrieve(string $file): string {
        if (is_null($file)) throw new Exception("No file name provided.");

        $config = require __DIR__ . '/config.php';

        if (file_exists($config["uploadFolder"] . "/" . $file)) {
            if (!copy($config["uploadFolder"] . "/" . $file, $config["tempDirectory"] . "/" . $file)) {
                throw new Exception("Couldn't move file from local to temporary storage.");
            }

            return $config["tempDirectory"] . "/" . $file;
        } else {
            throw new Exception("Couldn't find specified file in uploads folder.");
        }
    }
}