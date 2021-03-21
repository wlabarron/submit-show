<?php
$attributes = require './processing/requireAuth.php';

$config = require './processing/config.php';
$connections = require './processing/databaseConnections.php';
require './processing/formHandler.php';

$shows = $connections["details"]->query($config["allShowsQuery"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit Show - <?php echo $config["organisationName"]; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.4.1/css/bootstrap.min.css"
          integrity="sha256-L/W5Wfqfa0sdBNIKN9cG6QA5F2qx4qICmU2VgLruv9Y=" crossorigin="anonymous"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css"
          integrity="sha256-h20CPZ0QyXlBuAw7A+KluUYx/3pK+c7lYEpqLTlxjYQ=" crossorigin="anonymous"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.css"
          integrity="sha256-DVV5cU9pxHH2Rofop3XD3b3rbfrOSIy2nU61DEbYy1Q=" crossorigin="anonymous"/>
    <link rel="stylesheet" href="resources/style.css?version=4">
    <style>
        <?php
            if (isset($showAlertStyling)) {
                echo $showAlertStyling;
            }
        ?>
    </style>
</head>
<body>
<!-- partial:index.partial.html -->
<noscript>
    <style>
        #page-content {
            display: none;
        }
    </style>
    <div class="container">
        <div class="alert alert-danger mt-2" role="alert">
            This page needs JavaScript to work.<br>
            You'll need to <a href="https://www.enable-javascript.com/" target="_blank" class="alert-link">enable
                JavaScript</a>, then come back and refresh the page.
        </div>
    </div>
</noscript>
<div class="container" id="files-unsupported">
    <div class="alert alert-danger mt-2" role="alert">
        Your browser doesn't support some of the technologies this uploader needs. Swap to another one, such as an
        up-to-date version of Firefox, then try there.<br>
        If you've not got another web browser installed, you can <a href="https://www.mozilla.org/en-GB/firefox/new/"
                                                                    target="_blank" class="alert-link">download
            Firefox</a>.
    </div>
</div>
<div class="container" id="submit-success">
    <div class="alert alert-success mt-2" role="alert">
        <strong>Your show was submitted successfully.</strong> Thank you. You can upload another below, if you're so
        inclined, or leave the page.
    </div>
</div>
<div class="container" id="submit-fail">
    <div class="alert alert-danger mt-2" role="alert">
        <strong>Something went wrong submitting your show.</strong> Please report this to technical staff, including the
        date and time you tried to upload your show. If your show is due to broadcast imminently, please submit your
        show
        by alternative means. Sorry about that.
    </div>
</div>
<div class="container" id="submit-invalid">
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

    <form action="/index.php"
          method="POST"
          enctype="multipart/form-data"
          onsubmit='// Remove warning when navigating away
                      window.onbeforeunload = null;
                      $("#submit").html("<i class=\"fas fa-circle-notch fa-spin \"></i> Submitting...");
                      $("#submit").prop("disabled", true);
                      $("#submit").removeClass("btn-outline-success");
                      $("#submit").addClass("btn-outline-dark");'
    >
        <input type="hidden" name="showFileUploadName" id="showFileUploadName">
        <input type="hidden" name="name" id="hiddenShowName">

        <div class="form-group">
            <label for="showNameInput">Show name</label>
            <select class="form-control" id="showNameInput" aria-describedby="showNameHelp" name="name"
                    onchange="handleSpecialShowInput()" required>
                <option value="" disabled selected>Choose show name...</option>
                <optgroup label="Shows">
                    <?php
                    while ($show = $shows->fetch_assoc()) {
                        echo "<option value='" . $show["id"] . "'>" . $show["name"] . "</option>";
                    }
                    ?>
                </optgroup>
                <optgroup label="Other">
                    <option value='special'>One-off or Special Show</option>
                </optgroup>
            </select>
            <small id="showNameHelp" class="form-text text-muted">
                Show missing? Please report it to technical staff.
            </small>
        </div>

        <div id="specialShowDetails">
            <div class="form-group">
                <label for="specialShowName">Show name</label>
                <input type="text" class="form-control" id="specialShowName" aria-describedby="specialShowNameHelp"
                       name="specialShowName" maxlength="50">
                <small id="specialShowNameHelp" class="form-text text-muted">
                    Enter the name of the show.
                </small>
            </div>
            <div class="form-group">
                <label for="specialShowPresenter">Show presenter</label>
                <input type="text" class="form-control" id="specialShowPresenter"
                       aria-describedby="specialShowPresenterHelp" name="specialShowPresenter" maxlength="50">
                <small id="specialShowPresenterHelp" class="form-text text-muted">
                    Enter the show's presenter.
                </small>
            </div>
        </div>

        <div class="form-group bootstrap-timepicker timepicker">
            <label for="broadcast-date">Original broadcast date</label>
            <input type="text" class="form-control make-disabled-input-appear-normal" id="broadcast-date"
                   aria-describedby="broadcastDateHelp" name="date" required maxlength="30" readonly
                   placeholder="Choose a date...">
            <small id="broadcastDateHelp" class="form-text text-muted">
                Enter the date this show was first broadcast (or when it will be broadcasted for the first time, as
                appropriate).
            </small>
        </div>

        <div class="alert alert-warning" role="alert" id="initial-form-invalid">
            Hang on! Make sure you've filled in all the fields above.
        </div>

        <div class="alert alert-warning mt-2" role="alert" id="show-file-oversized">
            <strong>The show file you chose is too big.</strong> The maximum size
            is <?php echo $config["maxShowFileSizeFriendly"]; ?>.
            Please try again with a smaller version of the file. If you're not sure how to do this, please contact
            technical staff. Thank you.
        </div>

        <button class="btn btn-lg btn-outline-dark w-100" id="uploadAndContinueButton" onclick="uploadAndContinue()"
                type="button">Upload and Continue
        </button>

        <div id="detailsForm">
            <hr>
            <div class="form-group">
                <label for="broadcast-time">Original broadcast end time</label>
                <input type="text" class="form-control" id="broadcast-time" aria-describedby="broadcastEndTimeHelp"
                       name="endTime" step="300" placeholder="Type or choose a time..." required
                       data-toggle="tooltip" data-trigger="focus" data-placement="top"
                       title="Enter the show's end time, not the start.">
                <small id="broadcastEndTimeHelp" class="form-text text-muted">
                    Enter the time this show ended or will finish when broadcast on air. This doesn't need to be
                    to-the-minute - if your show's time slot is noon-2pm, you'd enter 2pm here, even if you finished at
                    1:56pm.
                </small>
            </div>
            <div class="form-group form-check">
                <input class="form-check-input" type="checkbox" value="true" id="endedOnFollowingDay"
                       name="endedOnFollowingDay" aria-describedby="endedOnFollowingDayHelp">
                <label class="form-check-label" for="endedOnFollowingDay">
                    Show end time is on the day after the broadcast date.
                </label>
                <small id="endedOnFollowingDayHelp" class="form-text text-muted">
                    Tick this box if the show ran over midnight.
                </small>
            </div>
            <hr>
            <div class="form-group">
                <label for="showImageInput">Show cover image</label>
                <div id="defaultImageDisplay">
                    <div class="pl-5">
                        Saved photo:
                        <div class="col-md-5">
                            <img src="" alt="Previously uploaded cover image." width="100" id="defaultImage"/>
                        </div>
                    </div>
                    <select class="form-control"
                            id="imageSelection"
                            aria-label="Choose how to proceed with the cover image"
                            name="imageSelection"
                            onchange="changeShowImageSelection();">
                        <option value="saved">Use saved photo for this show</option>
                        <option value="upload" selected>Upload new photo</option>
                        <option value="none">Don't use a photo</option>
                    </select>
                </div>
            </div>
            <div class="bs-custom-file custom-file" id="imageFileUploader">
                <input type="file" class="custom-file-input" id="showImageInput" name="image"
                       onchange="checkShowCoverImageSize()">
                <label class="custom-file-label" for="showImageInput" aria-describedby="showImageInputHelp">Choose
                    file</label>
                <div class="alert alert-warning mt-2 show-image-oversized" role="alert">
                    <strong>The image you chose is too big.</strong> The maximum size
                    is <?php echo $config["maxShowImageSizeFriendly"]; ?>.
                    Please try again with a smaller version of the file. If you're not sure how to do this, please
                    contact technical staff. Thank you.
                </div>
                <small id="showImageInputHelp" class="form-text text-muted">
                    You can upload JPG or PNG files up to <?php echo $config["maxShowImageSizeFriendly"]; ?>.
                </small>
            </div>
            <hr>
            <div class="form-group">
                <label for="descriptionInput">Description</label>
                <textarea class="form-control joint-input-top" id="descriptionInput" rows="3"
                          maxlength="<?php echo 999 - strlen($config["fixedDescription"]); ?>"
                          name="description"
                          aria-describedby="descriptionHelp"></textarea>
                <textarea class="form-control joint-input-bottom" type="text" readonly
                          aria-label="Fixed description text"><?php echo str_replace("{n}", "&#13;", $config["fixedDescription"]); ?></textarea>
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
                    <option value="ambient">Ambient</option>
                    <option value="bass">Bass</option>
                    <option value="beats">Beats</option>
                    <option value="chillout">Chillout</option>
                    <option value="classical">Classical</option>
                    <option value="deep house">Deep House</option>
                    <option value="drum &amp; bass">Drum &amp; Bass</option>
                    <option value="dub">Dub</option>
                    <option value="dubstep">Dubstep</option>
                    <option value="edm">EDM</option>
                    <option value="electronica">Electronica</option>
                    <option value="funk">Funk</option>
                    <option value="garage">Garage</option>
                    <option value="hip hop">Hip Hop</option>
                    <option value="house">House</option>
                    <option value="indie">Indie</option>
                    <option value="jazz">Jazz</option>
                    <option value="pop">Pop</option>
                    <option value="rap">Rap</option>
                    <option value="reggae">Reggae</option>
                    <option value="r&amp;b">R&amp;B</option>
                    <option value="rock">Rock</option>
                    <option value="soul">Soul</option>
                    <option value="tech house">Tech House</option>
                    <option value="techno">Techno</option>
                    <option value="trance">Trance</option>
                    <option value="trap">Trap</option>
                    <option value="world">World</option>
                    <option value="business">Business</option>
                    <option value="comedy">Comedy</option>
                    <option value="education">Education</option>
                    <option value="lifestyle">Lifestyle</option>
                    <option value="interview">Interview</option>
                    <option value="news">News</option>
                    <option value="politics">Politics</option>
                    <option value="science">Science</option>
                    <option value="sport">Sport</option>
                    <option value="technology">Technology</option>
                    <option value="other">Other</option>
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
                    <label class="form-check-label" for="saveAsDefaults" aria-describedby="saveHelp">
                        Save these values as the defaults for this show
                    </label>
                    <small id="saveHelp" class="form-text text-muted">
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
            <div class="alert alert-warning mt-2 show-image-oversized" role="alert">
                <strong>The image you chose is too big.</strong> The maximum size
                is <?php echo $config["maxShowImageSizeFriendly"]; ?>.
                Please try again with a smaller version of the file. If you're not sure how to do this, please contact
                technical staff. Thank you.
            </div>
            <!-- Submit button behaviour modified by the <form> tag -->
            <button type="submit" id="submit" class="btn btn-lg btn-outline-dark w-100" disabled>
                <i class="fas fa-circle-notch fa-spin"></i> Uploading...
            </button>
            <p id="uploadingHelpText" class="text-center">You can submit your show once it has uploaded.</p>
        </div>
    </form>

    <!-- Modal if show file upload fails -->
    <div class="modal fade" id="showFileUploadFailAlert" data-backdrop="static" tabindex="-1" role="dialog"
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
                    <a href="/index.php" class="btn btn-primary">Retry</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // make some config items available to the JS
        var maxShowFileSize = <?php echo $config["maxShowFileSize"]; ?>;
        var maxShowImageSize = <?php echo $config["maxShowImageSize"]; ?>;
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.0/jquery.min.js"
            integrity="sha256-xNzN2a4ltkB44Mc/Jz3pT4iU1cmeR0FkXs4pru/JxaQ=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.0/js/bootstrap.bundle.min.js"
            integrity="sha256-Xt8pc4G0CdcRvI0nZ2lRpZ4VHng0EoUDMlGcBSQ9HiQ=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bs-custom-file-input/1.3.4/bs-custom-file-input.min.js"
            integrity="sha256-e0DUqNhsFAzOlhrWXnMOQwRoqrCRlofpWgyhnrIIaPo=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"
            integrity="sha256-bqVeqGdJ7h/lYPq6xrPv/YGzMEb6dNxlfiTUHSgRCp8=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.js"
            integrity="sha256-+jmym6PChFok5u02khNcYDy5AdfiIN7Y/QCbBqiNgeE=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/autosize.js/4.0.2/autosize.min.js"
            integrity="sha256-dW8u4dvEKDThJpWRwLgGugbARnA3O2wqBcVerlg9LMc=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flow.js/2.14.0/flow.min.js"
            integrity="sha256-pX7VAtlSGK55XgQjYFMvQbIRbHvD3R2Nb3JrdDDmxyk=" crossorigin="anonymous"></script>
    <script src="resources/script.js?version=10"></script>
</body>
</html>
