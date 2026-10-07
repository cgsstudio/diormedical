<?php
defined('ABSPATH') || exit;

// Get all patients
$patients = get_users([
    'role' => 'patient',
    'orderby' => 'display_name',
    'order' => 'ASC'
]);
?>

<section class="dior-tab-panel" id="tab-doc-form-test" style="display:none;">
    <div class="dior-content-pad">
        <div class="dior-page-title-bar new-design">
            <div class="title-left">
                <div class="title-icon-box blue-tint dior-ic-cc5b134d4e">
                    <i class="fas fa-file-medical"></i>
                </div>
                <div>
                    <h1>HIPAAtizer Form API Test</h1>
                    <p class="title-sub">Testing the /dior/v2/hipaa-intake REST API endpoint.</p>
                </div>
            </div>
        </div>

        <div class="master-table-wrapper">
            <div class="master-table-container">
                <div class="master-table-card">
                    <div class="master-table-header">
                        <div class="header-content">
                            <div class="table-title-section">
                                <h2 class="table-title">
                                    <i class="fas fa-server" style="margin-right:8px;"></i>REST API Data
                                </h2>
                                <div class="title-accent"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-content">
                        <table class="va-table" id="dior-form-test-api-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Email</th>
                                    <th>Action</th>
                                    <th>API Response</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($patients as $p): ?>
                                    <tr>
                                        <td><span class="cell-text"><strong><?php echo esc_html($p->display_name); ?></strong></span></td>
                                        <td><span class="cell-text"><?php echo esc_html($p->user_email); ?></span></td>
                                        <td>
                                            <button class="dior-btn-gold-primary dior-btn-sm" onclick="fetchHipaaIntake(<?php echo $p->ID; ?>)">
                                                <i class="fas fa-download"></i> Fetch Data via API
                                            </button>
                                        </td>
                                        <td>
                                            <div id="api_response_<?php echo $p->ID; ?>" style="display:none; max-width:400px; max-height:200px; overflow:auto; background:#1e293b; color:#a5b4fc; padding:10px; border-radius:6px; font-size:11px; margin-top:10px;">
                                                Loading...
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
function fetchHipaaIntake(patientId) {
    var responseBox = document.getElementById('api_response_' + patientId);
    responseBox.style.display = 'block';
    responseBox.innerText = 'Fetching from /wp-json/dior/v2/hipaa-intake/' + patientId + ' ...';
    
    // Add nonce if available, otherwise just standard fetch
    var headers = {
        'Accept': 'application/json'
    };
    if (typeof wpApiSettings !== 'undefined' && wpApiSettings.nonce) {
        headers['X-WP-Nonce'] = wpApiSettings.nonce;
    }

    fetch('/wp-json/dior/v2/hipaa-intake/' + patientId, {
        method: 'GET',
        headers: headers
    })
    .then(response => response.json())
    .then(data => {
        responseBox.innerText = JSON.stringify(data, null, 2);
    })
    .catch(error => {
        responseBox.style.color = '#ef4444';
        responseBox.innerText = 'Error: ' + error.message;
    });
}
</script>
