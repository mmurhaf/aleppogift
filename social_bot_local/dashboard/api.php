<?php
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$baseDir = dirname(__DIR__); // social_bot_local
$pythonPath = "C:/xampp/htdocs/aleppogift/.venv/Scripts/python.exe";
$logDir = "$baseDir/output/logs";

if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

function getProcessId($scriptName) {
    // Escape backslashes for WQL
    $search = str_replace("\\", "\\\\", $scriptName);
    // On Windows, use wmic to find python processes containing the script name
    $cmd = 'wmic process where "name=\'python.exe\' and commandline like \'%' . $search . '%\'" get processid';
    $output = shell_exec($cmd);
    
    // Parse output
    $lines = explode("\n", trim($output));
    foreach ($lines as $line) {
        if (preg_match('/^\d+$/', trim($line))) {
            return trim($line);
        }
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
        shell_exec("taskkill /F /PID $pid");
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