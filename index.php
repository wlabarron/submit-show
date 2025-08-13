<?php 
require './processing/promptLogin.php'; 
require_once 'processing/formHandler.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require "./components/head.html"; ?>
</head>
<body>
<?php require "./components/noscript.html"; ?>

<div class="container">
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
    
    <h1>Submit Show</h1>
    <p>Submit a show for scheduling and automatic upload to Mixcloud.</p>
    <!-- Action dynamically updated by JavaScript depending on what the user picks in the form  -->
    <form id="form" method="POST">
        <div class="form-group">
            <label for="nameDropdown">Show</label>
            <select class="form-select" id="nameDropdown" aria-describedby="nameDropdownHelp" name="id" required>
                <option value="" disabled selected>Choose show name...</option>
                <optgroup label="Shows" id="nameOptionGroup">
                    <?php
                        $shows = json_decode(file_get_contents("shows.json"), true);
                        foreach ($shows as $show) {
                            echo "<option value='" . $show["id"] . "' data-presenter='" . $show["presenter"] . "'>" . $show["name"] . "</option>";
                        }
                    ?>
                </optgroup>
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
                <input type="text" class="form-control" id="name" aria-describedby="nameHelp" name="name" maxlength="50" required>
                <small id="nameHelp" class="form-text text-muted">
                    Enter the name of the show.
                </small>
            </div>
        
            <div class="form-group">
                <label for="presenter">Show presenter</label>
                <input type="text" class="form-control" id="presenter" aria-describedby="presenterHelp" name="presenter"
                       maxlength="50" required>
                <small id="presenterHelp" class="form-text text-muted">
                    Enter the show's presenter.
                </small>
            </div>
        </div>
        
        <fieldset <?php if (!$config["serverRecordings"]["enabled"]) { echo "hidden"; } ?>>
            <legend class="fs-6">What would you like to do?</legend>
            <div class="form-check">
                <input class="form-check-input action" type="radio" name="action" value="select" id="trim" <?php if ($config["serverRecordings"]["enabled"]) { echo "checked"; } ?>>
                <label class="form-check-label" for="trim">
                    Trim a recording of a broadcasted show
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input action" type="radio" name="action" value="upload" id="upload" <?php if (!$config["serverRecordings"]["enabled"]) { echo "checked"; } ?>>
                <label class="form-check-label" for="upload">
                    Upload a file
                </label>
            </div>
        </fieldset>
        
        <button type="submit" class="btn btn-primary mt-3">Continue</button>
    </form>
    
    <script>
        const nameDropdown = document.getElementById("nameDropdown");
        const nameInput = document.getElementById("name");
        const presenterInput = document.getElementById("presenter");
        
        nameDropdown.addEventListener("change", function () {
            if (nameDropdown.value === "special") {
                // For special shows, we'll clear out any values lingering in the form                
                nameInput.value = "";
                presenterInput.value = "";
              
                nameAndPresenterEntryFields.hidden = false;
            } else {
                // For shows selected from the dropdown, we'll populate the hidden 'custom' name and presenter
                // inputs so we can get those values more easily in future
                nameAndPresenterEntryFields.hidden = true;
                
                nameInput.value      = nameDropdown.selectedOptions[0].innerText;
                presenterInput.value = nameDropdown.selectedOptions[0].dataset.presenter;
            }
        });
        
        function actionValueToUrl(value) {
            switch (value) {
                case "select":
                    return "select-file.php";
                case "upload":
                    return "upload-data.php";
                default:
                    return "";
            }
        }
        
        // Change form action depending on selected next step
        const form = document.getElementById("form");
        const actionInput = Array.from(document.getElementsByClassName("action"));
        actionInput.forEach(input => {
            input.addEventListener("change", e => {
                form.action = actionValueToUrl(e.target.value);
            })
        })
        
        // Set default form action to match default radio button
        form.action = actionValueToUrl(document.querySelector(".action:checked").value);
    </script>
</body>
</html>
