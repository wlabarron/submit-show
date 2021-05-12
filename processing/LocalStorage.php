<?php


class LocalStorage extends Storage {
    private array $config;

    /**
     * @inheritDoc
     */
    public function __construct() {
        $this->config = require __DIR__ . '/config.php';
    }

    /**
     * @inheritDoc
     */
    public function offloadFile(string $file) {
        if (file_exists($this->config["waitingUploadsFolder"] . "/" . $file)) {
            if (!rename($this->config["waitingUploadsFolder"] . "/" . $file, $this->config["uploadFolder"] . "/" . $file)) {
                throw new Exception("Couldn't move file from waiting to local storage.");
            }
        } else {
            throw new Exception("Couldn't find specified file in waiting folder.");
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