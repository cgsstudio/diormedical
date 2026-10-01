<section class="dior-tab-panel" id="tab-doc-appointments" style="display: none; background-color: #f8fafc; padding: 20px; position: relative; overflow: hidden;">
    
    <!-- Hide Watermark & Pseudo Elements -->
    <style>
    #tab-doc-appointments .dior-page-watermark,
    #tab-doc-appointments .page-watermark,
    #tab-doc-appointments .dior-big-title,
    #tab-doc-appointments .breadcrumb-title h4::after,
    #tab-doc-appointments .page-title::after,
    .dior-doctor-wrap #tab-doc-appointments .dior-page-watermark,
    .dior-doctor-wrap #tab-doc-appointments .page-watermark,
    .dior-doctor-wrap #tab-doc-appointments .dior-big-title,
    .dior-doctor-wrap #tab-doc-appointments .breadcrumb-title h4::after,
    .dior-doctor-wrap #tab-doc-appointments .page-title::after {
        display: none !important;
        content: none !important;
        opacity: 0 !important;
        visibility: hidden !important;
    }
    </style>

    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; position: relative; z-index: 5;">
        <div>
            <h4 class="mb-0" style="font-size: 20px; font-weight: 700; color: #1e293b;">Calendar</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list" style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0; align-items: center; font-size: 12px;">
                <li><a href="javascript:void(0)"><i class="fa-solid fa-house" style="color: #2563eb;"></i></a></li>
                <li style="color: #cbd5e1;">/</li>
                <li><a href="javascript:void(0)" style="color: #64748b; text-decoration: none;">Dashboard</a></li>
                <li style="color: #cbd5e1;">/</li>
                <li class="active"><span style="color: #1e293b; font-weight: 600;">Calendar</span></li>
            </ul>
        </div>
    </div>

    <div class="section-body" style="position: relative; z-index: 2;">
        <div class="card border-0 shadow-sm" style="border-radius: 14px; background: #ffffff; border: 1px solid #e7edf4; box-shadow: 0 4px 18px rgba(15,23,42,.05); padding: 24px;">
            <div class="row" style="display: flex; flex-wrap: wrap; gap: 20px; width: 100%; margin: 0;">
                
                <!-- Left Sidebar (Col-md-2 / 200px) -->
                <div style="flex: 0 0 200px; max-width: 200px; padding: 0;">
                    
                    <button type="button" class="btn btn-primary" style="width: 100%; background: #6366f1; color: #ffffff; border: none; border-radius: 20px; padding: 10px 16px; font-weight: 600; font-size: 13px; box-shadow: 0 4px 12px rgba(99,102,241,0.3); margin-bottom: 24px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                        Add Event
                    </button>
                    
                    <h5 style="font-size: 13px; font-weight: 700; color: #1e293b; margin: 0 0 14px 0;">My Calendars</h5>
                    
                    <div class="filter-container">
                        <ul class="filterCheck" style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px;">
                            <li style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #475569; font-weight: 500;">
                                <input type="checkbox" checked value="Work" style="width: 16px; height: 16px; accent-color: #6366f1; cursor: pointer;"> Work
                            </li>
                            <li style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #475569; font-weight: 500;">
                                <input type="checkbox" checked value="Personal" style="width: 16px; height: 16px; accent-color: #6366f1; cursor: pointer;"> Personal
                            </li>
                            <li style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #475569; font-weight: 500;">
                                <input type="checkbox" checked value="Important" style="width: 16px; height: 16px; accent-color: #6366f1; cursor: pointer;"> Important
                            </li>
                            <li style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #475569; font-weight: 500;">
                                <input type="checkbox" checked value="Travel" style="width: 16px; height: 16px; accent-color: #6366f1; cursor: pointer;"> Travel
                            </li>
                            <li style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #475569; font-weight: 500;">
                                <input type="checkbox" checked value="Friends" style="width: 16px; height: 16px; accent-color: #6366f1; cursor: pointer;"> Friends
                            </li>
                        </ul>
                    </div>

                </div>

                <!-- Right Calendar Content (Col-md-10 / flex-1) -->
                <div style="flex: 1 1 0%; min-width: 0; padding: 0;">
                    
                    <!-- Calendar Toolbar -->
                    <div class="cal-toolbar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                        
                        <!-- Left Controls: Navigation + Today Button -->
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <button type="button" class="cal-nav-btn" style="width: 32px; height: 32px; border-radius: 50%; border: 1px solid #e2e8f0; background: #ffffff; color: #64748b; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 12px;"><i class="fa-solid fa-chevron-left"></i></button>
                            <button type="button" class="cal-nav-btn" style="width: 32px; height: 32px; border-radius: 50%; border: 1px solid #e2e8f0; background: #ffffff; color: #64748b; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 12px;"><i class="fa-solid fa-chevron-right"></i></button>
                            <button type="button" class="cal-today-btn" style="background: #6366f1; color: #ffffff; border: none; border-radius: 20px; padding: 6px 18px; font-size: 12px; font-weight: 600; cursor: pointer;">today</button>
                        </div>

                        <!-- Center Month Title -->
                        <h3 style="font-size: 20px; font-weight: 700; color: #1e293b; margin: 0; text-align: center;">October 2026</h3>

                        <!-- Right View Toggles -->
                        <div class="cal-view-toggles" style="display: flex; background: #f1f5f9; border-radius: 20px; padding: 3px; gap: 2px;">
                            <button type="button" class="cal-view-btn active" style="background: #6366f1; color: #ffffff; border: none; border-radius: 16px; padding: 5px 14px; font-size: 12px; font-weight: 600; cursor: pointer;">month</button>
                            <button type="button" class="cal-view-btn" style="background: transparent; color: #64748b; border: none; border-radius: 16px; padding: 5px 14px; font-size: 12px; font-weight: 600; cursor: pointer;">week</button>
                            <button type="button" class="cal-view-btn" style="background: transparent; color: #64748b; border: none; border-radius: 16px; padding: 5px 14px; font-size: 12px; font-weight: 600; cursor: pointer;">day</button>
                            <button type="button" class="cal-view-btn" style="background: transparent; color: #64748b; border: none; border-radius: 16px; padding: 5px 14px; font-size: 12px; font-weight: 600; cursor: pointer;">list</button>
                        </div>

                    </div>

                    <!-- Calendar Grid Matrix -->
                    <div class="dior-cal-grid-wrapper" style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #ffffff;">
                        
                        <!-- Day Headers -->
                        <div style="display: grid; grid-template-columns: repeat(7, 1fr); background: #ffffff; border-bottom: 1px solid #e2e8f0;">
                            <div class="cal-day-header">Sun</div>
                            <div class="cal-day-header">Mon</div>
                            <div class="cal-day-header">Tue</div>
                            <div class="cal-day-header">Wed</div>
                            <div class="cal-day-header">Thu</div>
                            <div class="cal-day-header">Fri</div>
                            <div class="cal-day-header">Sat</div>
                        </div>

                        <!-- Days Grid Matrix (6 Rows x 7 Cols) -->
                        <div style="display: grid; grid-template-columns: repeat(7, 1fr); grid-auto-rows: 105px;">
                            
                            <!-- Row 1: Sep 27 - Oct 3 -->
                            <div class="cal-cell other-month">
                                <div class="cal-cell-top"><span class="cal-num">27</span></div>
                                <div class="cal-event-bar event-red">12a Go to Delhi</div>
                            </div>
                            <div class="cal-cell other-month"><div class="cal-cell-top"><span class="cal-num">28</span></div></div>
                            <div class="cal-cell other-month"><div class="cal-cell-top"><span class="cal-num">29</span></div></div>
                            <div class="cal-cell other-month"><div class="cal-cell-top"><span class="cal-num">30</span></div></div>
                            <div class="cal-cell is-today">
                                <div class="cal-cell-top"><span class="cal-num today-badge">1</span></div>
                                <div class="cal-event-bar event-green mb-1">12a All Day Event</div>
                                <div class="cal-event-bar event-blue">11a Lunch</div>
                            </div>
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">2</span></div></div>
                            <div class="cal-cell">
                                <div class="cal-cell-top"><span class="cal-num">3</span></div>
                                <div class="cal-event-bar event-green">12:30p Meeting</div>
                            </div>

                            <!-- Row 2: Oct 4 - Oct 10 -->
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">4</span></div></div>
                            <div class="cal-cell">
                                <div class="cal-cell-top"><span class="cal-num">5</span></div>
                                <div class="cal-event-bar event-orange">12p Shopping</div>
                            </div>
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">6</span></div></div>
                            <div class="cal-cell">
                                <div class="cal-cell-top"><span class="cal-num">7</span></div>
                                <div class="cal-event-bar event-cyan">10a Get To Gather</div>
                            </div>
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">8</span></div></div>
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">9</span></div></div>
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">10</span></div></div>

                            <!-- Row 3: Oct 11 - Oct 17 -->
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">11</span></div></div>
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">12</span></div></div>
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">13</span></div></div>
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">14</span></div></div>
                            <div class="cal-cell">
                                <div class="cal-cell-top"><span class="cal-num">15</span></div>
                                <div class="cal-event-bar event-green">10:30a Meeting</div>
                            </div>
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">16</span></div></div>
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">17</span></div></div>

                            <!-- Row 4: Oct 18 - Oct 24 -->
                            <div class="cal-cell">
                                <div class="cal-cell-top"><span class="cal-num">18</span></div>
                                <div class="cal-event-bar event-orange">7p Birthday Party</div>
                            </div>
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">19</span></div></div>
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">20</span></div></div>
                            <div class="cal-cell">
                                <div class="cal-cell-top"><span class="cal-num">21</span></div>
                                <div class="cal-event-bar event-cyan">10a Collage Party</div>
                            </div>
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">22</span></div></div>
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">23</span></div></div>
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">24</span></div></div>

                            <!-- Row 5: Oct 25 - Oct 31 -->
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">25</span></div></div>
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">26</span></div></div>
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">27</span></div></div>
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">28</span></div></div>
                            <div class="cal-cell">
                                <div class="cal-cell-top"><span class="cal-num">29</span></div>
                                <div class="cal-event-bar event-blue">4p Break</div>
                            </div>
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">30</span></div></div>
                            <div class="cal-cell"><div class="cal-cell-top"><span class="cal-num">31</span></div></div>

                            <!-- Row 6: Nov 1 - Nov 7 -->
                            <div class="cal-cell other-month"><div class="cal-cell-top"><span class="cal-num">1</span></div></div>
                            <div class="cal-cell other-month"><div class="cal-cell-top"><span class="cal-num">2</span></div></div>
                            <div class="cal-cell other-month"><div class="cal-cell-top"><span class="cal-num">3</span></div></div>
                            <div class="cal-cell other-month"><div class="cal-cell-top"><span class="cal-num">4</span></div></div>
                            <div class="cal-cell other-month"><div class="cal-cell-top"><span class="cal-num">5</span></div></div>
                            <div class="cal-cell other-month"><div class="cal-cell-top"><span class="cal-num">6</span></div></div>
                            <div class="cal-cell other-month"><div class="cal-cell-top"><span class="cal-num">7</span></div></div>

                        </div>

                    </div>

                </div>

            </div>
        </div>
    </div>
</section>

<!-- Custom Scoped Calendar Styles -->
<style>
.cal-day-header {
    padding: 10px;
    text-align: center;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    border-right: 1px solid #e2e8f0;
}
.cal-day-header:last-child {
    border-right: none;
}

.cal-cell {
    border-right: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
    padding: 6px 8px;
    background: #ffffff;
    position: relative;
    box-sizing: border-box;
}
.cal-cell:nth-child(7n) {
    border-right: none;
}
.cal-cell.other-month {
    background: #ffffff;
}
.cal-cell.other-month .cal-num {
    color: #cbd5e1;
}

.cal-cell-top {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 4px;
}
.cal-num {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
}
.today-badge {
    background: #6366f1;
    color: #ffffff;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
}

/* Event Bar Pills */
.cal-event-bar {
    font-size: 11px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 4px;
    color: #ffffff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 3px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.event-red { background: #ef4444 !important; }
.event-green { background: #10b981 !important; }
.event-blue { background: #2563eb !important; }
.event-orange { background: #f97316 !important; }
.event-cyan { background: #06b6d4 !important; }
</style>
