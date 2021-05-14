<?php


namespace submitShow;

use Exception;
use Storage;

/**
 * Class Recording represents a recording of a show.
 * @package submitShow
 */
class Recording {
    /**
     *
     * @var string[] An array of valid primary tags on Mixcloud.
     */
    public static array $PRIMARY_TAG_OPTIONS = array("Ambient", "Bass", "Beats", "Chillout", "Classical", "Deep House",
        "Drum &amp; Bass", "Dub", "Dubstep", "EDM", "Electronica", "Funk", "Garage", "Hip Hop", "House", "Indie",
        "Jazz", "Pop", "Rap", "Reggae", "R&amp;B", "Rock", "Soul", "Tech House", "Techno", "Trance", "Trap", "World",
        "Business", "Comedy", "Education", "Lifestyle", "Interview", "News", "Politics", "Science", "Sport",
        "Technology", "Other");

    private array $config;

    /**
     * @var string ID representing the show (not this recording specifically, the show as a whole).
     */
    private string $showID;
    /**
     * @var string The name of the show.
     */
    private string $name;
    /**
     * @var string The name of the presenter of the show.
     */
    private string $presenter;
    /**
     * @var array Tags describing the show. Maximum 5.
     */
    private array $tags = array();
    /**
     * @var string The start date of the show, in format `jS F Y` (like 4th December 2021). Used as part of the
     *             publication name.
     */
    private string $start;
    /**
     * @var int The UNIX timestamp of the show's end time. After this time, the show can be published to Mixcloud.
     */
    private int $end;
    /**
     * @var string|null A description of the show, to be used on Mixcloud.
     */
    private ?string $description;
    /**
     * @var string|null An email address to which a receipt for this show's submission should be sent, or null if no
     *                  email is to be sent. Note that this is in addition to the email address in the config file -- an
     *                  email is always sent to that address.
     */
    private ?string $submissionAlertEmail = null;
    /**
     * @var string|null An email address to which an email should be sent once the show is published to Mixcloud.
     */
    private ?string $publicationAlertEmail = null;
    /**
     * @var string The file extension used by the recording.
     */
    private string $extension;
    /**
     * @var string|null The image to be used for this file's upload to Mixcloud, or null if no image is to be used. This
     *                  is a string type and should contain a blob, which can be obtained like
     *                  {@code file_get_contents($path_to_image) }.
     */
    private ?string $image = null;
    /**
     * @var int The location of this file. One of {@code Storage::$LOCATION_HOLDING},
     * {@code Storage::$LOCATION_WAITING} or {@code Storage::$LOCATION_MAIN}.
     */
    private int $location;

    /**
     * Recording constructor.
     */
    public function __construct() {
        $this->config = require __DIR__ . "/config.php";
    }

    /**
     * @param string $showID The ID of the show, as a string.
     * @throws Exception If the show ID is empty.
     */
    public function setShowID(string $showID): void {
        if (empty($showID)) throw new Exception("Show ID was empty.");
        $this->showID = $showID;
    }

    /**
     * @param string $name The name of the show.
     * @throws Exception If validation fails -- the exception message will explain what was wrong.
     */
    public function setName(string $name): void {
        if (is_null($name) || strlen($name) == 0) throw new Exception("Show name was empty");
        if (strlen($name) > 50) throw new Exception("Show name was too long (max length is 50 characters).");
        $this->name = $name;
    }

    /**
     * @param string $presenter The show's presenter.
     * @throws Exception If validation fails -- the exception message will explain what was wrong.
     */
    public function setPresenter(string $presenter): void {
        if (is_null($presenter) || strlen($presenter) == 0) throw new Exception("Presenter name was empty");
        if (strlen($presenter) > 50) throw new Exception("Presenter name was too long (max length is 50 characters).");
        $this->presenter = $presenter;
    }

    /**
     * Adds a tag to the tags array for this show. The first tag should be one of {@code Recording::$PRIMARY_TAG_OPTIONS}.
     * Each tag can be a maximum of 20 characters long.
     * @param ?string $tag The tag to add.
     * @throws Exception If validation fails -- the exception message will explain what was wrong.
     */
    public function addTag(?string $tag): void {
        if (sizeof($this->tags) >= 6) throw new Exception("Tags array is full");

        if (!empty($tag)) {
            if (strlen($tag) > 20) throw new Exception("Tag was too long.");

            // If this is the first tag being added and it's not a default tag, throw exception
            if (sizeof($this->tags) == 0 && !in_array($tag, Recording::$PRIMARY_TAG_OPTIONS)) {
                throw new Exception("First tag isn't a default tag, but it should be.");
            }

            $this->tags[] = $tag;
        }
    }

    /**
     * @param string $start The date the show started on in any format which can be understood by {@code strtotime()}.
     * @throws Exception If the date provided is invalid.
     */
    public function setStart(string $start): void {
        if (is_null($start)) throw new Exception("No date provided.");

        $start = date("jS F Y", strtotime($start));

        if ($start === false) throw new Exception("Provided date cannot be parsed.");

        $this->start = $start;
    }

    /**
     * You must call {@code setStart()} before this.
     * @param string $time The end time of the show, as a string like "13:00".
     * @param bool $nextDay {@code true} if the show finished the day after it started (i.e. ran over midnight).
     * @throws Exception If validation fails.
     */
    public function setEnd(string $time, bool $nextDay = false): void {
        if (is_null($this->start)) throw new Exception("No start date stored before calculating end date time.");
        if (is_null($time)) throw new Exception("No time provided.");

        // Get the datetime the show ended at
        $endDateTime = strtotime($this->start . " " . $time);

        // If the end datetime is invalid
        if ($endDateTime === false) throw new Exception("End datetime invalid.");

        // If the show ended the day after it started, add 1 day onto the time (so the date is the following day).
        // Store in the object.
        $this->end = $nextDay ? $endDateTime + 86400 // 86400 seconds = 24 hours
            : $endDateTime;        // no change
    }

    /**
     * Stores the show's description, made up of the part passed to this function followed by the fixed description
     * in the config file.
     * @param string|null $description Descriptive text for this show. The maximum length is (995 - length of fixed
     *                                 description in config file).
     * @throws Exception If the description is too long.
     */
    public function setDescription(?string $description): void {
        if (is_null($description)) $this->description = null;

        // Check description length
        if (strlen($description) > (995 - strlen($this->config["fixedDescription"]))) {
            throw new Exception("Description too long when combined with fixed description in config file.");
        }

        $this->description = $description;
    }

    /**
     * @param string|null $submissionAlertEmail An email address to send a receipt to once the show has been submitted,
     *                                          in addition to the one in the config file.
     * @throws Exception If the email address is of an invalid format.
     */
    public function setSubmissionAlertEmail(?string $submissionAlertEmail): void {
        if (!filter_var($submissionAlertEmail, FILTER_VALIDATE_EMAIL)) throw new Exception("Receipt email address of invalid format.");

        $this->submissionAlertEmail = $submissionAlertEmail;
    }

    /**
     * @param string|null $publicationAlertEmail An email address to send a receipt to once the show has been published.
     * @throws Exception If the email address is of an invalid format.
     */
    public function setPublicationAlertEmail(?string $publicationAlertEmail): void {
        if (!filter_var($publicationAlertEmail, FILTER_VALIDATE_EMAIL)) throw new Exception("Receipt email address of invalid format.");
        $this->publicationAlertEmail = $publicationAlertEmail;
    }

    /**
     * Takes a full file name and takes everything after the last `.` as the file extension.
     * @param string $file The file name of the recording.
     */
    public function setFileExtension(string $file): void {
        $split = explode(".", $file);
        $this->extension = end($split);
    }

    /**
     * @param string|null $image The show image blob, obtained like {@code file_get_contents($path_to_image)}.
     */
    public function setImage(?string $image): void {
        $this->image = $image;
    }

    /**
     * Set the location of this recording's file.
     * @param int $location One of {@code Storage::$LOCATION_HOLDING},
     * {@code Storage::$LOCATION_WAITING} or {@code Storage::$LOCATION_MAIN}.
     * @throws Exception If the specified location is invalid.
     */
    public function setLocation(int $location): void {
        if ($location !== Storage::$LOCATION_HOLDING &&
            $location !== Storage::$LOCATION_WAITING &&
            $location !== Storage::$LOCATION_MAIN) throw new Exception("Invalid storage location.");

        $this->location = $location;
    }

    /**
     * @return string
     */
    public function getShowID(): string {
        return $this->showID;
    }

    /**
     * Get a nicely-formatted title of the show to publish to Mixcloud. Ensure a show name, presenter, and start time
     * are set before calling this function.
     * @return string The title to be used on Mixcloud.
     * @throws Exception If prerequisite data is not set before calling the function.
     */
    public function getPublicationName(): string {
        if (!isset($this->name)) throw new Exception("No show name stored before requesting publication name.");
        if (!isset($this->presenter)) throw new Exception("No presenter name stored before requesting publication name.");
        if (!isset($this->start)) throw new Exception("No start date  stored before requesting publication name.");

        // If the presenter's name is not in the show's name
        if (stristr($this->name, $this->presenter) == false) {
            // Prepare name in format "Show with Presenter: Dth Month Year"
            return $this->name . " with " . $this->presenter . ": " . $this->start;
        } else {
            // Prepare name in format "Show: Dth Month Year"
            return $this->name . ": " . $this->start;
        }
    }

    /**
     * @return array
     */
    public function getTags(): array {
        return $this->tags;
    }

    /**
     * @return int
     */
    public function getEnd(): int {
        return $this->end;
    }

    /**
     * @param bool $complete Set to false if you only want the part of the description which applies to this recording,
     *                       i.e. excluding the fixed section from the config file. true by default.
     * @return string|null
     */
    public function getDescription(bool $complete = true): ?string {
        if ($complete) {
            return $this->description  . "\n\n" . str_replace("{n}", "\n", $this->config["fixedDescription"]);
        } else {
            return $this->description;
        }
    }

    /**
     * @return string|null
     */
    public function getSubmissionAlertEmail(): ?string {
        return $this->submissionAlertEmail;
    }

    /**
     * @return string|null
     */
    public function getPublicationAlertEmail(): ?string {
        return $this->publicationAlertEmail;
    }

    /**
     * Constructs a file name for the recording. Ensure the show name, presenter, start date, and file extension are set
     * before calling this function.
     * @return string The file name, relative to the storage location.
     * @throws Exception If prerequisite data is missing or invalid.
     */
    public function getFileName(): string {
        if (is_null($this->name)) throw new Exception("No show name stored before requesting file name.");
        if (is_null($this->presenter)) throw new Exception("No presenter name stored before requesting file name.");
        if (is_null($this->start)) throw new Exception("No start date stored before requesting file name.");
        if (is_null($this->extension)) throw new Exception("No file extension stored before requesting file name.");

        // Put the date into the correct format
        $date = date("ymd", strtotime($this->start));

        // Decode any encoded special characters
        $name      = htmlspecialchars_decode($this->name, ENT_QUOTES);
        $presenter = htmlspecialchars_decode($this->presenter, ENT_QUOTES);

        // Replace special characters in show details with spaces
        $name      = preg_replace("/\W/", " ", $name);
        $presenter = preg_replace("/\W/", " ", $presenter);

        // replace multiple spaces with a single space and trim whitespace from ends
        $name      = trim(preg_replace("/\s+/", " ", $name));
        $presenter = trim(preg_replace("/\s+/", " ", $presenter));

        return $presenter . "-" . $name . " " . $date . "." . $this->extension;
    }

    /**
     * @return string The file name extension.
     */
    public function getExtension(): string {
        return $this->extension;
    }

    /**
     * @return string|null
     */
    public function getImage(): ?string {
        return $this->image;
    }

    /**
     * @return int
     */
    public function getLocation(): int {
        return $this->location;
    }
}