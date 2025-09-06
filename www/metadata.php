<?php
use submitShow\Database;
use submitShow\Recording;

require __DIR__ . '/../scripts/postOnly.php';
require __DIR__ . '/../scripts/promptLogin.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Recording.php';
require_once __DIR__ . '/../classes/Input.php';

$config = require __DIR__ . '/../config.php';
$database = new Database();

if ($_POST["action"] === "select") {
    require __DIR__ . '/../scripts/trimRecording.php';
}

if (isset($_POST["id"]) && is_numeric($_POST["id"])) {
    $id = Input::sanitise($_POST["id"]);
    $defaultData = $database->getDefaults($id);
    $defaultImage = $database->getDefaultImage($id);
} else {
    $defaultData = [];
    $defaultData["description"] = "";
    $defaultData["tags"] = ["", "", "", "", ""];
    $defaultImage = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require __DIR__ . "/../components/head.html"; ?>
</head>
<body>
<?php require __DIR__ . "/../components/noscript.html"; ?>

<div class="container">
    <h1 class="h3">About your show</h1>
    <form id="form" method="POST" action="/" enctype="multipart/form-data">
        <?php require __DIR__ . '/../components/return-to-sender.php'; ?>
        
        <p>Show cover image</p>
        
        <div class="form-group" <?php if (!$defaultImage) { echo "hidden"; }; ?>>
            <div id="defaultImageSection">
                <div class="ps-5">
                    Saved photo:
                    <div class="col-md-5">
                        <img src="/resources/default/image.php?show=<?php echo Input::sanitise($_POST["id"]); ?>" 
                        alt="Previously uploaded cover image." width="100" height="100" id="defaultImage"/>
                    </div>
                </div>
                <select class="form-select"
                        id="imageSource"
                        name="imageSource"
                        aria-label="Choose which cover image to use">
                    <option value="default" <?php if ( $defaultImage) { echo "selected"; }; ?>>Use saved photo for this show</option>
                    <option value="upload"  <?php if (!$defaultImage) { echo "selected"; }; ?>>Upload new photo</option>
                    <option value="none">Don't use a photo</option>
                </select>
            </div>
        </div>
        
        <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $config["maxShowImageSize"]; ?>" />

        <div class="form-group" id="imageUploader" <?php if ($defaultImage) { echo "hidden"; }; ?>>
            <input type="file" class="form-control" id="image" name="image" accept="image/png,image/jpeg"
                   aria-describedby="imageHelp" aria-label="Show cover image">
            <small id="imageHelp" class="form-text text-muted">
                You can upload JPG or PNG files up to <?php echo $config["maxShowImageSizeFriendly"]; ?>. Square-shaped is best.
            </small>
        </div>

        <div class="form-group">
            <label for="description" class="mb-2">Description</label>
            <textarea class="form-control joint-input-top" id="description" rows="3"
                      maxlength="<?php echo 995 - strlen($config["fixedDescription"]); ?>"
                      name="description"
                      aria-describedby="descriptionHelp"><?php 
                      echo $defaultData["description"]; 
                  ?></textarea>
            <textarea class="form-control joint-input-bottom" disabled readonly id="description-fixed" aria-label="Fixed description text"><?php
                 echo str_replace("{n}", "&#13;", $config["fixedDescription"]);
                ?></textarea>
            <small id="descriptionHelp" class="form-text text-muted">
                Describe what happened in your show or enter your tagline. This is a good place to include a link,
                for example, if you had a guest on your show you may want to link to their website.
            </small>
        </div>
        
        <div class="form-group">
            <fieldset>
                <legend class="fs-6 mb-2">Tags</legend>
                <input type="text" class="form-control joint-input-top" aria-label="Tag" aria-describedby="tagsHelp"
                    id="tag1" name="tag1" maxlength="20" list="tagSuggestions" value="<?php echo $defaultData["tags"][0] ?? ""; ?>">
                <input type="text" class="form-control joint-input-middle" aria-label="Tag" aria-describedby="tagsHelp"
                    id="tag2" name="tag2" maxlength="20" list="tagSuggestions" value="<?php echo $defaultData["tags"][1] ?? ""; ?>">
                <input type="text" class="form-control joint-input-middle" aria-label="Tag" aria-describedby="tagsHelp"
                    id="tag3" name="tag3" maxlength="20" list="tagSuggestions" value="<?php echo $defaultData["tags"][2] ?? ""; ?>">
                <input type="text" class="form-control joint-input-middle" aria-label="Tag" aria-describedby="tagsHelp"
                    id="tag4" name="tag4" maxlength="20" list="tagSuggestions" value="<?php echo $defaultData["tags"][3] ?? ""; ?>">
                <input type="text" class="form-control joint-input-bottom" aria-label="Tag" aria-describedby="tagsHelp"
                    id="tag5" name="tag5" maxlength="20" list="tagSuggestions" value="<?php echo $defaultData["tags"][4] ?? ""; ?>">
                <small id="tagsHelp" class="form-text text-muted">
                    Good tags are things like the genres of music in the show. Click into the box to see popular tags, or type your 
                    own. More popular tags will suggest your show to more people.
                </small>
                <datalist id="tagSuggestions">
                    <?php
                        foreach (Recording::$PRIMARY_TAG_OPTIONS as $tag) {
                            echo '<option value="' . $tag . '"></option>';
                        }
                    ?>
                </datalist>
            </fieldset>
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
        
        <?php if (isset($id)) { ?>
            <div id="saveFormDefaultsSection">
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
        <?php } ?>
        
        <div class="text-center">
            <hr>
            <p class="text-center">This show will be sent to the station programming team for playout or archival, and published to Mixcloud.</p>
            <button type="submit" id="submit-button" class="btn btn-lg btn-outline-success">Submit show</button>
        </div>
    </form>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/autosize.js/6.0.1/autosize.min.js" integrity="sha512-OjjaC+tijryqhyPqy7jWSPCRj7fcosu1zreTX1k+OWSwu6uSqLLQ2kxaqL9UpR7xFaPsCwhMf1bQABw2rCxMbg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        // Warn the user before they navigate away, unless they're submitting the form
        window.onbeforeunload = function () { return true;};
        document.getElementById("form").addEventListener("submit", e => {
            window.onbeforeunload = null;
            document.getElementById("submit-button").disabled = true; // prevent double submission
        })
        
        // Resize text areas as they're filled with content
        autosize(document.querySelectorAll('textarea'));
        
        // Validate selected image size
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
        
        // Show uploader if image source selection is "upload new image"
        const imageSource = document.getElementById("imageSource")
        imageSource?.addEventListener("change", function () {
            imageUploader.hidden = imageSource.value !== "upload";
        })
    </script>
</body>
</html>
