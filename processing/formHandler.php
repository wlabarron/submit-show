<?php
$attributes = require 'requireAuth.php';

$config = require 'config.php';
$connections = require 'databaseConnections.php';
require 'usefulFunctions.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // sanitise input
    foreach ($_POST as $item) {
        $item = clearUpInput($item);
    }

    // put all of the tags into an array
    $tags = array();

    if (!empty($_POST["tag1"])) {
        $tags[] = strtolower($_POST["tag1"]);
    }

    if (!empty($_POST["tag2"])) {
        $tags[] = strtolower($_POST["tag2"]);
    }

    if (!empty($_POST["tag3"])) {
        $tags[] = strtolower($_POST["tag3"]);
    }

    if (!empty($_POST["tag4"])) {
        $tags[] = strtolower($_POST["tag4"]);
    }

    if (!empty($_POST["tag5"])) {
        $tags[] = strtolower($_POST["tag5"]);
    }

    // options for primary tags
    $primaryTagOptions = array("ambient",
        "bass",
        "beats",
        "chillout",
        "classical",
        "deep house",
        "drum &amp; bass",
        "dub",
        "dubstep",
        "edm",
        "electronica",
        "funk",
        "garage",
        "hip hop",
        "house",
        "indie",
        "jazz",
        "pop",
        "rap",
        "reggae",
        "r&amp;b",
        "rock",
        "soul",
        "tech house",
        "techno",
        "trance",
        "trap",
        "world",
        "business",
        "comedy",
        "education",
        "lifestyle",
        "interview",
        "news",
        "politics",
        "science",
        "sport",
        "technology",
        "other");

    /////////////////////////
    // VALIDATION AND PREP //
    /////////////////////////

    $inputValid = true;

    //////////////
    // Presence //
    //////////////
    if (is_null($_POST["date"]) ||
        is_null($_POST["endTime"]) ||
        is_null($_POST["showFileUploadName"]) ||
        is_null($_POST["imageSelection"])) {
        $inputValid = false;
        logWithLevel("debug", "Form missing parts");
        // TODO form missing parts
    } else {
        //////////////
        // Datetime //
        //////////////
        // get the datetime the show ended at
        $endDateTime = strtotime($_POST["date"] . " " . $_POST["endTime"]);

        // if the end datetime is invalid
        if ($endDateTime === false) {
            $inputValid = false;
            logWithLevel("debug", "End datetime invalid.");
            // TODO end datetime is invalid
        } else {
            // if the show ended the day after it started, add 1 day onto the time (so the date is the following day)
            if (isset($_POST["endedOnFollowingDay"])) {
                $endDateTime = date_add($endDateTime, date_interval_create_from_date_string("1 day"));
            }

            // enforce formatting of date attribute since we output it later
            $_POST["date"] = date("jS F o", strtotime($_POST["date"]));

            /////////////////
            // Description //
            /////////////////
            // check description length
            if (strlen($_POST["description"]) > (999 - strlen($config["fixedDescription"]))) {
                $inputValid = false;
                logWithLevel("debug", "Description too long.");
                // TODO description too long
            }

            //////////
            // Tags //
            //////////
            // check length of each tag
            foreach ($tags as $tag) {
                if (strlen($tag) > 20) {
                    $inputValid = false;
                    logWithLevel("debug", "Tag too long.");
                    // TODO tag too long
                }
            }

            // check if the first tag (if it exists) is one of the options for a primary tag
            if (sizeof($tags) > 0) {
                if (!in_array($tags[0], $primaryTagOptions)) {
                    $inputValid = false;
                    logWithLevel("debug", "First tag isn't a default tag.");
                    // TODO first tag isn't a default tag
                }
            }

            /////////////////////////////////////
            // Publication Notification Emails //
            /////////////////////////////////////
            // If notifyOnPublish is ticked, set the email address to send the notification to based on the authentication details
            if (isset($_POST["notifyOnPublish"])) {
                $notificationEmail = $attributes["email"][0];
            } else {
                $notificationEmail = null;
            }

            ///////////////////////////////
            // File and Publication Name //
            ///////////////////////////////
            // check length of special show details
            if (strlen($_POST["specialShowName"]) > 50 ||
                strlen($_POST["specialShowPresenter"]) > 50) {
                $inputValid = false;
                logWithLevel("debug", "Special show info is too long.");
                // TODO Special show info is too long.
            } else {
                // work out what the show's file name should be
                $showFileName = prepareFileName($_POST["name"], $_POST["showFileUploadName"], $_POST["date"], $_POST["specialShowName"], $_POST["specialShowPresenter"]);

                // note the show's details for later
                $showDetails = getShowDetails($_POST["name"], $_POST["specialShowName"], $_POST["specialShowPresenter"]);

                // if the file exists in the holding folder of uploaded files
                if (file_exists($config["holdingDirectory"] . "/" . $showFileName)) {
                    // if S3 is configured
                    if (!empty($config["s3Endpoint"])) {
                        // the show will now be waiting for transfer to S3
                        $showFileLocation = "waiting";
                        // move the folder from the holding location to waiting
                        rename($config["holdingDirectory"] . "/" . $showFileName,
                            $config["waitingUploadsFolder"] . "/" . $showFileName);
                    } else {
                        // the show stays in local storage
                        $showFileLocation = "local";
                        // move the folder from the holding location to local storage
                        rename($config["holdingDirectory"] . "/" . $showFileName,
                            $config["uploadFolder"] . "/" . $showFileName);
                    }
                } else {
                    // Can't find the uploaded show file in the holding folder
                    $inputValid = false;
                    logWithLevel("warn", "Can't find uploaded show file " . $showFileName . " in the holding folder.");
                }

                ////////////
                // Images //
                ////////////
                if ($_POST["imageSelection"] == "upload") { // if the user has chosen to upload an image
                    // If they actually have uploaded an image
                    if (!empty($_FILES["image"]["name"])) {
                        // Get image file type
                        $fileType = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);

                        // if the image's type is not allowed
                        $allowTypes = array('jpg', 'png', 'jpeg');
                        if (!in_array(strtolower($fileType), $allowTypes)) { // TODO better image type checking
                            $inputValid = false;
                            logWithLevel("debug", "Image file format invalid.");
                        } else if ($_FILES["image"]["size"] > $config["maxShowImageSize"]) { // if the image is too large
                            $inputValid = false;
                            logWithLevel("debug", "Show image too large.");
                        } else {
                            // get the image data as a blob
                            $imgContent = file_get_contents($_FILES['image']['tmp_name']);

                        }
                    } else { // if they've not uploaded an image, use null
                        $imgContent = null;
                    }
                } else if ($_POST["imageSelection"] == "saved") { // if they've chosen to use a previously-saved image
                    // get the image from the database and store it in the variable
                    $getSavedImageQuery = $connections["submissions"]->prepare("SELECT image FROM saved_info WHERE `show` = ?");
                    $getSavedImageQuery->bind_param("i", $_POST["name"]);
                    $getSavedImageQuery->execute();
                    $savedImageResults = mysqli_fetch_assoc($getSavedImageQuery->get_result());
                    $imgContent = $savedImageResults["image"];
                } else { // if they've chosen not to use an image, use null
                    $imgContent = null;
                }
            }
        }
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
            logToDatabase($attributes["identifier"][0], "submission", $showDetails["name"]);

            // run the cron job now
            shell_exec("php cron.php");

            if ($isResubmission) {
                // send notification email
                notificationEmail($config["smtpRecipient"], $showDetails["name"] . " re-submitted",
                    "A show which was already in the system has been re-submitted:\n\n" .
                    $showDetails["name"] . " for " . $_POST["date"] . ".");
            } else {
                // send notification email
                notificationEmail($config["smtpRecipient"], $showDetails["name"] . " submitted",
                    "A new show has been submitted:\n\n" .
                    $showDetails["name"] . " for " . $_POST["date"] . ".");
            }

            // run the cron job just now asynchronously
            exec("php " . __DIR__ . "/cron.php");
        } else {
            $showAlertStyling = "#submit-fail {display:block}";
            logWithLevel("error", "Submission for show " . $showDetails["name"] . " failed.\n" . json_encode($_POST));
        }
    } else {
        $showAlertStyling = "#submit-invalid {display:block}";
        logWithLevel("info", "Show submission had invalid input.\n" . json_encode($_POST));
    }
}

