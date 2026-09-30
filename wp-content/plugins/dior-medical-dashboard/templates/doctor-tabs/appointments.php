<style>
/* Appointments Calendar CSS */
#tab-doc-appointments {
    font-family: 'Montserrat', 'DMSans', 'DM Sans', sans-serif;
    color: #334155;
    background: #F8FAFC;
}

/* Breadcrumb Header */
.app-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}
.app-page-title {
    font-size: 22px;
    font-weight: 700;
    color: #1E293B;
    margin: 0;
}
.app-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #64748B;
    font-weight: 500;
}
.app-breadcrumb i {
    color: #4F46E5;
}
.app-breadcrumb span {
    color: #94A3B8;
}

/* Calendar Layout Grid */
.app-calendar-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 24px;
}

/* Filter Card (Left Col) */
.filter-card {
    background: #FFFFFF;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    height: fit-content;
}
.btn-add-appt {
    width: 100%;
    background: #4F46E5;
    color: #FFF;
    border: none;
    padding: 12px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 24px;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-add-appt:hover {
    background: #4338CA;
}
.filter-card h4 {
    font-size: 16px;
    font-weight: 700;
    color: #1E293B;
    margin: 0 0 16px 0;
}
.filter-group {
    margin-bottom: 16px;
}
.filter-group label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: #475569;
    margin-bottom: 8px;
}
.filter-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    font-size: 13px;
    color: #1E293B;
    outline: none;
    appearance: none;
    background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="%2364748B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>') no-repeat right 12px center;
    background-color: #FFF;
}

/* Calendar Card (Right Col) */
.calendar-card {
    background: #FFFFFF;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
}

/* Calendar Header */
.cal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}
.cal-nav {
    display: flex;
    gap: 8px;
    align-items: center;
}
.cal-nav-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 1px solid #E2E8F0;
    background: #FFF;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748B;
    cursor: pointer;
    font-size: 12px;
}
.cal-today-btn {
    padding: 0 16px;
    height: 36px;
    border-radius: 20px;
    background: #EEF2FF;
    color: #4F46E5;
    border: none;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
}
.cal-title {
    font-size: 20px;
    font-weight: 700;
    color: #1E293B;
    margin: 0;
}
.cal-view-toggles {
    display: flex;
    background: #F1F5F9;
    border-radius: 20px;
    padding: 4px;
}
.cal-view-btn {
    padding: 6px 16px;
    border-radius: 16px;
    border: none;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    background: transparent;
    color: #64748B;
}
.cal-view-btn.active {
    background: #4F46E5;
    color: #FFF;
}

/* Calendar Grid */
.cal-grid {
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    overflow: hidden;
}
.cal-days-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: #F8FAFC;
    border-bottom: 1px solid #E2E8F0;
}
.cal-day-name {
    padding: 12px;
    text-align: center;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    border-right: 1px solid #E2E8F0;
}
.cal-day-name:last-child {
    border-right: none;
}
.cal-days-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    grid-auto-rows: 120px;
}
.cal-day-cell {
    border-right: 1px solid #E2E8F0;
    border-bottom: 1px solid #E2E8F0;
    padding: 12px;
    position: relative;
    background: #FFF;
}
.cal-day-cell:nth-child(7n) {
    border-right: none;
}
.cal-day-cell.other-month .cal-date-num {
    color: #CBD5E1;
}
.cal-date-num {
    font-size: 13px;
    font-weight: 600;
    color: #64748B;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
}
.cal-day-cell.today .cal-date-num {
    background: #4F46E5;
    color: #FFF;
}
@media (max-width: 1280px) {
    .app-page-title { font-size: 20px; }
    .app-calendar-layout { grid-template-columns: 250px 1fr; gap: 16px; }
}
@media (max-width: 1200px) {
    .app-page-title { font-size: 18px; }
    .app-calendar-layout { grid-template-columns: 220px 1fr; }
    .cal-date-num { width: 24px; height: 24px; font-size: 12px; }
}
@media (max-width: 768px) {
    .app-page-header { flex-wrap: wrap; gap: 8px; }
    .app-page-title { font-size: 16px; }
    .app-breadcrumb { font-size: 12px; }
    .app-calendar-layout { grid-template-columns: 1fr; }
}

</style>

<section class="dior-tab-panel" id="tab-doc-appointments" style="display: none;">
    <div class="app-page-header">
        <h2 class="app-page-title">Appointment Calendar</h2>
        <div class="app-breadcrumb">
            <i class="fa-solid fa-house"></i>
            <span>/</span>
            Appointments
            <span>/</span>
            <span style="color:#1E293B;">Appointment Calendar</span>
        </div>
    </div>

    <div class="app-calendar-layout">
        
        <!-- Left Filter Card -->
        <div class="filter-card">
            <button class="btn-add-appt">Add Appointment</button>
            
            <h4>Filters</h4>
            
            <div class="filter-group">
                <label>Doctor</label>
                <select>
                    <option>All</option>
                    <option>Dr. Sarah Smith</option>
                    <option>Dr. John Doe</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Department</label>
                <select>
                    <option>All</option>
                    <option>Cardiology</option>
                    <option>Neurology</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Status</label>
                <select>
                    <option>All</option>
                    <option>Upcoming</option>
                    <option>Completed</option>
                    <option>Cancelled</option>
                </select>
            </div>
        </div>

        <!-- Right Calendar Card -->
        <div class="calendar-card">
            <div class="cal-header">
                <div class="cal-nav">
                    <button class="cal-nav-btn"><i class="fa-solid fa-chevron-left"></i></button>
                    <button class="cal-nav-btn"><i class="fa-solid fa-chevron-right"></i></button>
                    <button class="cal-today-btn">today</button>
                </div>
                
                <h3 class="cal-title">September 2026</h3>
                
                <div class="cal-view-toggles">
                    <button class="cal-view-btn active">Month</button>
                    <button class="cal-view-btn">List</button>
                </div>
            </div>
            
            <div class="cal-grid">
                <div class="cal-days-header">
                    <div class="cal-day-name">Sun</div>
                    <div class="cal-day-name">Mon</div>
                    <div class="cal-day-name">Tue</div>
                    <div class="cal-day-name">Wed</div>
                    <div class="cal-day-name">Thu</div>
                    <div class="cal-day-name">Fri</div>
                    <div class="cal-day-name">Sat</div>
                </div>
                <div class="cal-days-grid">
                    <!-- Row 1 -->
                    <div class="cal-day-cell other-month"><div class="cal-date-num">30</div></div>
                    <div class="cal-day-cell other-month"><div class="cal-date-num">31</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">1</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">2</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">3</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">4</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">5</div></div>
                    
                    <!-- Row 2 -->
                    <div class="cal-day-cell"><div class="cal-date-num">6</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">7</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">8</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">9</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">10</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">11</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">12</div></div>
                    
                    <!-- Row 3 -->
                    <div class="cal-day-cell"><div class="cal-date-num">13</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">14</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">15</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">16</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">17</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">18</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">19</div></div>
                    
                    <!-- Row 4 -->
                    <div class="cal-day-cell"><div class="cal-date-num">20</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">21</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">22</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">23</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">24</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">25</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">26</div></div>
                    
                    <!-- Row 5 -->
                    <div class="cal-day-cell"><div class="cal-date-num">27</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">28</div></div>
                    <div class="cal-day-cell"><div class="cal-date-num">29</div></div>
                    <div class="cal-day-cell today"><div class="cal-date-num">30</div></div>
                    <div class="cal-day-cell other-month"><div class="cal-date-num">1</div></div>
                    <div class="cal-day-cell other-month"><div class="cal-date-num">2</div></div>
                    <div class="cal-day-cell other-month"><div class="cal-date-num">3</div></div>
                    
                    <!-- Row 6 -->
                    <div class="cal-day-cell other-month" style="border-bottom:none;"><div class="cal-date-num">4</div></div>
                    <div class="cal-day-cell other-month" style="border-bottom:none;"><div class="cal-date-num">5</div></div>
                    <div class="cal-day-cell other-month" style="border-bottom:none;"><div class="cal-date-num">6</div></div>
                    <div class="cal-day-cell other-month" style="border-bottom:none;"><div class="cal-date-num">7</div></div>
                    <div class="cal-day-cell other-month" style="border-bottom:none;"><div class="cal-date-num">8</div></div>
                    <div class="cal-day-cell other-month" style="border-bottom:none;"><div class="cal-date-num">9</div></div>
                    <div class="cal-day-cell other-month" style="border-bottom:none;"><div class="cal-date-num">10</div></div>
                </div>
            </div>
            
        </div>
    </div>
</section>
