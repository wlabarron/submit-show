<?php


use submitShow\Database;
use submitShow\Recording;

require __DIR__ . '/requireAuth.php';
require __DIR__ . '/Input.php';
require __DIR__ . '/Email.php';
require __DIR__ . '/Recording.php';
require __DIR__ . '/Database.php';
require __DIR__ . '/Storage.php';

$config = require __DIR__ . '/config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // sanitise input
    foreach ($_POST as $item) {
        $item = Input::sanitise($item);
    }

    try {
        $recording = new Recording();
        $database  = new Database();
        $storage   = Storage::getProvider();

        $recording->setShowID($_POST["id"]);
        $recording->setName($_POST["name"]);
        $recording->setPresenter($_POST["presenter"]);
        $recording->setStart($_POST["date"]);
        $recording->setEnd($_POST["end"]);
        $recording->setFileExtension($_POST["fileName"]);

        if (!empty($_POST["imageSelection"])) {
            switch ($_POST["imageSelection"]) {
                case "upload":
                    // TODO Handle image upload
                    break;
                case "default":
                    $recording->setImage(
                        $database->getDefaultImage(
                            $recording->getShowID()
                        )
                    );
                    break;
                default:
                    // No image
            }
        }

        $recording->setDescription($_POST["description"]);
        $recording->addTag($_POST["tag1"]);
        $recording->addTag($_POST["tag2"]);
        $recording->addTag($_POST["tag3"]);
        $recording->addTag($_POST["tag4"]);
        $recording->addTag($_POST["tag5"]);

        if (isset($_POST["notifyOnSubmit"]) && $_POST["notifyOnSubmit"])
            $recording->setSubmissionAlertEmail($_SESSION['samlUserdata']["email"][0]);
        if (isset($_POST["notifyOnPublish"]) && $_POST["notifyOnPublish"])
            $recording->setPublicationAlertEmail($_SESSION['samlUserdata']["email"][0]);

        $storage->moveToWaiting($recording->getFileName());
        $recording->setLocation(Storage::$LOCATION_WAITING);

        if (isset($_POST["saveAsDefaults"]) && $_POST["saveAsDefaults"])
            $database->saveAsDefault($recording);

        $isResubmission = $database->isResubmission($recording);

        $database->saveRecording($recording);

        // TODO Display success message

        if (!empty($_SESSION['samlNameId']))
            $database->log($_SESSION['samlNameId'], "submission", $recording->getPublicationName());

        // Send notification email
        if ($isResubmission) {
            Email::send($config["smtp"]["recipient"], $recording->getPublicationName() . " re-submitted",
                "A show which was already in the system has been re-submitted:\n\n" .
                $recording->getPublicationName() . ".", $recording->getSubmissionAlertEmail());
        } else {
            Email::send($config["smtp"]["recipient"], $recording->getPublicationName() . " submitted",
                "A new show has been submitted:\n\n" .
                $recording->getPublicationName() . ".", $recording->getSubmissionAlertEmail());
        }
    } catch (Exception $e) {
        // TODO Display error message
        // TODO Multiple catch blocks for different problems
        error_log("Failed to handle submitted form: " . $e->getMessage());
    }
}

