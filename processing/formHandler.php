<?php
require 'requireAuth.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // sanitise input
    foreach ($_POST as $item) {
        $item = clearUpInput($item);
    }

    $showData = new \SubmitShow\ShowData();
    try {
        // Populate show data object with form information
        $showData->storeStartDate($_POST["date"]);
        $showData->storeEndDateTime($_POST["endTime"], $_POST["endedOnFollowingDay"]);
        $showData->storeDescription($_POST["description"]);
        $showData->storeEmailAddresses($_POST["notifyOnSubmit"], $_POST["notifyOnPublish"]);
        $showData->storeShowNameAndIdAndPresenter($_POST["name"], $_POST["specialShowName"], $_POST["specialShowPresenter"]);
        $showData->storeFileName($_POST["showFileUploadName"]);
        $showData->addTag($_POST["tag1"]);
        $showData->addTag($_POST["tag2"]);
        $showData->addTag($_POST["tag3"]);
        $showData->addTag($_POST["tag4"]);
        $showData->addTag($_POST["tag5"]);

        switch ($_POST["imageSelection"]) {
            case "upload":
                $showData->handleImageUpload($_FILES["image"]);
                break;
            case "saved":
                $showData->getImageFromDatabase();
                break;
            default:
                throw new Exception("Invalid image type selection.");
        }

        // Move show from its location in the holding folder
        $showData->moveShowFileFromHolding();

        // If the show is a resubmission, remove the old submission
        $showData->removeOldSubmission();

        // Record this submission to the database
        $showData->insertSubmission();

        // Save this show's details as the defaults, if the user picked that
        if (isset($_POST["saveAsDefaults"])) {
            $showData->saveAsDefaults();
        }

        // Show success message
        $showAlertStyling = "#submit-success {display:block}";

        // Log submission to the database audit log
        logToDatabase($_SESSION['samlNameId'], "submission", $details["show"]);

        // Send notification email
        if ($showData->isResubmission()) {
            notificationEmail($config["smtpRecipient"], $showData->publicationName . " re-submitted",
                "A show which was already in the system has been re-submitted:\n\n" .
                $showData->publicationName . ".", $showData->submissionAlertEmail);
        } else {
            notificationEmail($config["smtpRecipient"], $showData->publicationName . " submitted",
                "A new show has been submitted:\n\n" .
                $showData->publicationName . ".", $showData->submissionAlertEmail);
        }
    } catch (Exception $e) {
        $showAlertStyling = "#submit-invalid {display:block}";
        logWithLevel("info", "Show submission process threw an exception:\n" . $e->getMessage());
    }
}

