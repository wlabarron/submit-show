<?php


class LocalStorage extends Storage {
    private array $config;

    /**
     * @inheritDoc
     */
    public function __construct() {
        $this->config = require __DIR__ . '/../config.php';
    }

    /**
     * @inheritDoc
     */
    public function offload(string $file) {
        if (empty($file)) throw new Exception("No file name provided.");

        $waitingLocation = $this->config["waitingDirectory"] . "/" . $file;
        $targetLocation  = $this->config["localStorage"]["uploadsDirectory"] . "/" . $file;

        if (file_exists($waitingLocation)) {
            if (!Storage::createParentDirectories($targetLocation)) {
                throw new Exception("Couldn't make parent directories in target location.");
            }

            if (!rename($waitingLocation, $targetLocation)) {
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
        if (empty($file)) throw new Exception("No file name provided.");

        $uploadsLocation = $this->config["localStorage"]["uploadsDirectory"] . "/" . $file;
        $targetLocation  = $this->config["tempDirectory"] . "/" . $file;

        if (file_exists($uploadsLocation)) {
            if (!Storage::createParentDirectories($targetLocation)) {
                throw new Exception("Couldn't make parent directories in target location.");
            }

            if (!copy($uploadsLocation, $targetLocation)) {
                throw new Exception("Couldn't move file from waiting to local storage.");
            }

            return $targetLocation;
        } else {
            throw new Exception("Couldn't find specified file in waiting folder.");
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(string $file) {
        if (!unlink($this->config["localStorage"]["uploadsDirectory"] . "/" . $file)) {
            throw new Exception("Couldn't delete local file.");
        }
    }
}