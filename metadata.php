<?php 
use submitShow\Recording;

require './processing/promptLogin.php'; 
require_once  'processing/Recording.php';
$config = require './processing/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require "./components/head.html"; ?>
</head>
<body>
<?php require "./components/noscript.html"; ?>

<div class="container">
    <h1 class="h3">About your show</h1>
    <form>
        <div class="form-group">
            <!-- TODO Show the right bits based on selected show type -->
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
                <!-- Validate this -->
                You can upload JPG or PNG files up to <?php echo $config["maxShowImageSizeFriendly"]; ?>. Square-shaped is best.
            </small>
        </div>

        <div class="form-group">
            <label for="description" class="mb-2">Description</label>
            <textarea class="form-control joint-input-top" id="description" rows="3"
                      maxlength="<?php echo 995 - strlen($config["fixedDescription"]); ?>"
                      name="description"
                      aria-describedby="descriptionHelp"></textarea>
            <textarea class="form-control joint-input-bottom" disabled readonly aria-label="Fixed description text"><?php
                 echo str_replace("{n}", "&#13;", $config["fixedDescription"]);
                ?></textarea>
            <small id="descriptionHelp" class="form-text text-muted">
                Describe what happened in your show or enter your tagline. This is a good place to include a link,
                for example, if you had a guest on your show you may want to link to their website.
            </small>
        </div>
        
        <div class="form-group">
            <label class="mb-2">Tags</label>
            <select class="form-select joint-input-top" id="tag1" aria-label="Tag" aria-describedby="tagsHelp" name="tag1">
                <option value="" disabled selected>Choose primary tag...</option>
                <?php
                    foreach (Recording::$PRIMARY_TAG_OPTIONS as $tag) {
                        echo '<option value="' . $tag . '">' . $tag. '</option>';
                    }
                ?>
            </select>
            <input type="text" class="form-control joint-input-middle" aria-label="Tag" aria-describedby="tagsHelp"
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
                    </div>';
        }
        ?>
        
        <div id="saveFormDefaultsSection">
            <!-- TODO Hide if custom show -->
            <div class="form-group form-check">
                <input class="form-check-input" type="checkbox" value="true" id="saveAsDefaults"
                       name="saveAsDefaults"
                       checked>
                <label class="form-check-label" for="saveAsDefaults" aria-describedby="saveAsDefaultsHelp">
                    Save these values as the defaults for this show
                </label>
                <small id="saveAsDefaultsHelp" class="form-text text-muted d-block">
                    If this box is ticked, next time you choose this show from the "Show name" list in the first step, 
                    the image, description, and tags you've chosen here will appear automatically.
                </small>
            </div>
        </div>
        
        <!-- TODO Action description based on submission type -->
        <!-- <p class="text-center">Once you submit this show, it'll be available for broadcast and posted to Mixcloud.</p> -->
        
        <button type="submit" class="btn btn-success mt-2">Submit show</button>
    </form>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/autosize.js/6.0.1/autosize.min.js" integrity="sha512-OjjaC+tijryqhyPqy7jWSPCRj7fcosu1zreTX1k+OWSwu6uSqLLQ2kxaqL9UpR7xFaPsCwhMf1bQABw2rCxMbg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        // Warn the user before they navigate away
        window.onbeforeunload = function () { return true;};
        
        // Resize text areas as they're filled with content
        autosize(document.querySelectorAll('textarea'));
        
        const imageInput = document.getElementById("image");
        imageInput.addEventListener('change', function () {
            const file = imageInput.files[0];
            if (file.size > <?php echo $config["maxShowImageSize"]; ?>) {
                imageInput.setCustomValidity("File too large. Maximum size is <?php echo $config["maxShowImageSizeFriendly"]; ?>.");
            } else {
                imageInput.setCustomValidity("");
            }
            
            imageInput.reportValidity();
        });
    </script>
</body>
</html>
