const form1                        = document.getElementById("form1");
const form2                        = document.getElementById("form2");
const showFileInput               = document.getElementById("showFileInput");
const nameDropdown                = document.getElementById("nameDropdown");
const nameOptionGroup             = document.getElementById("nameOptionGroup");
const nameAndPresenterEntryFields = document.getElementById("nameAndPresenterEntryFields");
const name                        = document.getElementById("name");
const presenter                   = document.getElementById("presenter");
const nameDropdown2               = document.getElementById("form2NameDropdown");
const name2                       = document.getElementById("form2Name");
const presenter2                  = document.getElementById("form2Presenter");
const date2                       = document.getElementById("form2Date");
const fileName2                   = document.getElementById("form2FileName");
const dateInput                   = document.getElementById("date");
const description                 = document.getElementById("description");
const imageSource                 = document.getElementById("imageSource");
const imageUploader               = document.getElementById("imageUploader");
const image                       = document.getElementById("image");
const imageErrors                 = document.getElementsByClassName("error-imageOversized");
const submitButton                = document.getElementById("submit");
const uploadingHelpText           = document.getElementById("uploadingHelpText");

function populateShowNameSelect() {
    fetch(showJSON)
        .then(function(response) {
            return response.json()
        })
        .then(function(shows) {
            for (const show of shows) {
                const option = document.createElement("option");
                option.value     = show[showIdKey];
                option.innerText = show[showNameKey];
                option.dataset.presenter = show[showPresenterKey];

                nameOptionGroup.appendChild(option);
            }
        })
}

document.addEventListener("DOMContentLoaded", function() {
    // Resize text areas as they're filled with content
    autosize(document.querySelectorAll('textarea'));

    // Warn the user before they navigate away
    window.onbeforeunload = function () { return true;};

    // Enable tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-toggle="tooltip"]'))
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })

    const testUploader = new Flow();
    // If the uploader isn't supported, hide the page content and show error
    if (!testUploader.support) {
        document.getElementById("page-content").hidden = true;
        document.getElementById("error-FilesUnsupported").hidden = false;
    }

    // When a file is added
    showFileInput.addEventListener('change', function () {
        const error         = document.getElementById("error-ShowFileOversized");
        const goButton      = document.getElementById("uploadAndContinueButton");
        const file          = showFileInput.files[0];

        if (file.size > maxShowFileSize) {
            goButton.disabled = true;
            error.hidden = false;
        } else {
            goButton.disabled = false;
            error.hidden = true;
        }
    });

    populateShowNameSelect();
});

nameDropdown.addEventListener("change", function () {
    if (nameDropdown.value === "special") {
        name.value = "";
        presenter.value = "";
        nameAndPresenterEntryFields.hidden = false;
    } else {
        nameAndPresenterEntryFields.hidden = true;
        name.value      = nameDropdown.selectedOptions[0].innerText;
        presenter.value = nameDropdown.selectedOptions[0].dataset.presenter;
    }
});

form1.addEventListener("submit", function (event) {
    event.preventDefault();

    // validate the input
    let inputValid = true;
    if (isNaN(Date.parse(dateInput.value))) {
        // Invalid or no date entered
        inputValid = false
        dateInput.setCustomValidity("Please enter a valid date.");
        dateInput.reportValidity();
    } else {
        dateInput.setCustomValidity("");
        dateInput.reportValidity();
    }

    if (inputValid) {
        // Create a new uploader, with a query including the current form data
        const uploader = new Flow({
            target: 'api/upload.php',
            uploadMethod: 'POST',
            singleFile: true,
            query: {
                name: name.value,
                presenter: presenter.value,
                date: dateInput.value
            },
            permanentErrors: [404, 406, 415, 500, 501]
        });

        // Add the files from our temporary uploader
        uploader.addFile(showFileInput.files[0]);

        // Display error if something goes wrong in the file upload
        uploader.on('fileError', function () {
            document.getElementById("page-content").hidden = true;
            document.getElementById("error-UploadFail").hidden = false;
            document.getElementById("error-UploadFail").focus();
            // Remove warning when navigating away
            window.onbeforeunload = null;
        });

        // Activate the submit button when the upload succeeds
        uploader.on('fileSuccess', function () {
            submitButton.disabled = false;
            submitButton.innerText = "Submit Show";
            submitButton.classList.add("btn-outline-success");
            submitButton.classList.remove("btn-outline-dark");
            submitButton.style.marginBottom = "1.5rem"; // reduce page reflow from removing uploading help text
            uploadingHelpText.remove();
        });

        // start upload
        uploader.upload();

        // disable the inputs we've used so far, hide the file uploader, show the rest of the form
        document.getElementById("showFileInputGroup").hidden = true;
        document.getElementById("uploadAndContinueButton").hidden = true;
        nameDropdown.disabled = true;
        name.disabled         = true;
        presenter.disabled    = true;
        dateInput.disabled    = true

        // Copy the values of form 1 into hidden fields of form 2, so they're submitted with the rest of the details
        fileName2.value     = showFileInput.files[0].name;
        nameDropdown2.value = nameDropdown.value;
        name2.value         = name.value;
        presenter2.value    = presenter.value;
        date2.value         = dateInput.value;

        if (nameDropdown.value === "special") {
            // This is a special show, so hide all the options to do with default values.
            document.getElementById("defaultImage").hidden = true;
            document.getElementById("saveFormDefaultsSection").hidden = true;
            document.getElementById("saveAsDefaults").checked = false;
        } else {
            // This isn't a one-off show, so we can go and fetch the default data.
            fetch("resources/default/data.php?show=" + nameDropdown.value)
                .then(function(response) {
                    if (response.ok) return response.json()
                    else return null
                })
                .then(function(data) {
                    if (data) { // There is default data
                        description.value = data.description;
                        autosize.update(description);

                        if (data.tags[0])
                            document.getElementById("tag1").value = data.tags[0];
                        if (data.tags[1])
                            document.getElementById("tag2").value = data.tags[1];
                        if (data.tags[2])
                            document.getElementById("tag3").value = data.tags[2];
                        if (data.tags[3])
                            document.getElementById("tag4").value = data.tags[3];
                        if (data.tags[4])
                            document.getElementById("tag5").value = data.tags[4];
                    }
                })

            fetch("resources/default/image.php?show=" + nameDropdown.value,
                {
                    // Only get headers, since we just need the HTTP status for now to see if an image exists.
                    method: "HEAD"
                }
            )
                .then(function(data) {
                    if (data.ok) { // Default image exists
                        document.getElementById("defaultImageSection").hidden = false;
                        document.getElementById("defaultImage").src = "resources/default/image.php?show=" + nameDropdown.value;
                        document.getElementById("imageSource").value = "default";
                        imageUploader.hidden = true;
                    }
                });
        }

        // Display the rest of the form.
        form2.hidden = false;
        document.getElementById("end").focus();
    }
});

imageSource.addEventListener("change", function () {
    imageUploader.hidden = imageSource.value !== "upload";
})

image.addEventListener("change", function () {
    // if image is too large
    if (image.files[0].size > maxShowImageSize) {
        // Field is invalid
        image.setCustomValidity("Image too big. The maximum size is " + maxShowImageSizeFriendly + ".");
        image.reportValidity();
    } else {
        // Field is valid
        image.setCustomValidity("");
        dateInput.reportValidity()
    }
})

form2.addEventListener("submit", function () {
    // Remove warning for navigating away
    window.onbeforeunload = null;

    // Disable submit button to prevent resubmission
    submitButton.disabled  = true;
    submitButton.innerHTML = '<i class="spinner-border"></i> Submitting...'
    submitButton.classList.remove("btn-outline-success");
    submitButton.classList.add("btn-outline-dark");
})