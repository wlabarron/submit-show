<?php 
require __DIR__ . '/../scripts/postOnly.php';
require __DIR__ . '/../scripts/promptLogin.php';
$config = require __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require __DIR__ . "/../components/head.html"; ?>
</head>
<body>
<?php require __DIR__ . "/../components/noscript.html"; ?>

<div class="container">
    <h1 class="h3">About your upload</h1>
    <form id="form" method="POST" action="upload-file.php">
        <?php require __DIR__ . '/../components/return-to-sender.php'; ?>
        
        <div class="form-group">
            <label for="date">Show date</label>
            <input type="date" class="form-control" id="date" aria-describedby="dateHelp" name="date" required
                   placeholder="YYYY-MM-DD" min="1970-01-01">
            <small id="dateHelp" class="form-text text-muted">
                Enter the first broadcast date of this show.
            </small>
        </div>
        
        <div class="form-group">
            <label for="end">End time</label>
            <input type="time" class="form-control" id="end" aria-describedby="endHelp"
                   name="end" placeholder="HH:MM" required>
            <small id="endHelp" class="form-text text-muted">
                Enter the time this show ended or will finish when broadcast on air. This doesn't need to be
                to-the-minute; if your show's time slot is 12:00-14:00, you can enter 14:00 here, even if you
                finished at 13:56.
            </small>
        </div>
        
        <div class="form-check">
            <input class="form-check-input" type="radio" name="endNextDay" value="0" id="endSameDay" checked>
            <label class="form-check-label" for="endSameDay">
                My show finished on the same day it started
            </label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="endNextDay" value="1" id="endNextDay">
            <label class="form-check-label" for="endNextDay">
                My show runs over midnight, finishing the day after it started
            </label>
        </div>
        
        <button type="submit" id="submit-button" class="btn btn-primary mt-3">Continue</button>
    </form>
    
    <script>
        // Warn the user before they navigate away, unless they're submitting the form
        window.onbeforeunload = function () { return true;};
        document.getElementById("form").addEventListener("submit", e => {
            window.onbeforeunload = null;
            document.getElementById("submit-button").disabled = true; // prevent double submission
        })
    </script>
</body>
</html>
