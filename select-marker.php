<?php 
require './components/post-only.php';
require './processing/promptLogin.php'; 
require_once  'processing/Input.php';
$config = require './processing/config.php';

$fileName = Input::sanitise($_POST["fileName"]);

if (isset($_GET["end"])) {
    $filePath = Input::fileNameToPath($fileName);
    $fileDuration = intval(shell_exec("mediainfo --Output='General;%Duration%'  \"$filePath\"")) / 1000; // ms to sec
    $excerptStartTime = $fileDuration - $config["serverRecordings"]["auditionTime"];
} else {
    $excerptStartTime = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require "./components/head.html"; ?>
</head>
<body>
<?php require "./components/noscript.html"; ?>

<div class="container">
    <h1 class="h3">Mark the <?php echo isset($_GET["end"]) ? "end" : "start" ?> of the show</h1>
    <form id="form" method="POST" action="<?php echo isset($_GET["end"]) ? "metadata.php" : "select-marker.php?end" ?>">
        <?php require './components/return-to-sender.php'; ?>
        
        <?php if (isset($_GET["end"])) {
            echo "<p>Now, do the same for the moment your show ended. Here's the last " . $config["serverRecordings"]["auditionTime"] / 60 . " min of the recording you selected.</p>
            <p>Use the play and pause buttons to listen to the excerpt, and the Test button to hear the 3 seconds running up to your marker. The Zoom slider lets you look closer.</p>";
        } else {
            echo "<p>This is an excerpt of the first " . $config["serverRecordings"]["auditionTime"] / 60 . " min of the recording you selected. Drag the red marker to the exact moment the show started.</p>
            <p>Use the play and pause buttons to listen to the excerpt, and the Test button to start playing from where you dropped the marker. The Zoom slider lets you look closer.</p>";
        }?>
        
        <div id="error" class="alert alert-danger" hidden>
            <span><strong>Couldn't load excerpt.</strong> Please go back or refresh the page and try again.</span>
        </div>
        
        <div id="markerUI">
            <!-- In order to supply the final step of the form with the necessary show metadata for submission, we're adding a date field here with the
                 show date extracted from the file name. We're also going to add a dummy end time, because we don't care what time it ended, because it has already aired,
                 so will be posted to Mixcloud immediately anyway. -->
            <input type="hidden" name="date" value="<?php echo substr(basename($fileName), 0, 10); // this should work because file names should start with YYYY-MM-DD. Should. ?>" />
            <input type="hidden" name="end" value="00:00" />
            <input type="hidden" name="endNextDay" value="false" />
            
            <input type="hidden" id="timestamp" name="<?php echo isset($_GET["end"]) ? "endTimestamp" : "startTimestamp" ?>" />
            
            <div id="playback-controls" class="playback-controls">
                <button id="play" type="button" class="btn btn-symbol-only btn-outline-dark disabled-until-loaded" disabled title="Play">&#x23F5;&#xFE0E;</button>
                <button id="pause" type="button" class="btn btn-symbol-only btn-outline-dark disabled-until-loaded" disabled title="Pause">&#x23F8;&#xFE0E;</button>
                <button id="test" type="button" class="btn btn-outline-dark disabled-until-loaded" disabled title="Test">Test</button>
                <div class="form-group w-25 mb-1">
                    <label for="customRange1" class="form-label d-inline">Zoom</label>
                    <input type="range" class="form-range d-inline disabled-until-loaded" disabled id="waveformZoom" value=0 max=40>
                </div>
            </div>
            
            <!-- TODO Marker touch target awfy wee -->
            <div id="waveform">
                <div id="loading" class="waveform-loading">
                        <div class="spinner-border mb-2" aria-hidden="true"></div>
                        <strong role="status">Loading excerpt</strong>
                </div>
            </div>
            
            <button type="submit" id="submit-button" class="btn btn-primary mt-2 disabled-until-loaded" disabled>Continue</button>
        </div>
    </form>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/wavesurfer.js/7.10.1/wavesurfer.min.js" integrity="sha512-m656G613DjhTIQ13jStfPSGeOLVbW0S3JoqDP9Zr3GY5TRUQjWI+wqmX+CGQe4226EH0vfVmMJ3Gc9moPwnPSA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/wavesurfer.js/7.10.1/plugins/regions.min.js" integrity="sha512-yiFu6xR1WkwLgWPrrJ6k4Cykbf93ruI43KL8xN6lrVy1/te3nFQ4UMUPJ/W0BPz7eKrQSon7O3Yecy34UdnCFQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        // Warn the user before they navigate away, unless they're submitting the form
        window.onbeforeunload = function () { return true;};
        document.getElementById("form").addEventListener("submit", e => {
            window.onbeforeunload = null;
        })
        
        const timestamp = document.getElementById("timestamp");
        const loading = document.getElementById("loading");
        const error = document.getElementById("error");
        const markerUI = document.getElementById("markerUI");
        
        const ws = WaveSurfer.create({
            container: '#waveform',
            waveColor: 'rgb(33, 37, 41)',
            height: 256,
            url: undefined, // load audio separately so we can catch errors: https://github.com/katspaugh/wavesurfer.js/discussions/3055
        })

        ws.load('/api/excerpt.php?file=<?php echo $fileName; ?>&part=<?php echo isset($_GET["end"]) ? "end" : "start" ?>')
            .catch(function (error) {
                error.hidden = false;
                markerUI.hidden = true;
                console.error(error);
            })

        // Create marker and record marker location in hidden input as it changes
        const wsRegions = ws.registerPlugin(WaveSurfer.Regions.create({}))
        wsRegions.on('region-updated', (region) => {
            timestamp.value = <?php echo $excerptStartTime; ?> + region.start;
        })
        
        ws.once('decode', () => {
            // Create marker and initialise its location. Marker starts a third of the way into the file for the start,
            // and two thirds of the way in for the end.
            const markerInitialLocation = ws.getDuration() / 3 <?php echo isset($_GET["end"]) ? " * 2" : "" ?>;
            wsRegions.addRegion({
                start: markerInitialLocation,
                color: "rgb(255,0,0)"
            })
            timestamp.value = <?php echo $excerptStartTime; ?> + markerInitialLocation;
            
            // Make the zoom slider work
            waveformZoom.value = 0;
            waveformZoom.oninput = (e) => {
                const minPxPerSec = Number(e.target.value)
                ws.zoom(minPxPerSec)
            }
            
            // Not loading any more
            loading.hidden = true;
            Array.from(document.querySelectorAll(".disabled-until-loaded")).forEach(el => el.disabled = false);
        })
        
        <?php if (isset($_GET["end"])) { ?>
            // The Stopper pauses playback when you run into the end marker.
            let stopper;
            
            function createStopper() {
                if (!stopper) { // only create a stopper if one doesn't already exist, otherwise we lose track and can't stop the stoppers
                    stopper = ws.on("timeupdate", time => {
                        if (time >= wsRegions.getRegions()[0].start) {
                            ws.pause();
                            deleteStopper();
                        }
                    })
                }
            }
            
            function deleteStopper() {
                if (stopper) { // only delete a stopper if it exists
                    stopper(); // calling the stopper as a function deletes it
                    stopper = undefined; // without a trace
                }
            }
            
            // When audio playback starts, if we're starting playing before the marker, create a Stopper.
            ws.on("play", () => {
                if (ws.getCurrentTime() < wsRegions.getRegions()[0].start) {
                    createStopper();
                }
            })
            
            // If the user seeks while playing and goes beyond the marker, get rid of the stopper so playback doesn't suddenly end.
            // Otherwise, they must've seeked to before the marker, so create a stopper.
            ws.on("seeking", (timeSeekedTo) => {
                if (timeSeekedTo >= wsRegions.getRegions()[0].start){
                    console.log("Seeked after marker, deleting stopper")
                    deleteStopper();
                } else {
                    console.log("Seeked before marker, making a stopper")
                    console.log("stopper: ", stopper)
                    createStopper();
                }
            })
            
            ws.on("pause", () => {
                deleteStopper();
            })
            
            ws.on("finish", () => {
                deleteStopper();
            })
        <?php } ?>
        
        ////////////////////
        // Button Actions //
        ////////////////////
        ws.on('click', () => {
          ws.play()
        })
        
        document.getElementById("play").addEventListener("click", e => {
            ws.play();
        })
        
        document.getElementById("pause").addEventListener("click", e => {
            ws.pause();
        })
        
        document.getElementById("test").addEventListener("click", e => {
            // Start playing from the marker if testing the start, or 3 seconds before the marker if testing the end
            ws.setTime(wsRegions.getRegions()[0].start <?php echo isset($_GET["end"]) ? " - 3" : "" ?>);
            ws.play()
        })
    </script>
</body>
</html>
