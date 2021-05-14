// First show file uploader object, only used for file picking
const showFileUploader = new Flow({
    singleFile: true,
    chunkSize: 10000000,
});

let showUploaded        = false;
let imageValid          = true;

const nameDropdown                = document.getElementById("nameDropdown");
const nameOptionGroup             = document.getElementById("nameOptionGroup");
const nameAndPresenterEntryFields = document.getElementById("nameAndPresenterEntryFields");
const name                        = document.getElementById("name");
const presenter                   = document.getElementById("presenter");
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

function updateStatusOfFormSubmitButton() {
    // If the image is valid and the file has been uploaded, allow form submission. Otherwise, disable the button
    if (showUploaded && imageValid) {
        submitButton.disabled = false;
        submitButton.innerText = "Submit Show";
        submitButton.classList.add("btn-outline-success");
        submitButton.classList.remove("btn-outline-dark");
        submitButton.style.marginBottom = "1rem"; // reduce page reflow from removing uploading help text
        uploadingHelpText.classList.add("hidden");
    } else {
        submitButton.disabled = true;
    }
}

document.addEventListener("DOMContentLoaded", function() {
    // Enable custom file picker
    bsCustomFileInput.init(".bs-custom-file input[type='file']");

    // Resize text areas as they're filled with content
    autosize($('textarea'));

    // Warn the user before they navigate away
    window.onbeforeunload = function () { return true;};

    // Enable tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-toggle="tooltip"]'))
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })

    // Set file browser area
    showFileUploader.assignBrowse(document.getElementById('showFileInput'));

    // If the uploader isn't supported, hide the page content and show error
    if (!showFileUploader.support) {
        document.getElementById("page-content").classList.add("hidden");
        document.getElementById("page-files-unsupported").classList.remove("hidden");
    }

    // When a file is added, show its name on the page
    showFileUploader.on('fileAdded', function(file) {
        const error         = document.getElementById("error-ShowFileOversized");
        const goButton      = document.getElementById("uploadAndContinueButton");
        const fileLabel     = document.getElementById("custom-file-label");
        const fileNameField = document.getElementById("fileName")

        if (file.size > maxShowFileSize) {
            goButton.disabled = true;
            error.classList.remove("hidden");
        } else {
            goButton.disabled = false;
            error.classList.add("hidden");

            fileLabel.innerText = file.name;
            fileNameField.value = file.name;
        }
    });

    populateShowNameSelect();
});

nameDropdown.addEventListener("change", function () {
    if (nameDropdown.value === "special") {
        name.value = "";
        presenter.value = "";
        nameAndPresenterEntryFields.classList.remove("hidden");
    } else {
        nameAndPresenterEntryFields.classList.add("hidden");
        name.value      = nameDropdown.selectedOptions[0].innerText;
        presenter.value = nameDropdown.selectedOptions[0].dataset.presenter;
    }
});

document.getElementById("uploadAndContinueButton").addEventListener("click", function () {
    const date      = document.getElementById("date");
    const invalid   = document.getElementById("error-InitialFormInvalid");

    // validate the input
    let inputValid = true;
    if (showFileUploader.files.length !== 1) {
        // More than one file selected
        inputValid = false;
    } else if (name.value === "") {
        // No name entered
        inputValid = false;
    } else if (presenter.value === "") {
        // No presenter name entered
        inputValid = false;
    } else if (isNaN(Date.parse(date.value))) {
        // Invalid or no date entered
        inputValid = false
    }

    if (inputValid) {
        // Create a new uploader, with a query including the current form data
        const uploader = new Flow({
            target: 'upload.php',
            uploadMethod: 'POST',
            singleFile: true,
            chunkSize: 10000000,
            query: {
                name: name.value,
                presenter: presenter.value,
                date: date.value
            },
            permanentErrors: [404, 406, 415, 500, 501]
        });

        // Add the files from our temporary uploader
        uploader.addFile(showFileUploader.files[0].file);

        // Display error if something goes wrong in the file upload
        uploader.on('fileError', function () {
            $('#error-UploadFail').modal('show');
            // Remove warning when navigating away
            window.onbeforeunload = null;
        });

        // Activate the submit button when the upload succeeds
        uploader.on('fileSuccess', function () {
            showUploaded = true;
            updateStatusOfFormSubmitButton();
        });

        // start upload
        uploader.upload();

        // disable the inputs we've used so far, hide the file uploader, show the rest of the form
        document.getElementById("showFileInputGroup").classList.add("hidden");
        document.getElementById("uploadAndContinueButton").classList.add("hidden");
        invalid.classList.add("hidden");

        nameDropdown.disabled = true;
        name.disabled         = true;
        presenter.disabled    = true;
        date.disabled         = true;

        if (nameDropdown.value === "special") {
            // This is a special show, so hide all the options to do with default values.
            document.getElementById("defaultImage").classList.add("hidden");
            document.getElementById("saveFormDefaultsSection").classList.add("hidden");
        } else {
            // This isn't a one-off show, so we can go and fetch the default data.
            fetch("/resources/default/data.php?show=" + nameDropdown.value)
                .then(function(response) {
                    return response.json()
                })
                .then(function(data) {
                    if (data.ok) { // There is default data
                        document.getElementById("description").value = data.description;

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

            fetch("/resources/default/image.php?show=" + nameDropdown.value,
                {
                    // Only get headers, since we just need the HTTP status for now to see if an image exists.
                    method: "HEAD"
                }
            )
                .then(function(data) {
                    if (data.ok) { // Default image exists
                        document.getElementById("defaultImageSection").classList.remove("hidden");
                        document.getElementById("defaultImage").src = "/resources/default/image.php?show=" + nameDropdown.value;
                        document.getElementById("imageSource").value = "default";
                    }
                });
        }

        // Display the rest of the form.
        document.getElementById("detailsForm").classList.remove("hidden");
    } else {
        // Display an error if the form so far is invalid
        invalid.classList.remove("hidden");
        $("#initial-form-invalid").slideDown();
    }
});

imageSource.addEventListener("change", function () {
    if (imageSource.value === "upload") {
        imageUploader.classList.remove("hidden");
    } else {
        imageUploader.classList.add("hidden");
    }
})

image.addEventListener("change", function () {
    // if image is too large
    if (image.files[0].size > maxShowImageSize) {
        // prevent form submission and show warning
        imageValid = false;
        updateStatusOfFormSubmitButton();

        for (const element of imageErrors) {
            element.classList.remove("hidden");
        }
    } else {
        // permit form submission
        imageValid = true;
        updateStatusOfFormSubmitButton();

        for (const element of imageErrors) {
            element.classList.add("hidden");
        }
    }
})