<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-consultation">
    <div class="dior-ic-3c0b10badc">
        <div class="dior-ic-b7f55b7e4a">
            <div class="dior-ic-18a3c7f768">
                <i class="fa-solid fa-video"></i>
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
                <i class="fa-solid fa-wifi dior-ic-9bf73c3191"></i> Signal: Strong (5G)
            </span>
            <span class="dior-ic-9b5f085d48">
                <div class="dior-ic-71cc54b832"></div> REC HD 1080p
            </span>
        </div>
    </div>

    <div class="dior-ic-8600368a08">
        
        <!-- Video Feed Section -->
        <div class="dior-ic-2b25452d99">
            <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=1200" alt="Doctor Video" class="dior-ic-000b842d7a">
            
            <div class="dior-ic-4a1fefb9df">
                <i class="fa-regular fa-clock dior-ic-8b19df99a6"></i> 01:19
            </div>

            <!-- PIP Window -->
            <div class="dior-ic-a443391c4f">
                <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=400" alt="Self View" class="dior-ic-000b842d7a">
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
                <button type="button" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'" class="dior-ic-df9983a8cc">
                    <i class="fa-solid fa-microphone"></i>
                </button>
                <button type="button" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'" class="dior-ic-df9983a8cc">
                    <i class="fa-solid fa-video"></i>
                </button>
                <button type="button" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'" class="dior-ic-df9983a8cc">
                    <i class="fa-solid fa-desktop"></i>
                </button>
                <button type="button" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'" class="dior-ic-df9983a8cc">
                    <i class="fa-regular fa-comment-dots"></i>
                </button>
                <button type="button" onmouseover="this.style.background='#DC2626'" onmouseout="this.style.background='#EF4444'" class="dior-ic-7c877a95fa">
                    <i class="fa-solid fa-phone-slash"></i>
                </button>
            </div>
        </div>

        <!-- Chat Panel -->
        <div class="dior-ic-f6cf83e34f">
            <div class="dior-ic-5c4ccb1aea">
                <button type="button" class="dior-ic-fef1baf303">
                    <i class="fa-regular fa-comments dior-ic-736c9ca116"></i> Live Chat
                </button>
                <button type="button" class="dior-ic-ac80b8900c">
                    <i class="fa-regular fa-clipboard dior-ic-736c9ca116"></i> Notes
                </button>
                <button type="button" class="dior-ic-ac80b8900c">
                    <i class="fa-solid fa-heart-pulse dior-ic-736c9ca116"></i> Vitals
                </button>
            </div>

            <div id="dior-chat-messages-container" class="dior-ic-543b3f8fa1">
                
                <!-- Doctor Message -->
                <div class="dior-ic-044f0b27b0">
                    <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=100" class="dior-ic-2061ceb509">
                    <div>
                        <div class="dior-ic-2c31c3e1bf">
                            <span class="dior-ic-a55dcb6086">Dr. Helen Miller</span>
                            <span class="dior-ic-37f02fdd9f">09:30 AM</span>
                        </div>
                        <div class="dior-ic-accfff7f7f">
                            Good morning Sarah! How are you feeling after taking the prescribed beta-blockers?
                        </div>
                    </div>
                </div>

                <!-- Patient Message -->
                <div class="dior-ic-68494619df">
                    <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=100" class="dior-ic-2061ceb509">
                    <div>
                        <div class="dior-ic-af753e564d">
                            <span class="dior-ic-a55dcb6086"><?php echo esc_html($profile['full_name'] ?? "Patient"); ?></span>
                            <span class="dior-ic-37f02fdd9f">09:31 AM</span>
                        </div>
                        <div class="dior-ic-02827fb633">
                            Hello Doctor! The chest tightness has reduced significantly, but I noticed mild dizziness in the morning.
                        </div>
                    </div>
                </div>

                <!-- Doctor Message -->
                <div class="dior-ic-044f0b27b0">
                    <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=100" class="dior-ic-2061ceb509">
                    <div>
                        <div class="dior-ic-2c31c3e1bf">
                            <span class="dior-ic-a55dcb6086">Dr. Helen Miller</span>
                            <span class="dior-ic-37f02fdd9f">09:32 AM</span>
                        </div>
                        <div class="dior-ic-accfff7f7f">
                            That can happen initially. Let us review your daily blood pressure readings.
                        </div>
                    </div>
                </div>

            </div>

            <div class="dior-ic-c5c02ad633">
                <div class="dior-ic-f487d8d9d8">
                    <input type="text" id="dior-chat-input" placeholder="Type a message..." onkeypress="if(event.key === 'Enter') diorSendChatMessage()" class="dior-ic-011b57cbc6">
                    <button type="button" id="dior-chat-send-btn" onclick="diorSendChatMessage()" class="dior-ic-5104258266">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    </section>
                <!-- ============================================================== -->
                <!-- 7. MEDICAL QUESTIONNAIRE TAB -->
                <!-- ============================================================== -->
