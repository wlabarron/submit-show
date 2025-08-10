<?php 
require './processing/promptLogin.php'; 
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
    <h1 class="h3">Upload file</h1>
    <form>
        <div class="form-group" id="showFileInputGroup">
            <label for="showFileInput">Show file</label>
            <input type="file" class="form-control" id="showFileInput" aria-describedby="showFileHelp"
                   name="showFile" accept="audio/mpeg,audio/MPA,audio/mpa-robust,.mp3,.m4a,.mp4,.aac,audio/aac,audio/aacp,audio/3gpp,audio/3gpp2,audio/mp4,audio/mp4a-latm,audio/mpeg4-generic" required>
            <small id="showFileHelp" class="form-text text-muted">
                You can upload MP3 or M4A (AAC) files up to  <?php echo $config["maxShowFileSizeFriendly"]; ?>.
            </small>
        </div>
        
        <button type="submit" class="btn btn-primary mt-2">Upload and continue</button>
    </form>
    
    <script>
        // Warn the user before they navigate away
        window.onbeforeunload = function () { return true;};
        
        const showFileInput = document.getElementById("showFileInput");
        
        showFileInput.addEventListener('change', function () {
            const file = showFileInput.files[0];
            if (file.size > <?php echo $config["maxShowFileSize"]; ?>) {
                showFileInput.setCustomValidity("File too large. Maximum size is <?php echo $config["maxShowFileSizeFriendly"]; ?>.");
            } else {
                showFileInput.setCustomValidity("");
            }
            
            showFileInput.reportValidity();
        });
    </script>
</body>
</html>
