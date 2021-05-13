<?php


class Link {
    /**
     * Returned a shortened URL if YOURLS is set up, otherwise will return the passed URL.
     * @param $url string The URL to shorten.
     * @return string The shortened URL if a shortening service is set up, otherwise just the input echoed back.
     */
    static function shorten(string $url): string {
        $config = require 'config.php';

        // if YOURLS is not configured, just return the URL
        if (empty($config["yourlsSignature"])) {
            return $url;
        } else {
            // Turn the signature into a time-limited version
            $timestamp = time();
            $signature = hash('sha512', $timestamp . $config["yourlsSignature"]);

            // Set up the request
            $ch = curl_init($config["yourlsApiUrl"]);
            curl_setopt($ch, CURLOPT_HEADER, 0);            // No header in the result
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return, do not echo result
            curl_setopt($ch, CURLOPT_POST, 1);              // This is a POST request
            curl_setopt($ch, CURLOPT_POSTFIELDS, array(           // Data to POST
                'timestamp' => $timestamp,
                'hash' => 'sha512',
                'signature' => $signature,
                'action' => 'shorturl',
                'url' => $url,
                'format' => 'json'
            ));

            // Fetch and return content
            $data = curl_exec($ch);
            curl_close($ch);

            // Return the short URL
            $data = json_decode($data);
            return $data->shorturl;
        }
    }
}