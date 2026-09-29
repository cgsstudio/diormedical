<section class="dior-tab-panel" id="tab-doc-payments">
                    <div class="dior-content-pad">
                        <div class="dior-page-title-bar new-design">
                            <div class="title-left">
                                <div class="title-icon-box blue-tint dior-ic-cc5b134d4e">
                                    <i class="fa-solid fa-credit-card"></i>
                                </div>
                                <div>
                                    <h1>Payment Records</h1>
                                    <p class="title-sub">All patient payment transactions via Stripe.</p>
                                </div>
                            </div>
                        </div>
                        <div class="dior-dash-table-card dior-box-card">
                            <div class="box-title-row">
                                <h3><i class="fa-solid fa-credit-card"></i> All Payments</h3>
                            </div>
                            <div class="table-responsive dior-ic-6599399370">
                                <?php
                                $all_pays = [];
                                foreach ($patients as $p) {
                                    $pays = get_user_meta($p['user_id'], 'dior_payments', true);
                                    if (!is_array($pays))
                                        continue;
                                    foreach ($pays as $pay) {
                                        $pay['patient_name'] = $p['full_name'];
                                        $all_pays[] = $pay;
                                    }
                                }
                                ?>
                                <table class="dior-clean-table" id="dior-payments-table">
                                    <thead>
                                        <tr>
                                            <th>Patient</th>
                                            <th>Invoice</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($all_pays)): ?>
                                            <tr>
                                                <td colspan="6" class="dior-ic-5996a6f9a0">No
                                                    payments found.</td>
                                            </tr>
                                        <?php else:
                                            foreach ($all_pays as $pay): ?>
                                                <tr>
                                                    <td><strong><?php echo esc_html($pay['patient_name'] ?? '�'); ?></strong>
                                                    </td>
                                                    <td><code
                                                            class="dior-ic-c1186de9bb"><?php echo esc_html($pay['id'] ?? '�'); ?></code>
                                                    </td>
                                                    <td class="dior-ic-f645209eb4">
                                                        <?php echo esc_html($pay['amount'] ?? '�'); ?>
                                                    </td>
                                                    <td><?php echo esc_html($pay['date'] ?? '�'); ?></td>
                                                    <td><?php echo esc_html($pay['method'] ?? 'Stripe'); ?></td>
                                                    <td><span
                                                            class="dior-st <?php echo strtolower($pay['status'] ?? '') === 'paid' ? 'ok' : 'pending'; ?>"><?php echo esc_html($pay['status'] ?? '�'); ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- -- NOTIFICATIONS -- -->
