<?php require './processing/promptLogin.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require "./components/head.html"; ?>
</head>
<body>
<?php require "./components/noscript.html"; ?>

<div class="container">
    <h1>Submit Show</h1>
    <p>Submit a show for scheduling and automatic upload to Mixcloud.</p>
    <form>
        <div class="form-group">
            <label for="nameDropdown">Show</label>
            <select class="form-select" id="nameDropdown" aria-describedby="nameDropdownHelp" name="id" required>
                <option value="" disabled selected>Choose show name...</option>
                <optgroup label="Shows" id="nameOptionGroup">
                    <?php
                        $shows = json_decode(file_get_contents("shows.json"), true);
                        foreach ($shows as $show) {
                            echo "<option value='" . $show["id"] . "'>" . $show["name"] . "</option>";
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
                <input type="text" class="form-control" id="name" aria-describedby="nameHelp" name="name" maxlength="50">
                <small id="nameHelp" class="form-text text-muted">
                    Enter the name of the show.
                </small>
            </div>
        
            <div class="form-group">
                <label for="presenter">Show presenter</label>
                <input type="text" class="form-control" id="presenter" aria-describedby="presenterHelp" name="presenter"
                       maxlength="50">
                <small id="presenterHelp" class="form-text text-muted">
                    Enter the show's presenter.
                </small>
            </div>
        </div>
        
        <fieldset>
            <legend class="fs-6">What would you like to do?</legend>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="action" value="trim" id="trim" checked>
                <label class="form-check-label" for="trim">
                    Trim a recording of a broadcasted show
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="action" value="upload" id="upload">
                <label class="form-check-label" for="upload">
                    Upload a file
                </label>
            </div>
        </fieldset>
        
        <button type="submit" class="btn btn-primary mt-3">Continue</button>
    </form>
    
    <script>
        const nameDropdown = document.getElementById("nameDropdown");
        const customName = document.getElementById("name");
        const customPresenter = document.getElementById("presenter");
        nameDropdown.addEventListener("change", function () {
            if (nameDropdown.value === "special") {
                customName.required = true;
                customPresenter.required = true;
                nameAndPresenterEntryFields.hidden = false;
            } else {
                customName.required = false;
                customPresenter.required = false;
                nameAndPresenterEntryFields.hidden = true;
            }
        });
    </script>
</body>
</html>
