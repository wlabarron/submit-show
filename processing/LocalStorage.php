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
    public function offload(string $file) {
        if (file_exists($this->config["waitingDirectory"] . "/" . $file)) {
            if (!rename($this->config["waitingDirectory"] . "/" . $file, $this->config["localStorage"]["uploadsDirectory"] . "/" . $file)) {
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

        if (file_exists($this->config["localStorage"]["uploadsDirectory"] . "/" . $file)) {
            if (!copy($this->config["localStorage"]["uploadsDirectory"] . "/" . $file, $this->config["tempDirectory"] . "/" . $file)) {
                throw new Exception("Couldn't move file from local to temporary storage.");
            }

            return $this->config["tempDirectory"] . "/" . $file;
        } else {
            throw new Exception("Couldn't find specified file in uploads folder.");
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