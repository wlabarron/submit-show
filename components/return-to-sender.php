<?php
// Creates HTML hidden inputs for everything POSTed to the server
foreach ($_POST as $key => $value) {
    echo '<input type="hidden" id="' . $key . '" name="' . $key . '" value="' . $value . '"/>';
}
?>