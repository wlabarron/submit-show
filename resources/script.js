$(document).ready(function () {
    // Initialise form input methods
  bsCustomFileInput.init(".bs-custom-file input[type='file']");
  autosize($('textarea'));

    $('#broadcast-date').datepicker({
        format: "DD, d MM yyyy",
        assumeNearbyYear: true,
        todayBtn: "linked",
        autoclose: true,
        todayHighlight: true,
        enableOnReadonly: false
    });

    $('#broadcast-time').timepicker({
        snapToStep: true,
        defaultTime: "00:00"
    });

    // Enable prompt if the user tries to navigate away
    window.onbeforeunload = function () {
        return true;
    };
});

function checkShowCoverImageSize() {
    $(".show-image-oversized").slideUp();
    // if image is too large
    if (document.getElementById("showImageInput").files[0].size > maxShowImageSize) {
        // prevent form submission and show warning
        $('#submit').prop("disabled", true);
        $(".show-image-oversized").slideDown();
    } else {
        // permit form submission
        $('#submit').prop("disabled", false);
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

// first show file uploader object, only used for file picking
var showFileUploader = new Flow({
    singleFile: true,
    chunkSize: 10000000,
});

// second file uploader, used to send the file and defined later
var uploader;

// Set file browser area
showFileUploader.assignBrowse(document.getElementById('showFileInput'));

// If the uploader isn't supported, hide the page content and show the rror
if(!showFileUploader.support) {
    $("#page-content").hide();
    $("#files-unsupported").show();
}

// When a file is added, show its name on the page
showFileUploader.on('fileAdded', function(file) {
    $("#show-file-oversized").slideUp();
    if (file.size > maxShowFileSize) {
        $('#uploadAndContinueButton').prop("disabled", true);
        $("#show-file-oversized").slideDown();
    } else {
        $('#uploadAndContinueButton').prop("disabled", false);
        $("#custom-file-label").text(file.name);
        $("#showFileUploadName").val(file.name);
    }
});

// Function used when "upload and continue" is clicked, to proceed through the form
function uploadAndContinue() {
    var inputValid = true;

    // validate the input
    if (showFileUploader.files.length !== 1) { // only one file
        inputValid = false;
    } else if ($("#showNameInput :selected").val() == "") { // name picked
        inputValid = false;
    } else if (isNaN(Date.parse($("#broadcast-date").val()))) { // date entered
        inputValid = false
    } else if ($("#showNameInput").val() === "special") { // if this is a one-off show
        if ($("#specialShowName").val() == "") {
            inputValid = false;
        } else if ($("#specialShowPresenter").val() == "") {
            inputValid = false;
        }
    }

    if (inputValid) {
        // create a new uploader, with a query including the current form data
        uploader = new Flow({
            target: '/processing/showFileUpload.php/',
            uploadMethod: 'POST',
            singleFile: true,
            chunkSize: 10000000,
            query: {
                showName: $("#showNameInput :selected").val(),
                specialShowName: $("#specialShowName").val(),
                specialShowPresenter: $("#specialShowPresenter").val(),
                broadcastDate: $("#broadcast-date").val()
            },
            permanentErrors: [404, 406, 415, 500, 501]
        });

        // add the files from our temporary uploader
        uploader.addFile(showFileUploader.files[0].file);

        // show error if something goes wrong in the file upload
        uploader.on('fileError', function(file, message){
            $('#showFileUploadFailAlert').modal('show');
            // Remove warning when navigating away
            window.onbeforeunload = null;
        });

        // activate the submit button when the upload succeeds
        uploader.on('fileSuccess', function(file){
            $("#uploadingHelpText").slideUp();
            $("#submit").text("Submit Show");
            $("#submit").prop('disabled', false);
            $("#submit").addClass("btn-outline-success");
            $("#submit").removeClass("btn-outline-dark");
        });

        // start upload
        uploader.upload();

        // copy the show name from the dropdown into a hidden input so it's POSTed when submitting the form, since in a
        // moment we'll disable the dropdown
        $("#hiddenShowName").val($("#showNameInput :selected").val());

        // disable the inputs we've used so far, hide the file uploader, show the rest of the form
        $("#showFileInputGroup").slideUp();
        $("#initial-form-invalid").slideUp();
        $("#showNameInput").prop('disabled', true);
        $("#specialShowName").prop('readonly', true);
        $("#specialShowPresenter").prop('readonly', true);
        $("#broadcast-date").prop('readonly', true);
        $("#uploadAndContinueButton").slideUp();

        // if this is a special show
        if ($("#showNameInput").val() === "special") {
            // hide and uncheck the option to save defaults
            $("#saveFormDefaultsSection").slideUp();
            $("#saveFormDefaultsSection").prop("checked", false)
            // show details form
            $("#detailsForm").slideDown();
        }
        // get show details for the form
        $.getJSON("/processing/getSavedShowInfo.php?show=" + $("#showNameInput").val(), function (data) {
            // fill form data
            $("#descriptionInput").val(data.description);
            // fill each tag which exists
            for (var i = 0; i < data.tags.length; i++) {
                $("#tag" + (i + 1)).val(data.tags[i]);
            }

            // show details form
            $("#detailsForm").slideDown();

            // if an image exists, mark it as such
            if (data.imageExists) {
                // show the default image display section and load in the image
                $("#defaultImageDisplay").slideDown();
                $("#imageSelection").val("saved");
                $("#imageFileUploader").slideUp();
                $("#defaultImage").attr("src", "/processing/getSavedShowImage.php?show=" + $("#showNameInput").val());
            }
        });
    } else {
        // show an error if the form so far is invalid
        $("#initial-form-invalid").slideDown();
    }
}