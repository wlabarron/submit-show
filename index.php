<?php

use submitShow\Extraction;
use submitShow\Recording;

$config     = require './processing/config.php';
require       './processing/promptLogin.php';
require_once  'processing/formHandler.php';
require_once  'processing/Recording.php';
require_once  'processing/Extraction.php';

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

header("Content-Security-Policy: default-src 'self'; script-src 'self' '$jsConfigHash' https://cdnjs.cloudflare.com/ajax/libs/wavesurfer.js/7.6.0/wavesurfer.min.js https://cdnjs.cloudflare.com/ajax/libs/wavesurfer.js/7.6.0/plugins/regions.min.js https://cdnjs.cloudflare.com/ajax/libs/autosize.js/6.0.1/autosize.min.js https://cdnjs.cloudflare.com/ajax/libs/flow.js/2.14.1/ https://ajax.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.2/css/bootstrap.min.css; img-src 'self' data:; media-src 'self' blob:");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit Show - <?php echo $config["organisationName"]; ?></title>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Submit a show for scheduling and automatic upload to Mixcloud.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.2/css/bootstrap.min.css" 
        integrity="sha512-b2QcS5SsA8tZodcDtGRELiGv5SaKSk1vDHDaQRda0htPYWZ6046lr3kJ5bAAQdpV2mmA/4v0wQF9MyU6/pDIAg==" 
        crossorigin="anonymous" referrerpolicy="no-referrer" />
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
        <a href="https://www.mozilla.org/en-GB/firefox/new/" target="_blank" class="alert-link" rel="noopener noreferrer">download Firefox</a>.
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
    <p class="mb-0" id="cancel" hidden><a href="/">&#8592; Cancel and restart</a></p>
    <h1>Submit Show</h1>
    <p id="pageIntro">Submit a show for scheduling and automatic upload to Mixcloud.</p>

    <?php
        if (Extraction::isEnabled()) { 
            echo '<form id="formFileLocation" autocomplete="off">
                        <div class="form-group">
                            <label for="fileLocationInput">What would you like to do?</label>
                            <select class="form-select" id="fileLocationInput" aria-describedby="fileLocationInputHelp" name="fileLocationInput" required>
                                <option value="extract">Extract a recording from the livestream</option>
                                <option value="upload">Upload a file</option>
                            </select>
                            <small id="fileLocationInputHelp" class="form-text text-muted">
                                You can extract your show from station\'s 24/7 stream recording, or upload a file you prepared elsewhere.
                            </small>
                        </div>
                        <button class="btn btn-lg btn-outline-dark w-100" id="uploadAndContinueButton" type="submit">Continue</button>
                    </form>';
        }
    ?>

    <form id="formExtract" autocomplete="off" hidden>
        <p>
            Enter an approximate time, then we'll get you the recording from a few minutes either side of that time. You can listen and refine
            the point to exactly where it should be.
        </p>
        
        <div class="form-group" id="recordingStartGroup">
            <label for="recordingStart">Recording start</label>
            <input type="datetime-local" class="form-control" id="recordingStart" aria-describedby="recordingStartHelp"
                name="start" step=1 required>
            <small id="recordingStartHelp" class="form-text text-muted">
                Enter the approximate time you started your show. We'll refine it in the next step.
            </small>
        </div>
        
        <div class="form-group" id="recordingEndGroup" hidden>
            <label for="recordingEnd">Recording end</label>
            <input type="datetime-local" class="form-control" id="recordingEnd" aria-describedby="recordingEndHelp"
                name="end" step=1>
            <small id="recordingEndHelp" class="form-text text-muted">
                Enter the approximate time you ended your show. We'll refine it in the next step.
            </small>
        </div>
        
        <button class="btn btn-lg btn-outline-dark w-100" id="uploadAndContinueButton" type="submit">Retrieve recording</button>
    </form>
        
    <div id="preparing" class="mt-4" hidden>
        <p class="text-center">
            <i class="spinner-border"></i> Retrieving your recording...
        </p>
    </div>
    
    <form id="formEditor" class="mt-4" hidden>
        <p>
            Drag the marker to adjust, then press "Trim and continue". You can change the time above and retrieve a
            new recording if you can't find the point you need.
        </p>
        
        <div class="form-group w-25 mb-1">
            <label for="customRange1" class="form-label d-inline">Zoom</label>
            <input type="range" class="form-range d-inline" id="waveformZoom" value=0 max=40>
        </div>
        
        <div id="waveform"></div>
        
        <button class="btn btn-lg btn-outline-dark w-100 mt-4" id="uploadAndContinueButton" type="submit">Trim and continue</button>
    </form>
    
    <form id="formUpload" autocomplete="off" <?php if (Extraction::isEnabled()) { echo "hidden"; } ?>>
        <?php if (Extraction::isEnabled()) { 
            echo "<p>If you made your show file elsewhere, upload it here.</p>"; 
        } ?>
        <div class="form-group" id="showFileInputGroup">
            <label for="showFileInput">Show file</label>
            <input type="file" class="form-control" id="showFileInput" aria-describedby="showFileHelp" required
                name="showFile" accept="audio/mpeg,audio/MPA,audio/mpa-robust,.mp3,.m4a,.mp4,.aac,audio/aac,audio/aacp,audio/3gpp,audio/3gpp2,audio/mp4,audio/mp4a-latm,
        audio/mpeg4-generic">
            <small id="showFileHelp" class="form-text text-muted">
                You can upload MP3 or M4A (AAC) files.
            </small>
        </div>
        
        <button class="btn btn-lg btn-outline-dark w-100" id="uploadAndContinueButton" type="submit">Upload and continue</button>
    </form>
    
    <form id="formTitle" autocomplete="off" hidden>
        <p>Which show is this?</p>
        
        <div class="form-group">
            <label for="nameDropdown">Show</label>
            <select class="form-select" id="nameDropdown" aria-describedby="nameDropdownHelp" name="id" required>
                <option value="" disabled selected>Choose show name...</option>
                <optgroup label="Shows" id="nameOptionGroup"></optgroup>
                <optgroup label="Other">
                    <option value='special'>One-off or Special Show</option>
                </optgroup>
            </select>
            <small id="nameDropdownHelp" class="form-text text-muted">
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
                <small id="presenterHelp" class="form-text text-muted">
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

        <div class="alert alert-warning mt-2" hidden role="alert" id="error-ShowFileOversized">
            <strong>The show file you chose is too big.</strong> The maximum size
            is <?php echo $config["maxShowFileSizeFriendly"]; ?>.
            Please try again with a smaller version of the file. If you're not sure how to do this, please contact
            technical staff. Thank you.
        </div>

        <button class="btn btn-lg btn-outline-dark w-100" id="uploadAndContinueButton" type="submit">Continue</button>
    </form>

    <form action="index.php"
          method="POST"
          enctype="multipart/form-data"
          id="formDetails"
          autocomplete="off"
          hidden>
        <input type="hidden" name="fileName" id="formDetailsFileName">
        <input type="hidden" name="id" id="formDetailsNameDropdown">
        <input type="hidden" name="name" id="formDetailsName">
        <input type="hidden" name="presenter" id="formDetailsPresenter">
        <input type="hidden" name="date" id="formDetailsDate">
        
        <p>And a few more details about your show...</p>

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
                        aria-label="Choose which cover image to use">
                    <option value="default">Use saved photo for this show</option>
                    <option value="upload" selected>Upload new photo</option>
                    <option value="none">Don't use a photo</option>
                </select>
            </div>
        </div>

        <div class="form-group" id="imageUploader">
            <input type="file" class="form-control" id="image" name="image" accept="image/png,image/jpeg"
                   aria-describedby="imageHelp" aria-label="Show cover image">
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
            <select class="form-select" id="tag1" aria-label="Tag" aria-describedby="tagsHelp" name="tag1">
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

        // TODO Change wording when extracting show
        <!-- Submit button behaviour modified by the <form> tag -->
        <button type="submit" id="submit" class="btn btn-lg btn-outline-dark w-100" aria-describedby="uploadingHelpText" disabled>
            <i class="spinner-border"></i> Uploading...
        </button>

        <p id="uploadingHelpText" class="text-center">You can submit your show once it has uploaded.</p>
    </form>

    <script><?php echo $jsConfig; ?></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/wavesurfer.js/7.6.0/wavesurfer.min.js" 
        integrity="sha512-SoFKNULy/Ef0R/Ah5dKmFcOuHmw5G3XPyg39XO+QltpCDEhKrdBx6YozVuIymwG5UeehhY3U6pXh9CS1/YMu2g==" crossorigin="anonymous" 
        referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/wavesurfer.js/7.6.0/plugins/regions.min.js" 
        integrity="sha512-EY0ou/YwlWyRVx2a6uYtztDcKyqkJc/ClyvtDNF+S3xPZy+lAShDwjZqbb5z/qCmToGulKGieC11PbgbZpPzhA==" crossorigin="anonymous" 
        referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flow.js/2.14.1/flow.min.js"
            integrity="sha512-sl2wYWEDCu3bj5w4kyd6+bglKUxb6IPQbyflpIEJbftwtZYZp7GZQ2erVGsls9BveJIvIVW+hzM+rMQQT9Bn5w=="
            crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/autosize.js/6.0.1/autosize.min.js" 
        integrity="sha512-OjjaC+tijryqhyPqy7jWSPCRj7fcosu1zreTX1k+OWSwu6uSqLLQ2kxaqL9UpR7xFaPsCwhMf1bQABw2rCxMbg==" 
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="resources/script.js?version=1.1"></script>
</body>
</html>
