<?php

use submitShow\Recording;

$config     = require './processing/config.php';
require       './processing/promptLogin.php';
require_once  'processing/formHandler.php';
require_once  'processing/Recording.php';

// There's some config values which need to be available in JS. They're put in at the end of this file, but we'll
// prepare it here so we can make a hash and add it to the CSP.
$jsConfig = 'const showJSON                 = "' . $config["showData"]["url"] . '";
             const showIdKey                = "' . $config["showData"]["idKey"] . '";
             const showNameKey              = "' . $config["showData"]["nameKey"] . '";
             const showPresenterKey         = "' . $config["showData"]["presenterKey"] . '";
             const maxShowFileSize          = ' . $config["maxShowFileSize"] . ';
             const maxShowImageSize         = ' . $config["maxShowImageSize"] . ';
             const maxShowImageSizeFriendly = "' .  $config["maxShowImageSizeFriendly"] . '";';
$jsConfigHash = "sha256-" . base64_encode(hash("sha256", $jsConfig, true));

header("Content-Security-Policy: default-src 'self'; script-src 'self' '$jsConfigHash' https://cdnjs.cloudflare.com/ajax/libs/autosize.js/4.0.2/ https://cdnjs.cloudflare.com/ajax/libs/flow.js/2.14.1/ https://cdn.jsdelivr.net/npm/time-input-polyfill@1.0.10/ https://cdn.jsdelivr.net/npm/date-input-polyfill@2.14.0/ ajax.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.0.0-beta3/css/bootstrap.min.css; img-src 'self' data:");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit Show - <?php echo $config["organisationName"]; ?></title>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.0.0-beta3/css/bootstrap.min.css"
          integrity="sha512-N415hCJJdJx+1UBfULt+i+ihvOn42V/kOjOpp1UTh4CZ70Hx5bDlKryWaqEKfY/8EYOu/C2MuyaluJryK1Lb5Q=="
          crossorigin="anonymous" />
    <link rel="stylesheet" href="resources/style.css?version=5">
    <noscript>
        <link rel="stylesheet" href="resources/noscript.css">
    </noscript>
</head>
<body>
<noscript>
    <div class="container">
        <div class="alert alert-danger mt-2" role="alert">
            This page needs JavaScript to work.<br>
            You'll need to enable JavaScript, then come back and refresh the page.
        </div>
    </div>
</noscript>

<div class="container" hidden id="error-UploadFail">
    <div class="alert alert-danger mt-2" role="alert">
        Something went wrong uploading your show file. Sorry about that. Please
        <a href="index.php" class="alert-link">try again</a>.
    </div>
</div>

<div class="container" hidden id="error-FilesUnsupported">
    <div class="alert alert-danger mt-2" role="alert">
        Your browser doesn't support some of the technologies this uploader needs. Swap to another one, such as an
        up-to-date version of Firefox, then try there.<br> If you've not got another web browser installed, you can
        <a href="https://www.mozilla.org/en-GB/firefox/new/" target="_blank" class="alert-link">download Firefox</a>.
    </div>
</div>

<?php
if (isset($uploadSuccess) && $uploadSuccess) {
    echo '<div class="container">
               <div class="alert alert-success mt-2" role="alert">
                    <strong>Your show was submitted successfully.</strong> Thank you. You can upload another below, 
                    if you\'re so inclined, or leave the page.
               </div>
          </div>';
}
if (isset($uploadInvalid) && $uploadInvalid) {
    echo '<div class="container">
               <div class="alert alert-danger mt-2" role="alert">
                    <strong>Something went wrong.</strong> Please try again, and if the problem persists, please report 
                    this to technical staff, including the  date and time you tried to upload your show. If your show 
                    is due to broadcast imminently, please submit your show by alternative means. Sorry about that.
               </div>
          </div>';
}
?>

<div class="container" id="page-content">
    <h1>Submit Show</h1>
    <p>Submit a show for scheduling and automatic upload to Mixcloud.</p>

    <form id="form1" autocomplete="off">
        <div class="form-group" id="showFileInputGroup">
            <label for="showFileInput">Show file</label>
            <input type="file" class="form-control" id="showFileInput" aria-describedby="showFileHelp"
                   name="showFile" accept="audio/mpeg,audio/MPA,audio/mpa-robust,.mp3,.m4a,.mp4,.aac,audio/aac,audio/aacp,audio/3gpp,audio/3gpp2,audio/mp4,audio/mp4a-latm,
    audio/mpeg4-generic" required>
            <small id="showFileHelp" class="form-text text-muted">
                You can upload MP3 or M4A (AAC) files.
            </small>
        </div>

        <div class="form-group">
            <label for="nameDropdown">Show</label>
            <select class="form-select" id="nameDropdown" aria-describedby="nameDropdownHelp" name="id" required>
                <option value="" disabled selected>Choose show name...</option>
                <optgroup label="Shows" id="nameOptionGroup"></optgroup>
                <optgroup label="Other">
                    <option value='special'>One-off or Special Show</option>
                </optgroup>
            </select>
            <small id="nameDropdown" class="form-text text-muted">
                Show missing? Please report it to technical staff.
            </small>
        </div>

        <div hidden id="nameAndPresenterEntryFields">
            <div class="form-group">
                <label for="name">Show name</label>
                <input type="text" class="form-control" id="name" required aria-describedby="nameHelp" name="name" maxlength="50">
                <small id="nameHelp" class="form-text text-muted">
                    Enter the name of the show.
                </small>
            </div>

            <div class="form-group">
                <label for="presenter">Show presenter</label>
                <input type="text" class="form-control" id="presenter" required aria-describedby="presenterHelp" name="presenter"
                       maxlength="50">
                <small id="presenter" class="form-text text-muted">
                    Enter the show's presenter.
                </small>
            </div>
        </div>

        <div class="form-group">
            <label for="date">Original broadcast date</label>
            <input type="date" class="form-control" id="date" aria-describedby="dateHelp" name="date" required
                   placeholder="YYYY-MM-DD" min="1970-01-01">
            <small id="dateHelp" class="form-text text-muted">
                Enter the date this show was first broadcast (or when it will be broadcast for the first time, as
                appropriate).
            </small>
        </div>

        <div class="alert alert-warning" hidden role="alert" id="error-InitialFormInvalid">
            Hang on! Make sure you've filled in all the fields above correctly.
        </div>

        <div class="alert alert-warning mt-2" hidden role="alert" id="error-ShowFileOversized">
            <strong>The show file you chose is too big.</strong> The maximum size
            is <?php echo $config["maxShowFileSizeFriendly"]; ?>.
            Please try again with a smaller version of the file. If you're not sure how to do this, please contact
            technical staff. Thank you.
        </div>

        <button class="btn btn-lg btn-outline-dark w-100" id="uploadAndContinueButton" type="submit">Upload and Continue
        </button>
    </form>

    <form action="index.php"
          method="POST"
          enctype="multipart/form-data"
          id="form2"
          autocomplete="off"
          hidden>
        <input type="hidden" name="fileName" id="form2FileName">
        <input type="hidden" name="id" id="form2NameDropdown">
        <input type="hidden" name="name" id="form2Name">
        <input type="hidden" name="presenter" id="form2Presenter">
        <input type="hidden" name="date" id="form2Date">

        <hr>

        <div class="form-group">
            <label for="end">Original broadcast end time</label>
            <input type="time" class="form-control" id="end" aria-describedby="endHelp"
                   name="end" placeholder="HH:MM" required>
            <small id="endHelp" class="form-text text-muted">
                Enter the time this show ended or will finish when broadcast on air. This doesn't need to be
                to-the-minute; if your show's time slot is 12:00-14:00, you'd enter 14:00 here, even if you
                finished at 13:56.
            </small>
        </div>
        <div class="form-group form-check">
            <input class="form-check-input" type="checkbox" value="true" id="endNextDay"
                   name="endNextDay" aria-describedby="endNextDayHelp">
            <label class="form-check-label" for="endNextDay">
                Show end time is on the day after the broadcast date.
            </label>
            <small id="endNextDayHelp" class="form-text text-muted d-block">
                Tick this box if the show ran over midnight.
            </small>
        </div>
        <hr>
        <div class="form-group">
            <label for="showImageInput">Show cover image</label>

            <div id="defaultImageSection" hidden>
                <div class="ps-5">
                    Saved photo:
                    <div class="col-md-5">
                        <img src="" alt="Previously uploaded cover image." width="100" id="defaultImage"/>
                    </div>
                </div>
                <select class="form-select"
                        id="imageSource"
                        name="imageSource"
                        aria-label="Choose how to proceed with the cover image">
                    <option value="default">Use saved photo for this show</option>
                    <option value="upload" selected>Upload new photo</option>
                    <option value="none">Don't use a photo</option>
                </select>
            </div>
        </div>

        <div class="form-group" id="imageUploader">
            <input type="file" class="form-control" id="image" name="image" accept="image/png,image/jpeg"
                   aria-describedby="imageHelp">
            <small id="imageHelp" class="form-text text-muted">
                You can upload JPG or PNG files up to <?php echo $config["maxShowImageSizeFriendly"]; ?>.
            </small>
        </div>

        <hr>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea class="form-control joint-input-top" id="description" rows="3"
                      maxlength="<?php echo 995 - strlen($config["fixedDescription"]); ?>"
                      name="description"
                      aria-describedby="descriptionHelp"></textarea>
            <textarea class="form-control joint-input-bottom" readonly aria-label="Fixed description text"><?php
                 echo str_replace("{n}", "&#13;", $config["fixedDescription"]);
                ?></textarea>
            <small id="descriptionHelp" class="form-text text-muted">
                Describe what happened in your show or enter your tagline. This is a good place to include a link,
                for example, if you had a guest on your show you may want to link to their website.
            </small>
        </div>

        <hr>

        <div class="form-group">
            <label>Tags</label>
            <select class="form-control" id="tag1" aria-label="Tag" aria-describedby="tagsHelp" name="tag1">
                <option value="" disabled selected>Choose primary tag...</option>
                <?php
                    foreach (Recording::$PRIMARY_TAG_OPTIONS as $tag) {

                        echo '<option value="' . $tag . '">' . $tag. '</option>';
                    }
                ?>
            </select>
            <input type="text" class="form-control joint-input-top" aria-label="Tag" aria-describedby="tagsHelp"
                   id="tag2" name="tag2" maxlength="20">
            <input type="text" class="form-control joint-input-middle" aria-label="Tag" aria-describedby="tagsHelp"
                   id="tag3" name="tag3" maxlength="20">
            <input type="text" class="form-control joint-input-middle" aria-label="Tag" aria-describedby="tagsHelp"
                   id="tag4" name="tag4" maxlength="20">
            <input type="text" class="form-control joint-input-bottom" aria-label="Tag" aria-describedby="tagsHelp"
                   id="tag5" name="tag5" maxlength="20">
            <small id="tagsHelp" class="form-text text-muted">
                Good tags are things like the genres of music in the show. Pick the first from the dropdown, and
                type up to four more into the boxes.
            </small>
        </div>

        <hr>

        <?php
        if ($config["smtp"]["enabled"] && isset($_SESSION['samlUserdata']["email"][0]) && !empty($_SESSION['samlUserdata']["email"][0])) {
            echo '<div class="form-group form-check">
                        <input class="form-check-input" type="checkbox" value="true" id="notifyOnSubmit"
                               name="notifyOnSubmit">
                        <label class="form-check-label" for="notifyOnSubmit" aria-describedby="notifyOnSubmitHelp">
                            Email me a receipt when I submit this show
                        </label>
                        <small id="notifyOnSubmitHelp" class="form-text text-muted d-flex">
                            If this box is ticked, an email will be sent to ' . $_SESSION['samlUserdata']["email"][0] . ' once you press the Submit button below.
                        </small>
                    </div>
                    <div class="form-group form-check">
                        <input class="form-check-input" type="checkbox" value="true" id="notifyOnPublish"
                               name="notifyOnPublish">
                        <label class="form-check-label" for="notifyOnPublish" aria-describedby="notifyOnPublishHelp">
                            Email me when this show is published to Mixcloud 
                        </label>
                        <small id="notifyOnPublishHelp" class="form-text text-muted d-flex">
                            If this box is ticked, an email will be sent to ' . $_SESSION['samlUserdata']["email"][0] . ' once this show is published to Mixcloud.
                        </small>
                    </div>
                    <hr>';
        }
        ?>

        <div id="saveFormDefaultsSection">
            <div class="form-group form-check">
                <input class="form-check-input" type="checkbox" value="true" id="saveAsDefaults"
                       name="saveAsDefaults"
                       checked>
                <label class="form-check-label" for="saveAsDefaults" aria-describedby="saveAsDefaultsHelp">
                    Save these values as the defaults for this show
                </label>
                <small id="saveAsDefaultsHelp" class="form-text text-muted d-block">
                    If this box is ticked, next time you choose this show from the "Show name" list, the image,
                    description, and tags you've chosen here will appear automatically.
                </small>
            </div>
            <hr>
        </div>

        <p class="text-center">This show will be sent to the scheduling team <strong>immediately</strong> for replay
            or broadcast, as appropriate.<br>
            This show will published to Mixcloud <strong>as soon as possible after the "end" date and time specified
                above</strong>.</p>

        <!-- Submit button behaviour modified by the <form> tag -->
        <button type="submit" id="submit" class="btn btn-lg btn-outline-dark w-100" disabled>
            <i class="spinner-border"></i> Uploading...
        </button>

        <p id="uploadingHelpText" class="text-center">You can submit your show once it has uploaded.</p>
    </form>

    <script><?php echo $jsConfig; ?></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flow.js/2.14.1/flow.min.js"
            integrity="sha512-sl2wYWEDCu3bj5w4kyd6+bglKUxb6IPQbyflpIEJbftwtZYZp7GZQ2erVGsls9BveJIvIVW+hzM+rMQQT9Bn5w=="
            crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/autosize.js/4.0.2/autosize.min.js"
            integrity="sha512-Fv9UOVSqZqj4FDYBbHkvdMFOEopbT/GvdTQfuWUwnlOC6KR49PnxOVMhNG8LzqyDf+tYivRqIWVxGdgsBWOmjg=="
            crossorigin="anonymous"></script>
    <script src="resources/script.js?version=12"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-input-polyfill@2.14.0/date-input-polyfill.dist.js"
            integrity="sha256-FcR3bJqClBNWiJqgW1E9yEgSRoAqRNcjOfgRfaH0LVw="
            crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/time-input-polyfill@1.0.10/dist/time-input-polyfill.auto.min.js"
            integrity="sha256-pPvhG+ZBiZnOJYuw+caBxTfDQONb+EGX0agZ6Fmt0Ns="
            crossorigin="anonymous"></script>
</body>
</html>
