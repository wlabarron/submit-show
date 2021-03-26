<?php


namespace SubmitShow;


class ShowData {
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
     * @var array|string[] An array of valid primary tags on Mixcloud.
     */
    public static array $primaryTagOptions = array("ambient", "bass", "beats", "chillout", "classical", "deep house",
        "drum &amp; bass", "dub", "dubstep", "edm", "electronica", "funk", "garage", "hip hop", "house", "indie",
        "jazz", "pop", "rap", "reggae", "r&amp;b", "rock", "soul", "tech house", "techno", "trance", "trap", "world",
        "business", "comedy", "education", "lifestyle", "interview", "news", "politics", "science", "sport",
        "technology", "other");

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
     * @var string Description of the show -- the description of the show itself, combined with the fixed description in
     * the config file.
     */
    private string $description;
    /**
     * @var string The email address to which a receipt should be sent when the show is submitted, or null if no email
     * should be sent.
     */
    private ?string $submissionAlertEmail = null;
    /**
     * @var string The email address to which a notification should be sent when the show is published to Mixcloud, or
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
     * @var string The image for the show, to use when publishing to Mixcloud.
     */
    private ?string $image = null;

    /**
     * Add a tag to the tags array. The first tag added should be from the list of default tags, tag max length is 20
     * characters.
     *
     * @param string|null $tag Tag to add.
     * @throws Exception If any of the tags are invalid.
     */
    function addTag(string $tag) {
        if (sizeof($this->tags >= 6)) throw new Exception("Tags array is full");

        if (!empty($tag)) {
            $tag = strtolower($tag);

            if (strlen($tag) > 20) throw new Exception("Tag was too long.");

            // If this is the first tag being added and it's not a default tag, throw exception
            if (sizeof($this->tags) == 0 && !in_array($this->tags[0], $primaryTagOptions)) {
                throw new Exception("First tag isn't a default tag, but it should be.");
            }

            $this->tags[] = $tag;
        }
    }

    /**
     * Stores the start date of the show.
     * @param string $date The date the show started, as a string.
     */
    private function storeStartDate(string $date) {
        if (is_null($date)) throw new Exception("No date provided.");
        if (is_null($time)) throw new Exception("No time provided.");

        $this->startDate = date("jS F Y", strtotime($date));
    }

    /**
     * Stores the end date and time of the show. {@see storeStartDate()} first.
     * @param string $time The time the show ended, as a string.
     * @param bool $nextDay `true` if the show finished the day after it started (i.e. ran over midnight).
     * @throws Exception If any parameters are invalid.
     */
    function storeEndDateTime(string $time, bool $nextDay = false): array {
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
    function storeDescription(string $description = null): string {
        $config = require __DIR__ . '/config.php';

        if (is_null($description)) return $config["fixedDescription"];

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
            if ($submit)  $this->submissionAlertEmail = $_SESSION['samlUserdata']["email"][0];
            if ($publish) $this->publicationAlertEmail = $_SESSION['samlUserdata']["email"][0];
        }
    }

    private function storeShowNameAndIdAndPresenter(string $id, string $specialName = null, string $specialPresenter = null) {
        if (is_null($id)) throw new Exception("No show name provided.");
        $this->showID = $id;

        if ($this->showID === "special") {
            if (is_null($specialName))      throw new Exception("Listed as special show, but no show name provided.");
            if (is_null($specialPresenter)) throw new Exception("Listed as special show, but no presenter name provided.");

            if (strlen($specialName) > 50)      throw new Exception("Special show name is too long.");
            if (strlen($specialPresenter) > 50) throw new Exception("Special show presenter name is too long.");

            $this->showName      = $specialName;
            $this->showPresenter = $specialPresenter;
        } else {
            // TODO Database connection handling
            $details = getShowDetails($name, $specialName, $specialPresenter);
            if (is_null($details["name"]))      throw new Exception("Couldn't retrieve show name.");
            if (is_null($details["presenter"])) throw new Exception("Couldn't retrieve show presenter.");

            $this->showName = $details["name"];
            $this->showPresenter = $details["presenter"];
        }
    }

    function storeFileName(string $uploadedFileName): array {
        if (is_null($this->showName))      throw new Exception("No show name in object.");
        if (is_null($this->showPresenter)) throw new Exception("No presenter name in object.");
        if (is_null($this->startDate))     throw new Exception("No start date in object.");
        if (is_null($uploadedFileName))    throw new Exception("No pre-existing file name provided.");

        // Put the date into the correct format
        $date = date("ymd", strtotime($this->startDate));

        // Split the existing file name by '.' and take the last part (the file extension)
        $extension = end(explode(".", $uploadedFileName));

        // TODO Is this step needed?
        // Decode the encoded special characters
        $name      = htmlspecialchars_decode($this->showName, ENT_QUOTES);
        $presenter = htmlspecialchars_decode($this->showPresenter, ENT_QUOTES);

        // replace special characters in show details with spaces for the file name
        $name      = preg_replace("/\W/", " ", $name);
        $presenter = preg_replace("/\W/", " ", $presenter);

        // replace multiple spaces with a single space and trim whitespace from ends
        $name      = trim(preg_replace("/\s+/", " ", $name));
        $presenter = trim(preg_replace("/\s+/", " ", $presenter));

        $this->fileName = $presenter . "-" . $name . " " . $date . "." . $extension;

    }

    /**
     * Moves a show file from the holding directory to a more permanent storage location, and returns what that location is.
     * @throws Exception If the show file can't be found in the holding directory or can't be moved to its new home.
     */
    function moveShowFileFromHolding()  {
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
     * Stores an image in this object based on the criteria passed.
     * @param string $imageChoice "upload" for uploaded images, "saved" for an image saved in the database.
     * @param string|null $showName If $imageChoice == "saved", this should be the ID of the show in the database.
     * @throws Exception If the request or image is invalid.
     */
    function handleImage(string $imageChoice) {
        if ($imageChoice == "upload") {
            // If the user has uploaded an image
            handleImageUpload();
        } else if ($imageChoice == "saved") {
            // If they've chosen to use a previously-saved image
            getImageFromDatabase($showName);
        } else {
            throw new Exception("No image choice passed.");
        }
    }

    /**
     * Checks if the uploaded image meets the given criteria, and if so, returns the image as a blob.
     * @return string|null The image, or null if there is no image.
     * @throws Exception If the image fails validation.
     */
    private function handleImageUpload(): ?string {
        // If they actually have uploaded an image
        if (!empty($_FILES["image"]["name"])) {
            // Get and check the image's file type
            $fileType = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
            $allowTypes = array('jpg', 'png', 'jpeg');
            if (!in_array(strtolower($fileType), $allowTypes)) { // TODO better image type checking
                throw new Exception("Uploaded image format not permitted.");
            }

            $config = require 'config.php';

            // Check the image's size
            if ($_FILES["image"]["size"] > $config["maxShowImageSize"]) {
                throw new Exception("Uploaded image too large.");
            }

            // Return the image data as a blob
            $this->image = file_get_contents($_FILES['image']['tmp_name']);
        }
    }

    /**
     * Gets a saved image from the database based on a given show ID.
     * @return string|null The image content.
     * @throws Exception If the request is invalid.
     */
    private function getImageFromDatabase(): ?string {
        if (is_null($this->showID))      throw new Exception("No show ID in object.");
        if ($this->showID === "special") throw new Exception("Attempted to get image from database for a special show.");

        // TODO handle database connection
        $connections = require 'databaseConnections.php';

        $getSavedImageQuery = $connections["submissions"]->prepare("SELECT image FROM saved_info WHERE `show` = ?");
        $getSavedImageQuery->bind_param("i", $showName);
        $getSavedImageQuery->execute();
        $savedImageResults = mysqli_fetch_assoc($getSavedImageQuery->get_result());

        $this->image = $savedImageResults["image"];
    }
}