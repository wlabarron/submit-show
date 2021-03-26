<?php

namespace SubmitShow;

use Exception;

class ShowData {
    // region Statics
    /**
     * @var int File is stored locally.
     */
    public static int $LOCATION_LOCAL = 0;
    /**
     * @var int File is stored in S3.
     */
    public static int $LOCATION_S3 = 1;
    /**
     * @var int File is waiting to be moved to S3.
     */
    public static int $LOCATION_WAITING = 2;
    /**
     * @var int File has just been uploaded and is awaiting processing.
     */
    public static int $LOCATION_HOLDING = 3;

    /**
     * @var string[] An array of valid primary tags on Mixcloud.
     */
    public static array $primaryTagOptions = array("ambient", "bass", "beats", "chillout", "classical", "deep house",
        "drum &amp; bass", "dub", "dubstep", "edm", "electronica", "funk", "garage", "hip hop", "house", "indie",
        "jazz", "pop", "rap", "reggae", "r&amp;b", "rock", "soul", "tech house", "techno", "trance", "trap", "world",
        "business", "comedy", "education", "lifestyle", "interview", "news", "politics", "science", "sport",
        "technology", "other");

    /**
     * @var ?int null
     */
    public static ?int $null = null;

    //endregion

    // region Show Details

    /**
     * @var string The database ID of the show, or "special" if the show isn't in the database.
     */
    private string $showID;
    /**
     * @var array An array of up to 5 tags describing the show. The first tag should be one of the {@see $primaryTagOptions}.
     */
    private array $tags = array();
    /**
     * @var int The UNIX timestamp of the show's end time.
     */
    private int $endTime;
    /**
     * @var string The date the show started on, in format `jS F Y` (like 4th December 2021).
     */
    private string $startDate;
    /**
     * @var string The name of the show as to be published to Mixcloud.
     */
    public string $publicationName;
    /**
     * @var ?string Description of the show -- the description of the show itself, combined with the fixed description in
     * the config file.
     */
    private ?string $description = null;
    /**
     * @var ?string The email address to which a receipt should be sent when the show is submitted, or null if no email
     * should be sent.
     */
    public ?string $submissionAlertEmail = null;
    /**
     * @var ?string The email address to which a notification should be sent when the show is published to Mixcloud, or
     * null if no email should be sent.
     */
    private ?string $publicationAlertEmail = null;
    /**
     * @var string The show's file name, in format "Presenter-Show Name 210312.type". The name should only contain
     * [A-Za-z0-9\-].
     */
    private string $fileName;
    /**
     * @var string The name of the show.
     */
    private string $showName;
    /**
     * @var string The presenter of the show.
     */
    private string $showPresenter;
    /**
     * @var int The current location of the show file.
     * @see $LOCATION_LOCAL
     * @see $LOCATION_S3
     * @see $LOCATION_HOLDING
     * @see $LOCATION_WAITING
     */
    private int $fileLocation;
    /**
     * @var ?string The image for the show, to use when publishing to Mixcloud.
     */
    private ?string $image = null;

    /**
     * @var int|null Populated by {@see isResubmission()}. `null` before population, `-1` if this show is a resubmission,
     * or the ID of the existing submission in the database.
     */
    private ?int $existingSubmissionId = null;

    // endregion

    // region Populate object data

    /**
     * Add a tag to the tags array. The first tag added should be from the list of default tags, tag max length is 20
     * characters.
     *
     * @param string|null $tag Tag to add.
     * @throws Exception If any of the tags are invalid.
     */
    function addTag(string $tag) {
        if (sizeof($this->tags) >= 6) throw new Exception("Tags array is full");

        if (!empty($tag)) {
            $tag = strtolower($tag);

            if (strlen($tag) > 20) throw new Exception("Tag was too long.");

            // If this is the first tag being added and it's not a default tag, throw exception
            if (sizeof($this->tags) == 0 && !in_array($this->tags[0], ShowData::$primaryTagOptions)) {
                throw new Exception("First tag isn't a default tag, but it should be.");
            }

            $this->tags[] = $tag;
        }
    }

    /**
     * Stores the start date of the show.
     * @param string $date The date the show started, as a string.
     * @throws Exception If the date is not provided or cannot be parsed.
     */
    function storeStartDate(string $date) {
        if (is_null($date)) throw new Exception("No date provided.");

        $start = date("jS F Y", strtotime($date));

        if ($start === false) throw new Exception("Provided date cannot be parsed.");

        $this->startDate = $start;
    }

    /**
     * Stores the end date and time of the show. {@see storeStartDate()} first.
     * @param string $time The time the show ended, as a string.
     * @param bool $nextDay `true` if the show finished the day after it started (i.e. ran over midnight).
     * @throws Exception If any parameters are invalid.
     */
    function storeEndDateTime(string $time, bool $nextDay = false) {
        if (is_null($this->startDate)) throw new Exception("No start date stored before calculating end date time.");
        if (is_null($time)) throw new Exception("No time provided.");

        // Get the datetime the show ended at
        $endDateTime = strtotime($this->startDate . " " . $time);

        // If the end datetime is invalid
        if ($endDateTime === false) throw new Exception("End datetime invalid.");

        // If the show ended the day after it started, add 1 day onto the time (so the date is the following day).
        // Store in the object.
        $this->endTime = $nextDay ? $endDateTime + 86400 // 86400 seconds = 24 hours
            : $endDateTime;        // no change
    }

    /**
     * Stores the show description (the passed part + the part in the config file), if the length is valid.
     * @param string|null $description The show-specific description to check (don't include the fixed part from the config
     *                                 file).
     * @throws Exception If the description is too long.
     */
    function storeDescription(string $description = null) {
        $config = require __DIR__ . '/config.php';

        if (is_null($description)) $this->description = $config["fixedDescription"];

        // Check description length
        if (strlen($description) > (999 - strlen($config["fixedDescription"]))) {
            throw new Exception("Description too long when combined with fixed description in config file.");
        }

        // Combined fixed description with passed description. Replace "{n}" in the fixed description with line breaks.
        $this->description = $description . "\n\n" . str_replace("{n}", "\n", $config["fixedDescription"]);
    }

    /**
     * Based on the two booleans submitted, prepare email addresses for the relevant notifications. The email address
     * is taken from SAML user data's `email` attribute.
     * @param bool $submit `true` if a notification should be sent to the submitter when they submit their show.
     * @param bool $publish `true` if a notification should be sent to the submitter when their show is published to
     *                       Mixcloud.
     */
    function storeEmailAddresses(bool $submit, bool $publish) {
        // If there is an email address in the SAML user data
        if (!is_null($_SESSION['samlUserdata']["email"][0])) {
            if ($submit) $this->submissionAlertEmail = $_SESSION['samlUserdata']["email"][0];
            if ($publish) $this->publicationAlertEmail = $_SESSION['samlUserdata']["email"][0];
        }
    }

    /**
     * Stores the show's name and presenter in the object, either by retrieving information with a given ID from the
     * database, or if the id is "special" then the provided name and presenter are used. Afterwards, {@see storePublicationName()}
     * is called to populate the publication name.
     * @param string $id ID of the show in the database, or "special" for a show not in the database.
     * @param string|null $specialName If the ID is "special", the show name to use.
     * @param string|null $specialPresenter If the ID is "special", the presenter name to use.
     * @throws Exception If the ID or special details are invalid, or nothing can be retrieved from the database.
     */
    function storeShowNameAndIdAndPresenter(string $id, string $specialName = null, string $specialPresenter = null) {
        if (is_null($id)) throw new Exception("No show name provided.");
        $this->showID = $id;

        if ($this->showID === "special") {
            if (is_null($specialName)) throw new Exception("Listed as special show, but no show name provided.");
            if (is_null($specialPresenter)) throw new Exception("Listed as special show, but no presenter name provided.");

            if (strlen($specialName) > 50) throw new Exception("Special show name is too long.");
            if (strlen($specialPresenter) > 50) throw new Exception("Special show presenter name is too long.");

            $this->showName = $specialName;
            $this->showPresenter = $specialPresenter;
        } else {
            // TODO Database connection handling
            $details = getShowDetails($this->showID, $specialName, $specialPresenter);
            if (is_null($details["name"])) throw new Exception("Couldn't retrieve show name.");
            if (is_null($details["presenter"])) throw new Exception("Couldn't retrieve show presenter.");

            $this->showName = $details["name"];
            $this->showPresenter = $details["presenter"];
        }

        $this->storePublicationName();
    }

    /**
     * Uses the show name, presenter, and start date stored in the object to generate a public-facing publication name.
     * @throws Exception If any of the prerequisite values are not stored in the object.
     */
    function storePublicationName() {
        if (is_null($this->showName)) throw new Exception("No show name in object.");
        if (is_null($this->showPresenter)) throw new Exception("No presenter name in object.");
        if (is_null($this->startDate)) throw new Exception("No start date in object.");

        // if the presenter's name is not in the show's name
        if (stristr($this->showName, $this->showPresenter) == false) {
            // prepare name in format "Show with Presenter: YYMMDD
            $this->publicationName = $this->showName . " with " . $this->showPresenter . ": " . $this->startDate;
        } else {
            // prepare name in format "Show: YYMMDD
            $this->publicationName = $this->showName . ": " . $this->startDate;
        }
    }

    /**
     * Generates a standard file name in form "Presenter Name-Show Name 210312.format" and stores in the object.
     * @param string $uploadedFileName The current file name of the show -- for example, from the form uploader.
     * @throws Exception If any prerequisite values are not in the object yet.
     */
    function storeFileName(string $uploadedFileName) {
        if (is_null($this->showName)) throw new Exception("No show name in object.");
        if (is_null($this->showPresenter)) throw new Exception("No presenter name in object.");
        if (is_null($this->startDate)) throw new Exception("No start date in object.");
        if (is_null($uploadedFileName)) throw new Exception("No pre-existing file name provided.");

        // Put the date into the correct format
        $date = date("ymd", strtotime($this->startDate));

        // Split the existing file name by '.' and take the last part (the file extension)
        $uploadedFileName = explode(".", $uploadedFileName);
        $extension = end($uploadedFileName);

        // TODO Is this step needed?
        // Decode the encoded special characters
        $name = htmlspecialchars_decode($this->showName, ENT_QUOTES);
        $presenter = htmlspecialchars_decode($this->showPresenter, ENT_QUOTES);

        // replace special characters in show details with spaces for the file name
        $name = preg_replace("/\W/", " ", $name);
        $presenter = preg_replace("/\W/", " ", $presenter);

        // replace multiple spaces with a single space and trim whitespace from ends
        $name = trim(preg_replace("/\s+/", " ", $name));
        $presenter = trim(preg_replace("/\s+/", " ", $presenter));

        $this->fileName = $presenter . "-" . $name . " " . $date . "." . $extension;
    }

    /**
     * Moves a show file from the holding directory to a more permanent storage location, and returns what that location is.
     * @throws Exception If the show file can't be found in the holding directory or can't be moved to its new home.
     */
    function moveShowFileFromHolding() {
        $config = require __DIR__ . '/config.php';

        if (is_null($this->fileName)) throw new Exception("File can't be moved as no file name stored in the show data object yet.");

        // if the file exists in the holding folder of uploaded files
        if (file_exists($config["holdingDirectory"] . "/" . $this->fileName)) {
            // if S3 is configured
            if (!empty($config["s3Endpoint"])) {
                // The show is waiting to go to S3
                $this->fileLocation = showData::$LOCATION_WAITING;
                $moveTarget = $config["waitingUploadsFolder"];
            } else {
                // The show is staying locally on this server
                $this->fileLocation = showData::$LOCATION_LOCAL;
                $moveTarget = $config["uploadFolder"];
            }

            // move the folder from the holding location to its target
            if (!rename($config["holdingDirectory"] . "/" . $this->fileName, $moveTarget . "/" . $this->fileName)) {
                throw new Exception("Couldn't move " . $this->fileName . " from holding directory to " . $this->fileLocation);
            }
        } else {
            // Can't find the uploaded show file in the holding folder
            throw new Exception("Can't find uploaded show file in the holding folder. Was looking for " .
                $this->fileName . ".");
        }
    }

    /**
     * Checks if the uploaded image meets the given criteria, and if so, returns the image as a blob.
     * @param $fileUpload object The image file upload object POSTed to the server (like {@code $_FILES["image"]}).
     * @throws Exception If the image fails validation.
     */
    function handleImageUpload(object $fileUpload) {
        // If they actually have uploaded an image
        if (!empty($fileUpload["name"])) {
            // Get and check the image's file type
            $fileType = pathinfo($fileUpload["name"], PATHINFO_EXTENSION);
            $allowTypes = array('jpg', 'png', 'jpeg');
            if (!in_array(strtolower($fileType), $allowTypes)) { // TODO better image type checking
                throw new Exception("Uploaded image format not permitted.");
            }

            $config = require 'config.php';

            // Check the image's size
            if ($fileUpload["size"] > $config["maxShowImageSize"]) {
                throw new Exception("Uploaded image too large.");
            }

            // Return the image data as a blob
            $this->image = file_get_contents($fileUpload['tmp_name']);
        }
    }

    /**
     * Gets a saved image from the database based on a given show ID.
     * @throws Exception If the request is invalid.
     */
    function getImageFromDatabase() {
        if (is_null($this->showID)) throw new Exception("No show ID in object.");
        if ($this->showID === "special") throw new Exception("Attempted to get image from database for a special show.");

        // TODO handle database connection
        $connections = require 'databaseConnections.php';

        $getSavedImageQuery = $connections["submissions"]->prepare("SELECT image FROM saved_info WHERE `show` = ?");
        $getSavedImageQuery->bind_param("i", $this->showID);
        $getSavedImageQuery->execute();
        $savedImageResults = mysqli_fetch_assoc($getSavedImageQuery->get_result());

        $this->image = $savedImageResults["image"];
    }

    // endregion

    // region Resubmissions

    /**
     * This function checks if the show which this object represents is already in the database. If it is, this is a
     * resubmission.
     *
     * Two shows with the same name, presenter, and date, will result in the same standard file name. This fact is used
     * to check if this is a resubmission.
     * @return bool `true` if the show is a resubmission, false otherwise.
     */
    function isResubmission(): bool {
        if (!is_null($this->existingSubmissionId)) return $this->existingSubmissionId >= 0;

        // TODO improve database connection
        $connections = require 'databaseConnections.php';

        // Check if there's already an entry in the database with this file name - this would be the case if a show
        // was being resubmitted
        $checkForExistingFileSubmission = $connections["submissions"]->prepare("SELECT id FROM submissions WHERE file = ? LIMIT 1");
        $checkForExistingFileSubmission->bind_param("s", $this->fileName);
        $checkForExistingFileSubmission->execute();
        $existingFileSubmissions = $checkForExistingFileSubmission->get_result()->fetch_assoc();

        if (!empty($existingFileSubmissions) && sizeof($existingFileSubmissions) > 0) {
            $this->existingSubmissionId = $existingFileSubmissions["id"];
            return true;
        } else {
            // Not a resubmission
            $this->existingSubmissionId = -1;
            return false;
        }
    }

    /**
     * If the show is a resubmission ({@see isResubmission()}), this function deletes the original submission's details
     * from the database. Otherwise, it does nothing.
     * @throws Exception If there are problems interacting with the database.
     */
    function removeOldSubmission() {
        if ($this->isResubmission()) {
            // TODO improve database connection
            $connections = require 'databaseConnections.php';

            // remove the tags associated with the existing submission
            $removeExistingSubmissionTags = $connections["submissions"]->prepare("DELETE FROM tags WHERE submission = ?");
            $removeExistingSubmissionTags->bind_param("i", $this->existingSubmissionId);
            if (!$removeExistingSubmissionTags->execute()) {
                throw new Exception("Failed to remove tags for existing submission: " . $removeExistingSubmissionTags->error);
            }

            // remove existing submission details
            $removeExistingSubmission = $connections["submissions"]->prepare("DELETE FROM submissions WHERE id = ?");
            $removeExistingSubmission->bind_param("i", $this->existingSubmissionId);
            if (!$removeExistingSubmission->execute()) {
                throw new Exception("Failed to remove tags for existing submission: " . $removeExistingSubmission->error);
            }

        }
    }

    // endregion

    // region Submissions

    /**
     * Inserts the show submission which this object represents into the database for publication.
     * @throws Exception If any prerequisite values are not yet stored in the object, or if there is a problem interacting
     * with the database.
     */
    function insertSubmission() {
        // TODO Database connection

        $location = null;
        switch ($this->fileLocation) {
            case ShowData::$LOCATION_LOCAL:
                $location = "local";
                break;
            case ShowData::$LOCATION_S3:
                $location = "s3";
                break;
            case ShowData::$LOCATION_WAITING:
                $location = "waiting";
                break;
            case ShowData::$LOCATION_HOLDING:
                $location = "holding";
                break;
            default:
                throw new Exception("Invalid file location in object.");
        }

        if (is_null($this->fileName)) throw new Exception("No file name in object.");
        if (is_null($this->publicationName)) throw new Exception("No publication name in object.");
        if (is_null($this->endTime)) throw new Exception("No end time in object.");

        $insertSubmissionQuery = $connections["submissions"]->prepare("INSERT INTO submissions (file_location, file, title, description, image, `end-datetime`, `notification-email`) VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?), ?)");
        $insertSubmissionQuery->bind_param("ssssbis", $location, $this->fileName, $this->publicationName, $this->description, ShowData::$null, $this->endTime, $this->publicationAlertEmail);
        $insertSubmissionQuery->send_long_data(4, $this->image);

        if (!$insertSubmissionQuery->execute()) throw new Exception("Failed to add show information to database: " . $insertSubmissionQuery->error);

        // Prepare a query to insert a tag into the database
        $insertTagsQuery = $connections["submissions"]->prepare("INSERT INTO tags (tag, submission) VALUES (?, (SELECT id FROM submissions WHERE file = ? LIMIT 1))");

        // iterate through the passed tags, inserting them for each
        foreach ($this->tags as $tag) {
            $insertTagsQuery->bind_param("ss", $tag, $this->fileName);
            if (!$insertTagsQuery->execute()) throw new Exception("Failed to add tag to database: " . $insertTagsQuery->error);
        }
    }

    // endregion

    // region Save as default

    /**
     * Checks if this show ID already has default values stored in the database.
     * @return bool true if defaults exist, false otherwise.
     * @throws Exception If there is a problem interacting with the database.
     */
    function showHasPreExistingDefaults(): bool {
        // TODO Database connection

        // Search for this show's ID in the saved details table
        $checkForExistingSavedDetails = $connections["submissions"]->prepare("SELECT * FROM saved_info WHERE `show` = ?");
        $checkForExistingSavedDetails->bind_param("i", $this->showID);
        if (!$checkForExistingSavedDetails->execute()) throw new Exception("Failed to check for pre-existing defaults: " . $checkForExistingSavedDetails->error);

        return mysqli_num_rows(mysqli_stmt_get_result($checkForExistingSavedDetails)) > 0;
    }

    /**
     * Stores the description, image, and tags in this object as the defaults for this show ID, replacing any pre-existing
     * defaults in the database.
     * @throws Exception If there is a problem interacting with the database.
     */
    function saveAsDefaults() {
        // TODO Database connection

        // if saved details already exist in the database for this show
        if ($this->showHasPreExistingDefaults()) {
            // delete saved tags - we'll re-insert them instead
            $tagsDeleteQuery = $connections["submissions"]->prepare("DELETE FROM saved_tags WHERE `show` = ?");
            $tagsDeleteQuery->bind_param("i", $_POST["name"]);
            if (!$tagsDeleteQuery->execute()) throw new Exception("Failed to delete pre-existing default tags: " . $tagsDeleteQuery->error);

            // prepare a query to update the entry for show details
            // ?s should match the query for adding new show details
            $saveDetailsQuery = $connections["submissions"]->prepare("UPDATE saved_info SET description = ?, image = ? WHERE `show` = ?");
        } else { // no saved details exist for this show
            // prepare a query to insert the saved show details
            // ?s should match the query for updating existing show details
            $saveDetailsQuery = $connections["submissions"]->prepare("INSERT INTO saved_info (description, image, `show`) VALUES (?, ?, ?)");
        }

        // insert details into the query
        $saveDetailsQuery->bind_param("sbi", $this->description, ShowData::$null, $this->showID);
        $saveDetailsQuery->send_long_data(1, $this->image);

        // save the show details to database
        if (!$saveDetailsQuery->execute()) throw new Exception("Failed to save default show details: " . $saveDetailsQuery->error);

        // Prepare a query for saving tags
        $saveTagsQuery = $connections["submissions"]->prepare("INSERT INTO saved_tags (`show`, tag) VALUES (?, ?)");

        // Iterate through the tags, inserting them into the database
        foreach ($this->tags as $tag) {
            $saveTagsQuery->bind_param("ss", $this->showID, $tag);
            if (!$saveTagsQuery->execute()) throw new Exception("Failed to save show tags: " . $saveTagsQuery->error);
        }
    }
    //endregion
}