const pageIntro                   = document.getElementById("pageIntro");
const formFileLocation            = document.getElementById("formFileLocation");
const formExtract                 = document.getElementById("formExtract");
const formTrim                    = document.getElementById("formTrim");
const formUpload                  = document.getElementById("formUpload");
const formTitle                   = document.getElementById("formTitle");
const formDetails                 = document.getElementById("formDetails");
const preparing                   = document.getElementById("preparing");
const fileLocationInput           = document.getElementById("fileLocationInput");
const waveformZoom                = document.getElementById("waveformZoom");
const recordingStartInput         = document.getElementById("recordingStart");
const recordingEndInput           = document.getElementById("recordingEnd");
const showFileInput               = document.getElementById("showFileInput");
const nameDropdown                = document.getElementById("nameDropdown");
const nameOptionGroup             = document.getElementById("nameOptionGroup");
const nameAndPresenterEntryFields = document.getElementById("nameAndPresenterEntryFields");
const name                        = document.getElementById("name");
const presenter                   = document.getElementById("presenter");
const nameDropdown2               = document.getElementById("formDetailsNameDropdown");
const name2                       = document.getElementById("formDetailsName");
const presenter2                  = document.getElementById("formDetailsPresenter");
const date2                       = document.getElementById("formDetailsDate");
const fileName2                   = document.getElementById("formDetailsFileName");
const dateInput                   = document.getElementById("date");
const endInput                    = document.getElementById("end");
const description                 = document.getElementById("description");
const imageSource                 = document.getElementById("imageSource");
const imageUploader               = document.getElementById("imageUploader");
const image                       = document.getElementById("image");
const imageErrors                 = document.getElementsByClassName("error-imageOversized");
const submitButton                = document.getElementById("submit");
const uploadingHelpText           = document.getElementById("uploadingHelpText");

let trimStart = null;
let trimEnd   = null;

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

formFileLocation.addEventListener("submit", function (event) {
    event.preventDefault();
    
    switch (fileLocationInput.value) {
        case "upload":
            formUpload.hidden = false;
            break;
        case "extract":
            formExtract.hidden = false;
            break;
    }
    
    pageIntro.hidden = true;
    formFileLocation.hidden = true;
})

recordingStartInput.addEventListener("change", function (event) {
    recordingEndInput.min = recordingStartInput.value;
})

formExtract.addEventListener("submit", function (event) {
    event.preventDefault();
    
    formExtract.hidden = true;
    preparing.hidden = false;
    
    const ws = WaveSurfer.create({
        container: '#waveform',
        waveColor: 'rgb(200, 0, 200)',
        progressColor: 'rgb(100, 0, 100)',
        url: 'api/stitch.php?from=' + recordingStartInput.value + "&to=" + recordingEndInput.value,
        mediaControls: true,
    })
    
    // TODO WebKit bug for the way we load audio if there is no type=""
    // TODO Generate peaks on server size
    // TODO Handling loading error
    
    const wsRegions = ws.registerPlugin(WaveSurfer.Regions.create({}))
    
    wsRegions.on('region-updated', (region) => {
        trimStart = region.start;
        trimEnd   = region.end;
    })
    
    wsRegions.on('region-double-clicked', (region) => {
      region.play()
      setTimeout(() => { region.play() }, 100);
    })
    
    wsRegions.on('region-out', (region) => {
      ws.pause()
    })
        
    ws.on('click', () => {
      ws.play()
    })
    
    ws.once('decode', () => {
        trimStart = ws.media.duration / 8;
        trimEnd   = ws.media.duration - (ws.media.duration / 8);
        
        wsRegions.addRegion({
            start: trimStart,
            end: trimEnd,
            color: 'rgba(255, 0, 0, 0.1)',
            drag: false,
            resize: true,
          })
        
        waveformZoom.oninput = (e) => {
            const minPxPerSec = Number(e.target.value)
            ws.zoom(minPxPerSec)
        }
      
        preparing.hidden = true;
        formTrim.hidden = false;
    })
})

formTrim.addEventListener("submit", function (event) {
    event.preventDefault();
    
    formTrim.hidden = true;
    formTitle.hidden = false;
    
    dateInput.value = recordingStartInput.value.split("T")[0];
    end.value = recordingEndInput.value.split("T")[1];
});

formUpload.addEventListener("submit", function (event) {
    event.preventDefault();
    formUpload.hidden = true;
    formTitle.hidden = false;
})

formTitle.addEventListener("submit", function (event) {
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
        if (showFileInput.value) {
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
                totalFail();
            });
    
            // Activate the submit button when the upload succeeds
            uploader.on('fileSuccess', function () {
                readyToSubmit();
            });
    
            // start upload
            uploader.upload();
            
            fileName2.value     = showFileInput.files[0].name;
        } else if (trimStart !== null && trimEnd !== null) {
            const trimStartTimestamp = new Date(recordingStartInput.valueAsNumber + (trimStart * 1000));
            const trimEndTimestamp   = new Date(recordingStartInput.valueAsNumber + (trimEnd   * 1000));
            
            fetch("api/stitch.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application.json"
                },
                body: JSON.stringify({
                    from:      trimStartTimestamp,
                    to:        trimEndTimestamp,
                    name:      name.value,
                    presenter: presenter.value,
                    date:      dateInput.value
                })
            }).then(function (response) {
                return response.text();
            }).then(function (name) {
                fileName2.value = name;
                readyToSubmit();
            }).catch(function () {
                totalFail();
            })
        }

        // Copy the values of form 1 into hidden fields of form 2, so they're submitted with the rest of the details
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

            fetch("resources/default/image.php?show=" + nameDropdown.value, {
                    // Only get headers, since we just need the HTTP status for now to see if an image exists.
                    method: "HEAD"
            }).then(function(data) {
                    if (data.ok) { // Default image exists
                        document.getElementById("defaultImageSection").hidden = false;
                        document.getElementById("defaultImage").src = "resources/default/image.php?show=" + nameDropdown.value;
                        document.getElementById("imageSource").value = "default";
                        imageUploader.hidden = true;
                    }
                });
        }

        // Display the rest of the form.
        formTitle.hidden = true;
        formDetails.hidden = false;
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

formDetails.addEventListener("submit", function () {
    // Remove warning for navigating away
    window.onbeforeunload = null;

    // Disable submit button to prevent resubmission
    submitButton.disabled  = true;
    submitButton.innerHTML = '<i class="spinner-border"></i> Submitting...'
    submitButton.classList.remove("btn-outline-success");
    submitButton.classList.add("btn-outline-dark");
})

function readyToSubmit() {
    submitButton.disabled = false;
    submitButton.innerText = "Submit Show";
    submitButton.classList.add("btn-outline-success");
    submitButton.classList.remove("btn-outline-dark");
    submitButton.style.marginBottom = "1.5rem"; // reduce page reflow from removing uploading help text
    uploadingHelpText.remove();
}

function totalFail() {
    document.getElementById("page-content").hidden = true;
    document.getElementById("error-UploadFail").hidden = false;
    document.getElementById("error-UploadFail").focus();
    // Remove warning when navigating away
    window.onbeforeunload = null;
}