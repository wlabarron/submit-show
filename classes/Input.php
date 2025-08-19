<?php

class Input {
    public static function sanitise(string $input): string {
        // Remove unnecessary characters
        $input = trim($input);
        // Replace forward slashes with code
        $input = stripslashes($input);
        // Replace angle brackets and HTML characters with code
        $input = htmlspecialchars($input, ENT_QUOTES);
        // Add slashes to make sure it reaches the database properly
        // Send the data back to the program
        return addslashes($input);
    }
    
    /**
     * Converts the name of a file in the selectable recordings folder to its complete path, sanitising
     * the user input and ensuring the file exists.
     *
     * @param string $name User-defined name of the file to use.
     *
     * @return string  Sanitised path to the file.
     */
    public static function fileNameToPath(string $name): string {
        $config = require __DIR__ . '/../config.php';
        
        // Using `basename` should remove any attempts at path traversal from the user input by only taking
        // the file name from the end of the provided string. We then use `realpath` to get the canonical
        // form of the path, again removing any funny-business.
        $filePath  = realpath($config["serverRecordings"]["recordingsDirectory"] . "/" . basename(Input::sanitise($name)));
        
        // Before loading the file, double-check we're in the directory we expect (or deeper)        
        if (strpos($filePath, $config["serverRecordings"]["recordingsDirectory"]) !== 0) {
            throw new Error("Path doesn't end up where we expect.");
        } else if (!file_exists($filePath)) { // and check the file exists
            throw new Error("File does not exist.");
        } else {
            return $filePath;
        }
    }
}