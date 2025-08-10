<?php 
require './components/post-only.php';
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
    <form id="form" method="POST" action="metadata.php">
        <?php require './components/return-to-sender.php'; ?>
        
        <div class="form-group" id="showFileInputGroup">
            <label for="showFileInput">Show file</label>
            <!-- No name for this input, so it won't be sent with the general form submission - the file is uploaded separately and just the name is left in the
                 next hidden field. This form also isn't enctype="multipart/form-data", so it wouldn't go anyway. -->
            <input type="file" class="form-control" id="showFileInput" aria-describedby="showFileHelp"
                   accept="audio/mpeg,audio/MPA,audio/mpa-robust,.mp3,.m4a,.mp4,.aac,audio/aac,audio/aacp,audio/3gpp,audio/3gpp2,audio/mp4,audio/mp4a-latm,audio/mpeg4-generic" required>
            <small id="showFileHelp" class="form-text text-muted">
                You can upload MP3 or M4A (AAC) files up to  <?php echo $config["maxShowFileSizeFriendly"]; ?>.
            </small>
        </div>
        
        <!-- Avoid potentially-variable behaviour across browsers by sending file name as a separate form field filled by JS, instead of relying on not using
             <form enctype="multipart/form-data"> which can have similar effect.  -->
        <input type="hidden" id="fileName" name="fileName" />
        
        <div id="upload-error-container" class="alert alert-danger" hidden>
            <span id="upload-error-body"></span>
        </div>
        
        <div id="progress-container" hidden>
            <h2 class="h5 text-center">Uploading...</h2>
            <div class="progress">
                <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-label="Upload progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="75"></div>
            </div>
        </div>

        <button type="submit" id="submit-button" class="btn btn-primary mt-2">Upload and continue</button>
    </form>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flow.js/2.14.1/flow.min.js" integrity="sha512-sl2wYWEDCu3bj5w4kyd6+bglKUxb6IPQbyflpIEJbftwtZYZp7GZQ2erVGsls9BveJIvIVW+hzM+rMQQT9Bn5w==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        // Warn the user before they navigate away
        window.onbeforeunload = function () { return true;};
        
        const form = document.getElementById("form");
        const showFileInput = document.getElementById("showFileInput");
        const showFileNameInput = document.getElementById("fileName");
        const uploadErrorContainer = document.getElementById("upload-error-container");
        const uploadErrorBody = document.getElementById("upload-error-body");
        const progressContainer = document.getElementById("progress-container");
        const progressBar = document.getElementById("progress-bar");
        const submitButton = document.getElementById("submit-button")
        
        // File size validation
        showFileInput.addEventListener('change', function () {
            const file = showFileInput.files[0];
            if (file.size > <?php echo $config["maxShowFileSize"]; ?>) {
                showFileInput.setCustomValidity("File too large. Maximum size is <?php echo $config["maxShowFileSizeFriendly"]; ?>.");
            } else {
                showFileInput.setCustomValidity("");
            }
            
            showFileInput.reportValidity();
        });
        
        form.addEventListener("submit", e => {
            e.preventDefault();
            
            // Create a new uploader, with a query including the current form data
            const uploader = new Flow({
                target: 'upload.php',
                uploadMethod: 'POST',
                singleFile: true,
                query: {
                    name: document.getElementById("name").value,
                    presenter: document.getElementById("presenter").value,
                    date: document.getElementById("date").value
                },
                permanentErrors: [404, 406, 415, 500, 501]
            });
            
            // Add the files from our temporary uploader
            uploader.addFile(showFileInput.files[0]);
            
            uploader.on('fileProgress', function(file, chunk) {
               progressBar.ariaValueNow = file.progress() * 100;
               progressBar.style.width =  (file.progress() * 100) + "%";
            });
            
            // Display error if something goes wrong in the file upload
            uploader.on('fileError', function (file, message) {
                submitButton.hidden = false;
                progressContainer.hidden = true;
                uploadErrorBody.innerText = message;
                uploadErrorContainer.hidden = false;
            });
            
            uploader.on('fileSuccess', function (file) {
                // record uploaded file name in a hidden field for easy access later
                showFileNameInput.value = file.name;
                // Remove navigation warning and proceed
                window.onbeforeunload = null;
                form.submit();
            });
            
            // reset error container
            uploadErrorContainer.hidden = true;
            uploadErrorBody.innerText = "Something went wrong with the upload. Please try again.";
            
            // hide submit button and show progress instead
            submitButton.hidden = true;
            progressContainer.hidden = false;
            progressContainer.focus();
            
            uploader.upload();
        })
    </script>
</body>
</html>
