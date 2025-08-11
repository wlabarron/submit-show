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
    <h1 class="h3">Mark the start</h1>
    <form id="form" method="POST" action="select-start.php">
        <?php require './components/return-to-sender.php'; ?>
        
        <p>This is an excerpt of the first <?php echo floor($config["serverRecordings"]["auditionTime"] / 60) ?> min of the recording you selected. Drag the red marker to the exact moment the show started.</p>
        <p>Use the play and pause buttons to listen to the excerpt, and the Test button to start playing from where you dropped the marker. The Zoom slider lets you look closer.</p>
        
        <input type="hidden" id="startTimestamp" name="startTimestamp" />
        
        <div class="playback-controls">
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
    </form>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/wavesurfer.js/7.10.1/wavesurfer.min.js" integrity="sha512-m656G613DjhTIQ13jStfPSGeOLVbW0S3JoqDP9Zr3GY5TRUQjWI+wqmX+CGQe4226EH0vfVmMJ3Gc9moPwnPSA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/wavesurfer.js/7.10.1/plugins/regions.min.js" integrity="sha512-yiFu6xR1WkwLgWPrrJ6k4Cykbf93ruI43KL8xN6lrVy1/te3nFQ4UMUPJ/W0BPz7eKrQSon7O3Yecy34UdnCFQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        const fileUrl = '/api/excerpt.php?file=<?php echo $selectedFile; ?>&part=start';
    </script>
    <script>
        // Warn the user before they navigate away, unless they're submitting the form
        window.onbeforeunload = function () { return true;};
        document.getElementById("form").addEventListener("submit", e => {
            window.onbeforeunload = null;
        })
        
        const startTimestamp = document.getElementById("startTimestamp");
        const loading = document.getElementById("loading");
        
        const ws = WaveSurfer.create({
            container: '#waveform',
            waveColor: 'rgb(33, 37, 41)',
            url: undefined, // load audio separately so we can catch errors: https://github.com/katspaugh/wavesurfer.js/discussions/3055
        })
        
        ws.load(fileUrl)
            .catch(function (error) {
                // TODO Sensible solution
                console.error(error);
            })
        
        // TODO WebKit bug for the way we load audio if there is no type=""

        const wsRegions = ws.registerPlugin(WaveSurfer.Regions.create({}))
                
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
        
        wsRegions.on('region-updated', (region) => {
            startTimestamp.value = region.start;
        })
        
        ws.once('decode', () => {
            const regionStart = ws.getDuration() / 3;
            
            wsRegions.addRegion({
                start: regionStart,
                color: "rgb(255,0,0)"
            })
            
            startTimestamp.value = regionStart;
            
            waveformZoom.value = 0;
            waveformZoom.oninput = (e) => {
                const minPxPerSec = Number(e.target.value)
                ws.zoom(minPxPerSec)
            }
            
            loading.hidden = true;
        })

    </script>
</body>
</html>
