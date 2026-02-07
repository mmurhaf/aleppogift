<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Bot Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .card { box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: none; margin-bottom: 20px; }
        .status-indicator { width: 15px; height: 15px; border-radius: 50%; display: inline-block; margin-right: 10px; }
        .status-running { background-color: #28a745; box-shadow: 0 0 10px #28a745; }
        .status-stopped { background-color: #dc3545; }
        .log-window { background: #1e1e1e; color: #00ff00; font-family: 'Courier New', monospace; padding: 15px; height: 300px; overflow-y: scroll; border-radius: 5px; font-size: 0.85rem; }
        .btn-action { min-width: 120px; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="text-center mb-0"><i class="fas fa-robot text-primary"></i> Social Bot Manager</h1>
                <p class="text-center text-muted">Control Panel for SocialMediaAutoBot Pro</p>
                <div class="text-center mt-2">
                    <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#readmeModal" onclick="loadReadme()">
                        <i class="fas fa-book"></i> View Documentation
                    </button>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- UI Service Card -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="card-title">Streamlit UI</h4>
                            <div>
                                <span id="ui-status-indicator" class="status-indicator status-stopped"></span>
                                <span id="ui-status-text" class="fw-bold text-danger">Stopped</span>
                            </div>
                        </div>
                        <p class="text-muted">The web interface for managing products and generating videos. Runs on Port 8501.</p>
                        
                        <div class="d-flex gap-2 mb-3">
                            <button onclick="controlService('start_ui')" class="btn btn-success btn-action" id="btn-start-ui">
                                <i class="fas fa-play"></i> Start
                            </button>
                            <button onclick="controlService('stop_ui')" class="btn btn-danger btn-action" id="btn-stop-ui" disabled>
                                <i class="fas fa-stop"></i> Stop
                            </button>
                            <button onclick="controlService('restart_ui')" class="btn btn-warning btn-action" id="btn-restart-ui">
                                <i class="fas fa-sync-alt"></i> Restart
                            </button>
                            <a href="http://localhost:8501" target="_blank" class="btn btn-primary btn-action" id="btn-open-ui">
                                <i class="fas fa-external-link-alt"></i> Open UI
                            </a>
                        </div>

                        <h6>Logs:</h6>
                        <div class="log-window" id="ui-logs">
                            Loading logs...
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scheduler Service Card -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="card-title">Scheduler Daemon</h4>
                            <div>
                                <span id="scheduler-status-indicator" class="status-indicator status-stopped"></span>
                                <span id="scheduler-status-text" class="fw-bold text-danger">Stopped</span>
                            </div>
                        </div>
                        <p class="text-muted">Background process that handles scheduled posts and analytics syncing.</p>
                        
                        <div class="d-flex gap-2 mb-3">
                            <button onclick="controlService('start_scheduler')" class="btn btn-success btn-action" id="btn-start-scheduler">
                                <i class="fas fa-play"></i> Start
                            </button>
                            <button onclick="controlService('stop_scheduler')" class="btn btn-danger btn-action" id="btn-stop-scheduler" disabled>
                                <i class="fas fa-stop"></i> Stop
                            </button>
                        </div>

                        <h6>Logs:</h6>
                        <div class="log-window" id="scheduler-logs">
                            Loading logs...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Documentation Modal -->
    <div class="modal fade" id="readmeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Application Documentation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <pre id="readme-content" style="white-space: pre-wrap; font-family: sans-serif;">Loading...</pre>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateStatus() {
            $.get('api.php?action=status', function(data) {
                // UI Status
                if (data.ui.running) {
                    $('#ui-status-indicator').removeClass('status-stopped').addClass('status-running');
                    $('#ui-status-text').removeClass('text-danger').addClass('text-success').text('Running (PID: ' + data.ui.pid + ')');
                    $('#btn-start-ui').prop('disabled', true);
                    $('#btn-stop-ui').prop('disabled', false);
                    $('#btn-restart-ui').prop('disabled', false);
                    // Always enable Open UI button so user can test connection even if status check lags
                    $('#btn-open-ui').removeClass('disabled');
                } else {
                    $('#ui-status-indicator').removeClass('status-running').addClass('status-stopped');
                    $('#ui-status-text').removeClass('text-success').addClass('text-danger').text('Stopped');
                    $('#btn-start-ui').prop('disabled', false);
                    $('#btn-stop-ui').prop('disabled', true);
                    $('#btn-restart-ui').prop('disabled', true);
                    // Keep enabled to allow manual checking
                    $('#btn-open-ui').removeClass('disabled'); 
                }

                // Scheduler Status
                if (data.scheduler.running) {
                    $('#scheduler-status-indicator').removeClass('status-stopped').addClass('status-running');
                    $('#scheduler-status-text').removeClass('text-danger').addClass('text-success').text('Running (PID: ' + data.scheduler.pid + ')');
                    $('#btn-start-scheduler').prop('disabled', true);
                    $('#btn-stop-scheduler').prop('disabled', false);
                } else {
                    $('#scheduler-status-indicator').removeClass('status-running').addClass('status-stopped');
                    $('#scheduler-status-text').removeClass('text-success').addClass('text-danger').text('Stopped');
                    $('#btn-start-scheduler').prop('disabled', false);
                    $('#btn-stop-scheduler').prop('disabled', true);
                }
            });
        }

        function refreshLogs() {
            $.get('api.php?action=get_logs&type=ui', function(data) {
                $('#ui-logs').html('<pre style="margin:0; white-space: pre-wrap;">' + (data.logs || 'No logs yet') + '</pre>');
                var uiLog = document.getElementById("ui-logs");
                uiLog.scrollTop = uiLog.scrollHeight;
            });
            $.get('api.php?action=get_logs&type=scheduler', function(data) {
                $('#scheduler-logs').html('<pre style="margin:0; white-space: pre-wrap;">' + (data.logs || 'No logs yet') + '</pre>');
                var schedLog = document.getElementById("scheduler-logs");
                schedLog.scrollTop = schedLog.scrollHeight;
            });
        }

        function controlService(action) {
            // Add loading state
            var btnId = '#btn-' + action.replace('_', '-');
            var originalText = $(btnId).html();
            $(btnId).html('<i class="fas fa-spinner fa-spin"></i> Processing...').prop('disabled', true);

            $.get('api.php?action=' + action, function(data) {
                // Wait a sec for process to spin up/down
                setTimeout(function() {
                    updateStatus();
                    refreshLogs();
                    alert(data.message);

        function loadReadme() {
            $.get('api.php?action=get_readme', function(data) {
                $('#readme-content').text(data.content);
            }, 'json');
        }
                }, 2000);
            }).fail(function() {
                alert('Request failed');
                updateStatus(); // Reset buttons
            });
        }

        // Initial Load
        $(document).ready(function() {
            updateStatus();
            refreshLogs();

            // Auto refresh
            setInterval(updateStatus, 5000);
            setInterval(refreshLogs, 5000);
        });
    </script>
</body>
</html>
