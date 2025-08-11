<?php 
require './components/post-only.php';
require './processing/promptLogin.php'; 
require_once  'processing/Input.php';
$config = require './processing/config.php';

$selectedFile = Input::sanitise($_POST["selectedFile"]);
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
            <p>Use the play and pause buttons to listen to the excerpt, and the Test button to start playing from where you dropped the marker. The Zoom slider lets you look closer.</p>";
        } else {
            echo "<p>This is an excerpt of the first " . $config["serverRecordings"]["auditionTime"] / 60 . " min of the recording you selected. Drag the red marker to the exact moment the show started.</p>
            <p>Use the play and pause buttons to listen to the excerpt, and the Test button to start playing from where you dropped the marker. The Zoom slider lets you look closer.</p>";
        }?>
        
        <div id="error" class="alert alert-danger" hidden>
            <span><strong>Couldn't load excerpt.</strong> Please go back or refresh the page and try again.</span>
        </div>
        
        <div id="markerUI">
            <input type="hidden" id="timestamp" name="<?php echo isset($_GET["end"]) ? "endTimestamp" : "startTimestamp" ?>" />
            
            <div id="playback-controls" class="playback-controls">
                <button id="play" type="button" class="btn btn-symbol-only btn-outline-dark" title="Play">&#x23F5;&#xFE0E;</button>
                <button id="pause" type="button" class="btn btn-symbol-only btn-outline-dark" title="Pause">&#x23F8;&#xFE0E;</button>
                <button id="test" type="button" class="btn btn-outline-dark" title="Test">Test</button>
                <div class="form-group w-25 mb-1">
                    <label for="customRange1" class="form-label d-inline">Zoom</label>
                    <input type="range" class="form-range d-inline" id="waveformZoom" value=0 max=40>
                </div>
            </div>
            
            <div id="waveform">
                <div id="loading" class="waveform-loading">
                        <div class="spinner-border mb-2" aria-hidden="true"></div>
                        <strong role="status">Loading excerpt</strong>
                </div>
            </div>
            
            <button type="submit" id="submit-button" class="btn btn-primary mt-2">Continue</button>
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

        ws.load('/api/excerpt.php?file=<?php echo $selectedFile; ?>&part=<?php echo isset($_GET["end"]) ? "end" : "start" ?>')
            .catch(function (error) {
                error.hidden = false;
                markerUI.hidden = true;
                console.error(error);
            })

        // Create marker and record marker location in hidden input as it changes
        const wsRegions = ws.registerPlugin(WaveSurfer.Regions.create({}))
        wsRegions.on('region-updated', (region) => {
            timestamp.value = region.start;
        })
        
        ws.once('decode', () => {
            // Create marker and initialise its location. Marker starts a third of the way into the file for the start,
            // and two thirds of the way in for the end.
            const markerInitialLocation = ws.getDuration() / 3 <?php echo isset($_GET["end"]) ? " * 2" : "" ?>;
            wsRegions.addRegion({
                start: markerInitialLocation,
                color: "rgb(255,0,0)"
            })
            timestamp.value = markerInitialLocation;
            
            // Make the zoom slider work
            waveformZoom.value = 0;
            waveformZoom.oninput = (e) => {
                const minPxPerSec = Number(e.target.value)
                ws.zoom(minPxPerSec)
            }
            
            // Not loading any more
            loading.hidden = true;
        })
        
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
            ws.setTime(wsRegions.getRegions()[0].start);
            ws.play();
        })
    </script>
</body>
</html>
