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
        snapToStep: true
    });
});

// first show file uploader object, only used for file picking
var showFileUploader = new Flow({
    singleFile: true,
    chunkSize: 10000000,
});

// second file uploader, used to send the file and defined later
var uploader;

// Set browser and drop areas
showFileUploader.assignBrowse(document.getElementById('showFileInput'));
showFileUploader.assignDrop(document.getElementById('showFileInputGroup'));

// If the uploader isn't supported, hide the page content and show the rror
if(!showFileUploader.support) {
    $("#page-content").hide();
    $("#files-unsupported").show();
}

// When a file is added, show its name on the page
showFileUploader.on('fileAdded', function(file){
    $("#custom-file-label").text(file.name);
    $("#showFileUploadName").val(file.name);
});

// Function used when "upload and continue" is clicked, to proceed through the form
function uploadAndContinue() {
    if ( // if the input so far seems valid
        showFileUploader.files.length === 1 &&
        $("#showNameInput :selected").val() !== "" &&
        !isNaN(Date.parse($("#broadcast-date").val()))
    ) {
        // create a new uploader, with a query including the current form data
        uploader = new Flow({
            target: '/processing/showFileUpload.php/',
            uploadMethod: 'POST',
            singleFile: true,
            chunkSize: 10000000,
            query: {
                showName: $("#showNameInput :selected").val(),
                broadcastDate: $("#broadcast-date").val()
            },
            permanentErrors: [404, 406, 415, 500, 501]
        });

        // add the files from our temporary uploader
        uploader.addFile(showFileUploader.files[0].file);

        // show error if something goes wrong in the file upload
        uploader.on('fileError', function(file, message){
            $('#showFileUploadFailAlert').modal('show');
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
        $("#broadcast-date").prop('readonly', true);
        $("#uploadAndContinueButton").slideUp();

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

function changeUploadState() {
    if ($("#imageSelection").val() === "upload") {
        $("#imageFileUploader").slideDown();
    } else {
        $("#imageFileUploader").slideUp();
    }
}