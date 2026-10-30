<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-consultation">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;padding:0 24px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">HD Telehealth Consultation Room</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fas fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span>HD Telehealth Consultation Room</span></li>
            </ul>
        </div>
    </div>

    <div class="dior-ic-3c0b10badc">
        <div class="dior-ic-b7f55b7e4a">
            <div class="dior-ic-18a3c7f768">
                <i class="fas fa-video fs-5" style="color:#22c55e;"></i>
            </div>
            <div>
                <h2 class="dior-ic-418a393e7b">HD Telehealth Consultation Room</h2>
                <span class="dior-ic-1445a9ac72">
                    Patient: <strong class="dior-ic-9e8ed370c5"><?php echo esc_html($profile['full_name'] ?? "Patient"); ?> (<?php echo esc_html($profile['patient_id'] ?? ""); ?>)</strong> &bull; Attending: <strong class="dior-ic-9e8ed370c5"><?php echo esc_html($appointments[0]['provider'] ?? "Attending Physician"); ?></strong>
                </span>
            </div>
        </div>
        <div class="dior-ic-c047e12f84">
            <span class="dior-ic-a931cc6ff0">
                <i class="fas fa-wifi dior-ic-9bf73c3191"></i> Connection status: Not connected
            </span>
            <span class="dior-ic-9b5f085d48">Secure video: Not connected</span>
        </div>
    </div>

    <div class="dior-ic-8600368a08">
        
        <!-- Video Feed Section -->
        <div class="dior-ic-2b25452d99">
            <div class="dior-ic-000b842d7a dior-video-unavailable d-flex flex-column align-items-center justify-content-center text-white" role="status"><i class="fas fa-video-slash mb-2"></i><span>Waiting for secure video connection</span></div>
            
            <div class="dior-ic-4a1fefb9df">
                <i class="far fa-clock dior-ic-8b19df99a6"></i> —
            </div>

            <!-- PIP Window -->
            <div class="dior-ic-a443391c4f">
                <div class="dior-ic-000b842d7a dior-self-view-unavailable d-flex align-items-center justify-content-center" role="status" aria-label="Self view is unavailable"><i class="fas fa-user"></i></div>
            </div>

            <!-- Doctor Name Tag -->
            <div class="dior-ic-25066496ad">
                <div class="dior-ic-b287838cb2"></div>
                <div>
                    <span class="dior-ic-4b2444ac29"><?php echo esc_html($appointments[0]['provider'] ?? "Attending Physician"); ?></span>
                    <span class="dior-ic-544705fa27"><?php echo esc_html($appointments[0]['provider_spec'] ?? "Telehealth Physician"); ?></span>
                </div>
            </div>

            <!-- Controls Dock -->
            <div class="dior-ic-0d29c8fc6a">
                <button type="button" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'" class="dior-ic-df9983a8cc btn">
                    <i class="fas fa-microphone"></i>
                </button>
                <button type="button" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'" class="dior-ic-df9983a8cc btn">
                    <i class="fas fa-video" style="color:#22c55e;"></i>
                </button>
                <button type="button" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'" class="dior-ic-df9983a8cc btn">
                    <i class="fas fa-desktop"></i>
                </button>
                <button type="button" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'" class="dior-ic-df9983a8cc btn">
                    <i class="far fa-comment-dots"></i>
                </button>
                <button type="button" onmouseover="this.style.background='#DC2626'" onmouseout="this.style.background='#EF4444'" class="dior-ic-7c877a95fa btn">
                    <i class="fas fa-phone-slash"></i>
                </button>
            </div>
        </div>

        <!-- Chat Panel -->
        <div class="dior-ic-f6cf83e34f">
            <div class="dior-ic-5c4ccb1aea">
                <button type="button" class="dior-ic-fef1baf303 btn">
                    <i class="far fa-comments dior-ic-736c9ca116"></i> Live Chat
                </button>
                <button type="button" class="dior-ic-ac80b8900c btn">
                    <i class="far fa-clipboard dior-ic-736c9ca116"></i> Notes
                </button>
                <button type="button" class="dior-ic-ac80b8900c btn">
                    <i class="fas fa-heart-pulse dior-ic-736c9ca116"></i> Vitals
                </button>
            </div>

            <div id="dior-chat-messages-container" class="dior-ic-543b3f8fa1">
                <div class="dior-consultation-empty-state text-center py-4" role="status">
                    <i class="far fa-comments mb-2" aria-hidden="true"></i>
                    <p class="mb-0">No saved messages are available for this consultation.</p>
                </div>
            </div>

            <div class="dior-ic-c5c02ad633">
                <div class="dior-ic-f487d8d9d8">
                    <input type="text" id="dior-chat-input" placeholder="Type a message..." onkeypress="if(event.key === 'Enter') diorSendChatMessage()" class="dior-ic-011b57cbc6 form-control">
                    <button type="button" id="dior-chat-send-btn" onclick="diorSendChatMessage()" class="dior-ic-5104258266 btn">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    </section>
                <!-- ============================================================== -->
                <!-- 7. MEDICAL QUESTIONNAIRE TAB -->
                <!-- ============================================================== -->
