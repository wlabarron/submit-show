<?php


namespace submitShow;


use Exception;
use mysqli;

class Database {
    private mysqli $connection;

    /**
     * Construct a new Database object, connecting to the data store specified in the config file.
     * @throws Exception If a connection to the data store can't be made.
     */
    public function __construct() {
        $config = require "config.php";

        // Connect to database, set charset and throw exception if connection failed.
        $this->connection = new mysqli($config["database"]["server"], $config["database"]["user"], $config["database"]["password"], $config["database"]["database"]);
        $this->connection->query("SET NAMES 'utf8'");
        if ($this->connection->connect_error) {
            error_log("Failed to connect to database: " . $this->connection->connect_error);
            unset($this->connection);
            throw new Exception("Failed to connect to database.");
        }
    }

    /**
     * Checks if the recording already exists in the database, based on the generated file name. This would be the case
     * if a show on a given date is being re-submitted. Returns the ID of the previous submission.
     * @param Recording $recording The recording to check if is a resubmission. Ensure it's got enough data to produce a
     *                             file name using {@code $recording->getFileName()}.
     * @return int|null The ID of the previous submission, or null if there is no previous submission.
     * @throws Exception
     */
    private function getPreviousSubmissionID(Recording $recording): ?int {
        $fileName = $recording->getFileName();

        // Check if there's already an entry in the database with this file name
        // This would be the case if a show was being resubmitted
        $query = $this->connection->prepare("SELECT id FROM submissions WHERE file = ? LIMIT 1");
        $query->bind_param("s", $fileName);
        $query->execute();
        $result = $query->get_result()->fetch_assoc();

        if (!empty($result) && sizeof($result) > 0) {
            return $result[0]["id"];
        } else {
            return null;
        }
    }

    /**
     * Checks if the recording already exists in the database, based on the generated file name. This would be the case
     * if a show on a given date is being re-submitted.
     * @param Recording $recording The recording to check if is a resubmission. Ensure it's got enough data to produce a
     *                             file name using {@code $recording->getFileName()}.
     * @return bool {@code true} if show is a resubmission, {@code false} otherwise.
     * @throws Exception
     */
    public function isResubmission(Recording $recording): bool {
        return !is_null($this->getPreviousSubmissionID($recording));
    }

    /**
     * Saves the specified recording to the database, overwriting any other entries with the same file name. If you need
     * to know if a show is a resubmission, make sure to call {@code isResubmission()} before this function.
     * @param Recording $recording The recording to save.
     * @throws Exception
     */
    public function saveRecording(Recording $recording) {
        if (!$this->connection->begin_transaction()) throw new Exception("Couldn't start database transaction.");

        try {
            $this->deletePreviousSubmission($recording);
            $this->saveNewEntry($recording);
        } catch (Exception $ex) {
            error_log("An exception was thrown during saving. Rolling back the database.");
            if (!$this->connection->rollback()) error_log("Failed to rollback database.");
            throw $ex;
        }
    }

    /**
     * @param Recording $recording
     * @throws Exception
     */
    private function saveNewEntry(Recording $recording) {
        $null                  = null;

        $file                  = $recording->getFileName();
        $publicationName       = $recording->getPublicationName();
        $location              = $recording->getLocation();
        $description           = $recording->getDescription();
        $end                   = $recording->getEnd();
        $publicationAlertEmail = $recording->getPublicationAlertEmail();
        $image                 = $recording->getImage();

        if (is_null($location))    throw new Exception("No location in recording object.");
        if (is_null($end))         throw new Exception("No end time in recording object.");

        $insertSubmissionQuery = $this->connection->prepare("INSERT INTO submissions (file_location, file, title, description, image, `end-datetime`, `notification-email`) VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?), ?)");
        $insertSubmissionQuery->bind_param("ssssbis", $location, $file, $publicationName, $description, $null, $end, $publicationAlertEmail);
        $insertSubmissionQuery->send_long_data(4, $image);

        if (!$insertSubmissionQuery->execute()) {
            error_log($insertSubmissionQuery->error);
            throw new Exception("Failed to add show information to database.");
        }

        // Prepare a query to insert a tag into the database
        $insertTagsQuery = $this->connection->prepare("INSERT INTO tags (tag, submission) VALUES (?, (SELECT id FROM submissions WHERE file = ? LIMIT 1))");

        // iterate through the passed tags, inserting them for each
        foreach ($recording->getTags() as $tag) {
            $insertTagsQuery->bind_param("ss", $tag, $file);
            if (!$insertTagsQuery->execute()) {
                error_log($insertTagsQuery->error);
                throw new Exception("Failed to add tag to database.");
            }
        }
    }

    /**
     * If there is another show in the database with the same file name, delete its record from the database. If there
     * is no pre-existing show, then this function does nothing (so is safe to call anyway).
     * @param Recording $recording The recording to check for duplicates of.
     * @throws Exception
     */
    private function deletePreviousSubmission(Recording $recording) {
        $oldID = $this->getPreviousSubmissionID($recording);
        if (!is_null($oldID)) {
            // remove the tags associated with the existing submission
            $tagsQuery = $this->connection->prepare("DELETE FROM tags WHERE submission = ?");
            $tagsQuery->bind_param("i", $oldID);
            if (!$tagsQuery->execute()) {
                error_log($tagsQuery->error);
                throw new Exception("Failed to remove tags for existing submission.");
            }

            // remove existing submission details
            $infoQuery = $this->connection->prepare("DELETE FROM submissions WHERE id = ?");
            $infoQuery->bind_param("i", $oldID);
            if (!$infoQuery->execute()) {
                error_log($infoQuery->error);
                throw new Exception("Failed to remove info for existing submission.");
            }
        }
    }

    /**
     * Saves the metadata of the given recording as the defaults for its show ID.
     * @param Recording $recording The recording whose metadata should be saved.
     * @throws Exception
     */
    public function saveAsDefault(Recording $recording) {
        if (!$this->connection->begin_transaction()) throw new Exception("Couldn't start database transaction.");

        try {
            $this->deletePreviousDefaults($recording);
            $this->saveDefaultsToDatabase($recording);
        } catch (Exception $ex) {
            error_log("An exception was thrown during saving defaults. Rolling back the database.");
            if (!$this->connection->rollback()) error_log("Failed to rollback database.");
            throw $ex;
        }
    }

    /**
     * Checks for saved defaults with the same show ID and deletes them from the database if they exist. If no pre-saved
     * defaults exist, this function does nothing (so is still safe to call).
     * @param Recording $recording The recording to check for defaults for.
     * @throws Exception
     */
    private function deletePreviousDefaults(Recording $recording) {
        $showID = $recording->getShowID();

        if (is_null($showID) || empty($showID)) throw new Exception("No show ID in recording's metadata.");

        // Remove info
        $infoQuery = $this->connection->prepare("DELETE FROM saved_info WHERE `show` = ?");
        $infoQuery->bind_param("s", $showID);
        if (!$infoQuery->execute()) {
            error_log($infoQuery->error);
            throw new Exception("Failed to remove old default info from database.");
        }

        // Remove tags
        $tagsQuery = $this->connection->prepare("DELETE FROM saved_tags WHERE `show` = ?");
        $tagsQuery->bind_param("s", $showID);
        if (!$tagsQuery->execute()) {
            error_log($tagsQuery->error);
            throw new Exception("Failed to remove old default tags from database.");
        }
    }

    /**
     * Saves the details in the given recording as the default for its show ID.
     * @param Recording $recording The recording whose details are to be saved.
     * @throws Exception
     */
    private function saveDefaultsToDatabase(Recording $recording) {
        $null = null;

        $description = $recording->getDescription();
        $showID      = $recording->getShowID();
        $image       = $recording->getImage();

        if (is_null($showID) || empty($showID)) throw new Exception("No show ID in recording's metadata.");

        $infoQuery = $this->connection->prepare("INSERT INTO saved_info (description, image, `show`) VALUES (?, ?, ?)");
        $infoQuery->bind_param("sbi", $description, $null, $showID);
        $infoQuery->send_long_data(1, $image);
        if (!$infoQuery->execute()) {
            error_log($infoQuery->error);
            throw new Exception("Failed to save default details.");
        }

        $tagsQuery = $this->connection->prepare("INSERT INTO saved_tags (`show`, tag) VALUES (?, ?)");
        foreach ($recording->getTags() as $tag) {
            $tagsQuery->bind_param("ss", $showID, $tag);
            if (!$tagsQuery->execute()) {
                error_log($tagsQuery->error);
                throw new Exception("Failed to save a default tag.");
            }
        }
    }
}