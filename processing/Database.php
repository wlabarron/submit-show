<?php

namespace submitShow;

use Exception;
use mysqli;
use Storage;

class Database {
    private mysqli $connection;
    private array $config;

    /**
     * Construct a new Database object, connecting to the data store specified in the config file.
     * @throws Exception If a connection to the data store can't be made.
     */
    public function __construct() {
        $this->config = require "config.php";

        // Connect to database, set charset and throw exception if connection failed.
        $this->connection = new mysqli($this->config["database"]["server"], $this->config["database"]["user"], $this->config["database"]["password"], $this->config["database"]["database"]);
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

        if (!empty($result)) {
            return $result["id"];
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
            $this->connection->commit();
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

        $insertSubmissionQuery = $this->connection->prepare("INSERT INTO submissions (`file-location`, file, title, description, image, `end-datetime`, `notification-email`) VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?), ?)");
        $insertSubmissionQuery->bind_param("isssbis", $location, $file, $publicationName, $description, $null, $end, $publicationAlertEmail);
        
        if (!is_null($image)) {
            $insertSubmissionQuery->send_long_data(4, $image);
        }

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
     * Delete a submission and its tags.
     * @param int $id The ID of the submission to delete.
     * @param bool $createTransaction Set to false to prevent this deletion operation from starting a database
     *                                transaction (for example, if the deletion is part of a larger transaction).
     * @throws Exception
     */
    public function deleteSubmission(int $id, bool $createTransaction = true) {
        if ($createTransaction)
            if (!$this->connection->begin_transaction())
                throw new Exception("Couldn't start database transaction.");

        // remove the tags associated with the existing submission
        $tagsQuery = $this->connection->prepare("DELETE FROM tags WHERE submission = ?");
        $tagsQuery->bind_param("i", $id);
        if (!$tagsQuery->execute()) {
            error_log($tagsQuery->error);
            if (!$this->connection->rollback()) error_log("Failed to rollback database.");
            throw new Exception("Failed to remove tags for existing submission.");
        }

        // remove existing submission details
        $infoQuery = $this->connection->prepare("DELETE FROM submissions WHERE id = ?");
        $infoQuery->bind_param("i", $id);
        if (!$infoQuery->execute()) {
            error_log($infoQuery->error);

            if ($createTransaction)
                if (!$this->connection->rollback())
                    error_log("Failed to rollback database.");

            throw new Exception("Failed to remove info for existing submission.");
        }

        if ($createTransaction) $this->connection->commit();
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
            $this->deleteSubmission($oldID);
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
            $this->connection->commit();
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

        $description = $recording->getDescription(false);
        $showID      = $recording->getShowID();
        $image       = $recording->getImage();

        if (is_null($showID) || empty($showID)) throw new Exception("No show ID in recording's metadata.");

        $infoQuery = $this->connection->prepare("INSERT INTO saved_info (description, image, `show`) VALUES (?, ?, ?)");
        $infoQuery->bind_param("sbi", $description, $null, $showID);
        
        if (!is_null($image)) {
            $infoQuery->send_long_data(1, $image);
        }
        
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

    /**
     * Gets the default show details from the database.
     * @param int $showID The show ID to get info about.
     * @return array An array with 2 keys: {@code description} is the show's default description. {@code tags} is an
     *               array of default tags.
     * @throws Exception
     */
    public function getDefaults(int $showID): array {
        $infoQuery = $this->connection->prepare("SELECT * FROM saved_info WHERE `show` = ?");
        $tagsQuery = $this->connection->prepare("SELECT tag FROM saved_tags WHERE `show` = ? ORDER BY id");

        $infoQuery->bind_param("i", $showID);
        $tagsQuery->bind_param("i", $showID);

        if (!$infoQuery->execute()) {
            error_log($infoQuery->error);
            throw new Exception("Failed query for default show info.");
        }
        $details = mysqli_fetch_assoc(mysqli_stmt_get_result($infoQuery));

        if (!$tagsQuery->execute()) {
            error_log($tagsQuery->error);
            throw new Exception("Failed query for default show tags.");
        }
        $tags    = mysqli_fetch_all(mysqli_stmt_get_result($tagsQuery));

        if (!empty($details["description"])) {
            $description = $details["description"];
        } else {
            $description = null;
        }

        return array(
            "description" => $description,
            "tags" => $tags
        );
    }

    /**
     * Gets the default show image from the database.
     * @param string $showID The show ID to get the image for.
     * @return string|null The image data as a blob, which can be turned into an image with a function like
     *                     {@code imagepng()}, or null if no image saved.
     * @throws Exception
     */
    public function getDefaultImage(string $showID): ?string {
        $query = $this->connection->prepare("SELECT image FROM saved_info WHERE `show` = ?");
        $query->bind_param("s", $showID);
        if (!$query->execute()) {
            error_log($query->error);
            throw new Exception("Failed query for default show image.");
        }

        $result = mysqli_fetch_assoc(mysqli_stmt_get_result($query));

        if (!empty($result["image"])) {
            return $result["image"];
        } else {
            return null;
        }
    }

    /**
     * @return array An array with details of shows due to publish.
     */
    public function getShowsForPublication(): ?array {
        $query = $this->connection->query("SELECT * FROM submissions WHERE `end-datetime` < CURRENT_TIMESTAMP AND `deletion-datetime` IS NULL");
        $result = array();
        while ($row = $query->fetch_assoc()) $result[] = $row;
        return $result;
    }
    
    /**
     * Postpones the publication of a show to Mixcloud, for example to delay submission if we are hitting a rate limit.
     * @param int $id ID of the show to postpone.
     * @param int $postponeTime The number of seconds to delay publication by.
     */
    public function postponePublication(int $id, int $postponeTime) {
        $query = $this->connection->prepare("UPDATE submissions SET `end-datetime` = `end-datetime` + INTERVAL ? SECOND WHERE id = ?;");
        $query->bind_param("ii", $postponeTime, $id);
        if (!$query->execute()) {
            error_log($query->error);
            throw new Exception("Couldn't postpone submission.");
        }
    }

    /**
     * Gets all tags associated with the given submission ID.
     * @param int $id The ID of the submission whose tags you want.
     * @return array An array of tags. Note that this could be an empty array if there are no tags.
     * @throws Exception
     */
    public function getSubmissionTags(int $id): array {
        $query = $this->connection->prepare("SELECT tag FROM tags WHERE `submission` = ? ORDER BY id");
        $query->bind_param("i", $id);
        if (!$query->execute()) {
            error_log($query->error);
            throw new Exception("Failed query for submission tags.");
        }

        $result = mysqli_fetch_all(mysqli_stmt_get_result($query));
        $tags = array();

        for ($i = 0; $i < sizeof($result); $i++) {
            array_push($tags, $result[$i][0]);
        }

        return $tags;
    }

    /**
     * Mark a submission in the database for deletion after the period specified in the config file.
     * @throws Exception
     */
    public function markSubmissionForDeletion(int $id) {
        if (empty($this->config["retentionPeriod"])) return;

        $deletionTime = date_create();
        date_add($deletionTime, date_interval_create_from_date_string($this->config["retentionPeriod"]));
        $deletionTime = $deletionTime->getTimestamp();
        
        $query = $this->connection->prepare("UPDATE submissions SET `deletion-datetime` = FROM_UNIXTIME(?) WHERE id = ?");
        $query->bind_param("ii", $deletionTime, $id);
        if (!$query->execute()) {
            error_log($query->error);
            throw new Exception("Couldn't mark submission for deletion.");
        }
    }

    /**
     * @return array An array with details of shows due to be deleted.
     */
    public function getExpiredSubmissions(): ?array {
        $query = $this->connection->query("SELECT * FROM submissions WHERE `deletion-datetime` < CURRENT_TIMESTAMP");
        $result = array();
        while ($row = $query->fetch_assoc()) $result[] = $row;
        return $result;
    }

    /**
     * @param int $id The database ID of the submission to update.
     * @param int $location The new location of the show. One of {@code Storage::$LOCATION_HOLDING},
     *                      {@code Storage::$LOCATION_WAITING} or {@code Storage::$LOCATION_MAIN}.
     * @throws Exception
     */
    public function updateLocation(int $id, int $location) {
        $query = $this->connection->prepare("UPDATE submissions SET `file-location` = ? WHERE id = ?");
        $query->bind_param("ii", $location, $id);
        if (!$query->execute()) {
            error_log($query->error);
            throw new Exception("Couldn't update submission location.");
        }
    }

    /**
     * @return array An array with details of shows which are in the waiting location.
     */
    public function getShowsToOffload(): array {
        $query  = $this->connection->query("SELECT * FROM submissions WHERE `file-location` = ". Storage::$LOCATION_WAITING);
        $result = array();
        while ($row = $query->fetch_assoc()) $result[] = $row;
        return $result;
    }

    /**
     * Logs an action to the database, but only if a user ID is provided.
     * @param string $userID The user who completed the action.
     * @param string $actionType The action completed.
     * @param string $actionDetail Details of the action.
     * @throws Exception
     */
    public function log(string $userID, string $actionType, string $actionDetail) {
        if (!empty($userID)) {
            $query = $this->connection->prepare("INSERT INTO log (user, action_type, action_detail) VALUES (?, ?, ?)");
            $query->bind_param("sss", $userID, $actionType, $actionDetail);
            if (!$query->execute()) {
                error_log($query->error);
                throw new Exception("Couldn't log info to the database.");
            }
        }
    }
}