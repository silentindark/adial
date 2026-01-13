<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-md-6">
            <h2>Campaign: <?php echo htmlspecialchars($campaign->name); ?></h2>
        </div>
        <div class="col-md-6 text-right">
            <?php if ($campaign->status == 'stopped'): ?>
                <button type="button" class="btn btn-success btn-control" data-action="start">
                    <i class="fas fa-play"></i> Start
                </button>
            <?php elseif ($campaign->status == 'running'): ?>
                <button type="button" class="btn btn-warning btn-control" data-action="pause">
                    <i class="fas fa-pause"></i> Pause
                </button>
                <button type="button" class="btn btn-danger btn-control" data-action="stop">
                    <i class="fas fa-stop"></i> Stop
                </button>
            <?php elseif ($campaign->status == 'paused'): ?>
                <button type="button" class="btn btn-success btn-control" data-action="start">
                    <i class="fas fa-play"></i> Resume
                </button>
                <button type="button" class="btn btn-danger btn-control" data-action="stop">
                    <i class="fas fa-stop"></i> Stop
                </button>
            <?php endif; ?>

            <a href="<?php echo site_url('campaigns/edit/'.$campaign->id); ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="<?php echo site_url('campaigns'); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Campaign Stats -->
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3><?php echo isset($stats->total) ? $stats->total : 0; ?></h3>
                    <small class="text-muted">Total Numbers</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="text-warning"><?php echo isset($stats->pending) ? $stats->pending : 0; ?></h3>
                    <small class="text-muted">Pending</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="text-success"><?php echo isset($stats->completed) ? $stats->completed : 0; ?></h3>
                    <small class="text-muted">Completed</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="text-danger"><?php echo isset($stats->failed) ? $stats->failed : 0; ?></h3>
                    <small class="text-muted">Failed</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Campaign Details</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th width="30%">Name</th>
                            <td><?php echo htmlspecialchars($campaign->name); ?></td>
                        </tr>
                        <tr>
                            <th>Description</th>
                            <td><?php echo htmlspecialchars($campaign->description); ?></td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <?php
                                $status_class = array(
                                    'running' => 'success',
                                    'stopped' => 'secondary',
                                    'paused' => 'warning'
                                );
                                $class = isset($status_class[$campaign->status]) ? $status_class[$campaign->status] : 'secondary';
                                ?>
                                <span class="badge badge-<?php echo $class; ?>" id="campaign-status">
                                    <?php echo ucfirst($campaign->status); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Trunk Type</th>
                            <td><?php echo strtoupper($campaign->trunk_type); ?></td>
                        </tr>
                        <tr>
                            <th>Trunk Value</th>
                            <td><code><?php echo htmlspecialchars($campaign->trunk_value); ?></code></td>
                        </tr>
                        <tr>
                            <th>Caller ID</th>
                            <td><?php echo htmlspecialchars($campaign->callerid); ?></td>
                        </tr>
                        <tr>
                            <th>Agent Destination</th>
                            <td>
                                <strong><?php echo ucfirst($campaign->agent_dest_type); ?>:</strong>
                                <code><?php echo htmlspecialchars($campaign->agent_dest_value); ?></code>
                            </td>
                        </tr>
                        <tr>
                            <th>Record Calls</th>
                            <td>
                                <?php if ($campaign->record_calls): ?>
                                    <span class="badge badge-success">Yes</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">No</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Concurrent Calls</th>
                            <td><?php echo $campaign->concurrent_calls; ?></td>
                        </tr>
                        <tr>
                            <th>Retry Times</th>
                            <td><?php echo $campaign->retry_times; ?></td>
                        </tr>
                        <tr>
                            <th>Retry Delay</th>
                            <td><?php echo $campaign->retry_delay; ?> seconds</td>
                        </tr>
                        <tr>
                            <th>Created</th>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($campaign->created_at)); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Upload Numbers</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="<?php echo site_url('campaigns/upload_numbers/'.$campaign->id); ?>" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>CSV File</label>
                            <input type="file" class="form-control-file" name="csv_file" accept=".csv,.txt" required>
                            <small class="form-text text-muted">
                                Upload CSV file with format: <code>number,name</code> (one per line, name is optional)<br>
                                Example: 1234567890,John Doe
                            </small>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload
                        </button>
                    </form>

                    <hr>

                    <h6>Add Numbers (Bulk)</h6>
                    <form id="addNumbersForm">
                        <div class="form-group">
                            <label>Numbers (one per line)</label>
                            <textarea class="form-control" id="numbers_bulk" name="numbers_bulk" rows="5"
                                      placeholder="Format: number,name&#10;Example:&#10;1234567890,John Doe&#10;9876543210,Jane Smith&#10;5555555555&#10;(name is optional)" required></textarea>
                            <small class="form-text text-muted">
                                Enter one number per line. Format: <code>number,name</code> (name is optional)
                            </small>
                        </div>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Numbers
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Numbers List -->
    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>
                        Campaign Numbers
                        <span class="badge badge-primary float-right">
                            <?php echo !empty($numbers) ? count($numbers) : 0; ?> numbers
                        </span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Phone Number</th>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Attempts</th>
                                    <th>Last Attempt</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($numbers)): ?>
                                    <?php foreach ($numbers as $number): ?>
                                        <?php
                                        // Parse data JSON to get name
                                        $name = '';
                                        if ($number->data) {
                                            $data = json_decode($number->data, true);
                                            $name = isset($data['name']) ? $data['name'] : '';
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo $number->id; ?></td>
                                            <td><?php echo htmlspecialchars($number->phone_number); ?></td>
                                            <td><?php echo $name ? htmlspecialchars($name) : '<span class="text-muted">-</span>'; ?></td>
                                            <td>
                                                <?php
                                                $status_classes = array(
                                                    'pending' => 'warning',
                                                    'calling' => 'info',
                                                    'answered' => 'success',
                                                    'failed' => 'danger',
                                                    'completed' => 'success',
                                                    'no_answer' => 'secondary',
                                                    'busy' => 'warning'
                                                );
                                                $class = isset($status_classes[$number->status]) ? $status_classes[$number->status] : 'secondary';
                                                ?>
                                                <span class="badge badge-<?php echo $class; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $number->status)); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $number->attempts; ?></td>
                                            <td><?php echo $number->last_attempt ? date('Y-m-d H:i:s', strtotime($number->last_attempt)) : '-'; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-danger btn-sm btn-delete-number" data-id="<?php echo $number->id; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <div class="text-muted">
                                                <i class="fas fa-list-ol fa-3x mb-3"></i>
                                                <h5>No numbers added yet</h5>
                                                <p>Use the "Add Numbers (Bulk)" form above or upload a CSV file to add phone numbers to this campaign.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Campaign control
    $('.btn-control').click(function() {
        var action = $(this).data('action');
        var campaignId = <?php echo $campaign->id; ?>;

        $.ajax({
            url: '<?php echo site_url('campaigns/control'); ?>/' + campaignId + '/' + action,
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Failed to control campaign');
            }
        });
    });

    // Add numbers (bulk)
    $('#addNumbersForm').submit(function(e) {
        e.preventDefault();

        var numbersBulk = $('#numbers_bulk').val();
        var campaignId = <?php echo $campaign->id; ?>;

        if (!numbersBulk.trim()) {
            alert('Please enter at least one number');
            return;
        }

        // Disable button during submission
        var $btn = $(this).find('button[type="submit"]');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Adding...');

        $.ajax({
            url: '<?php echo site_url('campaigns/add_numbers_bulk'); ?>/' + campaignId,
            type: 'POST',
            data: { numbers_bulk: numbersBulk },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success message
                    alert('✓ ' + response.message + '\n\nPage will reload to show the numbers.');
                    // Clear the textarea
                    $('#numbers_bulk').val('');
                    // Reload page
                    window.location.reload(true); // Force reload from server
                } else {
                    alert('Error: ' + response.message);
                    $btn.prop('disabled', false).html('<i class="fas fa-plus"></i> Add Numbers');
                }
            },
            error: function(xhr, status, error) {
                alert('Failed to add numbers. Error: ' + error);
                $btn.prop('disabled', false).html('<i class="fas fa-plus"></i> Add Numbers');
            }
        });
    });

    // Delete number
    $('.btn-delete-number').click(function() {
        if (confirm('Are you sure you want to delete this number?')) {
            var numberId = $(this).data('id');

            $.ajax({
                url: '<?php echo site_url('campaigns/delete_number'); ?>/' + numberId,
                type: 'POST',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Failed to delete number');
                }
            });
        }
    });
});
</script>
