<?php
require 'requireAuth.php';

$config = require 'config.php';
$connections = require 'databaseConnections.php';
require 'usefulFunctions.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // sanitise input
    foreach ($_POST as $item) {
        $item = clearUpInput($item);
    }



    $details = array();
    $showData = new \SubmitShow\ShowData();
    try {
        // TODO Construct show data object
//        $showData->storeDates($_POST["date"], $_POST["endTime"], $_POST["endedOnFollowingDay"]);
//        $showData->validateShowName($_POST["name"], $_POST["showFileUploadName"], $details["startDate"], $_POST["specialShowName"], $_POST["specialShowPresenter"]);
//        $showData->handleImage($_POST["imageSelection"], $_POST["name"]);
//        $showData->storeDescription($_POST["description"]);
//        $showData->checkIfEmail($_POST["notifyOnSubmit"], $_POST["notifyOnPublish"]);
//        // TODO tags
//        $showData->moveShowFile();
//
//        $details[] = validateShowName();
//        $details["location"] = moveShowFile($details["file"]);
//        $details["image"]    = handleImage();
//        $details["description"] =  validateDescription($_POST["description"]);
//        $details["tags"] = validateTags($_POST["tag1"], $_POST["tag2"], $_POST["tag3"], $_POST["tag4"], $_POST["tag5"]);
//        $details[] = checkIfEmail($_POST["notifyOnSubmit"], $_POST["notifyOnPublish"]);
    } catch (Exception) {
        $details = null;
        $showAlertStyling = "#submit-invalid {display:block}";
        logWithLevel("info", "Show submission had invalid input.\n" . json_encode($_POST));
    }

    /////////////////
    // HANDLE DATA //
    /////////////////

    // If the input is valid
    if (!is_null($details)) {
        // Check if there's already an entry in the database with this file name - this would be the case if a show
        // was being resubmitted
        $checkForExistingFileSubmission = $connections["submissions"]->prepare("SELECT id FROM submissions WHERE file = ? LIMIT 1");
        $checkForExistingFileSubmission->bind_param("s", $details["file"]);
        $checkForExistingFileSubmission->execute();
        $existingFileSubmissions = $checkForExistingFileSubmission->get_result()->fetch_assoc();

        $existingSubmissionRemoved = false;
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
        if (!$isResubmission || $existingSubmissionRemoved) {
            $showSubmitted = true;

            // Put the submitted description together with the fixed one
            $description = $_POST["description"] . "\n\n" . str_replace("{n}", "\n", $config["fixedDescription"]);

            // Prepare the name for the Mixcloud upload
            // if the presenter's name is not in the show's name
            if (stristr($details["show"], $details["presenter"]) == false) {
                // prepare name in format "Show with Presenter: YYMMDD
                $mixcloudName = $details["show"] . " with " . $details["presenter"] . ": " . $details["startDate"];
            } else {
                // prepare name in format "Show: YYMMDD
                $mixcloudName = $details["show"] . ": " . $details["startDate"];
            }

            // Insert the submission into the database
            $insertSubmissionQuery = $connections["submissions"]->prepare("INSERT INTO submissions (file_location, file, title, description, image, `end-datetime`, `notification-email`) VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?), ?)");
            $null = null;
            $insertSubmissionQuery->bind_param("ssssbis", $details["location"], $details["file"], $mixcloudName, $details["description"], $null, $details["endTime"], $details["publish"]);
            $insertSubmissionQuery->send_long_data(4, $details["image"]);

            if (!$insertSubmissionQuery->execute()) {
                // TODO insert failed
                $showSubmitted = false;
                logWithLevel("error", "Failed to add submission: " . $insertSubmissionQuery->error);
            }

            // Prepare a query to insert a tag into the database
            $insertTagsQuery = $connections["submissions"]->prepare("INSERT INTO tags (tag, submission) VALUES (?, (SELECT id FROM submissions WHERE file = ? LIMIT 1))");

            // iterate through the passed tags, inserting them for each
            foreach ($details["tags"] as $tag) {
                $insertTagsQuery->bind_param("ss", $tag, $details["file"]);

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
                foreach ($details["tags"] as $tag) {
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
            logWithLevel("info", "Submission for show " . $details["show"] . " recorded.");
            logToDatabase($_SESSION['samlNameId'], "submission", $details["show"]);

            // run the cron job now
            shell_exec("php cron.php");

            if ($isResubmission) {
                // send notification email
                notificationEmail($config["smtpRecipient"], $details["show"] . " re-submitted",
                    "A show which was already in the system has been re-submitted:\n\n" .
                    $details["name"] . " for " . $_POST["date"] . ".", $details["submit"]);
            } else {
                // send notification email
                notificationEmail($config["smtpRecipient"], $details["name"] . " submitted",
                    "A new show has been submitted:\n\n" .
                    $details["name"] . " for " . $_POST["date"] . ".", $details["submit"]);
            }
        } else {
            $showAlertStyling = "#submit-fail {display:block}";
            logWithLevel("error", "Submission for show " . $details["name"] . " failed.\n" . json_encode($_POST));
        }
    }
}

