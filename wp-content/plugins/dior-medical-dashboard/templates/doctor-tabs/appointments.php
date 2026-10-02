<section class="dior-tab-panel" id="tab-doc-appointments" style="display: none; background-color: #f8fafc; padding: 20px; position: relative; overflow: hidden;">
    
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
                        <div id="dior-calendar-container" style="width: 100%; min-height: 500px;"></div>
                    </div>

                </div>

            </div>
        </div>
    </div>
</section>

<?php
$appts = Dior_Doctor_Dynamic::render_appointments(get_current_user_id());
$formatted_events = [];
if (!empty($appts) && is_array($appts)) {
    foreach ($appts as $app) {
        $status = isset($app['status']) ? $app['status'] : '';
        if ($status === 'Cancelled' || $status === 'No Show') continue;
        $formatted_events[] = [
            'id' => isset($app['appt_uid']) ? $app['appt_uid'] : '',
            'title' => (isset($app['patient_name']) ? $app['patient_name'] : 'Patient') . ' - ' . (isset($app['condition_name']) ? $app['condition_name'] : 'Checkup'),
            'start' => (isset($app['appt_date']) ? $app['appt_date'] : date('Y-m-d')) . 'T' . (isset($app['appt_time']) ? date('H:i:s', strtotime($app['appt_time'])) : '09:00:00'),
            'backgroundColor' => ($status === 'Completed' ? '#10b981' : ($status === 'Pending' ? '#f97316' : '#2563eb')),
            'borderColor' => 'transparent'
        ];
    }
}
$events_json = json_encode($formatted_events);
?>

<script>
(function() {
    if (typeof FullCalendar === 'undefined' && !window.fcLoadingDior) {
        window.fcLoadingDior = true;
        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js';
        document.head.appendChild(script);
    }

    function initDiorCal() {
        if (typeof FullCalendar === 'undefined') {
            setTimeout(initDiorCal, 100);
            return;
        }
        var calEl = document.getElementById('dior-calendar-container');
        if (!calEl || calEl.dataset.inited) return;
        calEl.dataset.inited = 'true';

        var calendar = new FullCalendar.Calendar(calEl, {
            initialView: 'dayGridMonth',
            headerToolbar: false,
            height: 'auto',
            events: <?php echo $events_json; ?>,
            datesSet: function(info) {
                var titleEl = document.querySelector('.cal-toolbar h3');
                if (titleEl) titleEl.textContent = info.view.title;
            }
        });
        calendar.render();

        var btns = document.querySelectorAll('.cal-nav-btn');
        if (btns[0]) btns[0].addEventListener('click', () => calendar.prev());
        if (btns[1]) btns[1].addEventListener('click', () => calendar.next());
        
        var todayBtn = document.querySelector('.cal-today-btn');
        if (todayBtn) todayBtn.addEventListener('click', () => calendar.today());
        
        var viewBtns = document.querySelectorAll('.cal-view-btn');
        var views = ['dayGridMonth', 'timeGridWeek', 'timeGridDay', 'listWeek'];
        viewBtns.forEach((btn, i) => {
            btn.addEventListener('click', function() {
                viewBtns.forEach(b => { b.classList.remove('active'); b.style.background = 'transparent'; b.style.color = '#64748b'; });
                this.classList.add('active');
                this.style.background = '#6366f1';
                this.style.color = '#ffffff';
                if (views[i]) calendar.changeView(views[i]);
            });
        });

        var tabEl = document.getElementById('tab-doc-appointments');
        if (tabEl) {
            new MutationObserver(function(muts) {
                muts.forEach(function(m) {
                    if (m.target.style.display !== 'none') setTimeout(() => calendar.updateSize(), 50);
                });
            }).observe(tabEl, { attributes: true, attributeFilter: ['style'] });
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initDiorCal);
    else initDiorCal();
})();
</script>
