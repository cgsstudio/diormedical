document.addEventListener('DOMContentLoaded',function(){document.documentElement.classList.add('dior-hide-wp-admin-gap');const adminBar=document.getElementById('wpadminbar');if(adminBar)adminBar.classList.add('dior-js-hidden')});function diorDocSendChatMessage(){var input=document.getElementById('dior-doc-chat-input');var container=document.getElementById('dior-doc-chat-container');if(!input||!container)return;var text=input.value.trim();if(!text)return;var now=new Date();var hours=now.getHours();var minutes=now.getMinutes();var ampm=hours>=12?'PM':'AM';hours=hours%12;hours=hours?hours:12;minutes=minutes<10?'0'+minutes:minutes;var timeString=hours+':'+minutes+' '+ampm;var msgHtml=`
                <div class="dior-ic-00f1946021">
                    <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=100" class="dior-ic-47b323d0ae">
                    <div>
                        <div class="dior-ic-8d1a6a76fd">
                            <span class="dior-ic-402fa88e04">${(window.dior_doctor_vars && window.dior_doctor_vars.doctor_profile ? window.dior_doctor_vars.doctor_profile.full_name : 'Doctor')}</span>
                            <span class="dior-ic-1d80fd81c3">${timeString}</span>
                        </div>
                        <div class="dior-ic-7fc0dfacfc">
                            ${text.replace(/</g, "&lt;").replace(/>/g, "&gt;")}
                        </div>
                    </div>
                </div>
            `;container.insertAdjacentHTML('beforeend',msgHtml);input.value='';container.scrollTop=container.scrollHeight}
window.diorDocNonce=(window.dior_doctor_vars&&window.dior_doctor_vars.nonce?window.dior_doctor_vars.nonce:'');window.diorDocAjax=(window.dior_doctor_vars&&window.dior_doctor_vars.ajax_url?window.dior_doctor_vars.ajax_url:'');var diorDocNonce=window.diorDocNonce;var diorDocAjax=window.diorDocAjax;if(!window.pdmTab){window.pdmTab=function(btn,targetId){if(!btn&&!targetId)return;var button=(btn&&btn.closest)?(btn.closest('.dior-pdm-tab-btn')||btn):document.querySelector('.dior-pdm-tab-btn[data-tab="'+targetId+'"]');var wrap=(button&&button.closest('.dior-pdm'))||document.getElementById('modal-doc-patient-body')||document;wrap.querySelectorAll('.dior-pdm-tab-btn').forEach(function(b){b.classList.remove('active')});if(button)button.classList.add('active');wrap.querySelectorAll('.dior-pdm-tab-pane').forEach(function(p){p.classList.remove('active');p.style.display='none'});var target=wrap.querySelector('#'+targetId)||document.getElementById(targetId);if(target){target.classList.add('active');target.style.display='block'}}}