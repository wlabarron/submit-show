<?php

use submitShow\Recording;

$attributes = require './processing/requireAuth.php';
$config     = require './processing/config.php';
require       'processing/formHandler.php';
require       'processing/Recording.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit Show - <?php echo $config["organisationName"]; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css"
          integrity="sha512-P5MgMn1jBN01asBgU0z60Qk4QxiXo86+wlFahKrsQf37c9cro517WzVSPPV1tDKzhku2iJ2FVgL67wG03SGnNA=="
          crossorigin="anonymous" />
    <link rel="stylesheet" href="resources/style.css?version=4">
    <noscript>
        <link rel="stylesheet" href="resources/noscript.css">
    </noscript>
</head>
<body>
<noscript>
    <div class="container">
        <div class="alert alert-danger mt-2" role="alert">
            This page needs JavaScript to work.<br>
            You'll need to <a href="https://www.enable-javascript.com/" target="_blank" class="alert-link">enable
                JavaScript</a>, then come back and refresh the page.
        </div>
    </div>
</noscript>
<!-- TODO Alert display logic -->
<div class="container hidden" id="files-unsupported">
    <div class="alert alert-danger mt-2" role="alert">
        Your browser doesn't support some of the technologies this uploader needs. Swap to another one, such as an
        up-to-date version of Firefox, then try there.<br>
        If you've not got another web browser installed, you can <a href="https://www.mozilla.org/en-GB/firefox/new/"
                                                                    target="_blank" class="alert-link">download
            Firefox</a>.
    </div>
</div>

<div class="container hidden" id="submit-success">
    <div class="alert alert-success mt-2" role="alert">
        <strong>Your show was submitted successfully.</strong> Thank you. You can upload another below, if you're so
        inclined, or leave the page.
    </div>
</div>
<div class="container hidden" id="submit-fail">
    <div class="alert alert-danger mt-2" role="alert">
        <strong>Something went wrong submitting your show.</strong> Please report this to technical staff, including the
        date and time you tried to upload your show. If your show is due to broadcast imminently, please submit your
        show
        by alternative means. Sorry about that.
    </div>
</div>
<div class="container hidden" id="submit-invalid">
    <div class="alert alert-danger mt-2" role="alert">
        <strong>Something was wrong with the submitted info, but we're not sure what.</strong> Please try again, and if
        the problem persists, please report this to technical staff, including the date and time you tried to upload
        your
        show. If your show is due to broadcast imminently, please submit your show by alternative means. Sorry about
        that.
    </div>
</div>

<div class="container" id="page-content">
    <h1>Submit Show</h1>
    <p>Submit a show for scheduling and automatic upload to Mixcloud.</p>

    <div class="form-group" id="showFileInputGroup">
        <label for="showFileInput">Show file</label>
        <div class="custom-file">
            <input type="file" class="custom-file-input" id="showFileInput" aria-describedby="showFileHelp"
                   name="showFile" accept="audio/mpeg,audio/MPA,audio/mpa-robust,.mp3,.m4a,.mp4,.aac,audio/aac,audio/aacp,audio/3gpp,audio/3gpp2,audio/mp4,audio/mp4a-latm,
audio/mpeg4-generic" required>
            <label class="custom-file-label" id="custom-file-label" for="showFileInput">Choose file</label>
            <small id="showFileHelp" class="form-text text-muted">
                You can upload MP3 or M4A (AAC) files.
            </small>
        </div>
    </div>

    <form action="index.php"
          method="POST"
          enctype="multipart/form-data">

        <input type="hidden" name="fileName" id="fileName">

        <div class="form-group">
            <label for="nameDropdown">Show name</label>
            <select class="form-control" id="nameDropdown" aria-describedby="nameDropdownHelp" name="id" required>
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

        <div class="hidden" id="nameAndPresenterEntryFields">
            <div class="form-group">
                <label for="name">Show name</label>
                <input type="text" class="form-control" id="name" aria-describedby="nameHelp" name="name" maxlength="50">
                <small id="nameHelp" class="form-text text-muted">
                    Enter the name of the show.
                </small>
            </div>

            <div class="form-group">
                <label for="presenter">Show presenter</label>
                <input type="text" class="form-control" id="presenter" aria-describedby="presenterHelp" name="presenter"
                       maxlength="50">
                <small id="presenter" class="form-text text-muted">
                    Enter the show's presenter.
                </small>
            </div>
        </div>

        <div class="form-group">
            <label for="date">Original broadcast date</label>
            <input type="date" class="form-control" id="date" aria-describedby="dateHelp" name="date" required
                   placeholder="YYYY-MM-DD">
            <small id="dateHelp" class="form-text text-muted">
                Enter the date this show was first broadcast (or when it will be broadcasted for the first time, as
                appropriate).
            </small>
        </div>

        <div class="alert alert-warning hidden" role="alert" id="error-InitialFormInvalid">
            Hang on! Make sure you've filled in all the fields above.
        </div>

        <div class="alert alert-warning mt-2 hidden" role="alert" id="error-ShowFileOversized">
            <strong>The show file you chose is too big.</strong> The maximum size
            is <?php echo $config["maxShowFileSizeFriendly"]; ?>.
            Please try again with a smaller version of the file. If you're not sure how to do this, please contact
            technical staff. Thank you.
        </div>

        <button class="btn btn-lg btn-outline-dark w-100" id="uploadAndContinueButton" type="button">Upload and Continue
        </button>

        <div id="detailsForm">
            <hr>
            <div class="form-group">
                <label for="end">Original broadcast end time</label>
                <input type="time" class="form-control" id="end" aria-describedby="endHelp"
                       name="end" step="300" placeholder="Type or choose a time..." required
                       data-toggle="tooltip" data-trigger="focus" data-placement="top"
                       title="Enter the show's end time, not the start.">
                <small id="endHelp" class="form-text text-muted">
                    Enter the time this show ended or will finish when broadcast on air. This doesn't need to be
                    to-the-minute - if your show's time slot is 12:00 - 14:00, you'd enter 14:00 here, even if you
                    finished at 13:56.
                </small>
            </div>
            <div class="form-group form-check">
                <input class="form-check-input" type="checkbox" value="true" id="endNextDay"
                       name="endNextDay" aria-describedby="endNextDayHelp">
                <label class="form-check-label" for="endNextDay">
                    Show end time is on the day after the broadcast date.
                </label>
                <small id="endNextDayHelp" class="form-text text-muted">
                    Tick this box if the show ran over midnight.
                </small>
            </div>
            <hr>
            <div class="form-group">
                <label for="showImageInput">Show cover image</label>

                <div id="defaultImage">
                    <div class="pl-5">
                        Saved photo:
                        <div class="col-md-5">
                            <img src="" alt="Previously uploaded cover image." width="100" id="defaultImage"/>
                        </div>
                    </div>
                    <select class="form-control"
                            id="imageSource"
                            name="imageSource"
                            aria-label="Choose how to proceed with the cover image">
                        <option value="saved">Use saved photo for this show</option>
                        <option value="upload" selected>Upload new photo</option>
                        <option value="none">Don't use a photo</option>
                    </select>
                </div>
            </div>

            <div class="bs-custom-file custom-file" id="imageUploader">
                <input type="file" class="custom-file-input" id="image" name="image">
                <label class="custom-file-label" for="image" aria-describedby="imageHelp">Choose file</label>
                <div class="alert alert-warning mt-2 hidden imageOversized" role="alert">
                    <strong>The image you chose is too big.</strong> The maximum size
                    is <?php echo $config["maxShowImageSizeFriendly"]; ?>.
                    Please try again with a smaller version of the file. If you're not sure how to do this, please
                    contact technical staff. Thank you.
                </div>
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
            if (isset($_SESSION['samlUserdata']["email"][0]) && !empty($_SESSION['samlUserdata']["email"][0])) {
                echo '<div class="form-group form-check">
                            <input class="form-check-input" type="checkbox" value="true" id="notifyOnSubmit"
                                   name="notifyOnSubmit">
                            <label class="form-check-label" for="notifyOnSubmit" aria-describedby="notifyOnSubmitHelp">
                                Email me a receipt when I submit this show
                            </label>
                            <small id="notifyOnSubmitHelp" class="form-text text-muted">
                                If this box is ticked, an email will be sent to ' . $_SESSION['samlUserdata']["email"][0] . ' once you press the Submit button below.
                            </small>
                        </div>
                        <div class="form-group form-check">
                            <input class="form-check-input" type="checkbox" value="true" id="notifyOnPublish"
                                   name="notifyOnPublish">
                            <label class="form-check-label" for="notifyOnPublish" aria-describedby="notifyOnPublishHelp">
                                Email me when this show is published to Mixcloud 
                            </label>
                            <small id="notifyOnPublishHelp" class="form-text text-muted">
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
                    <small id="saveAsDefaultsHelp" class="form-text text-muted">
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

            <div class="alert alert-warning mt-2 hidden imageOversized" role="alert">
                <strong>The image you chose is too big.</strong> The maximum size
                is <?php echo $config["maxShowImageSizeFriendly"]; ?>.
                Please try again with a smaller version of the file. If you're not sure how to do this, please contact
                technical staff. Thank you.
            </div>

            <!-- Submit button behaviour modified by the <form> tag -->
            <button type="submit" id="submit" class="btn btn-lg btn-outline-dark w-100" disabled>
                <i class="spinner-border"></i> Uploading...
            </button>

            <p id="uploadingHelpText" class="text-center">You can submit your show once it has uploaded.</p>

        </div>
    </form>

    <!-- Modal if show file upload fails -->
    <div class="modal fade" id="error-UploadFail" data-backdrop="static" tabindex="-1" role="dialog"
         aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="staticBackdropLabel">Upload failed</h5>
                </div>
                <div class="modal-body">
                    Something went wrong uploading your show file. Sorry about that. Please try again.
                </div>
                <div class="modal-footer">
                    <a href="index.php" class="btn btn-primary">Retry</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        const showJSON          = "<?php echo $config["showData"]["url"]; ?>";
        const showIdKey         = "<?php echo $config["showData"]["idKey"]; ?>";
        const showNameKey       = "<?php echo $config["showData"]["nameKey"]; ?>";
        const showPresenterKey  = "<?php echo $config["showData"]["presenterKey"]; ?>";
        const maxShowFileSize   = <?php echo $config["maxShowFileSize"]; ?>;
        const maxShowImageSize  = <?php echo $config["maxShowImageSize"]; ?>;
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"
            integrity="sha512-894YE6QWD5I59HgZOGReFYm4dnWc1Qt5NtvYSaNcOP+u1T9qYdvdihz0PPSiiqn/+/3e7Jo4EaG7TubfWGUrMQ=="
            crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"
            integrity="sha512-wV7Yj1alIZDqZFCUQJy85VN+qvEIly93fIQAN7iqDFCPEucLCeNFz4r35FCo9s6WrpdDQPi80xbljXB8Bjtvcg=="
            crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bs-custom-file-input/1.3.4/bs-custom-file-input.min.js"
            integrity="sha512-91BoXI7UENvgjyH31ug0ga7o1Ov41tOzbMM3+RPqFVohn1UbVcjL/f5sl6YSOFfaJp+rF+/IEbOOEwtBONMz+w=="
            crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/autosize.js/4.0.2/autosize.min.js"
            integrity="sha512-Fv9UOVSqZqj4FDYBbHkvdMFOEopbT/GvdTQfuWUwnlOC6KR49PnxOVMhNG8LzqyDf+tYivRqIWVxGdgsBWOmjg=="
            crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flow.js/2.14.1/flow.min.js"
            integrity="sha512-sl2wYWEDCu3bj5w4kyd6+bglKUxb6IPQbyflpIEJbftwtZYZp7GZQ2erVGsls9BveJIvIVW+hzM+rMQQT9Bn5w=="
            crossorigin="anonymous"></script>
    <script src="resources/script.js?version=10"></script>
</body>
</html>
