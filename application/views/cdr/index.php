<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-md-6">
            <h2><?php echo $this->lang->line('cdr_title'); ?></h2>
        </div>
        <div class="col-md-6 text-right">
            <button type="button" class="btn btn-success" id="exportBtn">
                <i class="fas fa-file-excel"></i> <?php echo $this->lang->line('cdr_export_csv'); ?>
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-header">
            <h5><i class="fas fa-filter"></i> <?php echo $this->lang->line('cdr_filters'); ?></h5>
        </div>
        <div class="card-body">
            <form method="get" id="filterForm">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="campaign_id"><?php echo $this->lang->line('cdr_campaign'); ?></label>
                            <select class="form-control" id="campaign_id" name="campaign_id">
                                <option value=""><?php echo $this->lang->line('cdr_all_campaigns'); ?></option>
                                <?php if (!empty($campaigns)): ?>
                                    <?php foreach ($campaigns as $campaign): ?>
                                        <option value="<?php echo $campaign->id; ?>" <?php echo ($filter_campaign_id == $campaign->id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($campaign->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="disposition"><?php echo $this->lang->line('cdr_disposition'); ?></label>
                            <select class="form-control" id="disposition" name="disposition">
                                <option value=""><?php echo $this->lang->line('cdr_all_dispositions'); ?></option>
                                <option value="answered" <?php echo ($filter_disposition == 'answered') ? 'selected' : ''; ?>><?php echo $this->lang->line('cdr_disposition_answered'); ?></option>
                                <option value="no_answer" <?php echo ($filter_disposition == 'no_answer') ? 'selected' : ''; ?>><?php echo $this->lang->line('cdr_disposition_no_answer'); ?></option>
                                <option value="busy" <?php echo ($filter_disposition == 'busy') ? 'selected' : ''; ?>><?php echo $this->lang->line('cdr_disposition_busy'); ?></option>
                                <option value="failed" <?php echo ($filter_disposition == 'failed') ? 'selected' : ''; ?>><?php echo $this->lang->line('cdr_disposition_failed'); ?></option>
                                <option value="cancelled" <?php echo ($filter_disposition == 'cancelled') ? 'selected' : ''; ?>><?php echo $this->lang->line('cdr_disposition_cancelled'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="date_from"><?php echo $this->lang->line('cdr_date_from'); ?></label>
                            <input type="date" class="form-control" id="date_from" name="date_from"
                                   value="<?php echo $filter_date_from; ?>">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="date_to"><?php echo $this->lang->line('cdr_date_to'); ?></label>
                            <input type="date" class="form-control" id="date_to" name="date_to"
                                   value="<?php echo $filter_date_to; ?>">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label>&nbsp;</label><br>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> <?php echo $this->lang->line('btn_filter'); ?>
                            </button>
                            <a href="<?php echo site_url('cdr'); ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> <?php echo $this->lang->line('btn_clear'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- CDR Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm">
                    <thead>
                        <tr>
                            <th><?php echo $this->lang->line('cdr_id'); ?></th>
                            <th><?php echo $this->lang->line('cdr_campaign'); ?></th>
                            <th><?php echo $this->lang->line('cdr_callerid'); ?></th>
                            <th><?php echo $this->lang->line('cdr_destination'); ?></th>
                            <th><?php echo $this->lang->line('cdr_name'); ?></th>
                            <th><?php echo $this->lang->line('cdr_agent'); ?></th>
                            <th><?php echo $this->lang->line('cdr_start_time'); ?></th>
                            <th><?php echo $this->lang->line('cdr_answer_time'); ?></th>
                            <th><?php echo $this->lang->line('cdr_end_time'); ?></th>
                            <th><?php echo $this->lang->line('cdr_duration'); ?></th>
                            <th><?php echo $this->lang->line('cdr_billsec'); ?></th>
                            <th><?php echo $this->lang->line('cdr_disposition'); ?></th>
                            <th><?php echo $this->lang->line('cdr_recording'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($cdr_records)): ?>
                            <?php foreach ($cdr_records as $cdr): ?>
                                <?php
                                // Get destination name (only available for internal CDR, not asteriskcdrdb)
                                $dest_name = '';
                                if (isset($cdr->campaign_number_id) && $cdr->campaign_number_id) {
                                    $number = $this->Campaign_number_model->get_by_id($cdr->campaign_number_id);
                                    if ($number && $number->data) {
                                        $data = json_decode($number->data, true);
                                        $dest_name = isset($data['name']) ? $data['name'] : '';
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?php echo $cdr->id; ?></td>
                                    <td>
                                        <?php if ($cdr->campaign_id): ?>
                                            <a href="<?php echo site_url('campaigns/view/'.$cdr->campaign_id); ?>">
                                                Campaign #<?php echo $cdr->campaign_id; ?>
                                            </a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($cdr->callerid); ?></td>
                                    <td><?php echo htmlspecialchars($cdr->destination); ?></td>
                                    <td><?php echo $dest_name ? htmlspecialchars($dest_name) : '<span class="text-muted">-</span>'; ?></td>
                                    <td><?php echo htmlspecialchars($cdr->agent); ?></td>
                                    <td><?php echo $cdr->start_time ? date('Y-m-d H:i:s', strtotime($cdr->start_time)) : '-'; ?></td>
                                    <td><?php echo $cdr->answer_time ? date('Y-m-d H:i:s', strtotime($cdr->answer_time)) : '-'; ?></td>
                                    <td><?php echo $cdr->end_time ? date('Y-m-d H:i:s', strtotime($cdr->end_time)) : '-'; ?></td>
                                    <td><?php echo gmdate('H:i:s', $cdr->duration); ?></td>
                                    <td><?php echo gmdate('H:i:s', $cdr->billsec); ?></td>
                                    <td>
                                        <?php
                                        $disposition_class = array(
                                            'answered' => 'success',
                                            'no_answer' => 'warning',
                                            'busy' => 'info',
                                            'failed' => 'danger',
                                            'cancelled' => 'secondary'
                                        );
                                        $class = isset($disposition_class[$cdr->disposition]) ? $disposition_class[$cdr->disposition] : 'secondary';
                                        ?>
                                        <span class="badge badge-<?php echo $class; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $cdr->disposition)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $recording_exists = false;
                                        $full_recording_path = '';
                                        if (!empty($cdr->recording_file)) {
                                            // Check if it's an absolute path (starts with /)
                                            if (strpos($cdr->recording_file, '/') === 0) {
                                                $full_recording_path = $cdr->recording_file;
                                            } else {
                                                // Try monitor directory first (dialer recordings)
                                                $full_recording_path = '/var/spool/asterisk/monitor/' . $cdr->recording_file;
                                                if (!file_exists($full_recording_path)) {
                                                    // Fall back to recording directory
                                                    $full_recording_path = '/var/spool/asterisk/recording/' . $cdr->recording_file;
                                                }
                                            }
                                            $recording_exists = file_exists($full_recording_path);
                                        }
                                        ?>
                                        <?php if ($recording_exists): ?>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-primary btn-sm btn-play"
                                                        data-id="<?php echo $cdr->id; ?>"
                                                        title="Play">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                                <a href="<?php echo site_url('cdr/download_recording/'.$cdr->id); ?>"
                                                   class="btn btn-success btn-sm"
                                                   title="Download">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="13" class="text-center"><?php echo $this->lang->line('cdr_no_records'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if (!empty($pagination)): ?>
                <div class="mt-3">
                    <?php echo $pagination; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Audio Player Modal -->
<div class="modal fade" id="audioModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo $this->lang->line('cdr_play_recording'); ?></h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <audio id="audioPlayer" controls style="width: 100%;">
                    <source id="audioSource" src="" type="audio/mpeg">
                    Your browser does not support the audio element.
                </audio>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Play recording
    $('.btn-play').click(function() {
        var cdrId = $(this).data('id');
        var audioUrl = '<?php echo site_url('cdr/play_recording'); ?>/' + cdrId;

        $('#audioSource').attr('src', audioUrl);
        $('#audioPlayer')[0].load();
        $('#audioModal').modal('show');
    });

    // Export button
    $('#exportBtn').click(function() {
        // Get current filter values
        var params = $('#filterForm').serialize();
        window.location.href = '<?php echo site_url('cdr/export'); ?>?' + params;
    });
});
</script>
