<section class="dior-tab-panel documents-tab-panel" id="tab-doc-patient-review" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Patient Reviews</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list" style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0; align-items: center;">
                <li><a href="#"><i class="fa-solid fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li class="active"><span style="color: #1E293B; font-weight: 700; font-size: 14px;">Patient Reviews</span></li>
            </ul>
        </div>
    </div>

    <div class="docs-card">
        <div class="docs-header-container">
            <div class="docs-title-box">
                <h2>All Reviews</h2>
                <div class="docs-title-line"></div>
            </div>
            
            <div class="docs-actions-wrapper">
                <div class="docs-search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" placeholder="Search reviews...">
                </div>
                <div class="docs-actions-group">
                    <button class="docs-icon-btn docs-btn-primary" aria-label="Add new review" title="Add Review"><i class="fa-solid fa-plus"></i></button>
                    <button class="docs-icon-btn docs-btn-info" aria-label="Refresh data" title="Refresh"><i class="fa-solid fa-rotate-right"></i></button>
                </div>
            </div>
        </div>

        <div class="docs-table-wrapper">
            <table class="docs-table">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center"><input type="checkbox" class="docs-checkbox"></th>
                        <th>PATIENT <i class="fa-solid fa-sort"></i></th>
                        <th>RATING <i class="fa-solid fa-sort"></i></th>
                        <th>REVIEW DATE <i class="fa-solid fa-sort"></i></th>
                        <th>COMMENT</th>
                        <th>STATUS <i class="fa-solid fa-sort"></i></th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                        <td>
                            <div class="docs-user-profile">
                                <img src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/images/users/user-1.png'); ?>" class="docs-user-avatar" alt="John Doe" onerror="this.src='https://ui-avatars.com/api/?name=John+Doe&background=random'">
                                <span class="docs-user-name">John Doe</span>
                            </div>
                        </td>
                        <td>
                            <div style="color: #F59E0B; font-size: 14px;">
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                            </div>
                        </td>
                        <td><i class="regular fa-calendar docs-icon-blue"></i> Oct 01, 2026</td>
                        <td><div style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Great doctor, very helpful and took the time to explain everything clearly.</div></td>
                        <td><span class="docs-badge docs-badge-green">Published</span></td>
                        <td>
                            <div class="docs-row-actions">
                                <button class="docs-action-btn-sm docs-btn-edit" title="Edit Review"><i class="fa-solid fa-pen"></i></button>
                                <button class="docs-action-btn-sm docs-btn-delete" title="Delete Review"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-center"><input type="checkbox" class="docs-checkbox"></td>
                        <td>
                            <div class="docs-user-profile">
                                <img src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/images/users/user-2.png'); ?>" class="docs-user-avatar" alt="Alice Smith" onerror="this.src='https://ui-avatars.com/api/?name=Alice+Smith&background=random'">
                                <span class="docs-user-name">Alice Smith</span>
                            </div>
                        </td>
                        <td>
                            <div style="color: #F59E0B; font-size: 14px;">
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-regular fa-star" style="color: #CBD5E1;"></i>
                            </div>
                        </td>
                        <td><i class="regular fa-calendar docs-icon-blue"></i> Sep 28, 2026</td>
                        <td><div style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Very good consultation, though wait time was a bit long.</div></td>
                        <td><span class="docs-badge docs-badge-orange">Pending</span></td>
                        <td>
                            <div class="docs-row-actions">
                                <button class="docs-action-btn-sm docs-btn-edit" title="Edit Review"><i class="fa-solid fa-pen"></i></button>
                                <button class="docs-action-btn-sm docs-btn-delete" title="Delete Review"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="docs-pagination-container">
            <div class="docs-showing-text" style="color: #64748B; font-size: 13px; font-weight: 500;">0 selected / 2 total</div>
        </div>
    </div>
</section>
