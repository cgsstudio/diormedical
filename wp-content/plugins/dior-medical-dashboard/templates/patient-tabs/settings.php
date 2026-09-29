<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-settings">
    <div class="dior-ic-d8609c7118"></div>
    <div class="dior-ic-9617191b52">
        <div class="dior-ic-76db7de8db">
            <div class="dior-ic-7e521c6e89">
                <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=150" class="dior-ic-7ec5fb6fb5">
                <button type="button" class="dior-ic-a7eae853cc"><i class="fa-solid fa-camera"></i></button>
            </div>
            <div class="dior-ic-afa5d6e3f7">
                <div class="dior-ic-3541347c41">
                    <h2 class="dior-ic-f1d74f4175"><?php echo esc_html($profile['full_name'] ?? ""); ?></h2>
                    <span class="dior-ic-e88fe40e66"><?php echo esc_html($profile['patient_id'] ?? ""); ?></span>
                    <span class="dior-ic-9cd0a88c9e"><i class="fa-solid fa-circle-check"></i> Verified Patient</span>
                </div>
                <div class="dior-ic-ec8976c154">
                    <i class="fa-regular fa-envelope dior-ic-cd3611ed55"></i> <?php echo esc_html($profile['email'] ?? ""); ?> &bull; <i class="fa-solid fa-droplet dior-ic-ccc79123cc"></i> Blood Group: <strong class="dior-ic-9e8ed370c5"><?php echo esc_html($profile['blood_group'] ?? "—"); ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="dior-ic-8600368a08">
        <!-- Settings Nav -->
        <div class="dior-ic-fe46835042">
            <div class="dior-ic-668068c975">
                <a href="#" onclick="return false;" class="dior-ic-ef69a32719">
                    <i class="fa-solid fa-circle-user dior-ic-b842c7cb26"></i>
                    <div>
                        <span class="dior-ic-9fe27d5276">Account Profile</span>
                        <span class="dior-ic-cfbb453fcb">Personal details &amp; contact</span>
                    </div>
                </a>
                <a href="#" onmouseover="this.style.background='#F1F5F9'" onmouseout="this.style.background='transparent'" onclick="return false;" class="dior-ic-b0831a707f">
                    <i class="fa-solid fa-shield-halved dior-ic-a546442247"></i>
                    <div>
                        <span class="dior-ic-cb8c321fbe">Security &amp; Password</span>
                        <span class="dior-ic-252d442b87">Password, 2FA &amp; credentials</span>
                    </div>
                </a>
                <a href="#" onmouseover="this.style.background='#F1F5F9'" onmouseout="this.style.background='transparent'" onclick="return false;" class="dior-ic-b0831a707f">
                    <i class="fa-solid fa-bell dior-ic-a546442247"></i>
                    <div>
                        <span class="dior-ic-cb8c321fbe">Notifications</span>
                        <span class="dior-ic-252d442b87">Email, SMS &amp; alert controls</span>
                    </div>
                </a>
                <a href="#" onmouseover="this.style.background='#F1F5F9'" onmouseout="this.style.background='transparent'" onclick="return false;" class="dior-ic-0eae8df1fa">
                    <i class="fa-solid fa-lock dior-ic-a546442247"></i>
                    <div>
                        <span class="dior-ic-cb8c321fbe">Privacy &amp; Data</span>
                        <span class="dior-ic-252d442b87">EMR consent &amp; data download</span>
                    </div>
                </a>
            </div>
        </div>

        <!-- Form Area -->
        <div class="dior-ic-957da3ee04">
            <div class="dior-ic-977cfd05ef">
                <div class="dior-ic-0de61c57b2">
                    <h3 class="dior-ic-e65d6db14a"><i class="fa-solid fa-address-card dior-ic-9b50cb0b81"></i> Personal Account Details</h3>
                    <p class="dior-ic-d3a1ecffb2">Update your primary identity, phone number, and address info</p>
                </div>
                <div class="dior-ic-269bafc628">
                    <div class="dior-ic-f5cff7aef3">
                        <div class="dior-ic-528b6f7817">
                            <label class="dior-ic-b67d4ad657">First Name <span class="dior-ic-bbfc66681c">*</span></label>
                            <div class="dior-ic-436662efb5">
                                <i class="fa-solid fa-user dior-ic-4af07ae930"></i>
                                <input type="text" value="<?php echo esc_attr($profile['first_name'] ?? ""); ?>" class="dior-ic-1834247b2e">
                            </div>
                        </div>
                        <div class="dior-ic-528b6f7817">
                            <label class="dior-ic-b67d4ad657">Last Name <span class="dior-ic-bbfc66681c">*</span></label>
                            <div class="dior-ic-436662efb5">
                                <i class="fa-solid fa-user dior-ic-4af07ae930"></i>
                                <input type="text" value="<?php echo esc_attr($profile['last_name'] ?? ""); ?>" class="dior-ic-1834247b2e">
                            </div>
                        </div>
                    </div>

                    <div class="dior-ic-f5cff7aef3">
                        <div class="dior-ic-528b6f7817">
                            <label class="dior-ic-b67d4ad657">Email Address <span class="dior-ic-bbfc66681c">*</span></label>
                            <div class="dior-ic-436662efb5">
                                <i class="fa-solid fa-envelope dior-ic-4af07ae930"></i>
                                <input type="email" value="<?php echo esc_html($profile['email'] ?? ""); ?>" class="dior-ic-1834247b2e">
                            </div>
                        </div>
                        <div class="dior-ic-528b6f7817">
                            <label class="dior-ic-b67d4ad657">Mobile Number <span class="dior-ic-bbfc66681c">*</span></label>
                            <div class="dior-ic-436662efb5">
                                <i class="fa-solid fa-phone dior-ic-4af07ae930"></i>
                                <input type="text" value="<?php echo esc_attr($profile['phone'] ?? ""); ?>" class="dior-ic-1834247b2e">
                            </div>
                        </div>
                    </div>

                    <div class="dior-ic-f5cff7aef3">
                        <div class="dior-ic-528b6f7817">
                            <label class="dior-ic-b67d4ad657">Date of Birth</label>
                            <div class="dior-ic-436662efb5">
                                <i class="fa-regular fa-calendar dior-ic-4af07ae930"></i>
                                <input type="date" value="<?php echo esc_attr($profile['dob'] ?? ""); ?>" class="dior-ic-1e1c917b91">
                            </div>
                        </div>
                        <div class="dior-ic-528b6f7817">
                            <label class="dior-ic-b67d4ad657">Blood Group</label>
                            <div class="dior-ic-d42fb89708">
                                <select class="dior-ic-ab946d8c2a">
                                    <option>A+</option>
                                    <option>A-</option>
                                    <option>B+</option>
                                    <option>B-</option>
                                    <option>AB+</option>
                                    <option>AB-</option>
                                    <option selected>O+</option>
                                    <option>O-</option>
                                </select>
                            </div>
                        </div>
                        <div class="dior-ic-528b6f7817">
                            <label class="dior-ic-b67d4ad657">City</label>
                            <input type="text" value="<?php echo esc_attr($profile['city'] ?? ""); ?>" class="dior-ic-a53f7b6566">
                        </div>
                    </div>

                    <div class="dior-ic-1be953d431">
                        <div class="dior-ic-528b6f7817">
                            <label class="dior-ic-b67d4ad657">Country</label>
                            <input type="text" value="<?php echo esc_attr($profile['country'] ?? ""); ?>" class="dior-ic-a53f7b6566">
                        </div>
                        <div class="dior-ic-900a3b0aa6">
                            <label class="dior-ic-b67d4ad657">Residential Address</label>
                            <input type="text" value="<?php echo esc_attr($profile['address'] ?? ""); ?>" class="dior-ic-a53f7b6566">
                        </div>
                    </div>
                </div>
                
                <div class="dior-ic-4674fd6006">
                    <a href="#" onclick="return false;" class="dior-ic-86feb638a3">Cancel</a>
                    <a href="#" onclick="return false;" class="dior-ic-ba8c65dd94"><i class="fa-solid fa-floppy-disk"></i> Save Changes</a>
                </div>
            </div>
        </div>
    </div>
</section>


                <!-- ============================================================== -->
                <!-- 2. APPOINTMENTS TAB -->
                <!-- ============================================================== -->
