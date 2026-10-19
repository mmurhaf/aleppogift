<?php
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$baseDir = dirname(__DIR__); // social_bot_local
$logDir = "$baseDir/output/logs";
$runDir = "$baseDir/output/run";

if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

function pidIsAlive($pid) {
    $pid = (int) $pid;
    if ($pid <= 0) {
        return false;
    }
    $output = shell_exec('tasklist /FI "PID eq ' . $pid . '" /NH');
    return is_string($output) && strpos($output, (string) $pid) !== false;
}

function readPidFile($path) {
    if (!is_file($path)) {
        return null;
    }
    $pid = trim((string) file_get_contents($path));
    return pidIsAlive($pid) ? $pid : null;
}

function getProcessId($scriptName) {
    global $runDir;
    $pidFile = $scriptName === 'scheduler_daemon.py' ? "$runDir/scheduler.pid" : "$runDir/ui.pid";
    $fromFile = readPidFile($pidFile);
    if ($fromFile) {
        return $fromFile;
    }

    $needle = str_replace("'", "''", $scriptName);
    $cmd = "powershell -NoProfile -Command \"Get-CimInstance Win32_Process | Where-Object { \$_.CommandLine -like '*$needle*' } | Select-Object -ExpandProperty ProcessId -First 1\"";
    $output = trim((string) shell_exec($cmd));
    if (preg_match('/^\d+$/', $output) && pidIsAlive($output)) {
        return $output;
    }
    return null;
}

function isRunning($pid) {
    if (!$pid) return false;
    $cmd = 'tasklist /FI "PID eq ' . $pid . '"';
    $output = shell_exec($cmd);
    return strpos($output, $pid) !== false;
}

function killProcess($pid) {
    if ($pid) {
        shell_exec("taskkill /F /T /PID $pid");
        return true;
    }
    return false;
}

function tailLog($file, $lines = 50) {
    if (!file_exists($file)) return "Log file not found.";
    // PowerShell is reliable for tailing on Windows
    $cmd = "powershell -command \"Get-Content '$file' -Tail $lines\"";
    return shell_exec($cmd);
}

switch ($action) {
    case 'status':
        $uiPid = getProcessId('streamlit run app.py');
        $schedulerPid = getProcessId('scheduler_daemon.py');
        
        echo json_encode([
            'ui' => [
                'running' => $uiPid ? true : false,
                'pid' => $uiPid,
                'url' => 'http://localhost:8501'
            ],
            'scheduler' => [
                'running' => $schedulerPid ? true : false,
                'pid' => $schedulerPid
            ]
        ]);
        break;

    case 'start_ui':
        $uiPid = getProcessId('streamlit run app.py');
        if ($uiPid) {
            echo json_encode(['status' => 'error', 'message' => 'UI is already running']);
        } else {
            // Using batch file wrapper to ensure correct Working Directory (crucial for imports) and Paths
            $batFile = "$baseDir\\run_ui.bat";
            // start /B runs in background. cmd /c runs the bat.
            $cmd = "start /B cmd /c \"$batFile\"";
            pclose(popen($cmd, "r"));
            
            echo json_encode(['status' => 'success', 'message' => 'UI started (checking...)']);
        }
        break;

    case 'stop_ui':
        $uiPid = getProcessId('streamlit run app.py');
        if ($uiPid) {
            killProcess($uiPid);
            echo json_encode(['status' => 'success', 'message' => 'UI stopped']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'UI not running']);
        }
        break;

    case 'restart_ui':
        $uiPid = getProcessId('streamlit run app.py');
        if ($uiPid) {
            killProcess($uiPid);
            sleep(2); // Wait for port to clear
        }
        $batFile = "$baseDir\\run_ui.bat";
        $cmd = "start /B cmd /c \"$batFile\"";
        pclose(popen($cmd, "r"));
        
        echo json_encode(['status' => 'success', 'message' => 'UI restarted']);
        break;

    case 'start_scheduler':
        $schedPid = getProcessId('scheduler_daemon.py');
        if ($schedPid) {
            echo json_encode(['status' => 'error', 'message' => 'Scheduler is already running']);
        } else {
            // Using batch file wrapper
            $batFile = "$baseDir\\run_scheduler.bat";
            $cmd = "start /B cmd /c \"$batFile\"";
            pclose(popen($cmd, "r"));
            
            echo json_encode(['status' => 'success', 'message' => 'Scheduler started']);
        }
        break;

    case 'stop_scheduler':
        $schedPid = getProcessId('scheduler_daemon.py');
        if ($schedPid) {
            killProcess($schedPid);
            echo json_encode(['status' => 'success', 'message' => 'Scheduler stopped']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Scheduler not running']);
        }
        break;

    case 'get_logs':
        $type = $_GET['type'] ?? 'ui';
        // Correcting logic based on likely client call
        if ($type === 'scheduler') $file = "$logDir/scheduler.log";
        else $file = "$logDir/ui.log";

        echo json_encode(['logs' => tailLog($file)]);
        break;

    case 'get_readme':
        $readmePath = "$baseDir/README.md";
        if (file_exists($readmePath)) {
            echo json_encode(['content' => file_get_contents($readmePath)]);
        } else {
            echo json_encode(['content' => 'README.md not found.']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}
?>