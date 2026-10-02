<?php
/**
 * Dior Medical - Doctor Dashboard Dynamic Operations.
 * Production-oriented CRUD/AJAX layer for doctor-only dashboard actions.
 */
if (!defined('ABSPATH')) exit;

class Dior_Doctor_Dynamic {
    const NONCE = 'dior_doctor_nonce';

    public static function init() {
        add_action('plugins_loaded', [__CLASS__, 'install_schema'], 20);
        $actions = [
            'appointment_delete','appointment_update','patient_status','patient_delete','patient_bulk_delete',
            'get_patient_profile','save_consultation','delete_consultation','document_save','document_delete',
            'review_save','review_delete','review_get','telemed_save','telemed_delete','notification_read',
            'notification_all_read','settings_save','chat_send','chat_get'
        ];
        foreach ($actions as $action) add_action('wp_ajax_dior_doc_' . $action, [__CLASS__, 'ajax_' . $action]);
    }

    private static function auth() {
        if (!is_user_logged_in() || !Dior_Doctor_Dashboard::can_access_for_dynamic()) {
            wp_send_json_error(['message'=>'Unauthorized'], 403);
        }
        if (!wp_verify_nonce($_POST['nonce'] ?? '', self::NONCE)) {
            wp_send_json_error(['message'=>'Security check failed.'], 403);
        }
    }
    private static function doctor_id() { return (int)get_current_user_id(); }
    private static function ensure_patient_row($pid) {
        global $wpdb; $t=self::table('patients'); $p=self::patient($pid); if(!$p) return; $exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE user_id=%d",(int)$pid)); if(!$exists){$wpdb->insert($t,['user_id'=>(int)$pid,'patient_uid'=>$p['patient_uid'],'first_name'=>$p['first_name']??'','last_name'=>$p['last_name']??'','email'=>$p['email']??'','phone'=>$p['phone']??'','status'=>$p['status']??'active','avatar_url'=>$p['avatar_url']??'','created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')]);} }
    private static function doctor_can_manage_appointment($a) {
        if(current_user_can('manage_options')) return true; if((int)($a['doctor_id']??0)===self::doctor_id()) return true; $docs=get_users(['role'=>'doctor','number'=>2,'fields'=>'ID']); return count($docs)===1 && (int)$docs[0]===self::doctor_id();
    }
    private static function table($name) { global $wpdb; return $wpdb->prefix . 'dior_' . $name; }

    public static function install_schema() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $cc = $wpdb->get_charset_collate();
        $tables = [];
        $tables[] = "CREATE TABLE " . self::table('reviews') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            review_uid VARCHAR(64) NOT NULL,
            patient_id BIGINT UNSIGNED NOT NULL,
            doctor_id BIGINT UNSIGNED NOT NULL,
            rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
            comment TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Published',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY review_uid (review_uid),
            KEY patient_id (patient_id),
            KEY doctor_id (doctor_id),
            KEY status (status)
        ) $cc;";
        $tables[] = "CREATE TABLE " . self::table('telemedicine') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tele_uid VARCHAR(64) NOT NULL,
            appointment_uid VARCHAR(64) NULL,
            patient_id BIGINT UNSIGNED NOT NULL,
            doctor_id BIGINT UNSIGNED NOT NULL,
            room_url VARCHAR(500) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Scheduled',
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tele_uid (tele_uid),
            KEY appointment_uid (appointment_uid),
            KEY patient_id (patient_id),
            KEY doctor_id (doctor_id),
            KEY status (status)
        ) $cc;";
        $tables[] = "CREATE TABLE " . self::table('chat_messages') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            appointment_uid VARCHAR(64) NOT NULL,
            patient_id BIGINT UNSIGNED NOT NULL,
            doctor_id BIGINT UNSIGNED NOT NULL,
            sender_user_id BIGINT UNSIGNED NOT NULL,
            message TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY appointment_uid (appointment_uid),
            KEY patient_id (patient_id),
            KEY doctor_id (doctor_id),
            KEY created_at (created_at)
        ) $cc;";
        $tables[] = "CREATE TABLE " . self::table('doctor_settings') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            doctor_id BIGINT UNSIGNED NOT NULL,
            setting_key VARCHAR(100) NOT NULL,
            setting_value LONGTEXT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY doctor_setting (doctor_id,setting_key)
        ) $cc;";
        foreach ($tables as $sql) dbDelta($sql);
    }

    public static function patients($doctor_id=0) {
        global $wpdb;
        $doctor_id = $doctor_id ?: self::doctor_id();
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dior_patients WHERE status <> 'deleted' ORDER BY created_at DESC"), ARRAY_A);
        if (!$rows) {
            $users = get_users(['role__in'=>['patient','subscriber','customer'],'number'=>500]);
            foreach ($users as $u) {
                $pid = get_user_meta($u->ID,'patient_id',true) ?: 'DM-' . (10000+$u->ID);
                $rows[] = ['id'=>0,'user_id'=>$u->ID,'patient_uid'=>$pid,'first_name'=>$u->first_name,'last_name'=>$u->last_name,'email'=>$u->user_email,'phone'=>get_user_meta($u->ID,'phone',true),'status'=>'active','avatar_url'=>get_user_meta($u->ID,'dior_profile_image',true)];
            }
        }
        return $rows;
    }
    public static function patient($id_or_uid) {
        global $wpdb;
        $t=$wpdb->prefix.'dior_patients';
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d OR patient_uid=%s LIMIT 1", (int)$id_or_uid, sanitize_text_field($id_or_uid)), ARRAY_A);
        if ($row) return $row;
        $u=get_userdata((int)$id_or_uid); if(!$u) return null;
        return ['id'=>0,'user_id'=>$u->ID,'patient_uid'=>get_user_meta($u->ID,'patient_id',true) ?: 'DM-'.(10000+$u->ID),'first_name'=>$u->first_name,'last_name'=>$u->last_name,'email'=>$u->user_email,'phone'=>get_user_meta($u->ID,'phone',true),'status'=>'active','avatar_url'=>get_user_meta($u->ID,'dior_profile_image',true)];
    }
    public static function patient_name($pid) { $p=self::patient($pid); return $p ? trim($p['first_name'].' '.$p['last_name']) : 'Patient'; }
    public static function patient_belongs_to_doctor($pid,$did) {
        if (current_user_can('manage_options')) return true;
        foreach (Dior_Appointment_Service::get_doctor_appointments($did) as $a) if ((int)($a['patient_id']??0)===(int)$pid) return true;
        return false;
    }

    public static function ajax_appointment_delete() {
        self::auth(); global $wpdb; $uid=sanitize_text_field($_POST['appt_uid']??''); $a=Dior_Appointment_Service::get_appointment($uid);
        if(!$a || !self::doctor_can_manage_appointment($a)) wp_send_json_error(['message'=>'Appointment not found or access denied.']);
        $ok=$wpdb->delete($wpdb->prefix.'dior_appointments',['appt_uid'=>$uid],['%s']);
        if($ok!==false){ if(class_exists('Dior_Audit_Service')) Dior_Audit_Service::log('appointment_delete','appointment',$uid,(int)$a['patient_id'],'success'); wp_send_json_success(['message'=>'Appointment permanently deleted.']); }
        wp_send_json_error(['message'=>'Unable to delete appointment.']);
    }
    public static function ajax_appointment_update() {
        self::auth(); global $wpdb; $uid=sanitize_text_field($_POST['appt_uid']??''); $a=Dior_Appointment_Service::get_appointment($uid); if(!$a) wp_send_json_error(['message'=>'Appointment not found.']);
        if(!self::doctor_can_manage_appointment($a)) wp_send_json_error(['message'=>'Access denied.'],403);
        $date=sanitize_text_field($_POST['date']??$a['appt_date']); $time=sanitize_text_field($_POST['time']??$a['appt_time']); $status=sanitize_text_field($_POST['status']??$a['status']);
        $allowed=['Pending','Confirmed','In Progress','Completed','Cancelled','No Show']; if(!in_array($status,$allowed,true)) wp_send_json_error(['message'=>'Invalid status.']);
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) wp_send_json_error(['message'=>'Invalid date.']);
        $conflict=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dior_appointments WHERE patient_id=%d AND appt_date=%s AND appt_time=%s AND appt_uid<>%s AND status NOT IN('Cancelled','No Show') AND is_deleted=0 LIMIT 1",$a['patient_id'],$date,$time,$uid));
        if($conflict) wp_send_json_error(['message'=>'This patient already has an active appointment at that time.']);
        $old=$a; $wpdb->update($wpdb->prefix.'dior_appointments',['appt_date'=>$date,'appt_time'=>$time,'status'=>$status,'updated_at'=>current_time('mysql')],['appt_uid'=>$uid]);
        if(method_exists('Dior_Appointment_Service','log_history_public')) Dior_Appointment_Service::log_history_public($old,'doctor_update',['new_status'=>$status,'new_date'=>$date,'new_time'=>$time]);
        if(class_exists('Dior_Notification_Service')) Dior_Notification_Service::send_appointment_rescheduled($uid,$old['appt_date'],$old['appt_time'],$date,$time,'doctor');
        wp_send_json_success(['message'=>'Appointment updated successfully.','appointment'=>Dior_Appointment_Service::get_appointment($uid)]);
    }

    public static function ajax_patient_status(){ self::auth(); global $wpdb; $pid=(int)($_POST['patient_id']??0); self::ensure_patient_row($pid); if(!self::patient_belongs_to_doctor($pid,self::doctor_id())) wp_send_json_error(['message'=>'Access denied.'],403); self::ensure_patient_row($pid); $status=sanitize_key($_POST['status']??'active'); if(!in_array($status,['active','inactive','blocked'],true)) wp_send_json_error(['message'=>'Invalid status.']); $wpdb->update(self::table('patients'),['status'=>$status,'updated_at'=>current_time('mysql')],['user_id'=>$pid]); update_user_meta($pid,'dior_patient_status',$status); wp_send_json_success(['message'=>'Patient status updated.']); }
    public static function ajax_patient_delete(){ self::auth(); global $wpdb; $pid=(int)($_POST['patient_id']??0); self::ensure_patient_row($pid); if(!self::patient_belongs_to_doctor($pid,self::doctor_id())) wp_send_json_error(['message'=>'Access denied.'],403); $wpdb->update(self::table('patients'),['status'=>'deleted','updated_at'=>current_time('mysql')],['user_id'=>$pid]); update_user_meta($pid,'dior_patient_status','deleted'); wp_send_json_success(['message'=>'Patient removed from the database.']); }
    public static function ajax_patient_bulk_delete(){ self::auth(); $ids=array_map('intval',(array)($_POST['patient_ids']??[])); if(!$ids) wp_send_json_error(['message'=>'Select at least one patient.']); foreach($ids as $id){ if(self::patient_belongs_to_doctor($id,self::doctor_id())){ $_POST['patient_id']=$id; self::delete_patient_quiet($id); } } wp_send_json_success(['message'=>'Selected patients deleted.']); }
    private static function delete_patient_quiet($id){ global $wpdb; $wpdb->update(self::table('patients'),['status'=>'deleted','updated_at'=>current_time('mysql')],['user_id'=>(int)$id]); update_user_meta($id,'dior_patient_status','deleted'); }

    public static function ajax_get_patient_profile(){ self::auth(); $p=self::patient(sanitize_text_field($_POST['patient_id']??'')); if(!$p || !self::patient_belongs_to_doctor($p['user_id'],self::doctor_id())) wp_send_json_error(['message'=>'Patient not found or access denied.']); wp_send_json_success(['patient'=>$p]); }

    public static function ajax_save_consultation(){ self::auth(); $pid=(int)($_POST['patient_id']??0); self::ensure_patient_row($pid); if(!self::patient_belongs_to_doctor($pid,self::doctor_id())) wp_send_json_error(['message'=>'Select a patient assigned to this doctor.']); $res=Dior_Encounter_Service::save_encounter(['patient_id'=>$pid,'encounter_uid'=>sanitize_text_field($_POST['note_id']??''),'encounter_type'=>'Consultation Note','subjective'=>sanitize_textarea_field($_POST['note']??''),'assessment'=>sanitize_textarea_field($_POST['assessment']??''),'plan'=>sanitize_textarea_field($_POST['plan']??''),'diagnosis'=>sanitize_text_field($_POST['diagnosis']??''),'follow_up'=>sanitize_text_field($_POST['follow_up']??'')]); if(is_wp_error($res)) wp_send_json_error(['message'=>$res->get_error_message()]); wp_send_json_success(['message'=>'Consultation note saved.','note'=>$res]); }
    public static function ajax_delete_consultation(){ self::auth(); $pid=(int)($_POST['patient_id']??0); $nid=sanitize_text_field($_POST['note_id']??''); if(!$pid||!$nid) wp_send_json_error(['message'=>'Invalid note.']); $res=Dior_Encounter_Service::delete_encounter($nid,$pid); if(is_wp_error($res)) wp_send_json_error(['message'=>$res->get_error_message()]); wp_send_json_success(['message'=>'Consultation note deleted.']); }

    public static function ajax_document_save(){ self::auth(); global $wpdb; $pid=(int)($_POST['patient_id']??0); self::ensure_patient_row($pid); if(!self::patient_belongs_to_doctor($pid,self::doctor_id())) wp_send_json_error(['message'=>'Access denied.']); $id=(int)($_POST['id']??0); $data=['patient_id'=>$pid,'author_id'=>self::doctor_id(),'title'=>sanitize_text_field($_POST['title']??'Document'),'category'=>sanitize_text_field($_POST['category']??'Clinical Record'),'file_name'=>sanitize_file_name($_POST['file_name']??'document.txt'),'file_path'=>sanitize_text_field($_POST['file_path']??''),'file_type'=>sanitize_text_field($_POST['file_type']??'TXT'),'content_html'=>wp_kses_post($_POST['content_html']??''),'updated_at'=>current_time('mysql')]; if($id){$wpdb->update(self::table('documents'),$data,['id'=>$id]);}else{$data['doc_uid']='DOC-'.wp_generate_uuid4();$data['created_at']=current_time('mysql');$wpdb->insert(self::table('documents'),$data);$id=$wpdb->insert_id;} wp_send_json_success(['message'=>'Document saved.','id'=>$id]); }
    public static function ajax_document_delete(){ self::auth(); global $wpdb; $id=(int)($_POST['id']??0); $doc=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('documents').' WHERE id=%d',$id),ARRAY_A); if(!$doc||!self::patient_belongs_to_doctor($doc['patient_id'],self::doctor_id())) wp_send_json_error(['message'=>'Access denied.']); $wpdb->delete(self::table('documents'),['id'=>$id],['%d']); wp_send_json_success(['message'=>'Document deleted.']); }

    public static function ajax_review_save(){ self::auth(); global $wpdb; $pid=(int)($_POST['patient_id']??0); self::ensure_patient_row($pid); if(!self::patient_belongs_to_doctor($pid,self::doctor_id())) wp_send_json_error(['message'=>'Access denied.']); $id=(int)($_POST['id']??0); $data=['patient_id'=>$pid,'doctor_id'=>self::doctor_id(),'rating'=>max(1,min(5,(int)($_POST['rating']??5))),'comment'=>sanitize_textarea_field($_POST['comment']??''),'status'=>sanitize_text_field($_POST['status']??'Published'),'updated_at'=>current_time('mysql')]; if($id)$wpdb->update(self::table('reviews'),$data,['id'=>$id]);else{$data['review_uid']='REV-'.wp_generate_uuid4();$data['created_at']=current_time('mysql');$wpdb->insert(self::table('reviews'),$data);$id=$wpdb->insert_id;} wp_send_json_success(['message'=>'Review saved.','id'=>$id]); }
    public static function ajax_review_delete(){ self::auth(); global $wpdb; $id=(int)($_POST['id']??0); $r=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('reviews').' WHERE id=%d',$id),ARRAY_A); if(!$r||(!current_user_can('manage_options')&&(int)$r['doctor_id']!==self::doctor_id()))wp_send_json_error(['message'=>'Access denied.']);$wpdb->delete(self::table('reviews'),['id'=>$id],['%d']);wp_send_json_success(['message'=>'Review deleted.']); }
    public static function ajax_review_get(){ self::auth(); global $wpdb; $id=(int)($_POST['id']??0); $r=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('reviews').' WHERE id=%d',$id),ARRAY_A); if(!$r)wp_send_json_error(['message'=>'Review not found.']); wp_send_json_success(['review'=>$r,'patient'=>self::patient($r['patient_id'])]); }

    public static function ajax_telemed_save(){ self::auth(); global $wpdb; $pid=(int)($_POST['patient_id']??0); $appt=sanitize_text_field($_POST['appointment_uid']??''); if(!$pid||!self::patient_belongs_to_doctor($pid,self::doctor_id()))wp_send_json_error(['message'=>'Access denied.']);$id=(int)($_POST['id']??0);$data=['appointment_uid'=>$appt,'patient_id'=>$pid,'doctor_id'=>self::doctor_id(),'room_url'=>esc_url_raw($_POST['room_url']??''),'status'=>sanitize_text_field($_POST['status']??'Scheduled'),'notes'=>sanitize_textarea_field($_POST['notes']??''),'updated_at'=>current_time('mysql')];if($id)$wpdb->update(self::table('telemedicine'),$data,['id'=>$id]);else{$data['tele_uid']='TEL-'.wp_generate_uuid4();$data['created_at']=current_time('mysql');$wpdb->insert(self::table('telemedicine'),$data);$id=$wpdb->insert_id;}wp_send_json_success(['message'=>'Telemedicine record saved.','id'=>$id]);}
    public static function ajax_telemed_delete(){self::auth();global $wpdb;$id=(int)($_POST['id']??0);$r=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('telemedicine').' WHERE id=%d',$id),ARRAY_A);if(!$r||(!current_user_can('manage_options')&&(int)$r['doctor_id']!==self::doctor_id()))wp_send_json_error(['message'=>'Access denied.']);$wpdb->delete(self::table('telemedicine'),['id'=>$id],['%d']);wp_send_json_success(['message'=>'Telemedicine record deleted.']);}

    public static function ajax_notification_read(){self::auth();global $wpdb;$id=(int)($_POST['id']??0);$wpdb->update(self::table('notifications'),['is_read'=>1,'read_at'=>current_time('mysql')],['id'=>$id,'doctor_id'=>self::doctor_id()]);wp_send_json_success(['message'=>'Notification marked as read.']);}
    public static function ajax_notification_all_read(){self::auth();global $wpdb;$wpdb->query($wpdb->prepare('UPDATE '.self::table('notifications').' SET is_read=1,read_at=%s WHERE doctor_id=%d',current_time('mysql'),self::doctor_id()));wp_send_json_success(['message'=>'All notifications marked as read.']);}

    public static function ajax_settings_save(){self::auth();global $wpdb;$allowed=['clinic_name','timezone','consultation_duration','booking_buffer','email_new_booking','email_reminders','dashboard_alerts','sms_emergency'];foreach($allowed as $key){$val=isset($_POST[$key])?(is_array($_POST[$key])?wp_json_encode($_POST[$key]):sanitize_text_field($_POST[$key])):'';$wpdb->replace(self::table('doctor_settings'),['doctor_id'=>self::doctor_id(),'setting_key'=>$key,'setting_value'=>$val,'updated_at'=>current_time('mysql')],['%d','%s','%s','%s']);}wp_send_json_success(['message'=>'Settings saved successfully.']);}

    public static function ajax_chat_send(){self::auth();global $wpdb;$uid=sanitize_text_field($_POST['appointment_uid']??'');$a=Dior_Appointment_Service::get_appointment($uid);if(!$a||!self::patient_belongs_to_doctor($a['patient_id'],self::doctor_id())|| (int)$a['doctor_id']!==self::doctor_id())wp_send_json_error(['message'=>'This consultation is not assigned to you.'],403);$msg=sanitize_textarea_field($_POST['message']??'');if(!$msg)wp_send_json_error(['message'=>'Message required.']);$wpdb->insert(self::table('chat_messages'),['appointment_uid'=>$uid,'patient_id'=>$a['patient_id'],'doctor_id'=>self::doctor_id(),'sender_user_id'=>self::doctor_id(),'message'=>$msg,'created_at'=>current_time('mysql')]);wp_send_json_success(['message'=>'Message sent.']);}
    public static function ajax_chat_get(){self::auth();global $wpdb;$uid=sanitize_text_field($_POST['appointment_uid']??'');$a=Dior_Appointment_Service::get_appointment($uid);if(!$a||!self::patient_belongs_to_doctor($a['patient_id'],self::doctor_id())|| (int)$a['doctor_id']!==self::doctor_id())wp_send_json_error(['message'=>'Consultation access denied.'],403);$rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('chat_messages').' WHERE appointment_uid=%s ORDER BY id ASC',$uid),ARRAY_A);wp_send_json_success(['messages'=>$rows,'patient'=>self::patient($a['patient_id']),'appointment'=>$a]);}

    public static function get_settings($doctor_id=0){global $wpdb;$doctor_id=$doctor_id?:self::doctor_id();$rows=$wpdb->get_results($wpdb->prepare('SELECT setting_key,setting_value FROM '.self::table('doctor_settings').' WHERE doctor_id=%d',$doctor_id),ARRAY_A);$out=[];foreach($rows as $r)$out[$r['setting_key']]=$r['setting_value'];return $out;}
    public static function render_appointments($doctor_id=0){$doctor_id=$doctor_id?:self::doctor_id();$a=Dior_Appointment_Service::get_doctor_appointments($doctor_id);foreach($a as &$r){$r['patient_name']=self::patient_name($r['patient_id']??0);$p=self::patient($r['patient_id']??0);$r['patient_uid']=$p['patient_uid']??'';}return $a;}
    public static function render_documents(){global $wpdb;$rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('documents').' WHERE author_id=%d AND is_deleted=0 ORDER BY id DESC',self::doctor_id()),ARRAY_A);foreach($rows as &$r)$r['patient_name']=self::patient_name($r['patient_id']);return $rows;}
    public static function render_reviews(){global $wpdb;$rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('reviews').' WHERE doctor_id=%d ORDER BY id DESC',self::doctor_id()),ARRAY_A);foreach($rows as &$r)$r['patient_name']=self::patient_name($r['patient_id']);return $rows;}
    public static function render_telemedicine(){global $wpdb;$rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('telemedicine').' WHERE doctor_id=%d ORDER BY id DESC',self::doctor_id()),ARRAY_A);foreach($rows as &$r)$r['patient_name']=self::patient_name($r['patient_id']);return $rows;}
    public static function render_notifications($type='all'){global $wpdb;$where='doctor_id=%d';$args=[self::doctor_id()];if($type==='alerts'){$where.=" AND type='appointment'";}elseif($type==='system'){$where.=" AND type<>'appointment'";}return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('notifications')." WHERE $where ORDER BY id DESC LIMIT 100",...$args),ARRAY_A);}
}
Dior_Doctor_Dynamic::init();
