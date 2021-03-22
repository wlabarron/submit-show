<?php
require 'requireAuth.php';

$config = require 'config.php';
$connections = require 'databaseConnections.php';
require 'usefulFunctions.php';

/**
 * Return an array of the (up to) 5 tags passed, after validating them.
 *
 * The checks made are: first tag is from the list of default tags, tag max length is 20 characters.
 *
 * @param string|null $tag1 Tag 1, or null.
 * @param string|null $tag2 Tag 2, or null.
 * @param string|null $tag3 Tag 3, or null.
 * @param string|null $tag4 Tag 4, or null.
 * @param string|null $tag5 Tag 5, or null.
 * @return array     An array of the tags provided.
 * @throws Exception If any of the tags are invalid.
 */
function validateTags(string $tag1 = null, string $tag2 = null, string $tag3 = null, string $tag4 = null, string $tag5 = null): array {
    // options for primary tags
    $primaryTagOptions = array("ambient", "bass", "beats", "chillout", "classical", "deep house", "drum &amp; bass", "dub",
        "dubstep", "edm", "electronica", "funk", "garage", "hip hop", "house", "indie", "jazz", "pop", "rap", "reggae",
        "r&amp;b", "rock", "soul", "tech house", "techno", "trance", "trap", "world", "business", "comedy", "education",
        "lifestyle", "interview", "news", "politics", "science", "sport", "technology", "other");

    // Convert tags to lowercase and put into an array.
    $tags = array();
    if (!empty($tag1)) $tags[] = strtolower($tag1);
    if (!empty($tag2)) $tags[] = strtolower($tag2);
    if (!empty($tag3)) $tags[] = strtolower($tag3);
    if (!empty($tag4)) $tags[] = strtolower($tag4);
    if (!empty($tag5)) $tags[] = strtolower($tag5);

    if (sizeof($tags) == 0) {
        // No tags, so it's valid and good to go.
        return $tags;
    } else {

        // Check the first tag is included in the array of acceptable tags.
        if (!in_array($tags[0], $primaryTagOptions)) {
            throw new Exception("First tag isn't a default tag, but it should be.");
        }

        // Check each tag's length.
        foreach ($tags as $tag) {
            if (strlen($tag) > 20) {
                throw new Exception("A tag was too long.");
            }
        }

        // All good!
        return $tags;
    }
}

/**
 * Validates and formats the date and time of the show.
 * @param string $date The date the show started, as a string.
 * @param string $time The time the show started, as a string.
 * @param bool $nextDay `true` if the show finished the day after it started (i.e. ran over midnight).
 * @return array An array with two elements: "endTime", with the UNIX timestamp of the show's end time, and "startDate",
 *               with the date the show started on in format "jS F Y" (21st March 2021).
 * @throws Exception If any parameters are invalid.
 */
function validateEndDateTime(string $date, string $time, bool $nextDay = false): array {
    if (is_null($date)) throw new Exception("No date provided.");
    if (is_null($time)) throw new Exception("No time provided.");

    // Get the datetime the show ended at
    $endDateTime = strtotime($_POST["date"] . " " . $_POST["endTime"]);

    // If the end datetime is invalid
    if ($endDateTime === false) throw new Exception("End datetime invalid.");

    // If the show ended the day after it started, add 1 day onto the time (so the date is the following day)
    $endDateTime = $nextDay ? $endDateTime + 86400 // 86400 seconds = 24 hours
                            : $endDateTime;        // no change

    return array(
        "endTime"   => $endDateTime,
        "startDate" => date("jS F Y", strtotime($date))
    );
}

/**
 * Checks the length of the provided description combined with the length of the fixed description in the config file
 * is valid.
 * @param string|null $description The show-specific description to check (don't include the fixed part from the config
 *                                 file).
 * @return  string The passed description combined with the fixed description from the config file.
 * @throws Exception If the description is too long/
 */
function validateDescription(string $description = null): string {
    $config = require 'config.php';

    if (is_null($description)) return $config["fixedDescription"];

    // Check description length
    if (strlen($description) > (999 - strlen($config["fixedDescription"]))) {
        throw new Exception("Description too long when combined with fixed description in config file.");
    }

    // Combined fixed description with passed description. Replace "{n}" in the fixed description with line breaks.
    return $description . "\n\n" . str_replace("{n}", "\n", $config["fixedDescription"]);
}

/**
 * Based on the two booleans submitted, prepare email addresses for the relevant notifications.
 * @param bool $submit `true` if a notification should be sent to the submitter when they submit their show.
 * @param bool $publish `true` if a notification should be sent to the submitter when their show is published to
 *                       Mixcloud.
 * @return array An array with elements "submit" and "publish". Each will be either null if no email is to be sent, or
 *               the address of the email recipient according to the SAML user data.
 */
function checkIfEmail(bool $submit, bool $publish): array {
    $submitEmail = $publishEmail = null;

    // If there is an email address in the SAML user data
    if (!is_null($_SESSION['samlUserdata']["email"][0])) {
        $submitEmail = $submit ? $_SESSION['samlUserdata']["email"][0]
            : null;

        $publishEmail = $publish ? $_SESSION['samlUserdata']["email"][0]
            : null;
    }

    return array(
        "submit"  => $submitEmail,
        "publish" => $publishEmail
    );
}

/**
 * For shows from the database, this will retrieve the show's name and presenter. For special show, this will validate
 * the name and presenter.
 * @param string $name The ID of a show in the database, or "special" for special show.
 * @param string $fileName The name of the uploaded show file.
 * @param string $date The show date.
 * @param string|null $specialName If name == "special", the name of the show.
 * @param string|null $specialPresenter If name == "special", the presenter of the show.
 * @return array An array with 3 elements: "file" being the normalised file name of the show, "show" being the name of
 *               the show, and "presenter" being the show's presenter.
 * @throws Exception If any validation checks fail.
 */
function validateShowName(string $name, string $fileName, string $date, string $specialName = null, string $specialPresenter = null): array {
    if (is_null($name))     throw new Exception("No show name provided.");
    if (is_null($date))     throw new Exception("No show date provided.");
    if (is_null($fileName)) throw new Exception("No upload file name provided.");

    if ($name === "special") {
        if (is_null($specialName))      throw new Exception("Listed as special show, but no show name provided.");
        if (is_null($specialPresenter)) throw new Exception("Listed as special show, but no presenter name provided.");

        if (strlen($specialName) > 50)      throw new Exception("Special show name is too long.");
        if (strlen($specialPresenter) > 50) throw new Exception("Special show presenter name is too long.");
    }

    $details = getShowDetails($name, $specialName, $specialPresenter);
    if (is_null($details["name"]))      throw new Exception("Couldn't retrieve show name.");
    if (is_null($details["presenter"])) throw new Exception("Couldn't retrieve show presenter.");

    return array(
        "file"      => prepareFileName($name, $fileName, $date, $specialName, $specialPresenter),
        "show"      => $details["name"],
        "presenter" => $details["presenter"]
    );
}

/**
 * Moves a show file from the holding directory to a more permanent storage location, and returns what that location is.
 * @param string $fileName The file name of the show to handle.
 * @return string "local" if the show is remaining in local storage on this server, "waiting" if it is waiting to move
 *                to S3.
 * @throws Exception If the show file can't be found in the holding directory or can't be moved to its new home.
 */
function moveShowFile(string $fileName): string  {
    $config = require 'config.php';

    // if the file exists in the holding folder of uploaded files
    if (file_exists($config["holdingDirectory"] . "/" . $fileName)) {
        $location = null;

        // if S3 is configured
        if (!empty($config["s3Endpoint"])) {
            // The show is waiting to go to S3
            $location = "waiting";
            $moveTarget = $config["waitingUploadsFolder"];
        } else {
            // The show is staying locally on this server
            $location = "local";
            $moveTarget = $config["uploadFolder"];
        }

        // move the folder from the holding location to its target
        if (!rename($config["holdingDirectory"] . "/" . $fileName, $moveTarget . "/" . $fileName)) {
            throw new Exception("Couldn't move " . $fileName . " from holding directory to " . $location);
        }

        return $location;
    } else {
        // Can't find the uploaded show file in the holding folder
        throw new Exception("Can't find uploaded show file in the holding folder. Was looking for " .
            $fileName . ".");
    }
}

/**
 * Returns an image based on the criteria passed.
 * @param string $imageChoice "upload" for uploaded images, "saved" for an image saved in the database.
 * @param string|null $showName If $imageChoice == "saved", this should be the ID of the show in the database.
 * @return string|null Either the show image, or null.
 * @throws Exception If the request or image is invalid.
 */
function handleImage(string $imageChoice, string $showName = null): ?string {
    if ($imageChoice == "upload") {
        // If the user has uploaded an image
        return handleImageUpload();
    } else if ($_POST["imageSelection"] == "saved") {
        // If they've chosen to use a previously-saved image
        return getImageFromDatabase($showName);
    } else {
        $imgContent = null;
    }
}

/**
 * Checks if the uploaded image meets the given criteria, and if so, returns the image as a blob.
 * @return string|null The image, or null if there is no image.
 * @throws Exception If the image fails validation.
 */
function handleImageUpload(): ?string {
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
        return file_get_contents($_FILES['image']['tmp_name']);
    } else {
        // No image uploaded.
        return null;
    }
}

/**
 * Gets a saved image from the database based on a given show ID.
 * @param string $showName The ID of the show whose image you wish to retrieve.
 * @return string|null The image content.
 * @throws Exception If the request is invalid.
 */
function getImageFromDatabase(string $showName): ?string {
    if ($showName === "special") throw new Exception("Attempted to get image from database for a special show.");

    $connections = require 'databaseConnections.php';

    $getSavedImageQuery = $connections["submissions"]->prepare("SELECT image FROM saved_info WHERE `show` = ?");
    $getSavedImageQuery->bind_param("i", $showName);
    $getSavedImageQuery->execute();
    $savedImageResults = mysqli_fetch_assoc($getSavedImageQuery->get_result());

    return $savedImageResults["image"];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // sanitise input
    foreach ($_POST as $item) {
        $item = clearUpInput($item);
    }


    /////////////////
    // HANDLE DATA //
    /////////////////

    // If the input is valid
    if ($inputValid) {
        // Check if there's already an entry in the database with this file name - this would be the case if a show
        // was being resubmitted
        $checkForExistingFileSubmission = $connections["submissions"]->prepare("SELECT id FROM submissions WHERE file = ? LIMIT 1");
        $checkForExistingFileSubmission->bind_param("s", $showFileName);
        $checkForExistingFileSubmission->execute();
        $existingFileSubmissions = $checkForExistingFileSubmission->get_result()->fetch_assoc();

        // if a submission with this file name already exists, this is a resubmission
        if (!empty($existingFileSubmissions) && sizeof($existingFileSubmissions) > 0) {
            $isResubmission = true;
            $existingSubmissionRemoved = true;

            // get the ID of the existing submission
            $existingSubmissionID = $existingFileSubmissions["id"];

            // remove the tags associated with the existing submission
            $removeExistingSubmissionTags = $connections["submissions"]->prepare("DELETE FROM tags WHERE submission = ?");
            $removeExistingSubmissionTags->bind_param("i", $existingSubmissionID);
            if (!$removeExistingSubmissionTags->execute()) {
                // TODO tag removal failed
                $existingSubmissionRemoved = false;
                logWithLevel("error", "Failed to remove tags for existing submission: " . $removeExistingSubmissionTags->error);
            }

            // remove existing submission details
            $removeExistingSubmission = $connections["submissions"]->prepare("DELETE FROM submissions WHERE id = ?");
            $removeExistingSubmission->bind_param("i", $existingSubmissionID);
            if (!$removeExistingSubmission->execute()) {
                // TODO submission removal failed
                $existingSubmissionRemoved = false;
                logWithLevel("error", "Failed to remove existing submission: " . $removeExistingSubmission->error);
            }
        } else {
            $isResubmission = false;
        }


        // if this is not a resubmission OR if it is a resubmission and the previous submission was removed properly
        if (!$isResubmission ||
            ($isResubmission && $existingSubmissionRemoved)) {
            $showSubmitted = true;

            // Put the submitted description together with the fixed one
            $description = $_POST["description"] . "\n\n" . str_replace("{n}", "\n", $config["fixedDescription"]);

            // Prepare the name for the Mixcloud upload
            // if the presenter's name is not in the show's name
            if (stristr($showDetails["name"], $showDetails["presenter"]) == false) {
                // prepare name in format "Show with Presenter: YYMMDD
                $mixcloudName = $showDetails["name"] . " with " . $showDetails["presenter"] . ": " . $_POST["date"];
            } else {
                // prepare name in format "Show: YYMMDD
                $mixcloudName = $showDetails["name"] . ": " . $_POST["date"];
            }

            // Insert the submission into the database
            $insertSubmissionQuery = $connections["submissions"]->prepare("INSERT INTO submissions (file_location, file, title, description, image, `end-datetime`, `notification-email`) VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?), ?)");
            $null = null;
            $insertSubmissionQuery->bind_param("ssssbis", $showFileLocation, $showFileName, $mixcloudName, $description, $null, $endDateTime, $notificationEmail);
            $insertSubmissionQuery->send_long_data(4, $imgContent);

            if (!$insertSubmissionQuery->execute()) {
                // TODO insert failed
                $showSubmitted = false;
                logWithLevel("error", "Failed to add submission: " . $insertSubmissionQuery->error);
            }

            // Prepare a query to insert a tag into the database
            $insertTagsQuery = $connections["submissions"]->prepare("INSERT INTO tags (tag, submission) VALUES (?, (SELECT id FROM submissions WHERE file = ? LIMIT 1))");

            // iterate through the passed tags, inserting them for each
            foreach ($tags as $tag) {
                $insertTagsQuery->bind_param("ss", $tag, $showFileName);

                if (!$insertTagsQuery->execute()) {
                    // TODO insert failed
                    $showSubmitted = false;
                    logWithLevel("error", "Failed to add tags for submission: " . $insertTagsQuery->error);
                }
            }

            // Save the details to save
            if (isset($_POST["saveAsDefaults"])) {
                // Find out if there's already any details saved for this show
                $checkForExistingSavedDetails = $connections["submissions"]->prepare("SELECT * FROM saved_info WHERE `show` = ?");
                $checkForExistingSavedDetails->bind_param("i", $_POST["name"]);
                $checkForExistingSavedDetails->execute();

                // if saved details already exist in the database for this show
                if (mysqli_num_rows(mysqli_stmt_get_result($checkForExistingSavedDetails)) > 0) {
                    // delete saved tags - we'll re-insert them instead
                    $tagsDeleteQuery = $connections["submissions"]->prepare("DELETE FROM saved_tags WHERE `show` = ?");
                    $tagsDeleteQuery->bind_param("i", $_POST["name"]);
                    $tagsDeleteQuery->execute();

                    // prepare a query to update the entry for show details
                    // ?s should match the query for adding new show details
                    $saveDetailsQuery = $connections["submissions"]->prepare("UPDATE saved_info SET description = ?, image = ? WHERE `show` = ?");
                } else { // no saved details exist for this show
                    // prepare a query to insert the saved show details
                    // ?s should match the query for updating existing show details
                    $saveDetailsQuery = $connections["submissions"]->prepare("INSERT INTO saved_info (description, image, `show`) VALUES (?, ?, ?)");
                }

                // insert details into the query
                $saveDetailsQuery->bind_param("sbi", $_POST["description"], $null, $_POST["name"]);
                if (isset($imgContent)) {
                    $saveDetailsQuery->send_long_data(1, $imgContent);
                }
                // save the show details to database
                if (!$saveDetailsQuery->execute()) {
                    logWithLevel("error", "Failed to save show details: " . $saveDetailsQuery->error);
                    // TODO report this?
                }

                // Prepare a query for saving tags
                $saveTagsQuery = $connections["submissions"]->prepare("INSERT INTO saved_tags (`show`, tag) VALUES (?, ?)");

                // Iterate through the tags, inserting them into the database
                foreach ($tags as $tag) {
                    $saveTagsQuery->bind_param("ss", $_POST["name"], $tag);

                    if (!$saveTagsQuery->execute()) {
                        logWithLevel("error", "Failed to save show tags: " . $saveTagsQuery->error);
                        // TODO report this?
                    }
                }
            }
        } else {
            $showSubmitted = false;
        }


        // If the show was submitted successfully, log, report to user, send email, otherwise, log and report to user
        if ($showSubmitted) {
            $showAlertStyling = "#submit-success {display:block}";
            logWithLevel("info", "Submission for show " . $showDetails["name"] . " recorded.");
            logToDatabase($_SESSION['samlNameId'], "submission", $showDetails["name"]);

            // run the cron job now
            shell_exec("php cron.php");

            if ($isResubmission) {
                // send notification email
                notificationEmail($config["smtpRecipient"], $showDetails["name"] . " re-submitted",
                    "A show which was already in the system has been re-submitted:\n\n" .
                    $showDetails["name"] . " for " . $_POST["date"] . ".", $receiptEmail);
            } else {
                // send notification email
                notificationEmail($config["smtpRecipient"], $showDetails["name"] . " submitted",
                    "A new show has been submitted:\n\n" .
                    $showDetails["name"] . " for " . $_POST["date"] . ".", $receiptEmail);
            }
        } else {
            $showAlertStyling = "#submit-fail {display:block}";
            logWithLevel("error", "Submission for show " . $showDetails["name"] . " failed.\n" . json_encode($_POST));
        }
    } else {
        $showAlertStyling = "#submit-invalid {display:block}";
        logWithLevel("info", "Show submission had invalid input.\n" . json_encode($_POST));
    }
}

