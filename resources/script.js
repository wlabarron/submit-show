// First show file uploader object, only used for file picking
const showFileUploader = new Flow({
    singleFile: true,
    chunkSize: 10000000,
});

function populateShowNameSelect() {
    const nameOptionGroup = document.getElementById("nameOptionGroup");

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

const name      = document.getElementById("name");
const presenter = document.getElementById("presenter");
const nameDropdown = document.getElementById("nameDropdown");
const nameAndPresenterEntryFields = document.getElementById("nameAndPresenterEntryFields");
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

        // TODO Default images and descriptions
        // // if this is a special show
        // if ($("#showNameInput").val() === "special") {
        //     // hide and uncheck the option to save defaults
        //     $("#saveFormDefaultsSection").slideUp();
        //     $("#saveFormDefaultsSection").prop("checked", false)
        //     // show details form
        //     $("#detailsForm").slideDown();
        // }
        // // get show details for the form
        // $.getJSON("/processing/getSavedShowInfo.php?show=" + $("#showNameInput").val(), function (data) {
        //     // fill form data
        //     $("#descriptionInput").val(data.description);
        //     // fill each tag which exists
        //     for (var i = 0; i < data.tags.length; i++) {
        //         $("#tag" + (i + 1)).val(data.tags[i]);
        //     }
        //
        //     // show details form
        //     $("#detailsForm").slideDown();
        //
        //     // if an image exists, mark it as such
        //     if (data.imageExists) {
        //         // show the default image display section and load in the image
        //         $("#defaultImageDisplay").slideDown();
        //         $("#imageSelection").val("saved");
        //         $("#imageFileUploader").slideUp();
        //         $("#defaultImage").attr("src", "/processing/getSavedShowImage.php?show=" + $("#showNameInput").val());
        //     }
        // });
    } else {
        // Display an error if the form so far is invalid
        invalid.classList.remove("hidden");
        $("#initial-form-invalid").slideDown();
    }
});

// The show file has not been uploaded yet
let showUploaded = false;
// The image provided is valid
let imageValid = true;
function updateStatusOfFormSubmitButton() {
    // If the image is valid and the file has been uploaded, allow form submission. Otherwise, disable the button
    if (showUploaded && imageValid) {
        $('#submit').prop("disabled", false);
        $("#uploadingHelpText").slideUp();
        $("#submit").text("Submit Show");
        $("#submit").prop('disabled', false);
        $("#submit").addClass("btn-outline-success");
        $("#submit").removeClass("btn-outline-dark");
    } else {
        $('#submit').prop("disabled", true);
    }
}

function checkShowCoverImageSize() {
    $(".show-image-oversized").slideUp();
    // if image is too large
    if (document.getElementById("showImageInput").files[0].size > maxShowImageSize) {
        // prevent form submission and show warning
        imageValid = false;
        updateStatusOfFormSubmitButton();
        $(".show-image-oversized").slideDown();
    } else {
        // permit form submission
        imageValid = true;
        updateStatusOfFormSubmitButton();
        $(".show-image-oversized").slideUp();
    }
}

function handleSpecialShowInput() {
    // if we've chosen a special one off show from dropdown
    if ($("#showNameInput").val() === "special") {
        // show the extra inputs
        $("#specialShowDetails").slideDown();
        // extra inputs are required
        $("#specialShowName").prop("required", true);
        $("#specialShowPresenter").prop("required", false);
    } else {
        // hide, empty, and make not-required the extra inputs
        $("#specialShowDetails").slideUp();
        $("#specialShowName").val("");
        $("#specialShowName").prop("required", false);
        $("#specialShowPresenter").val("");
        $("#specialShowPresenter").prop("required", false);
    }
}

function changeShowImageSelection() {
    if ($("#imageSelection").val() === "upload") {
        $("#imageFileUploader").slideDown();
    } else {
        $("#imageFileUploader").slideUp();
    }
}

