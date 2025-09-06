<?php

use submitShow\Database;
use submitShow\Recording;

require __DIR__ . '/requireAuth.php';
require __DIR__ . '/../classes/Input.php';
require __DIR__ . '/../classes/Email.php';
require __DIR__ . '/../classes/Recording.php';
require __DIR__ . '/../classes/Database.php';
require __DIR__ . '/../classes/Storage.php';

$config = require __DIR__ . '/../config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // sanitise input
    foreach ($_POST as $item) {
        $item = Input::sanitise($item);
    }

    try {
        $recording = new Recording();
        $database  = new Database();
        $storage   = Storage::getProvider();

        if ($_POST["id"] === "special") $recording->setShowID(null);
        else                            $recording->setShowID($_POST["id"]);

        $recording->setName($_POST["name"]);
        $recording->setPresenter($_POST["presenter"]);
        $recording->setStart($_POST["date"]);
        $recording->setEnd($_POST["end"], $_POST["endNextDay"]);
        $recording->setFileExtension($_POST["fileName"]);

        if (!empty($_POST["imageSource"])) {
            switch ($_POST["imageSource"]) {
                case "upload":
                    // If they actually have uploaded an image
                    if (!empty($_FILES["image"]["name"])) {
                        // Get and check the image's file type
                        // TODO better image type checking
                        $fileType = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
                        $allowTypes = array('jpg', 'png', 'jpeg');
                        if (!in_array(strtolower($fileType), $allowTypes))
                            throw new Exception("Uploaded image format not permitted.");

                        // Check the image's size
                        if ($_FILES["image"]["size"] > $config["maxShowImageSize"])
                            throw new Exception("Uploaded image too large.");

                        // Return the image data as a blob
                        $recording->setImage(file_get_contents($_FILES["image"]['tmp_name']));
                    }
                    break;
                case "default":
                    if (!empty($recording->getShowID()))
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

        $uploadSuccess = true;

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
        $uploadInvalid = true;
        // TODO Multiple catch blocks for different problems
        error_log("Failed to handle submitted form: " . $e->getMessage());
    }
}

