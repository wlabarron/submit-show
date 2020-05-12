<?php
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

    ////////////////
    // Validation //
    ////////////////

    $inputValid = true;

    error_log(json_encode($_POST));

    if (is_null($_POST["date"]) ||
        is_null($_POST["endTime"]) ||
        is_null($_POST["tag1"]) ||
        is_null($_POST["showFileUploadName"]) ||
        is_null($_POST["imageSelection"])) {
        $inputValid = false;
        error_log("Form missing parts");
        // TODO form missing parts
    } else {
        // get the datetime the show ended at
        $endDateTime = strtotime($_POST["date"] . " " . $_POST["endTime"]);

        // if the end datetime is invalid
        if ($endDateTime === false) {
            $inputValid = false;
            error_log("End datetime invalid.");
            // TODO end datetime is invalid
        } else {
            // if the show ended the day after it started, add 1 day onto the time (so the date is the following day)
            if (isset($_POST["endedOnFollowingDay"])) {
                $endDateTime = date_add($endDateTime, date_interval_create_from_date_string("1d"));
            }

            // check description length
            if (strlen($_POST["description"]) > (999 - strlen($config["fixedDescription"]))) {
                $inputValid = false;
                error_log("Description too long.");
                // TODO description too long
            }

            // check length of each tag
            foreach ($tags as $tag) {
                if (strlen($tag) > 20) {
                    $inputValid = false;
                    error_log("Tag too long.");
                    // TODO tag too long
                }
            }

            // check if the first tag is one of the options for a primary tag
            if (!in_array($tags[0], $primaryTagOptions)) {
                $inputValid = false;
                error_log("First tag isn't a default tag.");
                // TODO first tag isn't a default tag
            }

            // Get show details
            // prepare a database query for show info
            $showDetailsQuery = $connections["details"]->prepare($config["oneShowQuery"]);
            $showDetailsQuery->bind_param("i", $_POST["name"]);

            // get the info about the show
            $showDetailsQuery->execute();
            $showDetails = mysqli_fetch_assoc($showDetailsQuery->get_result());

            // get the show's date
            $date = date("ymd", strtotime($_POST["date"]));

            // split the show file's name by "."
            $fileNameSplit = explode(".", $_POST["showFileUploadName"]);

            // have a guess at the path of the uploaded file
            $showFilePath = $config["uploadFolder"] . $showDetails["presenter"] . "-" . $showDetails["name"] . " " . $date . "." . end($fileNameSplit);

            if (!file_exists($showFilePath)) {
                $inputValid = false;
                error_log("Can't find uploaded show file. " . $showFilePath);
                // TODO can't find uploaded show file
            } else if ($_POST["imageSelection"] == "upload") {
                if (!empty($_FILES["image"]["name"])) {
                    // Get image file type
                    $fileType = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);

                    error_log($_FILES["image"]["name"]);

                    // if the image's type is allowed
                    $allowTypes = array('jpg', 'png', 'jpeg');
                    if (in_array(strtolower($fileType), $allowTypes)) { // TODO better image type checking
                        // get the image data as a blob
                        $imgContent = file_get_contents($_FILES['image']['tmp_name']);
                    } else {
                        $inputValid = false;
                        // TODO image format invalid
                    }
                } else {
                    $imgContent = null;
                }
            } else if ($_POST["imageSelection"] == "saved") {
                $getSavedImageQuery = $connections["submissions"]->prepare("SELECT image FROM saved_info WHERE `show` = ?");
                $getSavedImageQuery->bind_param("i", $_POST["name"]);
                $getSavedImageQuery->execute();
                $savedImageResults = mysqli_fetch_assoc($getSavedImageQuery->get_result());
                $imgContent = $savedImageResults["image"];
            }
        }
    }

    /////////////////////
    // Handle the data //
    /////////////////////

    if ($inputValid) {
        $showSubmitted = true;

        // Encode the description and prepare the Mixcloud name
        $description = $_POST["description"] . "\n\n" . str_replace("{n}", "\n", $config["fixedDescription"]);
        $mixcloudName = $showDetails["name"] . " with " . $showDetails["presenter"] . ": " . date("jS F o", strtotime($_POST["date"]));

        $insertSubmissionQuery = $connections["submissions"]->prepare("INSERT INTO submissions (file, title, description, image, `end-datetime`) VALUES (?, ?, ?, ?, FROM_UNIXTIME(?))");
        $null = null;
        $insertSubmissionQuery->bind_param("sssbi", $showFilePath, $mixcloudName, $description, $null, $endDateTime);
        if (isset($imgContent)) {
            $insertSubmissionQuery->send_long_data(3, $imgContent);
        }

        if (!$insertSubmissionQuery->execute()) {
            // TODO insert failed
            $showSubmitted = false;
            error_log($insertSubmissionQuery->error);
        }

        $insertTagsQuery = $connections["submissions"]->prepare("INSERT INTO tags (tag, submission) VALUES (?, (SELECT id FROM submissions WHERE file = ? LIMIT 1))");

        foreach ($tags as $tag) {
            $insertTagsQuery->bind_param("ss", $tag, $showFilePath);

            if (!$insertTagsQuery->execute()) {
                // TODO insert failed
                $showSubmitted = false;
                error_log($insertTagsQuery->error);
            }
        }

        // Save the details to save

        if (isset($_POST["saveAsDefaults"])) {
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
                error_log($saveDetailsQuery->error);
                // TODO report this?
            }

            $saveTagsQuery = $connections["submissions"]->prepare("INSERT INTO saved_tags (`show`, tag) VALUES (?, ?)");

            foreach ($tags as $tag) {
                $saveTagsQuery->bind_param("ss", $_POST["name"], $tag);

                if (!$saveTagsQuery->execute()) {
                    error_log($saveTagsQuery->error);
                    // TODO report this?
                }
            }
        }

        if ($showSubmitted) {
            // TODO rejoice
        } else {
            // TODO cry
        }
    }
} else {
    // TODO invalid input error
}