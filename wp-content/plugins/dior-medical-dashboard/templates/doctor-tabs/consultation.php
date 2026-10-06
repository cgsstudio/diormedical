<section class="dior-tab-panel dior-ic-057168d87d" id="tab-doc-consultation">
    <div class="dior-ic-ea8916712a">
        <div class="dior-ic-5ddcbd786a">
            <div class="dior-ic-f6aee5f0a8">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#22c55e" class="bi bi-camera-reels" viewBox="0 0 16 16" style="background:transparent;"><path d="M6 3a3 3 0 1 1-6 0 3 3 0 0 1 6 0M1 3a2 2 0 1 0 4 0 2 2 0 0 0-4 0"/><path d="M9 6h.5a2 2 0 0 1 1.983 1.738l3.11-1.382A1 1 0 0 1 16 7.269v7.462a1 1 0 0 1-1.406.913l-3.111-1.382A2 2 0 0 1 9.5 16H2a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zm6 8.73V7.27l-3.5 1.555v4.35zM1 8v6a1 1 0 0 0 1 1h7.5a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1"/><path d="M9 6a3 3 0 1 0 0-6 3 3 0 0 0 0 6M7 3a2 2 0 1 1 4 0 2 2 0 0 1-4 0"/></svg>
            </div>
            <div>
                <h2 class="dior-ic-317242c61b">HD Telehealth Consultation Room</h2>
                <span class="dior-ic-16e5b5f745">
                    Patient: <strong class="dior-ic-c756814291"><?php echo esc_html(($patients[0]['full_name'] ?? 'Patient') . ' (#' . ($patients[0]['patient_id'] ?? '') . ')'); ?></strong> &bull; Attending: <strong class="dior-ic-c756814291"><?php echo esc_html($doctor['full_name'] ?? 'Doctor'); ?></strong>
                </span>
            </div>
        </div>
        <div class="dior-ic-0587d65489">
            <span class="dior-ic-47ed8bd5f6">
                <i class="fas fa-wifi dior-ic-6b696d6067"></i> Signal: Strong (5G)
            </span>
            <span class="dior-ic-76680e0633">
                <div class="dior-ic-be97942b4b"></div> REC HD 1080p
            </span>
        </div>
    </div>

    <div class="dior-ic-5b71f72a68">
        
        <!-- Video Feed Section -->
        <div class="dior-ic-22880f6e4c">
            <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=1200" alt="Patient Video" class="dior-ic-e16079f983">
            
            <div class="dior-ic-9a53b4f681">
                <i class="far fa-clock dior-ic-a88d084c2a"></i> 01:19
            </div>

            <!-- PIP Window -->
            <div class="dior-ic-084b53cceb">
                <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=400" alt="Doctor View" class="dior-ic-e16079f983">
            </div>

            <!-- Patient Name Tag -->
            <div class="dior-ic-ab525e73ff">
                <div class="dior-ic-5f8f75b44a"></div>
                <div>
                    <span class="dior-ic-0964f59deb"><?php echo esc_html($patients[0]['full_name'] ?? 'Patient'); ?></span>
                    <span class="dior-ic-584b5d3c14">Patient &bull; DOB: 1982</span>
                </div>
            </div>

            <!-- Controls Dock -->
            <div class="dior-ic-dfaab74274">
                <button type="button" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'" class="dior-ic-eed07edb71">
                    <i class="fas fa-microphone"></i>
                </button>
                <button type="button" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'" class="dior-ic-eed07edb71">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#22c55e" class="bi bi-camera-reels" viewBox="0 0 16 16" style="background:transparent;"><path d="M6 3a3 3 0 1 1-6 0 3 3 0 0 1 6 0M1 3a2 2 0 1 0 4 0 2 2 0 0 0-4 0"/><path d="M9 6h.5a2 2 0 0 1 1.983 1.738l3.11-1.382A1 1 0 0 1 16 7.269v7.462a1 1 0 0 1-1.406.913l-3.111-1.382A2 2 0 0 1 9.5 16H2a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zm6 8.73V7.27l-3.5 1.555v4.35zM1 8v6a1 1 0 0 0 1 1h7.5a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1"/><path d="M9 6a3 3 0 1 0 0-6 3 3 0 0 0 0 6M7 3a2 2 0 1 1 4 0 2 2 0 0 1-4 0"/></svg>
                </button>
                <button type="button" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'" class="dior-ic-eed07edb71">
                    <i class="fas fa-desktop"></i>
                </button>
                <button type="button" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'" class="dior-ic-eed07edb71">
                    <i class="far fa-comment-dots"></i>
                </button>
                <button type="button" onmouseover="this.style.background='#DC2626'" onmouseout="this.style.background='#EF4444'" class="dior-ic-ad808f026b">
                    <i class="fas fa-phone-slash"></i>
                </button>
            </div>
        </div>

        <!-- Chat Panel -->
        <div class="dior-ic-bb6476677f">
            <div class="dior-ic-bc4ca09166">
                <button type="button" class="dior-ic-dabeb751b0">
                    <i class="far fa-comments dior-ic-3186e094cd"></i> Live Chat
                </button>
                <button type="button" class="dior-ic-9bd5ed2b38">
                    <i class="far fa-clipboard dior-ic-3186e094cd"></i> Notes
                </button>
                <button type="button" class="dior-ic-9bd5ed2b38">
                    <i class="fas fa-heart-pulse dior-ic-3186e094cd"></i> Vitals
                </button>
            </div>

            <div id="dior-doc-chat-container" class="dior-ic-b4ee6f95ea">
                
                <!-- Doctor Message (Self) -->
                <div class="dior-ic-24c42cbb35">
                    <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=100" class="dior-ic-dfaaa69152">
                    <div>
                        <div class="dior-ic-f7f816b5bb">
                            <span class="dior-ic-f8593507d9"><?php echo esc_html($doctor['full_name'] ?? 'Doctor'); ?></span>
                            <span class="dior-ic-987a5822aa">09:30 AM</span>
                        </div>
                        <div class="dior-ic-022388a2ad">
                            Good morning Sarah! How are you feeling after taking the prescribed beta-blockers?
                        </div>
                    </div>
                </div>

                <!-- Patient Message -->
                <div class="dior-ic-5f1491b92d">
                    <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=100" class="dior-ic-dfaaa69152">
                    <div>
                        <div class="dior-ic-41a06c4cd4">
                            <span class="dior-ic-f8593507d9"><?php echo esc_html($patients[0]['full_name'] ?? 'Patient'); ?></span>
                            <span class="dior-ic-987a5822aa">09:31 AM</span>
                        </div>
                        <div class="dior-ic-92320f1192">
                            Hello Doctor! The chest tightness has reduced significantly, but I noticed mild dizziness in the morning.
                        </div>
                    </div>
                </div>

                <!-- Doctor Message (Self) -->
                <div class="dior-ic-24c42cbb35">
                    <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=100" class="dior-ic-dfaaa69152">
                    <div>
                        <div class="dior-ic-f7f816b5bb">
                            <span class="dior-ic-f8593507d9"><?php echo esc_html($doctor['full_name'] ?? 'Doctor'); ?></span>
                            <span class="dior-ic-987a5822aa">09:32 AM</span>
                        </div>
                        <div class="dior-ic-022388a2ad">
                            That can happen initially. Let us review your daily blood pressure readings.
                        </div>
                    </div>
                </div>

            </div>

            <div class="dior-ic-246261583f">
                <div class="dior-ic-3548a2a22d">
                    <input type="text" id="dior-doc-chat-input" placeholder="Type a message to the patient..." onkeypress="if(event.key === 'Enter') diorDocSendChatMessage()" class="dior-ic-303509aad7">
                    <button type="button" id="dior-doc-chat-send-btn" onclick="diorDocSendChatMessage()" class="dior-ic-24f1826d8c">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
</section>
