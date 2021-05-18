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
}